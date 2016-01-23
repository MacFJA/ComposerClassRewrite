<?php

namespace MacFJA\ClassRewrite\Visitor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/**
 * Class Getter.
 * A PhpParser visitor to retrieve class information (FQCN, Short class name, interface, parent class FQCN)
 *
 * @package MacFJA\ClassRewrite\Visitor
 * @author  MacFJA
 * @license MIT
 */
class Getter extends NodeVisitorAbstract
{
    const TYPE_NAMESPACE   = 1;
    const TYPE_SHORT_NAME  = 2;
    const TYPE_PARENT_NAME = 3;
    const TYPE_INTERFACES  = 4;
    /**
     * The type of value to get
     *
     * @var int
     */
    protected $type;
    /**
     * The value found in the file
     *
     * @var string|string[]
     */
    protected $value;

    /**
     * Getter constructor.
     *
     * @param int $type The type of data to get (see class constants)
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * Get the value
     *
     * @return string|string[]
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * The visitor method
     *
     * @param Node $node The visited node
     *
     * @return void
     */
    public function leaveNode(Node $node)
    {
        if (self::TYPE_NAMESPACE === $this->type && $node instanceof Node\Stmt\Namespace_) {
            $this->value = $node->name->toString();
        } elseif ($node instanceof Node\Stmt\Class_
            && in_array($this->type, array(self::TYPE_SHORT_NAME, self::TYPE_PARENT_NAME, self::TYPE_INTERFACES), true)
        ) {
            if (self::TYPE_SHORT_NAME === $this->type) {
                $this->value = $node->name;
            } elseif (self::TYPE_PARENT_NAME === $this->type) {
                $this->value = $node->extends->toString();
            } elseif (self::TYPE_INTERFACES === $this->type) {
                $value = array();
                foreach ($node->implements as $interface) {
                    $value[] = $interface->toString();
                }
                $this->value = $value;
            }
        } elseif ($node instanceof Node\Stmt\Interface_ && self::TYPE_INTERFACES === $this->type) {
            $value = array();
            //foreach ($node->extends as $interface) {
            //    $value[] = $interface->toString();
            //}
            $this->value = $value;
        }
    }

    /**
     * Read a data from a class file
     *
     * @param string $file The class file to parse/read
     * @param int    $type The type of data to read
     *
     * @return string|string[]
     */
    public static function readFromFile($file, $type)
    {
        $parser    = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $getter = new self($type);
        $traverser->addVisitor($getter);

        // read the file that should be converted
        $code = file_get_contents($file);

        // parse
        $stmts = $parser->parse($code);

        // traverse
        $traverser->traverse($stmts);

        $value = $getter->getValue();
        if (null === $value) {
            $value = $type === self::TYPE_INTERFACES?array():'';
        }
        return $value;
    }

    /**
     * Get the FQCN (Full Qualifier Class Name) of a class file.
     *
     * @param string $file The class file to read
     *
     * @return string
     */
    // @codingStandardsIgnoreStart
    public static function getFQCN($file)
    {
        // @codingStandardsIgnoreEnd
        return self::readFromFile($file, self::TYPE_NAMESPACE).'\\'.self::readFromFile($file, self::TYPE_SHORT_NAME);
    }
}
