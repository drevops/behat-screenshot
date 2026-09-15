<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotAwareContextInterface;
use DrevOps\BehatScreenshotExtension\ScreenshotConfig;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Passes the extension configuration to every screenshot-aware context.
 *
 * Purges the screenshot directory once per run when purging is enabled.
 */
class ScreenshotContextInitializer implements ContextInitializer {

  /**
   * Whether the screenshot directory has been purged in this run.
   */
  protected bool $hasPurged = FALSE;

  /**
   * ScreenshotContextInitializer constructor.
   *
   * @param array<mixed> $config
   *   Configuration processed by the extension's configuration tree, keyed as
   *   in behat.yml.
   *
   * @codeCoverageIgnore
   */
  public function __construct(
    protected array $config,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function initializeContext(Context $context): void {
    if (!$context instanceof ScreenshotAwareContextInterface) {
      return;
    }

    $config = ScreenshotConfig::fromArray($this->applyEnvironmentOverrides($this->config));

    if ($config->shouldPurge && !$this->hasPurged) {
      $fs = $this->getFilesystem();

      if ($fs->exists($config->dir)) {
        $fs->remove($this->getFinder()->files()->in($config->dir));
      }

      $this->hasPurged = TRUE;
    }

    $context->setScreenshotConfig($config);
  }

  /**
   * Apply the environment variables that override configuration keys.
   *
   * A truthy BEHAT_SCREENSHOT_DIR replaces "dir", and a truthy
   * BEHAT_SCREENSHOT_PURGE turns "purge" on.
   *
   * @param array<mixed> $config
   *   Processed configuration.
   *
   * @return array<mixed>
   *   Processed configuration with the environment variables applied.
   */
  protected function applyEnvironmentOverrides(array $config): array {
    $dir = getenv('BEHAT_SCREENSHOT_DIR');

    if ($dir) {
      $config['dir'] = $dir;
    }

    if (getenv('BEHAT_SCREENSHOT_PURGE')) {
      $config['purge'] = TRUE;
    }

    return $config;
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
