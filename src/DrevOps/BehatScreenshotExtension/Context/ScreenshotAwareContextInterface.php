<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use DrevOps\BehatScreenshotExtension\ScreenshotConfig;

/**
 * Defines a context capable of capturing screenshots.
 */
interface ScreenshotAwareContextInterface extends Context {

  /**
   * Set screenshot configuration.
   *
   * @param \DrevOps\BehatScreenshotExtension\ScreenshotConfig $config
   *   Screenshot configuration.
   */
  public function setScreenshotConfig(ScreenshotConfig $config): static;

  /**
   * Get screenshot configuration.
   *
   * @return \DrevOps\BehatScreenshotExtension\ScreenshotConfig
   *   Screenshot configuration.
   *
   * @throws \RuntimeException
   *   When no screenshot configuration has been set.
   */
  public function getScreenshotConfig(): ScreenshotConfig;

  /**
   * Capture a screenshot.
   *
   * @param array<string,mixed> $config
   *   Screenshot configuration with the following keys:
   *   - filename: (string|null) Custom filename for the screenshot.
   *   - is_failed: (bool) Whether this is a failed test screenshot.
   *   - is_fullscreen: (bool) Whether to capture a fullscreen screenshot.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \InvalidArgumentException
   *   When the configuration holds a key not listed above.
   */
  public function captureScreenshot(array $config = []): void;

  /**
   * Get a screenshot of the current browser window.
   *
   * @return string
   *   Screenshot content.
   *
   * @throws \Behat\Mink\Exception\DriverException
   *   When the driver cannot capture a screenshot.
   */
  public function getScreenshot(): string;

  /**
   * Get a screenshot of the full page height.
   *
   * @return string
   *   Screenshot content.
   *
   * @throws \Behat\Mink\Exception\DriverException
   *   When the driver cannot capture a screenshot.
   */
  public function getScreenshotFullscreen(): string;

  /**
   * Write screenshot content into a file in the screenshot directory.
   *
   * @param string $filename
   *   Filename to write.
   * @param string $content
   *   Content to write into a file.
   *
   * @throws \RuntimeException
   *   When the file cannot be written.
   */
  public function writeScreenshotContent(string $filename, string $content): void;

  /**
   * Get before step scope.
   *
   * @return \Behat\Behat\Hook\Scope\BeforeStepScope
   *   The before step scope.
   */
  public function getBeforeStepScope(): BeforeStepScope;

  /**
   * Adds information to context.
   *
   * @param string $label
   *   Debug information label.
   * @param string $value
   *   Debug information value.
   */
  public function appendInfo(string $label, string $value): void;

  /**
   * Render information.
   *
   * @return string
   *   Rendered debug information.
   */
  public function renderInfo(): string;

}
