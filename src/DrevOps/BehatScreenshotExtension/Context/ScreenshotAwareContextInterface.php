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
   * @param bool $fail
   *   Create screenshots on fail.
   * @param string $fail_prefix
   *   File name prefix for a failed test.
   * @param string $filename_pattern
   *   File name pattern.
   * @param string $filename_pattern_failed
   *   File name pattern failed.
   * @param array<int,string> $info_types
   *   Show these info types in the screenshot.
   *
   * @return $this
   */
  public function setScreenshotParameters(string $dir, bool $fail, string $fail_prefix, string $filename_pattern, string $filename_pattern_failed, array $info_types): static;

}
