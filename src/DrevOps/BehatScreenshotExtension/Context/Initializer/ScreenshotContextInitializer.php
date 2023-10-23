<?php
/**
 * @file
 * This file is part of the DrevOps\BehatScreenshot package.
 */

namespace DrevOps\BehatScreenshotExtension\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotAwareContext;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Class ScreenshotContextInitializer.
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
     * Screenshot filename pattern.
     *
     * @var string
     */
    protected string $filenamePattern;

    /**
     * Makes screenshot when fail.
     *
     * @var bool
     */
    protected $fail;

    /**
     * Prefix for failed screenshot files.
     *
     * @var string
     */
    private $failPrefix;

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
    protected $needsPurging;

    /**
     * ScreenshotContextInitializer constructor.
     *
     * @param string $dir             Screenshot dir.
     * @param bool   $fail            Screenshot when fail.
     * @param string $failPrefix      File name prefix for a failed test.
     * @param bool   $purge           Purge dir before start script.
     * @param string $filenamePattern Filename pattern to write to.
     */
    public function __construct(string $dir, bool $fail, string $failPrefix, bool $purge, string $filenamePattern)
    {
        $this->needsPurging = true;
        $this->dir = $dir;
        $this->fail = $fail;
        $this->failPrefix = $failPrefix;
        $this->filenamePattern = $filenamePattern;
        $this->purge = $purge;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeContext(Context $context): void
    {
        if ($context instanceof ScreenshotAwareContext) {
            $dir = $this->resolveScreenshotDir();
            $filenamePattern = $this->resolveScreenshotFilenamePattern();
            $context->setScreenshotParameters($dir, $this->fail, $this->failPrefix, $filenamePattern);
            if ($this->shouldPurge() && $this->needsPurging) {
                $this->purgeFilesInDir($dir);
                $this->needsPurging = false;
            }
        }
    }

    /**
     * Remove files in directory.
     *
     * @param string $dir
     *   Directory to purge files in.
     */
    protected function purgeFilesInDir(string $dir): void
    {
        $fs = new Filesystem();
        $finder = new Finder();
        if ($fs->exists($dir)) {
            $fs->remove($finder->files()->in($dir));
        }
    }

    /**
     * Resolve directory using one of supported paths.
     *
     * @return string
     *   Path to the screenshots directory.
     */
    protected function resolveScreenshotDir(): string
    {
        $dir = getenv('BEHAT_SCREENSHOT_DIR');
        if (!empty($dir)) {
            return $dir;
        }

        return $this->dir;
    }

    /**
     * Resolve filename pattern for screenshots.
     *
     * @return string
     *   Filename pattern containing tokens for replacement.
     */
    protected function resolveScreenshotFilenamePattern()
    {
        $filenamePattern = getenv('BEHAT_SCREENSHOT_FILENAME_PATTERN');
        if (!empty($filenamePattern)) {
            return $filenamePattern;
        }

        return $this->filenamePattern;
    }

    /**
     * Decide if 'purge' flag was set.
     *
     * @return bool
     *   TRUE if should purge, FALSE otherwise.
     */
    protected function shouldPurge(): bool
    {
        return getenv('BEHAT_SCREENSHOT_PURGE') || $this->purge;
    }
}
