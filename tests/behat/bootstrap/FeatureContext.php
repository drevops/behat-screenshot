<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\MinkExtension\Context\MinkContext;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends MinkContext implements Context {

  /**
   * Base URL for JavaScript scenarios.
   */
  protected string $javascriptBaseUrl;

  use ScreenshotTrait;

  /**
   * FeatureContext constructor.
   *
   * @param array<string> $parameters
   *   Array of parameters from config.
   */
  public function __construct(array $parameters) {
    $this->screenshotInitParams($parameters);
    // Set the screenshot token host to override any real host.
    putenv('BEHAT_SCREENSHOT_TOKEN_HOST=example.com');
    // Set the JavaScript override base URL.
    $this->javascriptBaseUrl = getenv('BEHAT_JAVASCRIPT_BASE_URL') ?: 'http://host.docker.internal:8888';
  }

  /**
   * Update base URL for JavaScript scenarios.
   *
   * @BeforeScenario
   */
  public function beforeScenarioUpdateBaseUrl(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('javascript')) {
      $environment = $scope->getEnvironment();
      if ($environment instanceof InitializedContextEnvironment) {
        foreach ($environment->getContexts() as $context) {
          if ($context instanceof RawMinkContext) {
            $context->setMinkParameter('base_url', $this->javascriptBaseUrl);
          }
        }
      }
    }
  }

}
