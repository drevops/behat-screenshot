<?php

/**
 * @file
 * Behat context to enable Screenshot support in tests.
 */

namespace IntegratedExperts\BehatScreenshot;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Driver\GoutteDriver;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Class ScreenshotContext.
 */
class ScreenshotContext extends RawMinkContext implements SnippetAcceptingContext
{
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
    protected $dateFormat;

    /**
     * Date time zone.
     *
     * @var string
     */
    protected $dateTimeZone;

    /**
     * Flag to create a screenshot when test fails.
     *
     * @var bool
     */
    protected $onFail;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the context constructor through
     * behat.yml.
     *
     * @param array $parameters Get parameters for construct test.
     */
    public function __construct($parameters = [])
    {
        $this->dir = isset($parameters['dir']) ? $parameters['dir'] : __DIR__.'/screenshot';
        $this->dateFormat = isset($parameters['dateFormat']) ? $parameters['dateFormat'] : 'Ymh_His';
        $this->onFail = isset($parameters['fail']) ? $parameters['fail'] : true;

        $this->scenarioStartedTimestamp = date($this->dateFormat);
    }

    /**
     * Init values required for snapshots.
     *
     * @param BeforeScenarioScope $scope Scenario scope.
     *
     * @BeforeScenario
     */
    public function beforeScenarioScreenshotInit(BeforeScenarioScope $scope)
    {
        if ($scope->getScenario()->hasTag('javascript')) {
            if ($this->getSession()->getDriver() instanceof Selenium2Driver) {
                $this->getSession()->resizeWindow(1440, 900, 'current');
            }
        }

        $this->scenarioName = $scope->getScenario()->getTitle();
        $this->number = 0;
    }

    /**
     * After scope event handler to print last response on error.
     *
     * @param AfterStepScope $event After scope event.
     *
     * @AfterStep
     */
    public function printLastResponseOnError(AfterStepScope $event)
    {
        if ($this->onFail && !$event->getTestResult()->isPassed()) {
            $this->saveDebugScreenshot();
        }
    }

    /**
     * Save debug screenshot.
     *
     * Handles different driver types.
     *
     * @When /^(?:|I\s)save screenshot$/
     */
    public function saveDebugScreenshot()
    {
        $this->prepareDir();

        $driver = $this->getSession()->getDriver();
        if ($driver instanceof GoutteDriver) {
            // Goutte is a pure PHP browser, so the only 'screenshot' we can save
            // is actual HTML of the page.
            $filename = $this->makeScreenshotFileName('html', $this->number++);
            // Try to get a response from the visited page, if there is any loaded
            // content at all.
            try {
                $html = $this->getSession()->getDriver()->getContent();
                $this->writeFile($filename, $html);
            } catch (Exception $e) {
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
     * @param string $ext File extension without dot.
     * @param int $index File index to include.
     *
     * @return string
     *   Unique file name.
     */
    protected function makeScreenshotFileName($ext, $index)
    {
        $filename = wordwrap($this->scenarioName, 40);
        $filename = strpos($filename, "\n") !== false
            ? substr($filename, 0, strpos($filename, "\n"))
            : $filename;
        $filename = str_replace('/', 'SLASH', $filename);
        $filename = str_replace(' ', '_', $filename);
        $filename = strtolower($filename);
        $filename = sprintf(
            '%s_%s_%02d',
            $this->scenarioStartedTimestamp,
            $filename,
            $index
        );

        return $filename.'.'.$ext;
    }


    /**
     * Prepare directory for write new screenshot.
     */
    protected function prepareDir()
    {
        // Clear stat cache and force creation of the screenshot dir.
        // This is required to handle slow file systems, like the ones used in VMs.
        clearstatcache(true, $this->dir);
        @mkdir($this->dir);
    }


    /**
     * Write data into file.
     *
     * @param string $filename Name for write file.
     * @param string $data Data for write ito file.
     */
    protected function writeFile($filename, $data)
    {
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.$filename, $data);
    }
}
