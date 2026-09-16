<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\MinkExtension\Context\MinkContext;
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends MinkContext implements Context {

  use ScreenshotTrait;

  /**
   * Environment variable replacing the base URL of JavaScript scenarios.
   */
  public const ENV_JAVASCRIPT_BASE_URL = 'BEHAT_JAVASCRIPT_BASE_URL';

  /**
   * Base URL for JavaScript scenarios.
   */
  protected string $javascriptBaseUrl;

  /**
   * FeatureContext constructor.
   *
   * @param array<string> $parameters
   *   Array of parameters from config.
   */
  public function __construct(array $parameters) {
    $this->screenshotInitParams($parameters);
    // Override any real host in the screenshot token.
    putenv(ScreenshotContext::ENV_TOKEN_HOST . '=example.com');
    $this->javascriptBaseUrl = getenv(self::ENV_JAVASCRIPT_BASE_URL) ?: 'http://host.docker.internal:8888';
  }

  /**
   * Update base URL for JavaScript scenarios.
   */
  #[BeforeScenario('@javascript')]
  public function beforeScenarioUpdateBaseUrl(BeforeScenarioScope $scope): void {
    $environment = $scope->getEnvironment();

    if (!$environment instanceof InitializedContextEnvironment) {
      return;
    }

    foreach ($environment->getContexts() as $context) {
      if ($context instanceof RawMinkContext) {
        $context->setMinkParameter('base_url', $this->javascriptBaseUrl);
      }
    }
  }

}
