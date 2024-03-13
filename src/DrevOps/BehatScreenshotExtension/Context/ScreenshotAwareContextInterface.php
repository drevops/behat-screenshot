<?php

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
   * @param string $failPrefix
   *   File name prefix for a failed test.
   * @param string $filenamePattern
   *   File name pattern.
   * @param string $filenamePatternFailed
   *   File name pattern failed.
   *
   * @return $this
   */
  public function setScreenshotParameters(string $dir, bool $fail, string $failPrefix, string $filenamePattern, string $filenamePatternFailed): static;

}
