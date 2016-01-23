<?php

namespace MacFJA\ClassRewrite;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use MacFJA\ClassRewrite\Visitor\Getter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class Plugin.
 * The entry point of the plugin.
 *
 * @package MacFJA\ClassRewrite
 * @author  MacFJA
 * @license MIT
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * The console Input/Output object
     *
     * @var IOInterface
     */
    protected $io;
    /**
     * The Composer configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Apply plugin modifications to composer
     *
     * @param Composer    $composer The Composer object
     * @param IOInterface $io       The console Input/Output object
     *
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->config = $composer->getConfig();
        $this->io     = $io;
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
        );

    }

    /**
     * Print a message in the user console
     *
     * @param string $level   The message level ("normal", "verbose", "very-verbose", "debug")
     * @param string $message The message (sprintf format)
     * @param array  $vars    The message variables
     *
     * @return void
     */
    protected function output($level, $message, array $vars = array())
    {
        if ('normal' === $level
            || ('verbose' === $level && $this->io->isVerbose())
            || ('very-verbose' === $level && $this->io->isVeryVerbose())
            || ('debug' === $level && $this->io->isDebug())
        ) {
            $this->io->write(vsprintf($message, $vars));
        }
    }

    /**
     * The callback function of the event `ScriptEvents::PRE_AUTOLOAD_DUMP`
     *
     * @param Event $event The Composer event
     *
     * @return void
     *
     * @throws \InvalidArgumentException with Symfony\Component\Finder\Finder
     * @throws \RuntimeException with Composer\Config
     */
    public function findRewrite(Event $event)
    {
        $toRewrite = array();
        $composer  = $event->getComposer();
        $config    = $this->config;

        $allPath  = array();
        $packages = $composer->getRepositoryManager()->getLocalRepository()->getPackages();

        $autoloads = $composer->getAutoloadGenerator()->parseAutoloads(
            $composer->getAutoloadGenerator()->buildPackageMap(
                $composer->getInstallationManager(),
                $composer->getPackage(),
                $packages
            ),
            $composer->getPackage()
        );

        $worker = new Worker();
        $worker->setCacheDir(
            $config->has('rewrite-dir') ?
                $config->get('rewrite-dir') :
                $config->get('vendor-dir') . DIRECTORY_SEPARATOR . '_rewrite'
        );

        $this->output('very-verbose', 'Clear rewriters cache');
        $worker->clearCache();

        foreach ($autoloads as $loaderType => $package) {
            if (!in_array($loaderType, array('psr-4', 'psr-0'), true)) {
                continue;
            }
            foreach ($package as $packageName => $paths) {
                $this->output('very-verbose', 'Analysing namespace "%s" (%s)', array($packageName, $loaderType));
                if (in_array($packageName, $this->getIgnoreNamespace($composer), true)) {
                    $this->output('very-verbose', '  <info>(skipped)</info>');
                    continue;
                }
                /**
                 * The file in reading
                 *
                 * @var SplFileInfo $file
                 */
                foreach (Finder::create()
                             ->in(array_values($paths))
                             ->exclude($worker->getCacheDir())
                             ->name('*.php')
                             ->ignoreDotFiles(true)
                             ->ignoreVCS(true)
                             ->ignoreUnreadableDirs(true)
                             ->files() as $file) {

                    $this->output('debug', '- On file "%s"', array($file->getRealPath()));

                    if (in_array(
                        Rewriter::class,
                        Getter::readFromFile($file->getRealPath(), Getter::TYPE_INTERFACES),
                        true
                    )) {
                        $this->output(
                            'very-verbose',
                            ' - Find "<info>%s</info>" rewriting "<comment>%s</comment>"',
                            array(
                                Getter::getFQCN($file->getRealPath()),
                                Getter::readFromFile($file->getRealPath(), Getter::TYPE_PARENT_NAME)
                            )
                        );
                        /**
                         * The FQCN of the parent class (the rewritten FQCN)
                         *
                         * @var string $parentFQCN
                         */
                        $parentFQCN             = Getter::readFromFile($file->getRealPath(), Getter::TYPE_PARENT_NAME);
                        $toRewrite[$parentFQCN] = $file->getRealPath();
                        continue;
                    }
                    $allPath[$file->getRealPath()] = Getter::getFQCN($file->getRealPath());
                }
            }
        }

        $allPath = array_filter($allPath, function ($value) use ($toRewrite) {
            return array_key_exists($value, $toRewrite);
        });

        $this->output('verbose', 'Build rewrite class cache');
        $allPath = array_flip($allPath);
        foreach ($toRewrite as $parentClassName => $rewriteFile) {
            $this->output(
                'very-verbose',
                ' - For class "<info>%s</info>" (rewriting "<comment>%s</comment>")',
                array(Getter::getFQCN($rewriteFile), $parentClassName)
            );
            $worker->rewriteClass($allPath[$parentClassName], $rewriteFile);
        }

        $this->output('verbose', 'Add rewrite class in Composer Autoload');
        $autoload               = $composer->getPackage()->getAutoload();
        $autoload['classmap'][] = $worker->getCacheDir();
        $composer->getPackage()->setAutoload($autoload);
    }

    protected $ignored;

    /**
     * Get the list of namespace that are ignore for rewrites
     *
     * @param Composer $composer The Composer object
     *
     * @return array
     */
    protected function getIgnoreNamespace($composer)
    {
        if (null === $this->ignored) {
            /**
             * List of required packages
             *
             * @var PackageInterface[] $packages
             */
            $packages   = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
            $packages[] = $composer->getPackage();
            $ignores    = array();
            foreach ($packages as $package) {
                $extra = $package->getExtra();
                if (!array_key_exists('composer-class-rewrite', $extra)
                    || !array_key_exists('ignore-namespace', $extra['composer-class-rewrite'])
                ) {
                    continue;
                }
                $ignores = array_merge($ignores, $extra['composer-class-rewrite']['ignore-namespace']);
            }
            $this->ignored = $ignores;
        }

        return $this->ignored;
    }
}
