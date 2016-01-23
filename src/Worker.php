<?php

namespace MacFJA\ClassRewrite;

use MacFJA\ClassRewrite\Visitor\Getter;
use MacFJA\ClassRewrite\Visitor\Rewriter;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

/**
 * Class Worker.
 * This class contains all code to manipulate class.
 *
 * @package MacFJA\ClassRewrite
 * @author  MacFJA
 * @license MIT
 */
class Worker
{
    /**
     * The path where put rewritten classes
     *
     * @var string
     */
    protected $cacheDir = '_rewrite';

    /**
     * Get the path use to put rewritten classes
     *
     * @return string
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * Set the path where to put rewritten classes
     *
     * @param string $cacheDir The path
     *
     * @return void
     */
    public function setCacheDir($cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    /**
     * The list of rewritten class.
     * The key is the file name, the value is class name
     *
     * @var array
     */
    protected $rewritten = array();

    /**
     * Get the list of rewritten classes
     *
     * @return array
     */
    public function getRewritten()
    {
        return $this->rewritten;
    }

    /**
     * Clear the cache directory (remove of contents, and recreate the directory)
     *
     * @return void
     */
    public function clearCache()
    {
        if (file_exists($this->cacheDir)) {
            foreach (glob($this->cacheDir.DIRECTORY_SEPARATOR.'*') as $file) {
                unlink($file);
            }
            rmdir($this->cacheDir);
        }
        mkdir($this->cacheDir, 0777, true);
    }

    /**
     * Rewrite a class
     *
     * @param string $parentFile      The path to the parent class (rewritten class)
     * @param string $replacementFile The path to the replacement class (rewriter class)
     *
     * @return void
     */
    public function rewriteClass($parentFile, $replacementFile)
    {
        // Adding a 'C' at the begin to avoid issue with '\' (namespace separator)
        $parentHash = 'C'.sha1_file($parentFile);
        // Adding a 'C' at the begin to avoid issue with '\' (namespace separator)
        $replacementHash = 'C'.sha1_file($replacementFile);

        $namespace = Getter::readFromFile($parentFile, Getter::TYPE_NAMESPACE);
        $className = Getter::readFromFile($parentFile, Getter::TYPE_SHORT_NAME);

        $parentFCQN = $namespace.'\\'.$parentHash;

        file_put_contents(
            $this->cacheDir.DIRECTORY_SEPARATOR.$parentHash.'.php',
            $this->rebuildClass($parentFile, null, $parentHash)
        );

        file_put_contents(
            $this->cacheDir.DIRECTORY_SEPARATOR.$replacementHash.'.php',
            $this->rebuildClass($replacementFile, null, $className, '\\'.$parentFCQN)
        );

        $this->rewritten[$replacementHash.'.php'] = $namespace.'\\'.$className;
    }

    /**
     * Create a new class base on an existing class, and change some value
     *
     * @param string      $sourceFile   The path of the existing class
     * @param null|string $newNamespace The new namespace of the class (leave `null` to keep existing value)
     * @param null|string $newClassName The new class name of the class (leave `null` to keep existing value)
     * @param null|string $newExtends   The new class to extends (leave `null` to keep existing value)
     *
     * @return string
     */
    public function rebuildClass($sourceFile, $newNamespace = null, $newClassName = null, $newExtends = null)
    {
        $parser        = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $traverser     = new NodeTraverser();
        $prettyPrinter = new Standard();

        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new Rewriter($newNamespace, $newClassName, $newExtends));

        $stmts = $parser->parse(file_get_contents($sourceFile));

        $stmts = $traverser->traverse($stmts);

        return $prettyPrinter->prettyPrintFile($stmts);
    }
}
