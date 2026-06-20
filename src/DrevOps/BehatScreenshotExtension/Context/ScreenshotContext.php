<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatScreenshotExtension\AnimatedGif;
use DrevOps\BehatScreenshotExtension\Tokenizer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ScreenshotContext.
 */
class ScreenshotContext extends RawMinkContext implements ScreenshotAwareContextInterface {

  /**
   * Screenshot directory path.
   */
  protected string $dir = '';

  /**
   * Make screenshots on failed tests.
   */
  protected bool $onFailed = FALSE;

  /**
   * Always take fullscreen screenshots.
   */
  protected bool $alwaysFullscreen = FALSE;

  /**
   * Capture screenshot after every step.
   */
  protected bool $onEveryStep = FALSE;

  /**
   * Whether the current scenario has the @screenshots tag.
   */
  protected bool $scenarioHasScreenshotsTag = FALSE;

  /**
   * Animated GIF settings (keys: enabled, frame_delay).
   *
   * @var array<string,mixed>
   */
  protected array $animation = [];

  /**
   * Whether the current scenario should produce an animated GIF.
   */
  protected bool $scenarioIsAnimated = FALSE;

  /**
   * Screenshot image data collected during the current scenario.
   *
   * @var array<int,string>
   */
  protected array $animationFrames = [];

  /**
   * Image data of the most recent PNG screenshot written by screenshot().
   */
  protected ?string $lastScreenshotData = NULL;

