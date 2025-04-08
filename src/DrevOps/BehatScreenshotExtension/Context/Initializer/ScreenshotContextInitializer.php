<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotAwareContextInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Class ScreenshotContextInitializer.
 */
class ScreenshotContextInitializer implements ContextInitializer {

  /**
   * Flag to purge files in the directory.
   */
  protected bool $needsPurging = TRUE;

  /**
   * ScreenshotContextInitializer constructor.
   *
   * @param string $dir
   *   Screenshot dir.
   * @param bool $onFailed
   *   Create screenshot on failed test.
   * @param string $failedPrefix
   *   File name prefix for a failed test.
   * @param bool $purge
   *   Purge dir before start script.
   * @param bool $alwaysFullscreen
   *   Always take fullscreen screenshots.
   * @param string $fullscreenAlgorithm
   *   Algorithm to use for fullscreen screenshots ('stitch' or 'resize').
   * @param string $filenamePattern
   *   File name pattern.
   * @param string $filenamePatternFailed
   *   File name pattern failed.
   * @param array<int,string> $infoTypes
   *   Show these info types in the screenshot.
   *
   * @codeCoverageIgnore
   */
  public function __construct(
    protected string $dir,
    protected bool $onFailed,
    private readonly string $failedPrefix,
    protected bool $purge,
    protected bool $alwaysFullscreen,
    protected string $fullscreenAlgorithm,
    protected string $filenamePattern,
    protected string $filenamePatternFailed,
    protected array $infoTypes = [],
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function initializeContext(Context $context): void {
    if ($context instanceof ScreenshotAwareContextInterface) {
      $dir = getenv('BEHAT_SCREENSHOT_DIR') ?: $this->dir;

      if ((getenv('BEHAT_SCREENSHOT_PURGE') || $this->purge) && $this->needsPurging) {
        $fs = $this->getFilesystem();
        if ($fs->exists($dir)) {
          $fs->remove($this->getFinder()->files()->in($dir));
        }
        $this->needsPurging = FALSE;
      }

      $context->setScreenshotParameters(
        $dir,
        $this->onFailed,
        $this->failedPrefix,
        $this->alwaysFullscreen,
        $this->fullscreenAlgorithm,
        $this->filenamePattern,
        $this->filenamePatternFailed,
        $this->infoTypes
      );
    }
  }

  /**
   * Get filesystem instance.
   *
   * @return \Symfony\Component\Filesystem\Filesystem
   *   Filesystem instance.
   */
  protected function getFilesystem(): Filesystem {
    // @codeCoverageIgnoreStart
    return new Filesystem();
    // @codeCoverageIgnoreEnd
  }

  /**
   * Get finder instance.
   *
   * @return \Symfony\Component\Finder\Finder
   *   Finder instance.
   */
  protected function getFinder(): Finder {
    // @codeCoverageIgnoreStart
    return new Finder();
    // @codeCoverageIgnoreEnd
  }

}
