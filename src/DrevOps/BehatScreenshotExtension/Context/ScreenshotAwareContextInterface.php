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
   * @param bool $should_capture_on_failed
   *   Whether to capture a screenshot after a failed step.
   * @param string $failed_prefix
   *   Filename prefix for a failed test.
   * @param bool $should_always_capture_fullscreen
   *   Whether to capture every screenshot fullscreen.
   * @param bool $should_capture_on_every_step
   *   Whether to capture a screenshot after every step.
   * @param string $filename_pattern
   *   Filename pattern.
   * @param string $filename_pattern_failed
   *   Filename pattern for failed tests.
   * @param array<int,string> $info_types
   *   Show these info types in the screenshot.
   * @param array<string,mixed> $animation
   *   Animated GIF configuration (keys: enabled, frame_delay, max_width,
   *   max_height).
   */
  public function setScreenshotConfig(string $dir, bool $should_capture_on_failed, string $failed_prefix, bool $should_always_capture_fullscreen, bool $should_capture_on_every_step, string $filename_pattern, string $filename_pattern_failed, array $info_types, array $animation): static;

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
