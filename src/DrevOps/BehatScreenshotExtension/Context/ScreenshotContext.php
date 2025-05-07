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
   * Algorithm to use for fullscreen screenshots ('stitch' or 'resize').
   */
  protected string $fullscreenAlgorithm = 'resize';

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
  public function setScreenshotParameters(string $dir, bool $on_failed, string $failed_prefix, bool $always_fullscreen, string $fullscreen_algorithm, string $filename_pattern, string $filename_pattern_failed, array $info_types): static {
    $this->dir = $dir;
    $this->onFailed = $on_failed;
    $this->failedPrefix = $failed_prefix;
    $this->alwaysFullscreen = $always_fullscreen;
    $this->fullscreenAlgorithm = $fullscreen_algorithm;
    $this->filenamePattern = $filename_pattern;
    $this->filenamePatternFailed = $filename_pattern_failed;
    $this->infoTypes = $info_types;

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
    // Use the configured algorithm if both are available.
    if ($this->fullscreenAlgorithm === 'stitch' && extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
      return $this->getScreenshotFullscreenWithStitching();
    }

    // Fall back to resize if stitching is unavailable or resize was selected.
    return $this->getScreenshotFullscreenWithResize();
  }

  /**
   * Save fullscreen screenshot using the stitching method.
   *
   * @return string
   *   Screenshot data.
   */
  protected function getScreenshotFullscreenWithStitching(): string {
    $session = $this->getSession();
    $driver = $session->getDriver();

    // Get viewport dimensions and total document height.
    $dimensions = $session->evaluateScript("
        return {
          viewportWidth: window.innerWidth,
          viewportHeight: window.innerHeight,
          documentHeight: Math.max(
            document.documentElement.scrollHeight,
            document.body.scrollHeight
          )
        };
      ");

    // If dimensions are not available, fallback to regular screenshot.
    if (empty($dimensions) || !is_array($dimensions)) {
      return $this->getScreenshot();
    }

    // Ensure we have proper integer values.
    $viewport_width = isset($dimensions['viewportWidth']) && is_numeric($dimensions['viewportWidth'])
      ? (int) $dimensions['viewportWidth'] : 0;
    $viewport_height = isset($dimensions['viewportHeight']) && is_numeric($dimensions['viewportHeight'])
      ? (int) $dimensions['viewportHeight'] : 0;
    $document_height = isset($dimensions['documentHeight']) && is_numeric($dimensions['documentHeight'])
      ? (int) $dimensions['documentHeight'] : 0;

    if ($viewport_width <= 0 || $viewport_height <= 0 || $document_height <= 0) {
      return $this->getScreenshot();
    }

    // Use a smaller overlap value to reduce the chance of visible seams.
    $overlap = 50;
    $effective_viewport_height = $viewport_height - $overlap;

    // Calculate needed screenshots.
    $screenshot_count = (int) ceil($document_height / $effective_viewport_height);
    if ($screenshot_count < 1) {
      $screenshot_count = 1;
    }

    // Remember original scroll position to restore later.
    $original_scroll_top = $session->evaluateScript("return window.pageYOffset;");

    // Capture screenshots at each scroll position - store in memory.
    $screenshots = [];

    // Add delay between scrolling and screenshot for rendering stability.
    // 200ms.
    $scroll_delay = 200000;

    for ($i = 0; $i < $screenshot_count; $i++) {
      // Calculate the exact scroll position for each screenshot.
      if ($i === 0) {
        $scroll_position = 0;
      }
      elseif ($i === $screenshot_count - 1 && $screenshot_count > 1) {
        // For the last screenshot, position it to show the page bottom.
        // Calculate a position that shows the bottom of the page.
        // Subtract full viewport height to see the entire last section.
        $scroll_position = $document_height - $viewport_height;

        // Ensure we don't scroll to a negative position.
        if ($scroll_position < 0) {
          $scroll_position = 0;
        }

        // Check if position is too close to the previous screenshot.
        // Want at least 80% new content in final screenshot for best results.
        $previous_position = ($i - 1) * $effective_viewport_height;

        // 80% new content means previous position plus 20% of viewport height.
        $min_last_position = $previous_position + ($viewport_height * 0.2);

        // If position has too much overlap with previous screenshot,
        // use minimum position (still showing at least half of page bottom).
        if ($scroll_position < $min_last_position) {
          // Use the position that ensures 80% new content.
          $scroll_position = $min_last_position;

          // But make sure we still see the bottom edge if possible.
          if ($scroll_position + $viewport_height < $document_height) {
            // Adjust to show bottom edge, ensure at least 40% new content.
            $scroll_position = max($scroll_position, $document_height - $viewport_height);
          }
        }
      }
      else {
        // Middle screenshots should be positioned precisely.
        $scroll_position = $i * $effective_viewport_height;
      }

      // Use a more reliable scrolling method.
      $session->executeScript(sprintf("window.scrollTo({top: %s, left: 0, behavior: 'instant'});", $scroll_position));

      // Wait for scrolling and rendering to complete.
      usleep($scroll_delay);

      // Force browser to wait until rendering is complete.
      $actual_position = $session->evaluateScript("
          // Force a reflow to ensure rendering is complete
          document.body.getBoundingClientRect();
          return window.pageYOffset;
        ");

      // Make sure we're at the right position, try again if needed.
      // Allow a 2px margin of error.
      $actual_pos = is_numeric($actual_position) ? (float) $actual_position : 0;
      if (abs($actual_pos - $scroll_position) > 2) {
        // If the scroll position isn't exact, try one more time.
        // We know scroll_position is a float|int, so this is a safe cast.
        $session->executeScript(sprintf("window.scrollTo({top: %s, left: 0, behavior: 'instant'});", (string) $scroll_position));
        usleep($scroll_delay);
        // Force reflow.
        $session->evaluateScript("document.body.getBoundingClientRect();");
      }

      // Capture the screenshot.
      $screenshots[] = $driver->getScreenshot();
    }

    // Restore original scroll position.
    // Ensure we have a valid value to avoid type issues.
    $original_pos = is_numeric($original_scroll_top) ? (int) $original_scroll_top : 0;
    $session->executeScript(sprintf('window.scrollTo(0, %d);', $original_pos));

    // Stitch screenshots together.
    return $this->stitchImages($screenshots, $viewport_width, $viewport_height, $overlap);
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
    $original_width = 0;
    $original_height = 0;

    // Get the current window size.
    if (method_exists($session, 'getWindowSize')) {
      $size = $session->getWindowSize();
      $original_width = $size['width'];
      $original_height = $size['height'];
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

    // Take the screenshot.
    $screenshot = $this->getScreenshot();

    // Restore the original window size if we have dimensions.
    if ($original_width > 0 && $original_height > 0) {
      try {
        $session->resizeWindow($original_width, $original_height, 'current');
      }
      catch (\Exception) {
        // Ignore errors during restoration.
      }
    }

    return $screenshot;
  }

  /**
   * Stitch multiple screenshots together into a single fullscreen image.
   *
   * @param array<int, string> $images
   *   Array of screenshot binary strings to stitch.
   * @param int $width
   *   Width of the screenshots.
   * @param int $viewport_height
   *   Height of each viewport screenshot.
   * @param int $overlap
   *   Overlap between screenshots.
   *
   * @return string
   *   The stitched image as a binary string.
   */
  protected function stitchImages(array $images, int $width, int $viewport_height, int $overlap): string {
    if (empty($images)) {
      throw new \RuntimeException('No images to stitch.');
    }

    if (count($images) === 1) {
      return $images[0];
    }

    // Convert all screenshots to GD images first and analyze them.
    $image_resources = [];
    $image_heights = [];

    foreach ($images as $index => $image_content) {
      $img_resource = imagecreatefromstring($image_content);
      if ($img_resource === FALSE) {
        throw new \RuntimeException('Could not create image from data at index ' . $index);
      }
      $image_resources[] = $img_resource;
      $image_heights[] = imagesy($img_resource);
    }

    $num_resources = count($image_resources);
    $last_index = $num_resources - 1;

    // Calculate heights more precisely by analyzing actual content.
    // This helps determine exact stitch points and avoid duplicating elements.
    // Create a taller image to ensure we have enough space.
    // We can trim excess space at the end if needed.
    $estimated_height = $viewport_height + (($viewport_height - $overlap) * ($num_resources - 1)) + 100;

    // Create a new blank image for the full height.
    // Ensure we have positive dimensions for GD functions.
    $safe_width = max(1, $width);
    $safe_height = max(1, $estimated_height);
    $full_image = imagecreatetruecolor($safe_width, $safe_height);
    if ($full_image === FALSE) {
      throw new \RuntimeException('Could not create a new image for stitching.');
    }

    // Initialize with white background.
    $white = imagecolorallocate($full_image, 255, 255, 255);
    if ($white !== FALSE) {
      imagefill($full_image, 0, 0, $white);
    }

    // Process each image and add it to the combined image using a new approach.
    $current_y = 0;
    $actual_height = 0;

    foreach ($image_resources as $index => $img_resource) {
      $img_height = $image_heights[$index];

      if ($index === 0) {
        // For the first image, copy the entire thing.
        imagecopy($full_image, $img_resource, 0, 0, 0, 0, $width, $img_height);

        // Update the current position and track actual height.
        $current_y = $img_height - $overlap;
        $actual_height = $img_height;
      }
      elseif ($index === $last_index) {
        // For the last image, we need to be extra careful.
        // First determine which portion to use to avoid overlap issues.
        $source_y = $overlap;

        // For last image, decide if we include it all or just part.
        if ($num_resources === 2) {
          // With only 2 images, use simple blending at the overlap point.
          imagecopy($full_image, $img_resource, 0, $current_y, 0, $source_y,
            $width, $img_height - $source_y);

          $actual_height = $current_y + ($img_height - $source_y);
        }
        else {
          // With 3+ images, be careful with the last one to prevent odd stitch
          // lines. Calculate how much new content to copy - bottom of page
          // without duplicate content.
          $remaining_height = $img_height - $source_y;

          imagecopy($full_image, $img_resource, 0, $current_y, 0, $source_y, $width, $remaining_height);

          $actual_height = $current_y + $remaining_height;
        }
      }
      else {
        // For middle image, use consistent blending approach.
        $source_y = $overlap;
        $copy_height = $img_height - $source_y;

        imagecopy($full_image, $img_resource, 0, $current_y, 0, $source_y, $width, $copy_height);

        // Update position for next image.
        $current_y += $copy_height;
        $actual_height = $current_y + $overlap;
      }
    }

    // Clean up all the images.
    foreach ($image_resources as $img_resource) {
      imagedestroy($img_resource);
    }

    // Create a new image with the exact height we need.
    // This prevents any extra white space at the bottom.
    if ($actual_height < $estimated_height && $actual_height > 0) {
      // Create a new image with the exact height we need.
      // Ensure we have positive dimensions for GD functions.
      $safe_width = max(1, $width);
      $safe_height = max(1, $actual_height);
      $final_image = imagecreatetruecolor($safe_width, $safe_height);
      if ($final_image === FALSE) {
        throw new \RuntimeException('Could not create final image for stitching.');
      }

      // Copy from working image to final image.
      imagecopy($final_image, $full_image, 0, 0, 0, 0, $width, $actual_height);

      // Free the memory from the temporary image.
      imagedestroy($full_image);
      $full_image = $final_image;
    }

    // Output to string with high quality (1 = highest quality, 9 = lowest).
    ob_start();
    imagepng($full_image, NULL, 1);
    $image_data = ob_get_clean();

    // Free memory.
    imagedestroy($full_image);

    if ($image_data === FALSE) {
      throw new \RuntimeException('Failed to generate PNG data from stitched image.');
    }

    return $image_data;
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

}