  /**
   * Prefix for failed screenshot files.
   */
  protected string $failedPrefix = '';

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
  public function setScreenshotParameters(string $dir, bool $on_failed, string $failed_prefix, bool $always_fullscreen, bool $on_every_step, string $filename_pattern, string $filename_pattern_failed, array $info_types, array $animation): static {
    $this->dir = $dir;
    $this->onFailed = $on_failed;
    $this->failedPrefix = $failed_prefix;
    $this->alwaysFullscreen = $always_fullscreen;
    $this->onEveryStep = $on_every_step;
    $this->filenamePattern = $filename_pattern;
    $this->filenamePatternFailed = $filename_pattern_failed;
    $this->infoTypes = $info_types;
    $this->animation = $animation;

    return $this;
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
   * Detect screenshot tags and reset per-scenario animation state.
   *
   * Tags are read at both the scenario and feature level, so either may
   * enable the behaviour.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   Scenario scope.
   *
   * @BeforeScenario
   */
  public function beforeScenarioCheckScreenshotsTag(BeforeScenarioScope $scope): void {
    $scenario = $scope->getScenario();
    $feature = $scope->getFeature();

    $this->scenarioHasScreenshotsTag = $scenario->hasTag('screenshots') || $feature->hasTag('screenshots');
    $this->scenarioIsAnimated = !empty($this->animation['enabled']) || $scenario->hasTag('screenshots:animated') || $feature->hasTag('screenshots:animated');
    $this->animationFrames = [];
  }

  /**
   * Init values required for screenshots.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   Scenario scope.
   *
   * @BeforeScenario @javascript
   */
  public function beforeScenarioInit(BeforeScenarioScope $scope): void {
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
   * After step handler to print the last response on error.
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
    if (!$event->getTestResult()->isPassed() && $this->onFailed) {
      $this->screenshot([
        'is_failed' => TRUE,
        'fullscreen' => $this->alwaysFullscreen,
      ]);
    }
  }

  /**
   * Capture screenshot after every step when enabled.
   *
   * @param \Behat\Behat\Hook\Scope\AfterStepScope $event
   *   After step scope event.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *
   * @AfterStep
   */
  public function captureScreenshotAfterStep(AfterStepScope $event): void {
    // Capture on passed steps when per-step screenshots are globally enabled,
    // the @screenshots tag is active, or animation is recording.
    // Failed steps are covered separately by on_failed to avoid duplicates.
    if (($this->onEveryStep || $this->scenarioHasScreenshotsTag || $this->scenarioIsAnimated) && $event->getTestResult()->isPassed()) {
      $this->screenshot([
        'fullscreen' => $this->alwaysFullscreen,
      ]);

      if ($this->scenarioIsAnimated && $this->lastScreenshotData !== NULL) {
        $this->animationFrames[] = $this->lastScreenshotData;
      }
    }
  }

  /**
   * Assemble the captured frames into an animated GIF.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   After scenario scope.
   *
   * @AfterScenario
   */
  public function afterScenarioAnimate(AfterScenarioScope $scope): void {
    if (!$this->scenarioIsAnimated || $this->animationFrames === [] || !$this->isAnimatedGifSupported()) {
      return;
    }

    $frame_delay = isset($this->animation['frame_delay']) && is_numeric($this->animation['frame_delay']) ? (int) $this->animation['frame_delay'] : 500;
    $content = $this->getAnimatedGif()->encode($this->animationFrames, $frame_delay);
    $this->animationFrames = [];

    $this->saveScreenshotContent($this->makeAnimationFileName($scope), $content);
  }

  /**
   * {@inheritdoc}
   *
   * @When I save screenshot
   * @Then save screenshot
   */
  public function iSaveScreenshot(): void {
    $this->screenshot();
  }

  /**
   * Save fullscreen screenshot.
   *
   * @When I save fullscreen screenshot
   * @Then save fullscreen screenshot
   */
  public function iSaveFullscreenScreenshot(): void {
    $this->screenshot(['fullscreen' => TRUE]);
  }

  /**
   * Save screenshot with name.
   *
   * @When I save screenshot with name :filename
   * @Then save screenshot with name :filename
   */
  public function iSaveScreenshotWithName(string $filename): void {
    $this->screenshot(['filename' => $filename]);
  }

  /**
   * Save fullscreen screenshot with name.
   *
   * @When I save fullscreen screenshot with name :filename
   * @Then save fullscreen screenshot with name :filename
   */
  public function iSaveFullscreenScreenshotWithName(string $filename): void {
    $this->screenshot(['filename' => $filename, 'fullscreen' => TRUE]);
  }

  /**
   * Save screenshot with specific dimensions.
   *
   * @When I save :width x :height screenshot
   * @Then save :width x :height screenshot
   */
  public function iSaveSizedScreenshot(string|int $width = 1440, string|int $height = 900): void {
    try {
      $this->getSession()->resizeWindow(intval($width), intval($height), 'current');
    }
    catch (UnsupportedDriverActionException) {
      // Nothing to do here - drivers without resize support may proceed.
    }

    $this->screenshot();
  }

  /**
   * Take a screenshot.
   *
   * @param array<string,mixed> $options
   *   Screenshot options with the following keys:
   *   - filename: (string|null) Custom filename for the screenshot.
   *   - is_failed: (bool) Whether this is a failed test screenshot.
   *   - fullscreen: (bool) Whether to take a fullscreen screenshot.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   */
  public function screenshot(array $options = []): void {
    $is_fullscreen = (isset($options['fullscreen']) && $options['fullscreen']) || $this->alwaysFullscreen;

    $filename = isset($options['filename']) && is_scalar($options['filename']) ? strval($options['filename']) : NULL;
    $is_failed = isset($options['is_failed']) && is_scalar($options['is_failed']) && $options['is_failed'];

    $this->lastScreenshotData = NULL;

    $driver = $this->getSession()->getDriver();
    $info = $this->renderInfo();

    try {
      $content = $driver->getContent();
      $content = empty($info) ? $content : nl2br($info) . "<hr/>\n" . $content;
    }
    catch (DriverException) {
      // Do nothing if the driver does not have any content - most
      // likely the page has not been loaded yet.
      return;
    }

    $filename_html = $this->makeFileName('html', $filename, $is_failed);
    $this->saveScreenshotContent($filename_html, $content);

    // Drivers that do not support making screenshots, including Goutte
    // driver which is shipped with Behat, throw exception. For such drivers,
    // screenshot stored as an HTML page (without referenced assets).
    try {
      $content = $is_fullscreen ? $this->getScreenshotFullscreen() : $this->getScreenshot();
    }
    // @codeCoverageIgnoreStart
    catch (UnsupportedDriverActionException) {
      // Nothing to do here - drivers without support for screenshots
      // simply do not have them created.
      return;
    }
    // @codeCoverageIgnoreEnd
    // Re-create the filename with a different extension to group content
    // and screenshot files together by name.
    $filename_png = $this->makeFileName('png', $filename, $is_failed);
    $this->saveScreenshotContent($filename_png, $content);
    $this->lastScreenshotData = $content;
  }

  /**
   * Get screenshot.
   *
   * @return string
   *   Screenshot data.
   */
  public function getScreenshot(): string {
    return $this->getSession()->getDriver()->getScreenshot();
  }

  /**
   * Get fullscreen screenshot.
   */
  public function getScreenshotFullscreen(): string {
    return $this->getScreenshotFullscreenWithResize();
  }

  /**
   * Save fullscreen screenshot by temporarily resizing the browser window.
   *
   * @return string
   *   Screenshot data.
   */
  protected function getScreenshotFullscreenWithResize(): string {
    $session = $this->getSession();
    $session->getDriver();

    // Store original window size to restore it later.
    // Default to the standard size set in beforeScenarioInit().
    $original_width = 1440;
    $original_height = 900;

    // Get the current window size using JavaScript.
    try {
      $original_dimensions = $session->evaluateScript("
        return {
          width: window.outerWidth,
          height: window.outerHeight
        };
      ");

      if (!empty($original_dimensions) && is_array($original_dimensions)) {
        $original_width = isset($original_dimensions['width']) && is_numeric($original_dimensions['width'])
          ? (int) $original_dimensions['width'] : 1440;
        $original_height = isset($original_dimensions['height']) && is_numeric($original_dimensions['height'])
          ? (int) $original_dimensions['height'] : 900;
      }
    }
    catch (\Exception) {
      // Use default dimensions if JavaScript evaluation fails.
    }

    $dimensions = $session->evaluateScript("
        return {
          scrollWidth: Math.max(
            document.documentElement.scrollWidth,
            document.body ? document.body.scrollWidth : 0
          ),
          scrollHeight: Math.max(
            document.documentElement.scrollHeight,
            document.body ? document.body.scrollHeight : 0
          )
        };
      ");

    if (empty($dimensions) || !is_array($dimensions)) {
      return $this->getScreenshot();
    }

    // Ensure we have numeric values.
    $scroll_height = isset($dimensions['scrollHeight']) && is_numeric($dimensions['scrollHeight'])
      ? (int) $dimensions['scrollHeight']
      : 0;
    if ($scroll_height <= 0) {
      return $this->getScreenshot();
    }

    // Use a reasonable width, but set height to match the document height.
    $fullscreen_width = $original_width ?: 1440;
    $fullscreen_height = $scroll_height;

    // Add some buffer to ensure the entire page is captured.
    $fullscreen_height += 200;

    // Resize the window to capture the full page height.
    $session->resizeWindow($fullscreen_width, $fullscreen_height, 'current');

    // Add a small delay to ensure the resize completes before taking
    // screenshot.
    usleep(100000);

    // Take the screenshot.
    $screenshot = $this->getScreenshot();

    // Always restore the original window size.
    try {
      $session->resizeWindow($original_width, $original_height, 'current');
    }
    catch (\Exception) {
      // Ignore errors during restoration - best effort attempt.
    }

    return $screenshot;
  }

  /**
   * Save screenshot content into a file.
   *
   * @param string $filename
   *   File name to write.
   * @param string $content
   *   Content to write into a file.
   */
  public function saveScreenshotContent(string $filename, string $content): void {
    (new Filesystem())->mkdir($this->dir, 0755);
    $file_path = $this->dir . DIRECTORY_SEPARATOR . $filename;
    $success = file_put_contents($file_path, $content);
    if ($success === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException(sprintf('Failed to save screenshot to %s. Check permissions and disk space.', $file_path));
      // @codeCoverageIgnoreEnd
    }
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
   * {@inheritdoc}
   */
  public function appendInfo(string $label, string $value): void {
    $this->info[$label] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function renderInfo(): string {
    $this->compileInfo();

    // Use a non-HTML output to make this output universal.
    return implode("\n", array_map(
      fn(string $key, $value): string => sprintf('%s: %s', $key, $value),
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
        try {
          $current_url = $this->getSession()->getCurrentUrl();
        }
        catch (\Exception) {
          $current_url = 'not available';
        }

        $this->appendInfo('Current URL', $current_url);
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
   * Get current timestamp.
   *
   * @return int
   *   Current timestamp.
   *
   * @codeCoverageIgnore
   */
  protected function getCurrentTime(): int {
    return time();
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
   * @param bool $is_failed
   *   Make filename for fail case.
   *
   * @return string
   *   Unique file name.
   *
   * @throws \Exception
   */
  protected function makeFileName(string $ext, ?string $filename = NULL, bool $is_failed = FALSE): string {
    if ($is_failed) {
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
        $url = str_replace($host, (string) getenv('BEHAT_SCREENSHOT_TOKEN_HOST'), $url);
      }
      // @codeCoverageIgnoreEnd
    }

    $data = [
      'ext' => $ext,
      'failed_prefix' => $this->failedPrefix,
      'feature_file' => $feature->getFile(),
      'step_line' => $step->getLine(),
      'step_name' => $step->getText(),
      'timestamp' => $this->getCurrentTime(),
      'url' => $url,
    ];

    return Tokenizer::replaceTokens($filename, $data);
  }

  /**
   * Make animated GIF filename for a scenario.
   *
   * Format: timestamp.featurefilename_scenariolinenumber.gif.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   After scenario scope.
   *
   * @return string
   *   Unique animated GIF file name grouped with the scenario step files.
   *
   * @throws \Exception
   */
  protected function makeAnimationFileName(AfterScenarioScope $scope): string {
    $data = [
      'feature_file' => $scope->getFeature()->getFile(),
      'timestamp' => $this->getCurrentTime(),
    ];

    return Tokenizer::replaceTokens('{datetime:U}.{feature_file}.feature_' . $scope->getScenario()->getLine() . '.gif', $data);
  }

  /**
   * Check whether the runtime can encode animated GIFs.
   *
   * @return bool
   *   TRUE when the GD image functions required for encoding are available.
   */
  protected function isAnimatedGifSupported(): bool {
    return function_exists('imagecreatefromstring') && function_exists('imagegif');
  }

  /**
   * Get an animated GIF encoder instance.
   *
   * @return \DrevOps\BehatScreenshotExtension\AnimatedGif
   *   Animated GIF encoder.
   */
  protected function getAnimatedGif(): AnimatedGif {
    return new AnimatedGif();
  }

}
