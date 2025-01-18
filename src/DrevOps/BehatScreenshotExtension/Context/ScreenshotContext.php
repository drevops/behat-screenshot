<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatScreenshotExtension\Tokenizer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ScreenshotContext.
 */
class ScreenshotContext extends RawMinkContext implements ScreenshotAwareContextInterface {

  /**
   * Makes screenshot when fail.
   */
  protected bool $fail = FALSE;

  /**
   * Prefix for failed screenshot files.
   */
  protected string $failPrefix = '';

  /**
   * Screenshot directory name.
   */
  protected string $dir = '';

  /**
   * Filename pattern.
   */
  protected string $filenamePattern;

  /**
   * Filename pattern failed.
   */
  protected string $filenamePatternFailed;

  /**
   * Information types to be added to a screenshot.
   *
   * @var array<int,string>
   */
  protected array $infoTypes = [];

  /**
   * Information to be added to a screenshot.
   *
   * @var array<string, string>
   */
  protected array $info = [];

  /**
   * Before step scope.
   */
  protected BeforeStepScope $beforeStepScope;

  /**
   * {@inheritdoc}
   */
  public function setScreenshotParameters(string $dir, bool $fail, string $fail_prefix, string $filename_pattern, string $filename_pattern_failed, array $info_types): static {
    $this->dir = $dir;
    $this->fail = $fail;
    $this->failPrefix = $fail_prefix;
    $this->filenamePattern = $filename_pattern;
    $this->filenamePatternFailed = $filename_pattern_failed;
    $this->infoTypes = $info_types;

    return $this;
  }

  /**
   * Init values required for screenshots.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   Scenario scope.
   *
   * @BeforeScenario
   */
  public function beforeScenarioInit(BeforeScenarioScope $scope): void {
    if (!$scope->getScenario()->hasTag('javascript')) {
      return;
    }

    $driver = $this->getSession()->getDriver();

    try {
      // Start driver's session manually if it is not already started.
      if (!$driver->isStarted()) {
        $driver->start();
      }

      $this->getSession()->resizeWindow(1440, 900, 'current');
    }
    catch (UnsupportedDriverActionException $exception) {
      // Nothing to do here - drivers without support for visual screenshots
      // simply do not have them created.
    }
    catch (DriverException $exception) {
      throw new \RuntimeException(sprintf("Unable to connect to the driver's server: %s", $exception->getMessage()), $exception->getCode(), $exception);
    }
  }

  /**
   * Init values required for a screenshot.
   *
   * @BeforeStep
   */
  public function beforeStepInit(BeforeStepScope $scope): void {
    $this->beforeStepScope = $scope;
  }

  /**
   * After step handler to print last response on error.
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
    if (!$event->getTestResult()->isPassed() && $this->fail) {
      $this->iSaveScreenshot(TRUE);
    }
  }

  /**
   * Save screenshot content into a file.
   *
   * @param bool $is_failure
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
  public function iSaveScreenshot(bool $is_failure = FALSE, ?string $filename = NULL): void {
    $file_name = $this->makeFileName('html', $filename, $is_failure);

    try {
      $driver = $this->getSession()->getDriver();
      $content = $driver->getContent();

      $info = $this->renderInfo();
      $content = empty($info) ? $content : nl2br($info) . "<hr/>\n" . $content;
    }
    catch (DriverException) {
      // Do nothing if the driver does not have any content - most
      // likely the page has not been loaded yet.
      return;
    }

    $this->saveScreenshotContent($file_name, $content);

    // Drivers that do not support making screenshots, including Goutte
    // driver that is shipped with Behat, throw exception. For such drivers,
    // screenshot stored as an HTML page (without referenced assets).
    try {
      $driver = $this->getSession()->getDriver();
      $content = $driver->getScreenshot();
      // Preserve filename, but change the extension - this is to group
      // content and screenshot files together by name.
      $file_name = $this->makeFileName('png', $filename, $is_failure);
      $this->saveScreenshotContent($file_name, $content);
    }
    // @codeCoverageIgnoreStart
    catch (UnsupportedDriverActionException) {
      // Nothing to do here - drivers without support for screenshots
      // simply do not have them created.
    }
    // @codeCoverageIgnoreEnd
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
      $this->getSession()->resizeWindow(intval($width), intval($height), 'current');
    }
    catch (UnsupportedDriverActionException) {
      // Nothing to do here - drivers without resize support may proceed.
    }

    $this->iSaveScreenshot();
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

  /**
   * Adds information to context.
   *
   * @param string $label
   *   Debug information label.
   * @param string $value
   *   Debug information value.
   */
  public function appendInfo(string $label, string $value): void {
    $this->info[$label] = $value;
  }

