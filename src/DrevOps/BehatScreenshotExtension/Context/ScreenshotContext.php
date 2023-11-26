<?php

/**
 * @file
 * Behat context to enable Screenshot support in tests.
 */

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
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
     * Screenshot step text, sanitised for filename.
     *
     * @var string
     */
    protected $stepText;

    /**
     * Current URL, sanitised for filename.
     *
     * @var string
     */
    protected $currentUrl;

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
     * Prefix for failed screenshot files.
     *
     * @var string
     */
    private $failPrefix;

    /**
     * Pattern for filename generation.
     *
     * @var string
     */
    private $filenamePattern = '{datetime:u}.{fail_prefix}{feature_file}_{step_line}.{ext}';

    /**
     * Tokens for filename generation.
     *
     * @var array<string>
     */
    private $filenameTokens = [];

    /**
     * {@inheritdoc}
     */
    public function setScreenshotParameters(string $dir, bool $fail, string $failPrefix, string|null $filenamePattern = null): static
    {
        $this->dir = $dir;
        $this->fail = $fail;
        $this->failPrefix = $failPrefix;
        if (!is_null($filenamePattern)) {
            $this->filenamePattern = $filenamePattern;
        }

        return $this;
    }

    /**
     * Init values required for snapshots.
     *
     * @param BeforeScenarioScope $scope Scenario scope.
     *
     * @BeforeScenario
     */
    public function beforeScenarioInit(BeforeScenarioScope $scope): void
    {
        if ($scope->getScenario()->hasTag('javascript')) {
            $driver = $this->getSession()->getDriver();
            if ($driver instanceof Selenium2Driver) {
                // Start driver's session manually if it is not already started.
                if (!$driver->isStarted()) {
                    $driver->start();
                }
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
    public function beforeStepInit(BeforeStepScope $scope): void
    {
        $featureFile = $scope->getFeature()->getFile();
        if (!$featureFile) {
            throw new \RuntimeException('Feature file not found.');
        }
        $this->featureFile = $featureFile;
        $this->stepLine = $scope->getStep()->getLine();

        $this->setFilenameTokens($scope);
    }

    /**
     * After scope event handler to print last response on error.
     *
     * @param AfterStepScope $event After scope event.
     *
     * @AfterStep
     */
    public function printLastResponseOnError(AfterStepScope $event): void
    {
        if ($this->fail && !$event->getTestResult()->isPassed()) {
            $this->iSaveScreenshot(true);
        }
    }

    /**
     * Save debug screenshot.
     *
     * Handles different driver types.
     *
     * @param bool $fail Denotes if this was called in a context of the failed
     *                   test.
     *
     * @When save screenshot
     * @When I save screenshot
     */
    public function iSaveScreenshot($fail = false): void
    {
        $driver = $this->getSession()->getDriver();

        $fileName = $this->makeFileName('html', $fail ? $this->failPrefix : '');

        try {
            $data = $driver->getContent();
        } catch (DriverException $exception) {
            // Do not do anything if the driver does not have any content - most
            // likely the page has not been loaded yet.
            return;
        }

        $this->saveScreenshotData($fileName, $data);

        // Drivers that do not support making screenshots, including Goutte
        // driver that is shipped with Behat, throw exception. For such drivers,
        // screenshot stored as an HTML page (without referenced assets).
        try {
            $data = $driver->getScreenshot();
            // Preserve filename, but change the extension - this is to group
            // content and screenshot files together by name.
            $fileName = substr($fileName, 0, -1 * strlen('html')).'png';
            $this->saveScreenshotData($fileName, $data);
        } catch (UnsupportedDriverActionException $exception) {
            // Nothing to do here - drivers without support for screenshots
            // simply do not have them created.
        }
    }

    /**
     * Save screenshot with specific dimensions.
     *
     * @param int $width  Width to resize browser to.
     * @param int $height Height to resize browser to.
     *
     * @When save :width x :height screenshot
     * @When I save :width x :height screenshot
     */
    public function iSaveSizedScreenshot(string|int $width = 1440, string|int $height = 900): void
    {
        try {
            $this->getSession()->resizeWindow((int) $width, (int) $height, 'current');
        } catch (UnsupportedDriverActionException $exception) {
            // Nothing to do here - drivers without resize support may proceed.
        }
        $this->iSaveScreenshot();
    }

    /**
     * Save screenshot data into a file.
     *
     * @param string $filename
     *   File name to write.
     * @param string $data
     *   Data to write into a file.
     */
    protected function saveScreenshotData(string $filename, string $data): void
    {
        $this->prepareDir($this->dir);
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.$filename, $data);
    }

    /**
     * Prepare directory.
     *
     * @param string $dir Name of preparing directory.
     */
    protected function prepareDir(string $dir): void
    {
        $fs = new Filesystem();
        $fs->mkdir($dir, 0755);
    }

    /**
     * Make screenshot filename.
     *
     * Example format: behat-{date:u}.{step_file}-{$step_line}.{ext}
     *
     * @param string $ext    File extension without dot.
     * @param string $prefix Optional file name prefix for a failed test.
     *
     * @return string File name.
     */
    protected function makeFileName(string $ext, string $prefix = ''): string
    {
        $this->setFilenameToken('ext', $ext);

        return strtr($this->filenamePattern, $this->filenameTokens);
    }

    /**
     * Set filename tokens, ensuring safe strings.
     *
     * Retains alphanumeric characters and period, replacing everything else with underscore.
     * Trims resulting leading/trailing underscores from values except for BC on "prefix".
     *
     * @param string $key
     *   Token to set, without curly braces.
     * @param string $value
     *   Value to set token to.
     */
    protected function setFilenameToken(string $key, string|int $value) : void
    {
        $value = preg_replace("/[^[:alnum:]\.]+/i", "_", (string) $value);
        if (!is_null($value)) {
            if ('prefix' === $key) {
                $this->filenameTokens["{{$key}}"] = $value;
            } else {
                $this->filenameTokens["{{$key}}"] = trim($value, '_');
            }
        }
    }

    /**
     * Set tokens for capture filename.
     *
     * @param BeforeStepScope $scope
     * @return void
     */
    private function setFilenameTokens(BeforeStepScope $scope)
    {
        $this->setFilenameToken('feature_file', basename($this->featureFile));
        $this->setFilenameToken('step_line', (string) $this->stepLine);
        $this->setFilenameToken('step_text', $scope->getStep()->getText());
        $this->setFilenameToken('datetime:u', sprintf('%01.2f', microtime(true)));
        $this->setFilenameToken('datetime', date('Ymd_His'));
        $this->setFilenameToken('fail_prefix', $this->failPrefix);
        $this->setFilenameToken('url', 'unknown');
        try {
            $currentUrl = $this->getSession()->getDriver()->getCurrentUrl();
            $this->setFilenameToken('url', $currentUrl);
            if ($currentUrlParts = parse_url($currentUrl)) {
                foreach ($currentUrlParts as $key => $value) {
                    $this->setFilenameToken("url:{$key}", $value);
                }
            }
        } catch (\Exception $exception) {
            // Browser may not be on a URL yet.
        }

        // Sprintf for step_line.
        $pattern = '/{(step_line:.*)}/U';
        // @FIXME Should use the configured filenamePattern here.
        preg_match_all($pattern, "some.{step_line:%03d}.other", $matches);
        if (!empty($matches[1])) {
            foreach ($matches as $tokens) {
                foreach ($tokens as $token) {
                    $parts = explode(':', $token);
                    $this->setFilenameToken("step_line:{$parts[1]}", sprintf($parts[1], $this->stepLine));
                }
            }
        }
    }
}
