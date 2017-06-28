<?php
/**
 * @file
 * This file is part of the IntegratedExperts\BehatScreenshot package.
 */

namespace IntegratedExperts\BehatScreenshotExtension\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use IntegratedExperts\BehatScreenshotExtension\Context\ScreenshotAwareContext;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Class ScreenshotContextInitializer
 */
class ScreenshotContextInitializer implements ContextInitializer
{

    /**
     * Screenshot directory name.
     *
     * @var string
     */
    protected $dir;

    /**
     * Makes screenshot when fail.
     *
     * @var bool
     */
    protected $fail;

    /**
     * Purge dir before start test.
     *
     * @var bool
     */
    protected $purge;

    /**
     * Check if need to actually purge.
     *
     * @var bool
     */
    protected $doPurge;

    /**
     * ScreenshotContextInitializer constructor.
     *
     * @param string $dir   Screenshot dir.
     * @param bool   $fail  Screenshot when fail.
     * @param bool   $purge Purge dir before start script.
     */
    public function __construct($dir, $fail, $purge)
    {
        $this->doPurge = true;
        $this->dir = $dir;
        $this->fail = $fail;
        $this->purge = $purge;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof ScreenshotAwareContext) {
            $context->setScreenshotParameters($this->dir, $this->fail);
            if ($this->purge && $this->doPurge) {
                $this->purgeFilesInDir();
                $this->doPurge = false;
            }
        }
    }

    /**
     * Remove files in directory.
     */
    protected function purgeFilesInDir()
    {
        $fs = new Filesystem();
        $finder = new Finder();
        if ($fs->exists($this->dir)) {
            $fs->remove($finder->files()->in($this->dir));
        }
    }
}
