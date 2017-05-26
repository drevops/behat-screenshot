<?php
/**
 * @file
 * This file is part of the IntegratedExperts\BehatScreenshot package.
 */

namespace IntegratedExperts\Behat\Screenshot\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use IntegratedExperts\Behat\Screenshot\Context\ScreenshotContextInterface;
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
    private $dir;

    /**
     * Makes screenshot when fail.
     *
     * @var bool
     */
    private $fail;

    /**
     * Purge dir before start test.
     *
     * @var bool
     */
    private $purge;

    /**
     * Does need to clear directory trigger.
     *
     * @var bool
     */
    private $toPurge;

    /**
     * ScreenshotContextInitializer constructor.
     *
     * @param string $dir   Screenshot dir.
     * @param bool   $fail  Screenshot when fail.
     * @param bool   $purge Purge dir before start script.
     */
    public function __construct($dir, $fail, $purge)
    {
        $this->toPurge = true;
        $this->dir = $dir;
        $this->fail = $fail;
        $this->purge = $purge;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof ScreenshotContextInterface) {
            $context->setParameters($this->dir, $this->fail);
            // Calling clearing screenshot directory function.
            if ($this->purge && $this->toPurge) {
                $this->purgeFilesInDir();
                $this->toPurge = false;
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
