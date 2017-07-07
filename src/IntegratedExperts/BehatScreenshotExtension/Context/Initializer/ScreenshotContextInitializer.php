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
     * Check if need to actually purge.
     *
     * @var bool
     */
    protected $needsPurging;

    /**
     * Check if need to save in html.
     *
     * @var bool
     */
    protected $html;

    /**
     * Check to need save in png.
     *
     * @var bool
     */
    protected $png;

    /**
     * ScreenshotContextInitializer constructor.
     *
     * @param string $dir   Screenshot dir.
     * @param bool   $fail  Screenshot when fail.
     * @param bool   $purge Purge dir before start script.
     * @param bool   $fail Save in html format.
     * @param bool   $html Save in png format.
     */
    public function __construct($dir, $fail, $purge, $html, $png)
    {
        $this->needsPurging = true;
        $this->dir = $dir;
        $this->fail = $fail;
        $this->purge = $purge;
        $this->html = $html;
        $this->png = $png;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof ScreenshotAwareContext) {
            $dir = $this->resolveDir();
            $context->setScreenshotParameters($dir, $this->fail, $this->html, $this->png);
            if ($this->purge && $this->needsPurging) {
                $this->purgeFilesInDir();
                $this->needsPurging = false;
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

    /**
     * Resolve directory using one of supported paths.
     */
    protected function resolveDir()
    {
        $dir = getenv('BEHAT_SCREENSHOT_DIR');
        if (!empty($dir)) {
            return $dir;
        }

        return $this->dir;
    }
}
