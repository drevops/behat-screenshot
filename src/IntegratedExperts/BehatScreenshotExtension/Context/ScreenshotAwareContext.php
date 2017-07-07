<?php

/**
 * @file
 * Behat context interface to enable Screenshot.
 */

namespace IntegratedExperts\BehatScreenshotExtension\Context;

use Behat\Behat\Context\Context;

/**
 * Interface ScreenshotContext.
 */
interface ScreenshotAwareContext extends Context
{

    /**
     * Set context parameters.
     *
     * @param string $dir
     * @param bool   $fail
     *
     * @return $this
     */
    public function setScreenshotParameters($dir, $fail, $html, $png);
}
