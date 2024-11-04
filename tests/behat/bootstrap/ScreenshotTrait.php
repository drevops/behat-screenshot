<?php

declare(strict_types=1);

/**
 * Additional screenshot helpers.
 */
trait ScreenshotTrait {

  /**
   * Screenshot directory.
   *
   * @var string
   */
  protected $screenshotDir;

  /**
   * Init test parameters.
   *
   * @param array<string> $parameters
   *   Array of parameters from config.
   */
  public function screenshotInitParams(array $parameters): void {
    if (getenv('BEHAT_SCREENSHOT_DIR')) {
      $this->screenshotDir = getenv('BEHAT_SCREENSHOT_DIR');
    }
    elseif (isset($parameters['screenshot_dir'])) {
      $this->screenshotDir = $parameters['screenshot_dir'];
    }
    else {
      throw new RuntimeException('Screenshots dir is not set.');
    }
  }

  /**
   * Go to the screenshot test page.
   *
   * @Given /^(?:|I )am on (?:|the )screenshot test page$/
   * @Given /^(?:|I )go to (?:|the )screenshot test page$/
   * @Given /^(?:|I )am on (?:|the )screenshot test page with query "([^"]+)" and fragment "([^"]+)"$/
   * @Given /^(?:|I )go to (?:|the )screenshot test page with query "([^"]+)" and fragment "([^"]+)"$/
   */
  public function goToScreenshotTestPage(string $query = '', string $fragment = ''): void {
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
   *   File name with a wildcard.
   *
   * @Given /^file wildcard "([^"]*)" should exist$/
   */
  public function assertFileShouldExist(string $wildcard): void {
    $wildcard = $this->screenshotDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      throw new \Exception(sprintf("Unable to find files matching wildcard '%s'", $wildcard));
    }
  }

  /**
   * Checks whether a file wildcard at provided path does not exist.
   *
   * @param string $wildcard
   *   File name with a wildcard.
   *
   * @Given /^file wildcard "([^"]*)" should not exist$/
   */
  public function assertFileShouldNotExist(string $wildcard): void {
    $wildcard = $this->screenshotDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (!empty($matches)) {
      throw new \Exception(sprintf("Files matching wildcard '%s' were found, but were not supposed to", $wildcard));
    }
  }

  /**
   * Remove all files from screenshot directory.
   *
   * @Given I remove all files from screenshot directory
   */
  public function emptyScreenshotDirectory(): void {
    $files = glob($this->screenshotDir . DIRECTORY_SEPARATOR . '/*');

    if (!empty($files)) {
      array_map('unlink', $files);
    }
  }

}
