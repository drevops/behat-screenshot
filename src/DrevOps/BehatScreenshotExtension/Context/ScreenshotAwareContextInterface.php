<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Context\Context;

/**
 * Defines a context capable of capturing screenshots.
 */
interface ScreenshotAwareContextInterface extends Context {

  /**
   * Set screenshot configuration.
   *
   * @param string $dir
   *   Directory to store screenshots.
   * @param bool $on_failed
   *   Create screenshots on fail.
   * @param string $failed_prefix
   *   File name prefix for a failed test.
   * @param bool $always_fullscreen
   *   Always capture fullscreen screenshots.
   * @param bool $on_every_step
   *   Capture screenshot after every step.
   * @param string $filename_pattern
   *   File name pattern.
   * @param string $filename_pattern_failed
   *   File name pattern for failed tests.
   * @param array<int,string> $info_types
   *   Show these info types in the screenshot.
   * @param array<string,mixed> $animation
   *   Animated GIF configuration (keys: enabled, frame_delay, max_width,
   *   max_height).
   */
  public function setScreenshotConfig(string $dir, bool $on_failed, string $failed_prefix, bool $always_fullscreen, bool $on_every_step, string $filename_pattern, string $filename_pattern_failed, array $info_types, array $animation): static;

  /**
   * Capture a screenshot.
   *
   * @param array<string,mixed> $config
   *   Screenshot configuration with the following keys:
   *   - filename: (string|null) Custom filename for the screenshot.
   *   - is_failed: (bool) Whether this is a failed test screenshot.
   *   - fullscreen: (bool) Whether to capture a fullscreen screenshot.
   *
   * @throws \Behat\Mink\Exception\DriverException
   */
  public function captureScreenshot(array $config = []): void;

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
