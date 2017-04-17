<?php

namespace IntegratedExperts\BehatScreenshot;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Driver\GoutteDriver;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\Exception;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Class ScreenshotContext.
 */
class ScreenshotContext extends RawMinkContext implements SnippetAcceptingContext {
  /**
   * Scenario name.
   *
   * @var string
   */
  protected $scenarioName;

  /**
   * The timestamp of the start of the scenario execution.
   *
   * @var string
   */
  protected $scenarioStartedTimestamp;

  /**
   * Directory where screenshots are stored.
   *
   * @var string
   */
  protected $dir;

  /**
   * Screenshot number.
   *
   * Used to track multiple screenshot within a single scenario.
   *
   * @var string
   */
  protected $number;

  /**
   * Date format format for screenshot file name.
   *
   * @var string
   */
  protected $dateFormat = 'd-m-Y_H-i-s';

  /**
   * Date time zone.
   *
   * @var string
   */
  protected $dateTimeZone = 'Australia/Melbourne';

  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   *
   * @var array $parameters
   */
  public function __construct($parameters = []) {
    $this->parameters = $parameters;

    if (isset($parameters['dir'])) {
      $this->dir = $parameters['dir'];
    }
    if (isset($parameters['dateFormat'])) {
      $this->dateFormat = $parameters['dateFormat'];
    }
    if (isset($parameters['dateTimeZone'])) {
      $this->dateTimeZone = $parameters['dateTimeZone'];
    }

    $currentDate = new \DateTime(NULL, new \DateTimeZone($this->dateTimeZone));

    $this->scenarioStartedTimestamp = $currentDate->format($this->dateFormat);
  }

  /**
   * Setup window screen size for test.
   *
   * @BeforeScenario
   */
  public function beforeScenarioSetBrowserViewportSize(BeforeScenarioScope $scope) {
    if ($scope->getScenario()->hasTag('javascript')) {
      if ($this->getSession()->getDriver() instanceof Selenium2Driver) {
        $this->getSession()->resizeWindow(1440, 900, 'current');
      }
    }
  }

  /**
   * Init values required for snapshots.
   *
   * @BeforeScenario
   */
  public function beforeScenarioScreenshotInit(BeforeScenarioScope $scope) {
    $this->scenarioName = $scope->getScenario()->getTitle();
    $paths = $scope->getSuite()->getSetting('paths');
    $this->dir = !empty($this->dir) ? $this->dir : getenv('BEHAT_SCREENSHOT_DIR') ? getenv('BEHAT_SCREENSHOT_DIR') : reset($paths) . '/screenshots';
    $this->number = 0;
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
    clearstatcache(TRUE, $this->dir);
    @mkdir($this->dir);

    $driver = $this->getSession()->getDriver();
    if ($driver instanceof GoutteDriver) {
      // Goutte is a pure PHP browser, so the only 'screenshot' we can save
      // is actual HTML of the page.
      $filename = $this->makeScreenshotFileName('html', $this->number++);
      // Try to get a response from the visited page, if there is any loaded
      // content at all.
      try {
        $html = $this->getSession()->getDriver()->getContent();
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . $filename, $html);
      }
      catch (Exception $e) {
      }
    }

    // Selenium driver covers Selenium and PhantomJS.
    if ($driver instanceof Selenium2Driver) {
      $filename = $this->makeScreenshotFileName('png', $this->number++);
      $this->saveScreenshot($filename, $this->dir);
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
    $filename = wordwrap($this->scenarioName, 40);
    $filename = strpos($filename, "\n") !== FALSE ? substr($filename, 0, strpos($filename, "\n")) : $filename;
    $filename = str_replace('/', 'SLASH', $filename);
    $filename = str_replace(' ', '_', $filename);
    $filename = strtolower($filename);
    $filename = $this->scenarioStartedTimestamp . '_' . $filename . '_' . sprintf('%02d', $index);

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
    $wildcard = $this->dir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      throw new \Exception(sprintf("Unable to find files matching wildcard '%s'", $wildcard));
    }
  }

}
