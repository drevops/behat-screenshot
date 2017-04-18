<?php

/**
 * @file
 * Feature context Behat testing.
 */

use Behat\Behat\Context\Context;
use \Behat\MinkExtension\Context\RawMinkContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawMinkContext implements Context {
  /**
   * Go to the phpserver test page.
   *
   * @Given /^(?:|I )am on (?:|the )phpserver test page$/
   * @When /^(?:|I )go to (?:|the )phpserver test page$/
   */
  public function goToPhpServerTestPage() {
    $this->getSession()->visit('http://localhost:8888/testpage.html');
  }

  /**
   * Go to the screenshot test page.
   *
   * @Given /^(?:|I )am on (?:|the )screenshot test page$/
   * @When /^(?:|I )go to (?:|the )screenshot test page$/
   */
  public function goToScreenshotTestPage() {
    $this->getSession()->visit('http://localhost:8888/screenshot/screenshot.html');
  }

  /**
   * Checks whether a file wildcard at provided path exists.
   *
   * @param string $wildcard
   *   File name with a wildcard.
   *
   * @Given /^file wildcard "([^"]*)" should exist$/
   */
  public function assertFileShouldExist($wildcard) {
    $wildcard = $this->dir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      throw new \Exception(sprintf("Unable to find files matching wildcard '%s'", $wildcard));
    }
  }

}
