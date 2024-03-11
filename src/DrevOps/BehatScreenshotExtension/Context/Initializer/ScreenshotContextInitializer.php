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
     * ScreenshotContextInitializer constructor.
     *
     * @param string $dir                   Screenshot dir.
     * @param bool   $fail                  Screenshot when fail.
     * @param string $failPrefix            File name prefix for a failed test.
     * @param bool   $purge                 Purge dir before start script.
     * @param string $filenamePattern       File name pattern.
     * @param string $filenamePatternFailed File name pattern failed.
     * @param bool   $needsPurging          Check if need to actually purge.
     */
    public function __construct(protected string $dir, protected bool $fail, private readonly string $failPrefix, protected bool $purge, protected string $filenamePattern, protected string $filenamePatternFailed, protected bool $needsPurging = true)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function initializeContext(Context $context): void
    {
        if ($context instanceof ScreenshotAwareContext) {
            $dir = $this->resolveScreenshotDir();
            $context->setScreenshotParameters($dir, $this->fail, $this->failPrefix, $this->filenamePattern, $this->filenamePatternFailed);
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
