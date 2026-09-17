<?php

declare(strict_types=1);

use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;

/**
 * Additional screenshot helpers.
 */
trait ScreenshotTrait {

  /**
   * Environment variable replacing the base URL of JavaScript scenarios.
   */
  public const ENV_JAVASCRIPT_BASE_URL = 'BEHAT_JAVASCRIPT_BASE_URL';

  /**
   * Screenshot directory.
   */
  protected string $screenshotDir;

  /**
   * Set the token host and take the directory from the screenshot context.
   */
  #[BeforeScenario]
  public function screenshotBeforeScenarioInit(BeforeScenarioScope $scope): void {
    // Override any real host in the screenshot token.
    putenv(ScreenshotContext::ENV_TOKEN_HOST . '=example.com');

    $environment = $scope->getEnvironment();

    if (!$environment instanceof InitializedContextEnvironment || !$environment->hasContextClass(ScreenshotContext::class)) {
      return;
    }

    $this->screenshotDir = $environment->getContext(ScreenshotContext::class)->getScreenshotConfig()->dir;
  }

  /**
   * Update base URL for JavaScript scenarios.
   */
  #[BeforeScenario('@javascript&&~@skip-base-url-rewrite')]
  public function screenshotBeforeScenarioUpdateBaseUrl(BeforeScenarioScope $scope): void {
    $environment = $scope->getEnvironment();

    if (!$environment instanceof InitializedContextEnvironment) {
      return;
    }

    $base_url = getenv(self::ENV_JAVASCRIPT_BASE_URL) ?: 'http://host.docker.internal:8888';

    foreach ($environment->getContexts() as $context) {
      if ($context instanceof RawMinkContext) {
        $context->setMinkParameter('base_url', $base_url);
      }
    }
  }

  /**
   * Go to the phpserver test page.
   */
  #[Given('/^(?:|I )am on (?:|the )phpserver test page$/')]
  #[Given('/^(?:|I )go to (?:|the )phpserver test page$/')]
  #[Given('/^(?:|I )am on (?:|the )phpserver test page with query "([^"]+)" and fragment "([^"]+)"$/')]
  #[Given('/^(?:|I )go to (?:|the )phpserver test page with query "([^"]+)" and fragment "([^"]+)"$/')]
  public function screenshotGoToTestPage(string $query = '', string $fragment = ''): void {
    $path = 'screenshot.html';

    if (!empty($query)) {
      $path = $path . '?' . $query;
    }

    if (!empty($fragment)) {
      $path = $path . '#' . $fragment;
    }

    $this->visitPath($path);
  }

  /**
   * Checks whether a file wildcard at provided path exists.
   *
   * @param string $wildcard
   *   Filename with a wildcard.
   */
  #[Then('/^file wildcard "([^"]*)" should exist$/')]
  public function screenshotAssertFileShouldExist(string $wildcard): void {
    $wildcard = $this->screenshotGetDir() . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      throw new \Exception(sprintf("Unable to find files matching wildcard '%s'.", $wildcard));
    }
  }

  /**
   * Checks whether a file wildcard at provided path does not exist.
   *
   * @param string $wildcard
   *   Filename with a wildcard.
   */
  #[Then('/^file wildcard "([^"]*)" should not exist$/')]
  public function screenshotAssertFileShouldNotExist(string $wildcard): void {
    $wildcard = $this->screenshotGetDir() . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (!empty($matches)) {
      throw new \Exception(sprintf("Files matching wildcard '%s' were found, but were not supposed to.", $wildcard));
    }
  }

  /**
   * Remove all files from screenshot directory.
   */
  #[When('I remove all files from screenshot directory')]
  public function screenshotEmptyDirectory(): void {
    $files = glob($this->screenshotGetDir() . DIRECTORY_SEPARATOR . '*');

    if (!empty($files)) {
      array_map(unlink(...), $files);
    }
  }

  /**
   * Get the screenshot directory.
   *
   * @return string
   *   Screenshot directory.
   *
   * @throws \RuntimeException
   *   When no screenshot context has set the directory.
   */
  protected function screenshotGetDir(): string {
    if (!isset($this->screenshotDir)) {
      throw new \RuntimeException('Screenshots dir is not set.');
    }

    return $this->screenshotDir;
  }

}
