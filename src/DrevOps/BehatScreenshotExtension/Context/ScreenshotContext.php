<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\TaggedNodeInterface;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatScreenshotExtension\AnimatedGifEncoder;
use DrevOps\BehatScreenshotExtension\ScreenshotConfig;
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
   * Environment variable replacing the current URL's host in filename tokens.
   */
  public const ENV_TOKEN_HOST = 'BEHAT_SCREENSHOT_TOKEN_HOST';

  /**
   * Extra window height ensuring the whole page is captured, in pixels.
   */
  public const FULLSCREEN_HEIGHT_BUFFER = 200;

  /**
   * Delay after a window resize before capturing, in microseconds.
   */
  public const WINDOW_RESIZE_SETTLE_MICROSECONDS = 100000;

  /**
   * Screenshot configuration.
   */
  protected ScreenshotConfig $screenshotConfig;

  /**
   * Whether the current scenario has the @screenshots tag.
   */
  protected bool $scenarioHasScreenshotsTag = FALSE;

  /**
   * Whether the current scenario should produce an animated GIF.
   */
  protected bool $scenarioIsAnimated = FALSE;

  /**
   * Encoder holding the current scenario's animation frames.
   */
  protected ?AnimatedGifEncoder $animationEncoder = NULL;

  /**
   * Content of the last PNG screenshot written by captureScreenshot().
   */
  protected ?string $lastScreenshotContent = NULL;

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
  public function setScreenshotConfig(ScreenshotConfig $config): static {
    $this->screenshotConfig = $config;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getScreenshotConfig(): ScreenshotConfig {
    if (!isset($this->screenshotConfig)) {
      throw new \RuntimeException(sprintf('Screenshot configuration has not been set on %s. Enable the DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension extension in the Behat configuration.', static::class));
    }

    return $this->screenshotConfig;
  }

  /**
   * Detect screenshot tags and reset per-scenario animation state.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   Scenario scope.
   */
  #[BeforeScenario]
  public function beforeScenarioCheckScreenshotsTag(BeforeScenarioScope $scope): void {
    $scenario = $scope->getScenario();
    $feature = $scope->getFeature();

    $this->scenarioHasScreenshotsTag = $this->isTagged($scenario, self::TAG_SCREENSHOTS) || $this->isTagged($feature, self::TAG_SCREENSHOTS);
    $this->scenarioIsAnimated = $this->resolveIsAnimated($scenario, $feature);
    $this->animationEncoder = NULL;
  }

  /**
   * Resolve whether the current scenario should produce an animated GIF.
   *
   * The suite-wide environment variable overrides all tags and the
   * configuration, so a run can disable animation without editing feature
   * files or the configuration.
   *
   * Nodes are checked from the most specific to the least specific. A scenario
   * tag overrides any feature tag, and a feature tag applies only when the
   * scenario has neither tag.
   *
   * Within a node the skip tag takes precedence over the opt-in tag, so the
   * result for a node with both tags is deterministic. The animation.enabled
   * configuration applies only when no node is tagged.
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
      if ($this->isTagged($node, self::TAG_SCREENSHOTS_ANIMATED_SKIP)) {
        return FALSE;
      }

      if ($this->isTagged($node, self::TAG_SCREENSHOTS_ANIMATED)) {
        return TRUE;
      }
    }

    return $this->getScreenshotConfig()->shouldAnimate;
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
   * Check whether a node carries a tag.
   *
   * A tag is reported with or without its leading "@", so both forms match.
   *
   * @param \Behat\Gherkin\Node\TaggedNodeInterface $node
   *   Feature, scenario or example node.
   * @param string $tag
   *   Tag name without the leading "@".
   *
   * @return bool
   *   TRUE when the node carries the tag.
   */
  protected function isTagged(TaggedNodeInterface $node, string $tag): bool {
    $tags = array_map(static fn(string $node_tag): string => ltrim($node_tag, '@'), $node->getTags());

    return in_array($tag, $tags, TRUE);
  }

  /**
   * Start the driver and resize the window to the default size.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   Scenario scope.
   */
  #[BeforeScenario('@javascript')]
  public function beforeScenarioInit(BeforeScenarioScope $scope): void {
    $driver = $this->getSession()->getDriver();

    try {
      if (!$driver->isStarted()) {
        $driver->start();
      }

      $this->getSession()->resizeWindow(self::DEFAULT_WINDOW_WIDTH, self::DEFAULT_WINDOW_HEIGHT, self::WINDOW_NAME_CURRENT);
    }
    catch (UnsupportedDriverActionException) {
      // No image screenshots are created for drivers without visual screenshot
      // support.
    }
    catch (DriverException $exception) {
      throw new \RuntimeException(sprintf("Unable to connect to the driver's server: %s.", $exception->getMessage()), $exception->getCode(), $exception);
    }
  }

  /**
   * Init values required for a screenshot.
   */
  #[BeforeStep]
  public function beforeStepInit(BeforeStepScope $scope): void {
    $this->beforeStepScope = $scope;
  }

  /**
   * Capture screenshot after a failed step when enabled.
   *
   * @param \Behat\Behat\Hook\Scope\AfterStepScope $scope
   *   After scope event.
   *
   * @throws \Behat\Mink\Exception\DriverException
   */
  #[AfterStep]
  public function afterStepCaptureFailedScreenshot(AfterStepScope $scope): void {
    if (!$scope->getTestResult()->isPassed() && $this->getScreenshotConfig()->shouldCaptureOnFailed) {
      $this->captureScreenshot([
        'is_failed' => TRUE,
        'fullscreen' => $this->getScreenshotConfig()->shouldAlwaysCaptureFullscreen,
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
   */
  #[AfterStep]
  public function afterStepCaptureScreenshot(AfterStepScope $scope): void {
    // Failed steps are covered separately by on_failed to avoid duplicates.
    if (($this->getScreenshotConfig()->shouldCaptureOnEveryStep || $this->scenarioHasScreenshotsTag || $this->scenarioIsAnimated) && $scope->getTestResult()->isPassed()) {
      $this->captureScreenshot([
        'fullscreen' => $this->getScreenshotConfig()->shouldAlwaysCaptureFullscreen,
      ]);

      if ($this->scenarioIsAnimated && $this->lastScreenshotContent !== NULL) {
        $this->addAnimationFrame($this->lastScreenshotContent);
      }
    }
  }

  /**
   * Add a captured screenshot to the current scenario's animation.
   *
   * @param string $content
   *   Raw screenshot content.
   */
  protected function addAnimationFrame(string $content): void {
    if (!$this->isAnimatedGifSupported()) {
      return;
    }

    if (!$this->animationEncoder instanceof AnimatedGifEncoder) {
      $this->animationEncoder = $this->createAnimatedGifEncoder();
    }

    $this->animationEncoder->addFrame($content);
  }

  /**
   * Assemble the captured frames into an animated GIF.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   After scenario scope.
   */
  #[AfterScenario]
  public function afterScenarioAnimate(AfterScenarioScope $scope): void {
    $encoder = $this->animationEncoder;

    if (!$this->scenarioIsAnimated || !$encoder instanceof AnimatedGifEncoder || $encoder->count() === 0) {
      $this->animationEncoder = NULL;

      return;
    }

    // Release the encoder even when rendering or writing fails, so no frames
    // remain for the next scenario.
    try {
      $content = $encoder->render($this->getScreenshotConfig()->animationFrameDelay);
      $this->writeScreenshotContent($this->makeAnimationFilename($scope), $content);
    }
    finally {
      $this->animationEncoder = NULL;
    }
  }

  /**
   * Save screenshot.
   */
  #[When('I save screenshot')]
  #[Then('save screenshot')]
  public function iSaveScreenshot(): void {
    $this->captureScreenshot();
  }

  /**
   * Save fullscreen screenshot.
   */
  #[When('I save fullscreen screenshot')]
  #[Then('save fullscreen screenshot')]
  public function iSaveFullscreenScreenshot(): void {
    $this->captureScreenshot(['fullscreen' => TRUE]);
  }

  /**
   * Save screenshot with name.
   */
  #[When('I save screenshot with name :filename')]
  #[Then('save screenshot with name :filename')]
  public function iSaveScreenshotWithName(string $filename): void {
    $this->captureScreenshot(['filename' => $filename]);
  }

  /**
   * Save fullscreen screenshot with name.
   */
  #[When('I save fullscreen screenshot with name :filename')]
  #[Then('save fullscreen screenshot with name :filename')]
  public function iSaveFullscreenScreenshotWithName(string $filename): void {
    $this->captureScreenshot(['filename' => $filename, 'fullscreen' => TRUE]);
  }

  /**
   * Save screenshot with specific dimensions.
   */
  #[When('I save :width x :height screenshot')]
  #[Then('save :width x :height screenshot')]
  public function iSaveSizedScreenshot(string|int $width = self::DEFAULT_WINDOW_WIDTH, string|int $height = self::DEFAULT_WINDOW_HEIGHT): void {
    try {
      $this->getSession()->resizeWindow((int) $width, (int) $height, self::WINDOW_NAME_CURRENT);
    }
    catch (UnsupportedDriverActionException) {
      // The screenshot is still captured for drivers without resize support.
    }

    $this->captureScreenshot();
  }

  /**
   * {@inheritdoc}
   */
  public function captureScreenshot(array $config = []): void {
    $is_fullscreen = (isset($config['fullscreen']) && $config['fullscreen']) || $this->getScreenshotConfig()->shouldAlwaysCaptureFullscreen;

    $filename = isset($config['filename']) && is_scalar($config['filename']) ? (string) $config['filename'] : NULL;
    $is_failed = isset($config['is_failed']) && is_scalar($config['is_failed']) && $config['is_failed'];

    // Both filenames use one timestamp, so the HTML and PNG files still share
    // a name when the capture crosses a second boundary.
    $timestamp = $this->getCurrentTime();

    $this->lastScreenshotContent = NULL;

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

    $filename_html = $this->makeFilename('html', $timestamp, $filename, $is_failed);
    $this->writeScreenshotContent($filename_html, $content);

    // A driver without screenshot support throws instead of capturing, so the
    // HTML file written above is the only record of the page. That file does
    // not include the page's referenced assets.
    try {
      $content = $is_fullscreen ? $this->getScreenshotFullscreen() : $this->getScreenshot();
    }
    // @codeCoverageIgnoreStart
    catch (UnsupportedDriverActionException) {
      return;
    }
    // @codeCoverageIgnoreEnd
    $filename_png = $this->makeFilename('png', $timestamp, $filename, $is_failed);
    $this->writeScreenshotContent($filename_png, $content);
    $this->lastScreenshotContent = $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getScreenshot(): string {
    return $this->getSession()->getDriver()->getScreenshot();
  }

  /**
   * {@inheritdoc}
   */
  public function getScreenshotFullscreen(): string {
    return $this->getScreenshotFullscreenWithResize();
  }

  /**
   * Get fullscreen screenshot by temporarily resizing the browser window.
   *
   * @return string
   *   Screenshot content.
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

    $scroll_height = isset($dimensions['scrollHeight']) && is_numeric($dimensions['scrollHeight']) ? (int) $dimensions['scrollHeight'] : 0;

    if ($scroll_height <= 0) {
      return $this->getScreenshot();
    }

    $fullscreen_width = $original_width ?: self::DEFAULT_WINDOW_WIDTH;
    $fullscreen_height = $scroll_height + self::FULLSCREEN_HEIGHT_BUFFER;

    $session->resizeWindow($fullscreen_width, $fullscreen_height, self::WINDOW_NAME_CURRENT);

    // Restore the window even when the capture fails, so the browser is not
    // left enlarged for the rest of the scenario.
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
   * {@inheritdoc}
   */
  public function writeScreenshotContent(string $filename, string $content): void {
    $dir = $this->getScreenshotConfig()->dir;
    $this->createFilesystem()->mkdir($dir, 0755);
    $file_path = $dir . DIRECTORY_SEPARATOR . $filename;
    $success = file_put_contents($file_path, $content);

    if ($success === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException(sprintf('Failed to save screenshot to %s. Check permissions and disk space.', $file_path));
      // @codeCoverageIgnoreEnd
    }
  }

  /**
   * {@inheritdoc}
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

    // Output plain text rather than HTML, so it can be used in any format.
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
    foreach ($this->getScreenshotConfig()->infoTypes as $type) {
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
        $this->appendInfo('Datetime', date('Y-m-d H:i:s', $this->getCurrentTime()));
      }
    }
  }

  /**
   * Get current timestamp.
   *
   * @return int
   *   Current timestamp.
   */
  protected function getCurrentTime(): int {
    return time();
  }

  /**
   * Make screenshot filename.
   *
   * @param string $ext
   *   File extension without dot.
   * @param int $timestamp
   *   Timestamp the datetime tokens are formatted from.
   * @param string|null $filename
   *   Optional filename.
   * @param bool $is_failed
   *   Make filename for fail case.
   *
   * @return string
   *   Unique filename.
   *
   * @throws \InvalidArgumentException
   */
  protected function makeFilename(string $ext, int $timestamp, ?string $filename = NULL, bool $is_failed = FALSE): string {
    if ($is_failed) {
      $filename = $this->getScreenshotConfig()->filenamePatternFailed;
    }
    elseif (empty($filename)) {
      $filename = $this->getScreenshotConfig()->filenamePattern;
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

    $token_host = getenv(self::ENV_TOKEN_HOST);

    if (!empty($url) && !empty($token_host)) {
      // @codeCoverageIgnoreStart
      $host = parse_url($url, PHP_URL_HOST);

      if ($host) {
        $url = str_replace($host, $token_host, $url);
      }
      // @codeCoverageIgnoreEnd
    }

    $data = [
      'ext' => $ext,
      'failed_prefix' => $this->getScreenshotConfig()->failedPrefix,
      'feature_file' => $feature->getFile(),
      'step_line' => $step->getLine(),
      'step_name' => $step->getText(),
      'timestamp' => $timestamp,
      'url' => $url,
    ];

    return Tokenizer::replaceTokens($filename, $data);
  }

  /**
   * Make animated GIF filename for a scenario.
   *
   * Format: {datetime:U}.{feature_file}.feature_<scenario line>.gif. The
   * step tokens of a configured filename_pattern have no scenario-level
   * value, so this pattern is fixed.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   After scenario scope.
   *
   * @return string
   *   Unique animated GIF filename grouped with the scenario step files.
   *
   * @throws \InvalidArgumentException
   */
  protected function makeAnimationFilename(AfterScenarioScope $scope): string {
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
   * Create an animated GIF encoder instance.
   *
   * @return \DrevOps\BehatScreenshotExtension\AnimatedGifEncoder
   *   New animated GIF encoder with the configured frame size caps.
   */
  protected function createAnimatedGifEncoder(): AnimatedGifEncoder {
    return new AnimatedGifEncoder($this->getScreenshotConfig()->animationMaxWidth, $this->getScreenshotConfig()->animationMaxHeight);
  }

  /**
   * Create a filesystem instance.
   *
   * @return \Symfony\Component\Filesystem\Filesystem
   *   New filesystem instance.
   */
  protected function createFilesystem(): Filesystem {
    return new Filesystem();
  }

}
