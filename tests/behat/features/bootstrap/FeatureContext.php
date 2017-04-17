<?php

/**
 * @file
 * Feature context Behat testing.
 */

require_once "PhpServerTrait.php";

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends MinkContext implements Context {

  use PhpServerTrait {
    PhpServerTrait::__construct as private __phpServerConstruct;
  }

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $fixtures_dir = dirname(__FILE__) . '/../fixtures';
    $this->__phpServerConstruct($fixtures_dir);
  }

  /**
   * Go to the phpserver test page.
   *
   * @Given /^(?:|I )am on (?:|the )phpserver test page$/
   * @When /^(?:|I )go to (?:|the )phpserver test page$/
   */
  public function goToPhpServerTestPage() {
    $this->getSession()->visit('http://localhost:8888/testpage.html');
  }

}
