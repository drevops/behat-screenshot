<?php

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatScreenshotExtension\Tokenizer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ScreenshotContext.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ScreenshotContext extends RawMinkContext implements ScreenshotAwareContextInterface {
  /**
   * Screenshot step line.
   */
  protected string $stepLine;

  /**
   * Makes screenshot when fail.
   */
  protected bool $fail = FALSE;

  /**
   * Screenshot directory name.
   */
  protected string $dir = '';

  /**
   * Prefix for failed screenshot files.
   */
  protected string $failPrefix = '';

  /**
   * Before step scope.
   */
  protected BeforeStepScope $beforeStepScope;

  /**
   * Filename pattern.
   */
  protected string $filenamePattern;

  /**
   * Filename pattern failed.
   */
  protected string $filenamePatternFailed;

  /**
   * {@inheritdoc}
   */
  public function setScreenshotParameters(string $dir, bool $fail, string $failPrefix, string $filenamePattern, string $filenamePatternFailed): static {
    $this->dir = $dir;
    $this->fail = $fail;
    $this->failPrefix = $failPrefix;
    $this->filenamePattern = $filenamePattern;
    $this->filenamePatternFailed = $filenamePatternFailed;

    return $this;
  }

  /**
   * Init values required for snapshots.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   Scenario scope.
   *
   * @BeforeScenario
   */
  public function beforeScenarioInit(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('javascript')) {
      $driver = $this->getSession()->getDriver();
      if ($driver instanceof Selenium2Driver) {
        try {
          // Start driver's session manually if it is not already started.
          if (!$driver->isStarted()) {
            $driver->start();
          }
          $this->getSession()->resizeWindow(1440, 900, 'current');
        }
        catch (\Exception $exception) {
          throw new \RuntimeException(
                sprintf(
                    'Please make sure that Selenium server is running. %s',
                    $exception->getMessage(),
                ),
                $exception->getCode(),
                $exception,
                    );
        }
      }
    }
  }

  /**
   * Init values required for snapshot.
   *
   * @BeforeStep
   */
  public function beforeStepInit(BeforeStepScope $scope): void {
    $featureFile = $scope->getFeature()->getFile();
    if (!$featureFile) {
      throw new \RuntimeException('Feature file not found.');
    }
    $this->beforeStepScope = $scope;
  }

  /**
   * After scope event handler to print last response on error.
   *
   * @param \Behat\Behat\Hook\Scope\AfterStepScope $event
   *   After scope event.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *
   * @AfterStep
   */
  public function printLastResponseOnError(AfterStepScope $event): void {
    if ($this->fail && !$event->getTestResult()->isPassed()) {
      $this->iSaveScreenshot(TRUE);
    }
  }

  /**
   * Save debug screenshot.
   *
   * Handles different driver types.
   *
   * @param bool $fail
   *   Denotes if this was called in a context of the failed
   *   test.
   * @param string|null $filename
   *   File name.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *
   * @When save screenshot
   * @When I save screenshot
   */
  public function iSaveScreenshot(bool $fail = FALSE, string $filename = NULL): void {
    $driver = $this->getSession()->getDriver();
    $fileName = $this->makeFileName('html', $filename, $fail);
    try {
      $data = $driver->getContent();
    }
    catch (DriverException) {
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
      $fileName = $this->makeFileName('png', $filename, $fail);
      $this->saveScreenshotData($fileName, $data);
    }
    catch (UnsupportedDriverActionException) {
      // Nothing to do here - drivers without support for screenshots
      // simply do not have them created.
    }
  }

  /**
   * Save screenshot with name.
   *
   * @param string $filename
   *   File name.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *
   * @When I save screenshot with name :filename
   */
  public function iSaveScreenshotWithName(string $filename): void {
    $this->iSaveScreenshot(FALSE, $filename);
  }

  /**
   * Save screenshot with specific dimensions.
   *
   * @param string|int $width
   *   Width to resize browser to.
   * @param string|int $height
   *   Height to resize browser to.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *
   * @When save :width x :height screenshot
   * @When I save :width x :height screenshot
   */
  public function iSaveSizedScreenshot(string|int $width = 1440, string|int $height = 900): void {
    try {
      $this->getSession()->resizeWindow((int) $width, (int) $height, 'current');
    }
    catch (UnsupportedDriverActionException) {
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
  protected function saveScreenshotData(string $filename, string $data): void {
    $this->prepareDir($this->dir);
    file_put_contents($this->dir . DIRECTORY_SEPARATOR . $filename, $data);
  }

  /**
   * Prepare directory.
   *
   * @param string $dir
   *   Name of preparing directory.
   */
  protected function prepareDir(string $dir): void {
    $fs = new Filesystem();
    $fs->mkdir($dir, 0755);
  }

  /**
   * Make screenshot filename.
   *
   * Format: microseconds.featurefilename_linenumber.ext.
   *
   * @param string $ext
   *   File extension without dot.
   * @param string|null $filename
   *   Optional file name.
   * @param bool $fail
   *   Make filename for fail case.
   *
   * @return string
   *   Unique file name.
   *
   * @throws \Exception
   */
  protected function makeFileName(string $ext, string $filename = NULL, bool $fail = FALSE): string {
    if ($fail) {
      $filename = $this->filenamePatternFailed;
    }
    elseif (empty($filename)) {
      $filename = $this->filenamePattern;
    }

    // Make sure {ext} token is on filename.
    if (!str_ends_with($filename, '.{ext}')) {
      $filename .= '.{ext}';
    }

    $feature = $this->getBeforeStepScope()->getFeature();
    $step = $this->getBeforeStepScope()->getStep();

    try {
      $url = $this->getSession()->getCurrentUrl();
    }
    catch (\Exception) {
      $url = NULL;
    }

    $data = [
      'ext' => $ext,
      'step_name' => $step->getText(),
      'step_line' => $step->getLine(),
      'feature_file' => $feature->getFile(),
      'url' => $url,
      'time' => time(),
      'fail_prefix' => $this->failPrefix,
    ];

    return Tokenizer::replaceTokens($filename, $data);
  }

  /**
   * Get before step scope.
   *
   * @return \Behat\Behat\Hook\Scope\BeforeStepScope
   *   The before step scope.
   */
  public function getBeforeStepScope(): BeforeStepScope {
    return $this->beforeStepScope;
  }

}
