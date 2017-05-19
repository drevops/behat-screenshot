<?php
/**
 * @file
 * This file is part of the IntegratedExperts\BehatScreenshot package.
 */

namespace IntegratedExperts\BehatScreenshot\Context\Initializer;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use IntegratedExperts\BehatScreenshot\Context\ScreenshotContextInterface;

/**
 * Class ScreenshotContextInitializer
 */
class ScreenshotContextInitializer implements ContextInitializer
{
    /**
     * Context parameters.
     *
     * @var array
     */
    private $parameters;

    /**
     * Does need to clear directory trigger.
     *
     * @var bool
     */
    private $purge = true;

    /**
     * ScreenshotContextInitializer constructor.
     *
     * @param array $parameters
     */
    public function __construct($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof ScreenshotContextInterface) {
            $context->setParameters($this->parameters);
            // Calling clearing screenshot directory function.
            if ($this->parameters['purge'] && $this->purge) {
                $context->purgeFilesInDir();
                $this->purge = false;
            }
        }
    }
}
