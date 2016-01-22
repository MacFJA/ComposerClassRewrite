<?php

namespace MacFJA\ClassRewrite;

use Composer\Autoload\ClassLoader;
use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use TokenReflection\Broker;
use TokenReflection\Broker\Backend\Memory;
use TokenReflection\Php\ReflectionClass;

class Worker implements PluginInterface, EventSubscriberInterface
{
    /** @var Composer */
    protected $composer;
    /** @var IOInterface */
    protected $io;
    protected $rewriters = array();

    /**
     * Apply plugin modifications to composer
     *
     * @param \Composer\Composer $composer
     * @param \Composer\IO\IOInterface $io
     */
    public function activate(\Composer\Composer $composer, \Composer\IO\IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::PRE_AUTOLOAD_DUMP => 'findRewrite',
            ScriptEvents::POST_AUTOLOAD_DUMP => 'redefineLoader'
        );
    }

    /**
     * @param Event $event
     * @throws \Exception
     */
    public function findRewrite(Event $event)
    {
        $finder = new Finder();

        $paths = $this->findProjectSources($event->getComposer());
        $paths[] = 'vendor';

        $files = $finder->in($paths)->name('*.php')->files();
        /** @var SplFileInfo[] $files */
        foreach ($files as $file) {
            $broker = new Broker(new Memory());
            $broker->processFile($file->getPath());
            /** @var ReflectionClass[] $classes */
            $classes = $broker->getClasses();

            foreach ($classes as $class) {
                if (!$class->implementsInterface('MacFJA\\ClassRewrite\\Rewriter')) {
                    continue;
                }
                if (array_key_exists($class->getParentClassName(), $this->rewriters)) {
                    throw new \Exception('At least 2 rewrite exists for class "'.$class->getParentClassName().'"!');
                }
                $this->rewriters[$class->getParentClassName()] = $class->getName();
            }
        }

        file_put_contents('composer.rewrites', json_encode($this->rewriters));
    }

    protected function findProjectSources(Composer $composer)
    {
        $autoload = $composer->getPackage()->getAutoload();
        $paths = array();

        foreach ($autoload as $type => $group) {
            foreach ($group as $namespace => $path) {
                if (!is_array($path)) {
                    $path = array($path);
                }

                $paths = array_merge($paths, $path);
            }
        }

        return $paths;
    }

    public function redefineLoader(Event $event)
    {
        $broker = new Broker(new Memory());
        $broker->processFile(__FILE__);
        $code = $broker->getClass(self::class)->getMethod('includeFile')->getSource();
        runkit_function_redefine('\Composer\Autoload\includeFile','$file', $code);
    }

    private function includeFile($file)
    {
        include $file;
        $broker = new Broker(new Memory());
        $broker->processFile($file);
        /** @var ReflectionClass[] $classes */
        $classes = $broker->getClasses();

        $rewriters = json_decode(file_get_contents('composer.rewrites'), true);

        foreach ($classes as $class) {
            $parent = $class->getParentClassName();
            if (array_key_exists($parent, $rewriters)) {
                $rewriter = $rewriters[$parent];

                if ($class->getName() === $rewriter) {
                    continue;
                }

                class_exists($rewriter, true);

                runkit_class_emancipate($class->getName());
                runkit_class_adopt($rewriter);
            }
        }
    }
}