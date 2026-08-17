<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\TaggedNodeInterface;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatScreenshotExtension\AnimatedGif;
use DrevOps\BehatScreenshotExtension\Tokenizer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Captures HTML and image screenshots during a Behat run.
 *
 * Provides the screenshot steps, the hooks that capture on failure and after
 * every step, and the per-scenario animated GIF assembly.
 */
class ScreenshotContext extends RawMinkContext implements ScreenshotAwareContextInterface {

  /**
   * Default delay between animated GIF frames, in milliseconds.
   */
  public const DEFAULT_FRAME_DELAY = 500;

  /**
   * Default browser window width, in pixels.
   */
  public const DEFAULT_WINDOW_WIDTH = 1440;

  /**
   * Default browser window height, in pixels.
   */
  public const DEFAULT_WINDOW_HEIGHT = 900;

  /**
   * Mink window name for the current browser window.
   */
  public const WINDOW_NAME_CURRENT = 'current';

  /**
   * Filename suffix carrying the file extension token.
   */
  public const FILENAME_EXTENSION_SUFFIX = '.{ext}';

  /**
   * Tag enabling per-step screenshots for a scenario or feature.
   */
  public const TAG_SCREENSHOTS = 'screenshots';

  /**
   * Tag enabling animated GIF assembly for a scenario or feature.
   */
  public const TAG_SCREENSHOTS_ANIMATED = 'screenshots:animated';

  /**
   * Tag disabling animated GIF assembly for a scenario or feature.
   */
  public const TAG_SCREENSHOTS_ANIMATED_SKIP = 'screenshots:animated:skip';

  /**
   * Environment variable disabling animated GIF assembly for the whole suite.
   */
  public const ENV_ANIMATION_SKIP = 'BEHAT_SCREENSHOT_ANIMATION_SKIP';

  /**
   * Extra window height ensuring the whole page is captured, in pixels.
   */
  public const FULLSCREEN_HEIGHT_BUFFER = 200;

  /**
   * Delay after a window resize before capturing, in microseconds.
   */
  public const WINDOW_RESIZE_SETTLE_MICROSECONDS = 100000;

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
   * Animated GIF settings (keys: enabled, frame_delay, max_width, max_height).
   *
   * @var array<string,mixed>
   */
  protected array $animation = [];

  /**
   * Whether the current scenario should produce an animated GIF.
   */
  protected bool $scenarioIsAnimated = FALSE;

  /**
   * Encoder holding the current scenario's animation frames.
   */
  protected ?AnimatedGif $animationEncoder = NULL;

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
   * Filename pattern for failed tests.
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
   * @var array<string,string>
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
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   Scenario scope.
   *
   * @BeforeScenario
   */
  public function beforeScenarioCheckScreenshotsTag(BeforeScenarioScope $scope): void {
    $scenario = $scope->getScenario();
    $feature = $scope->getFeature();

    $this->scenarioHasScreenshotsTag = $scenario->hasTag(self::TAG_SCREENSHOTS) || $feature->hasTag(self::TAG_SCREENSHOTS);
    $this->scenarioIsAnimated = $this->resolveIsAnimated($scenario, $feature);
    $this->animationEncoder = NULL;
  }

  /**
   * Resolve whether the current scenario should produce an animated GIF.
   *
   * The environment variable is a suite-wide switch that overrides everything
   * a feature file or the configuration can say, so a run can be stripped of
   * animation without editing either. Below it, scopes are consulted from the
   * most specific to the least specific, so a scenario tag decides on its own
   * and a feature tag only applies when the scenario carries neither tag.
   * Within a scope the skip tag wins, making a node tagged with both an opt-in
   * and an opt-out deterministic. The animation.enabled setting applies only
   * when no scope is tagged.
   *
   * @param \Behat\Gherkin\Node\TaggedNodeInterface ...$nodes
   *   Tagged nodes in order of decreasing specificity.
   *
   * @return bool
   *   TRUE when the scenario should produce an animated GIF.
   */
  protected function resolveIsAnimated(TaggedNodeInterface ...$nodes): bool {
    if ($this->isAnimationSkippedForSuite()) {
      return FALSE;
    }

    foreach ($nodes as $node) {
      if ($node->hasTag(self::TAG_SCREENSHOTS_ANIMATED_SKIP)) {
        return FALSE;
      }

      if ($node->hasTag(self::TAG_SCREENSHOTS_ANIMATED)) {
        return TRUE;
      }
    }

    return !empty($this->animation['enabled']);
  }

