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
   * Environment variable replacing the configured screenshot directory.
   */
  public const ENV_DIR = 'BEHAT_SCREENSHOT_DIR';

  /**
   * Environment variable enabling the screenshot directory purge.
   */
  public const ENV_PURGE = 'BEHAT_SCREENSHOT_PURGE';

  /**
   * Whether the screenshot directory has been purged in this run.
   */
  protected bool $hasPurged = FALSE;

  /**
   * ScreenshotContextInitializer constructor.
   *
   * @param array<mixed> $config
   *   Configuration processed by the extension's configuration tree, keyed as
   *   in the Behat configuration.
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
      $filesystem = $this->createFilesystem();

      if ($filesystem->exists($config->dir)) {
        $filesystem->remove($this->createFinder()->files()->in($config->dir));
      }

      $this->hasPurged = TRUE;
    }

    $context->setScreenshotConfig($config);
  }

  /**
   * Apply the environment variables that override configuration keys.
   *
   * @param array<mixed> $config
   *   Processed configuration.
   *
   * @return array<mixed>
   *   Processed configuration with the environment variables applied.
   */
  protected function applyEnvironmentOverrides(array $config): array {
    $dir = getenv(self::ENV_DIR);

    if ($dir) {
      $config['dir'] = $dir;
    }

    if (getenv(self::ENV_PURGE)) {
      $config['purge'] = TRUE;
    }

    return $config;
  }

  /**
   * Create a filesystem instance.
   *
   * @return \Symfony\Component\Filesystem\Filesystem
   *   New filesystem instance.
   */
  protected function createFilesystem(): Filesystem {
    return new Filesystem();
  }

  /**
   * Create a finder instance.
   *
   * @return \Symfony\Component\Finder\Finder
   *   New finder instance.
   */
  protected function createFinder(): Finder {
    return new Finder();
  }

}
