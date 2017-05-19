<?php

/**
 * @file
 * Behat context interface to enable Screenshot.
 */

namespace IntegratedExperts\BehatScreenshot\Context;

use Behat\Behat\Context\Context;

/**
 * Interface ScreenshotContext.
 */
interface ScreenshotContextInterface extends Context
{
    /**
     * Returned Context parameters.
     *
     * @return array
     */
    public function getParameters();

    /**
     * Set context parameters.
     *
     * @param array $parameters
     *
     * @return $this
     */
    public function setParameters($parameters);

    /**
     * Remove files in directory.
     */
    public function purgeFilesInDir();
}
