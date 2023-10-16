<?php

/**
 * @file
 * Behat context interface to enable Screenshot.
 */

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Context\Context;

/**
 * Interface ScreenshotContext.
 */
interface ScreenshotAwareContext extends Context
{

    /**
     * Set context parameters.
     *
     * @param string $dir        Directory to store screenshots.
     * @param bool   $fail       Create screenshots on fail.
     * @param string $failPrefix File name prefix for a failed test.
     *
     * @return $this
     */
    public function setScreenshotParameters(string $dir, bool $fail, string $failPrefix): static;
}
