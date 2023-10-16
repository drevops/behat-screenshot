<?php

/**
 * @file
 * Feature context Behat testing.
 */

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends MinkContext implements Context
{

    use ScreenshotTrait;

    /**
     * FeatureContext constructor.
     *
     * @param array<string> $parameters Array of parameters from config.
     */
    public function __construct(array $parameters)
    {
        $this->screenshotInitParams($parameters);
    }
}
