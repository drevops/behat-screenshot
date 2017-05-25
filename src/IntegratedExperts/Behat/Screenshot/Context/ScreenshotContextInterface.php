<?php

/**
 * @file
 * Behat context interface to enable Screenshot.
 */

namespace IntegratedExperts\Behat\Screenshot\Context;

use Behat\Behat\Context\Context;

/**
 * Interface ScreenshotContext.
 */
interface ScreenshotContextInterface extends Context
{
    /**
     * Set context parameters.
     *
     * @param string $dir
     * @param bool   $fail
     *
     * @return $this
     */
    public function setParameters($dir, $fail);
}