  /**
   * Render information.
   *
   * @return string
   *   Rendered debug information.
   */
  public function renderInfo(): string {
    $this->compileInfo();

    // Use a non-HTML output to make this output universal.
    return implode("\n", array_map(
      fn($key, $value): string => sprintf('%s: %s', $key, $value),
      array_keys($this->info),
      $this->info,
    ));
  }

  /**
   * Compile information.
   */
  protected function compileInfo(): void {
    foreach ($this->infoTypes as $type) {
      if ($type === 'url') {
        $this->appendInfo('Current URL', $this->getSession()->getCurrentUrl());
      }
      if ($type === 'feature') {
        $this->appendInfo('Feature', (string) $this->getBeforeStepScope()->getFeature()->getTitle());
      }
      if ($type === 'step') {
        $step = $this->getBeforeStepScope()->getStep();
        $this->appendInfo('Step', sprintf('%s (line %d)', $step->getText(), $step->getLine()));
      }
      if ($type === 'datetime') {
        $this->appendInfo('Datetime', date('Y-m-d H:i:s'));
      }
    }
  }

  /**
   * Get screenshot directory.
   *
   * @return string
   *   Screenshot directory.
   */
  public function getDir(): string {
    return $this->dir;
  }

  /**
   * Get current timestamp.
   *
   * @return int
   *   Current timestamp.
   *
   * @codeCoverageIgnore
   */
  public function getCurrentTime(): int {
    return time();
  }

  /**
   * Save screenshot content into a file.
   *
   * @param string $filename
   *   File name to write.
   * @param string $content
   *   Content to write into a file.
   */
  protected function saveScreenshotContent(string $filename, string $content): void {
    $this->prepareDir($this->dir);
    $success = file_put_contents($this->dir . DIRECTORY_SEPARATOR . $filename, $content);
    if ($success === FALSE) {
      throw new \RuntimeException(sprintf('Failed to save screenshot to %s', $filename));
    }
  }

  /**
   * Prepare directory.
   *
   * @param string $dir
   *   Name of preparing directory.
   */
  protected function prepareDir(string $dir): void {
    (new Filesystem())->mkdir($dir, 0755);
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
   * @param bool $is_failure
   *   Make filename for fail case.
   *
   * @return string
   *   Unique file name.
   *
   * @throws \Exception
   */
  protected function makeFileName(string $ext, ?string $filename = NULL, bool $is_failure = FALSE): string {
    if ($is_failure) {
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

    if (!empty($url) && !empty(getenv('BEHAT_SCREENSHOT_TOKEN_HOST'))) {
      // @codeCoverageIgnoreStart
      $host = parse_url($url, PHP_URL_HOST);
      if ($host) {
        $url = str_replace($host, getenv('BEHAT_SCREENSHOT_TOKEN_HOST'), $url);
      }
      // @codeCoverageIgnoreEnd
    }

    $data = [
      'ext' => $ext,
      'step_name' => $step->getText(),
      'step_line' => $step->getLine(),
      'feature_file' => $feature->getFile(),
      'url' => $url,
      'timestamp' => $this->getCurrentTime(),
      'fail_prefix' => $this->failPrefix,
    ];

    return Tokenizer::replaceTokens($filename, $data);
  }

}
