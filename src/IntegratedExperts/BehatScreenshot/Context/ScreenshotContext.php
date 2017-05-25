<?php

/**
 * @file
 * Behat context to enable Screenshot support in tests.
 */

namespace IntegratedExperts\BehatScreenshot\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\MinkExtension\Context\RawMinkContext;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Class ScreenshotContext.
 */
class ScreenshotContext extends RawMinkContext implements SnippetAcceptingContext, ScreenshotContextInterface
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
     * Screenshot context parameters.
     *
     * @var array
     */
    protected $parameters;

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
        if ($this->parameters['fail'] && !$event->getTestResult()->isPassed()) {
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
        $data = null;
        $driver = $this->getSession()->getDriver();

        if ($driver instanceof Selenium2Driver) {
            $data = $this->getSession()->getScreenshot();
            $ext = 'png';
        } elseif ($this->getSession()->getDriver()->getClient()->getInternalResponse()) {
            $data = $this->getSession()->getDriver()->getContent();
            $ext = 'html';
        }

        if ($data) {
            $this->saveScreenshotData($this->makeFileName($ext), $data);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function purgeFilesInDir()
    {
        $fs = new Filesystem();
        $finder = new Finder();
        if ($fs->exists($this->parameters['dir'])) {
            $fs->remove($finder->files()->in($this->parameters['dir']));
        }
    }

    protected function saveScreenshotData($filename, $data)
    {
        $this->prepareDir($this->parameters['dir']);
        file_put_contents($this->parameters['dir'].DIRECTORY_SEPARATOR.$filename, $data);
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
     *
     * @param string $dir Name of preparing directory.
     */
    protected function prepareDir($dir)
    {
        $fs = new Filesystem();
        $fs->mkdir($dir, 0755);
    }
}
