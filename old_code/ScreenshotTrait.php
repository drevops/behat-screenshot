<?php

/**
 * @file
 * Screenshot trait.
 */

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Driver\GoutteDriver;
use Behat\Mink\Driver\Selenium2Driver;

/**
 * Class ScreenshotTrait.
 */
trait ScreenshotTrait {
  /**
   * Scenario name.
   *
   * @var string
   */
  protected $screenshotScenarioName;

  /**
   * The timestamp of the start of the scenario execution.
   *
   * @var string
   */
  protected $screenshotScenarioStartedTimestamp;

  /**
   * Directory where screenshots are stored.
   *
   * @var string
   */
  protected $screenshotDir;

  /**
   * Screenshot number.
   *
   * Used to track multiple screenshot within a single scenario.
   *
   * @var string
   */
  protected $screenshotNumber;

  /**
   * Init values required for snapshots.
   *
   * @BeforeScenario
   */
  public function beforeScenarioScreenshotInit(BeforeScenarioScope $scope) {
    $this->screenshotScenarioStartedTimestamp = microtime(TRUE);
    $this->screenshotScenarioName = $scope->getScenario()->getTitle();
    $paths = $scope->getSuite()->getSetting('paths');
    $this->screenshotDir = getenv('BEHAT_SCREENSHOT_DIR') ? getenv('BEHAT_SCREENSHOT_DIR') : reset($paths) . '/screenshots';
    $this->screenshotNumber = 0;
  }

  /**
   * Save debug screenshot.
   *
   * Handles different driver types.
   *
   * @When /^(?:|I\s)save screenshot$/
   */
  public function saveDebugScreenshot() {
    // Clear stat cache and force creation of the screenshot dir.
    // This is required to handle slow file systems, like the ones used in VMs.
    clearstatcache(TRUE, $this->screenshotDir);
    @mkdir($this->screenshotDir);

    $driver = $this->getSession()->getDriver();
    if ($driver instanceof GoutteDriver) {
      // Goutte is a pure PHP browser, so the only 'screenshot' we can save
      // is actual HTML of the page.
      $filename = $this->makeScreenshotFileName('html', $this->screenshotNumber++);
      // Try to get a response from the visited page, if there is any loaded
      // content at all.
      try {
        $html = $this->getSession()->getDriver()->getContent();
        file_put_contents($this->screenshotDir . '/' . $filename, $html);
      }
      catch (Exception $e) {
      }
    }

    // Selenium driver covers Selenium and PhantomJS.
    if ($driver instanceof Selenium2Driver) {
      $filename = $this->makeScreenshotFileName('png', $this->screenshotNumber++);
      $this->saveScreenshot($filename, $this->screenshotDir);
    }
  }

  /**
   * Make screenshot filename.
   *
   * Format: micro.seconds_title_of_scenario_trimmed.ext.
   *
   * @param string $ext
   *   File extension without dot.
   * @param int $index
   *   File index to include.
   *
   * @return string
   *   Unique file name.
   */
  protected function makeScreenshotFileName($ext, $index) {
    $filename = wordwrap($this->screenshotScenarioName, 40);
    $filename = strpos($filename, "\n") !== FALSE ? substr($filename, 0, strpos($filename, "\n")) : $filename;
    $filename = str_replace('/', 'SLASH', $filename);
    $filename = str_replace(' ', '_', $filename);
    $filename = strtolower($filename);
    $filename = $this->screenshotScenarioStartedTimestamp . '_' . $filename . '_' . sprintf('%02d', $index);

    return $filename . '.' . $ext;
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
    $wildcard = $this->screenshotDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      throw new \Exception(sprintf("Unable to find files matching wildcard '%s'", $wildcard));
    }
  }

}