  /**
   * Check whether animation is disabled for the whole suite.
   *
   * @return bool
   *   TRUE when the environment variable holds a truthy value.
   */
  protected function isAnimationSkippedForSuite(): bool {
    return (bool) getenv(self::ENV_ANIMATION_SKIP);
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
      if (!$driver->isStarted()) {
        $driver->start();
      }

      $this->getSession()->resizeWindow(self::DEFAULT_WINDOW_WIDTH, self::DEFAULT_WINDOW_HEIGHT, self::WINDOW_NAME_CURRENT);
    }
    catch (UnsupportedDriverActionException) {
      // Drivers without visual screenshot support do not have them created.
    }
    catch (DriverException $exception) {
      throw new \RuntimeException(sprintf("Unable to connect to the driver's server: %s.", $exception->getMessage()), $exception->getCode(), $exception);
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
   * Save screenshot after a failed step when enabled.
   *
   * @param \Behat\Behat\Hook\Scope\AfterStepScope $scope
   *   After scope event.
   *
   * @throws \Behat\Mink\Exception\DriverException
   *
   * @AfterStep
   */
  public function printLastResponseOnError(AfterStepScope $scope): void {
    if (!$scope->getTestResult()->isPassed() && $this->onFailed) {
      $this->screenshot([
        'is_failed' => TRUE,
        'fullscreen' => $this->alwaysFullscreen,
      ]);
    }
  }

  /**
   * Capture screenshot after every step when enabled.
   *
   * @param \Behat\Behat\Hook\Scope\AfterStepScope $scope
   *   After step scope event.
   *
   * @throws \Behat\Mink\Exception\DriverException
   *
   * @AfterStep
   */
  public function captureScreenshotAfterStep(AfterStepScope $scope): void {
    // Failed steps are covered separately by on_failed to avoid duplicates.
    if (($this->onEveryStep || $this->scenarioHasScreenshotsTag || $this->scenarioIsAnimated) && $scope->getTestResult()->isPassed()) {
      $this->screenshot([
        'fullscreen' => $this->alwaysFullscreen,
      ]);

      if ($this->scenarioIsAnimated && $this->lastScreenshotData !== NULL) {
        $this->addAnimationFrame($this->lastScreenshotData);
      }
    }
  }

  /**
   * Add a captured screenshot to the current scenario's animation.
   *
   * @param string $data
   *   Raw screenshot data.
   */
  protected function addAnimationFrame(string $data): void {
    if (!$this->isAnimatedGifSupported()) {
      return;
    }

    if (!$this->animationEncoder instanceof AnimatedGif) {
      $this->animationEncoder = $this->getAnimatedGif();
    }

    $this->animationEncoder->addFrame($data);
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
    $encoder = $this->animationEncoder;

    if (!$this->scenarioIsAnimated || !$encoder instanceof AnimatedGif || $encoder->count() === 0) {
      $this->animationEncoder = NULL;

      return;
    }

    // The encoder is always released, even when rendering or writing fails,
    // so a non-critical artifact does not leak frames into the next scenario.
    try {
      $content = $encoder->render($this->animationSetting('frame_delay', self::DEFAULT_FRAME_DELAY));
      $this->saveScreenshotContent($this->makeAnimationFileName($scope), $content);
    }
    finally {
      $this->animationEncoder = NULL;
    }
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
  public function iSaveSizedScreenshot(string|int $width = self::DEFAULT_WINDOW_WIDTH, string|int $height = self::DEFAULT_WINDOW_HEIGHT): void {
    try {
      $this->getSession()->resizeWindow((int) $width, (int) $height, self::WINDOW_NAME_CURRENT);
    }
    catch (UnsupportedDriverActionException) {
      // Drivers without resize support may proceed.
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
   */
  public function screenshot(array $options = []): void {
    $is_fullscreen = (isset($options['fullscreen']) && $options['fullscreen']) || $this->alwaysFullscreen;

    $filename = isset($options['filename']) && is_scalar($options['filename']) ? (string) $options['filename'] : NULL;
    $is_failed = isset($options['is_failed']) && is_scalar($options['is_failed']) && $options['is_failed'];

    $this->lastScreenshotData = NULL;

    $driver = $this->getSession()->getDriver();
    $info = $this->renderInfo();

    try {
      $content = $driver->getContent();
      $content = empty($info) ? $content : nl2br($info) . "<hr/>\n" . $content;
    }
    catch (DriverException) {
      // The driver has no content, likely because the page is not loaded yet.
      return;
    }

    $filename_html = $this->makeFileName('html', $filename, $is_failed);
    $this->saveScreenshotContent($filename_html, $content);

    // Drivers that do not support screenshots, including the Goutte driver
    // shipped with Behat, throw an exception. For such drivers, the screenshot
    // is stored as an HTML page (without referenced assets).
    try {
      $content = $is_fullscreen ? $this->getScreenshotFullscreen() : $this->getScreenshot();
    }
    // @codeCoverageIgnoreStart
    catch (UnsupportedDriverActionException) {
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
   * Get fullscreen screenshot by temporarily resizing the browser window.
   *
   * @return string
   *   Screenshot data.
   */
  protected function getScreenshotFullscreenWithResize(): string {
    $session = $this->getSession();

    $original_width = self::DEFAULT_WINDOW_WIDTH;
    $original_height = self::DEFAULT_WINDOW_HEIGHT;

    try {
      $original_dimensions = $session->evaluateScript("
        return {
          width: window.outerWidth,
          height: window.outerHeight
        };
      ");

      if (!empty($original_dimensions) && is_array($original_dimensions)) {
        $original_width = isset($original_dimensions['width']) && is_numeric($original_dimensions['width'])
          ? (int) $original_dimensions['width'] : self::DEFAULT_WINDOW_WIDTH;
        $original_height = isset($original_dimensions['height']) && is_numeric($original_dimensions['height'])
          ? (int) $original_dimensions['height'] : self::DEFAULT_WINDOW_HEIGHT;
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

    $scroll_height = isset($dimensions['scrollHeight']) && is_numeric($dimensions['scrollHeight'])
      ? (int) $dimensions['scrollHeight']
      : 0;
    if ($scroll_height <= 0) {
      return $this->getScreenshot();
    }

    $fullscreen_width = $original_width ?: self::DEFAULT_WINDOW_WIDTH;
    $fullscreen_height = $scroll_height + self::FULLSCREEN_HEIGHT_BUFFER;

    $session->resizeWindow($fullscreen_width, $fullscreen_height, self::WINDOW_NAME_CURRENT);

    // The window is restored in a finally block so that a capture failure
    // does not leave the browser enlarged for the rest of the scenario.
    try {
      usleep(self::WINDOW_RESIZE_SETTLE_MICROSECONDS);

      return $this->getScreenshot();
    }
    finally {
      try {
        $session->resizeWindow($original_width, $original_height, self::WINDOW_NAME_CURRENT);
      }
      catch (\Exception) {
        // Restoration is best effort - errors are ignored.
      }
    }
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
      static fn(string $key, $value): string => sprintf('%s: %s', $key, $value),
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
   * @throws \InvalidArgumentException
   */
  protected function makeFileName(string $ext, ?string $filename = NULL, bool $is_failed = FALSE): string {
    if ($is_failed) {
      $filename = $this->filenamePatternFailed;
    }
    elseif (empty($filename)) {
      $filename = $this->filenamePattern;
    }

    if (!str_ends_with($filename, self::FILENAME_EXTENSION_SUFFIX)) {
      $filename .= self::FILENAME_EXTENSION_SUFFIX;
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
   * @throws \InvalidArgumentException
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
    return new AnimatedGif($this->animationSetting('max_width', 0), $this->animationSetting('max_height', 0));
  }

  /**
   * Read a numeric animation setting.
   *
   * @param string $name
   *   Setting name.
   * @param int $default
   *   Value used when the setting is absent or not numeric.
   *
   * @return int
   *   Setting value.
   */
  protected function animationSetting(string $name, int $default): int {
    return isset($this->animation[$name]) && is_numeric($this->animation[$name]) ? (int) $this->animation[$name] : $default;
  }

}
