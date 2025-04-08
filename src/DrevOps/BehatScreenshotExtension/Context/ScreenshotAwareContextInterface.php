<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Context\Context;

/**
 * Interface ScreenshotContext.
 */
interface ScreenshotAwareContextInterface extends Context {

  /**
   * Set context parameters.
   *
   * @param string $dir
   *   Directory to store screenshots.
   * @param bool $on_failed
   *   Create screenshots on fail.
   * @param string $failed_prefix
   *   File name prefix for a failed test.
   * @param bool $always_fullscreen
   *   Always take fullscreen screenshots.
   * @param string $fullscreen_algorithm
   *   Algorithm to use for fullscreen screenshots ('stitch' or 'resize').
   * @param string $filename_pattern
   *   File name pattern.
   * @param string $filename_pattern_failed
   *   File name pattern failed.
   * @param array<int,string> $info_types
   *   Show these info types in the screenshot.
   *
   * @return $this
   */
  public function setScreenshotParameters(string $dir, bool $on_failed, string $failed_prefix, bool $always_fullscreen, string $fullscreen_algorithm, string $filename_pattern, string $filename_pattern_failed, array $info_types): static;

  /**
   * Save screenshot content into a file.
   *
   * @param array<string,mixed> $options
   *   Contextual options.
   */
  public function screenshot(array $options): void;

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
