<?php

/**
 * @file
 * Behat context to enable Screenshot support in tests.
 */

namespace IntegratedExperts\BehatScreenshot;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\RuntimeException;

/**
 * Class ScreenshotContext.
 */
class ScreenshotContext extends RawMinkContext implements SnippetAcceptingContext
{
    /**
     * Screenshot step filename.
     *
     * @var string
     */
    protected $featureFile;

    /**
     * Screenshot step line.
     *
     * @var int
     */
    protected $stepLine;

    /**
     * Directory where screenshots are stored.
     *
     * @var string
     */
    protected $dir;

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
        $this->onFail = isset($parameters['fail']) ? $parameters['fail'] : true;
    }

    /**
     * Init function before tests run.
     *
     * @param BeforeSuiteScope $scope
     *
     * @BeforeSuite
     */
    public static function beforeSuitInit(BeforeSuiteScope $scope)
    {
        $contextSettings = null;
        foreach ($scope->getSuite()->getSetting('contexts') as $context) {
            if (isset($context['IntegratedExperts\BehatScreenshot\ScreenshotContext'][0])) {
                $contextSettings = $context['IntegratedExperts\BehatScreenshot\ScreenshotContext'][0];
                break;
            }
        }

        if (empty($contextSettings['dir'])) {
            throw new RuntimeException('Screenshot directory is not provided in Behat configuration');
        }

        $purge = false;
        if (getenv('BEHAT_SCREENSHOT_PURGE')) {
            $purge = (bool) getenv('BEHAT_SCREENSHOT_PURGE');
        } elseif (isset($contextSettings['purge'])) {
            $purge = $contextSettings['purge'];
        }

        if ($purge) {
            self::purgeFilesInDir($contextSettings['dir']);
        }
    }

    /**
     * Init values required for snapshots.
     *
     * @param BeforeScenarioScope $scope Scenario scope.
     *
     * @BeforeScenario
     */
    public function beforeScenarioInit(BeforeScenarioScope $scope)
    {
        if ($scope->getScenario()->hasTag('javascript')) {
            if ($this->getSession()->getDriver() instanceof Selenium2Driver) {
                $this->getSession()->resizeWindow(1440, 900, 'current');
            }
        }
    }

    /**
     * Init values required for snapshot.
     *
     * @param BeforeStepScope $scope
     *
     * @BeforeStep
     */
    public function beforeStepInit(BeforeStepScope $scope)
    {
        $this->featureFile = $scope->getFeature()->getFile();
        $this->stepLine = $scope->getStep()->getLine();
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
     * @When save screenshot
     * @When I save screenshot
     */
    public function saveDebugScreenshot()
    {
        $driver = $this->getSession()->getDriver();

        $data = $driver instanceof Selenium2Driver ? $this->getSession()->getScreenshot() : $this->getSession()->getDriver()->getContent();
        $ext = $driver instanceof Selenium2Driver ? 'png' : 'html';

        $this->saveScreenshotData($this->makeFileName($ext), $data);
    }

    protected function saveScreenshotData($filename, $data)
    {
        $this->prepareDir($this->dir);
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.$filename, $data);
    }

    /**
     * Make screenshot filename.
     *
     * Format: microseconds.featurefilename_linenumber.ext
     *
     * @param string $ext File extension without dot.
     *
     * @return string Unique file name.
     */
    protected function makeFileName($ext)
    {
        return sprintf('%01.2f.%s_[%s].%s', microtime(true), basename($this->featureFile), $this->stepLine, $ext);
    }

    /**
     * Prepare directory.
     */
    protected function prepareDir($dir)
    {
        $fs = new Filesystem();
        $fs->mkdir($dir, 0755);
    }

    /**
     * Remove files in directory.
     *
     * @param string $dir Directory name.
     */
    protected static function purgeFilesInDir($dir)
    {
        $fs = new Filesystem();
        $finder = new Finder();
        if ($fs->exists($dir)) {
            $fs->remove($finder->files()->in($dir));
        }
    }
}
