<?php

/**
 * @file
 * Behat context to enable Screenshot support in tests.
 */

namespace IntegratedExperts\BehatScreenshotExtension\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\MinkExtension\Context\RawMinkContext;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ScreenshotContext.
 */
class ScreenshotContext extends RawMinkContext implements SnippetAcceptingContext, ScreenshotAwareContext
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
     * Screenshot directory name.
     *
     * @var string
     */
    private $dir;

    /**
     * Makes screenshot when fail.
     *
     * @var bool
     */
    private $fail;

    /**
     * {@inheritdoc}
     */
    public function setScreenshotParameters($dir, $fail, $html, $png)
    {
        $this->dir = $dir;
        $this->fail = $fail;
        $this->html = $html;
        $this->png = $png;

        return $this;
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
        if ($this->fail && !$event->getTestResult()->isPassed()) {
            $this->iSaveScreenshot();
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
    public function iSaveScreenshot()
    {
        $data = null;
        $driver = $this->getSession()->getDriver();

        if ($driver instanceof Selenium2Driver) {
            // If both png and html are not set assume the default of saving png screen shots.
            if ($this->png || empty($this->png) && empty($this->html)) {
                $data = $this->getSession()->getScreenshot();
                $ext = 'png';
                if ($data) {
                    $this->saveScreenshotData($this->makeFileName($ext), $data);
                }
            }
            if ($this->html) {
                $data = $this->getSession()->getDriver()->getContent();
                $ext = 'html';
                if ($data) {
                    $this->saveScreenshotData($this->makeFileName($ext), $data);
                }
            }
        } elseif ($this->getSession()->getDriver()->getClient()->getInternalResponse()) {
            $data = $this->getSession()->getDriver()->getContent();
            $ext = 'html';
            if ($data) {
                $this->saveScreenshotData($this->makeFileName($ext), $data);
            }
        }

    }

    /**
     * Save screenshot data into a file.
     *
     * @param string $filename
     *   File name to write.
     * @param string $data
     *   Data to write into a file.
     */
    protected function saveScreenshotData($filename, $data)
    {
        $this->prepareDir($this->dir);
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.$filename, $data);
    }

    /**
     * Prepare directory.
     *
     * @param string $dir Name of preparing directory.
     */
    protected function prepareDir($dir)
    {
        $fs = new Filesystem();
        $fs->mkdir($dir, 0755);
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
}
