<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Profile;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\StepNode;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Tester\Result\TestResult;
use DrevOps\BehatScreenshot\Tests\Traits\GifParserTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Measure how long a scenario's animated GIF takes to assemble.
 *
 * Drives ScreenshotContext through the same hooks Behat calls, with the driver
 * replaced by prepared images. The steps and the AfterScenario handler that
 * builds the GIF are timed separately.
 *
 * Each run is measured twice: with every frame at viewport height, and with
 * one very long page among them. Comparing the two isolates the cost of a
 * single tall capture from the cost inherent to encoding that many frames.
 *
 * Excluded from the default suite because it takes minutes to run. Invoke it
 * with `composer profile`. Step counts can be overridden with
 * BEHAT_SCREENSHOT_PROFILE_STEPS, e.g. `BEHAT_SCREENSHOT_PROFILE_STEPS=10,20`.
 */
#[CoversNothing]
#[Group('profile')]
class AnimationAssemblyProfileTest extends TestCase {

  use GifParserTrait;

  /**
   * Width every captured frame shares, in pixels.
   */
  protected const FRAME_WIDTH = 1432;

  /**
   * Height of an ordinary viewport-sized capture, in pixels.
   */
  protected const VIEWPORT_HEIGHT = 900;

  /**
   * Height of the single long page, in pixels.
   */
  protected const LONG_PAGE_HEIGHT = 19090;

  /**
   * Step counts profiled when none are given in the environment.
   */
  protected const DEFAULT_STEPS = [25, 50, 100];

  protected function setUp(): void {
    parent::setUp();

    // GD is a suggested dependency, so the profiler has nothing to measure
    // without it - the animation is skipped at runtime for the same reason.
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagegif')) {
      $this->markTestSkipped('Profiling animated GIF assembly requires the gd extension.');
    }
  }

  public function testAnimationAssemblyCost(): void {
    $steps = $this->stepCounts();

    $report = [
      'Animated GIF assembly cost',
      '==========================',
      '',
      sprintf('Frames are %dpx wide. "long page" runs replace one frame mid-scenario', self::FRAME_WIDTH),
      sprintf('with a %dpx-tall capture, as always_fullscreen does on a long page.', self::LONG_PAGE_HEIGHT),
      '',
      sprintf('%-8s %-12s %9s %11s %8s %9s %14s %14s', 'steps', 'tallest', 'steps_s', 'assembly_s', 'total_s', 'peak_mb', 'px_captured', 'px_encoded'),
      str_repeat('-', 93),
    ];

    $rows = [];
    foreach ([self::VIEWPORT_HEIGHT, self::LONG_PAGE_HEIGHT] as $tallest) {
      foreach ($steps as $count) {
        $row = $this->profile($count, $tallest);
        $rows[$tallest][$count] = $row;
        $report[] = $this->formatRow($row);
      }
    }

    $report[] = '';
    $report[] = 'Cost of one long page, against the same scenario without one:';
    foreach ($steps as $count) {
      $uniform = $rows[self::VIEWPORT_HEIGHT][$count];
      $long = $rows[self::LONG_PAGE_HEIGHT][$count];
      $report[] = sprintf(
        '  %3d steps: total %6.2fs -> %7.2fs (%.1fx), pixels encoded %.1fx what was captured',
        $count,
        $uniform['total'],
        $long['total'],
        $uniform['total'] > 0 ? $long['total'] / $uniform['total'] : 0,
        $long['captured'] > 0 ? $long['encoded'] / $long['captured'] : 0
      );
    }

    $report[] = '';
    $report[] = 'Share of the total that falls after the last step:';
    foreach ($steps as $count) {
      $long = $rows[self::LONG_PAGE_HEIGHT][$count];
      $report[] = sprintf(
        '  %3d steps with a long page: %5.1f%% of %6.2fs (%.2fs of it blocking at scenario end)',
        $count,
        $long['total'] > 0 ? $long['assembly'] / $long['total'] * 100 : 0,
        $long['total'],
        $long['assembly']
      );
    }
    $report[] = '';

    $this->writeReport(implode("\n", $report) . "\n");

    // The timings only describe something real if each scenario did produce an
    // animation. Comparing the two timings against each other would be a race
    // rather than an assertion, so the report is left to show that.
    foreach ($rows as $by_step) {
      foreach ($by_step as $row) {
        $this->assertGreaterThan(0, $row['bytes']);
      }
    }
  }

  /**
   * Run one scenario and measure it.
   *
   * @param int $steps
   *   Number of steps in the scenario.
   * @param int $tallest
   *   Height of the tallest captured frame, in pixels.
   *
   * @return array<string,float|int>
   *   Timings, peak memory and pixel counts for the run.
   */
  protected function profile(int $steps, int $tallest): array {
    $screenshot_context = new ProfiledScreenshotContext();
    $screenshot_context->setScreenshotParameters('unused', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], ['enabled' => TRUE, 'frame_delay' => 500]);

    $before_scope = $this->createBeforeScenarioScope();
    $after_step_scope = $this->createAfterStepScope();
    $after_scenario_scope = $this->createAfterScenarioScope();

    $viewport = $this->createPage(self::FRAME_WIDTH, self::VIEWPORT_HEIGHT);
    $long = $tallest > self::VIEWPORT_HEIGHT ? $this->createPage(self::FRAME_WIDTH, $tallest) : $viewport;
    $long_at = intdiv($steps, 2);

    gc_collect_cycles();
    memory_reset_peak_usage();
    $baseline = memory_get_usage();

    $screenshot_context->beforeScenarioCheckScreenshotsTag($before_scope);

    $started = hrtime(TRUE);
    for ($step = 0; $step < $steps; $step++) {
      $screenshot_context->pending = $step === $long_at ? $long : $viewport;
      $screenshot_context->captureScreenshotAfterStep($after_step_scope);
    }
    $steps_elapsed = (hrtime(TRUE) - $started) / 1e9;
    $screenshot_context->pending = '';

    $started = hrtime(TRUE);
    $screenshot_context->afterScenarioAnimate($after_scenario_scope);
    $assembly_elapsed = (hrtime(TRUE) - $started) / 1e9;

    $captured = ($steps - 1) * self::FRAME_WIDTH * self::VIEWPORT_HEIGHT + self::FRAME_WIDTH * $tallest;

    return [
      'steps_count' => $steps,
      'tallest' => $tallest,
      'steps' => $steps_elapsed,
      'assembly' => $assembly_elapsed,
      'total' => $steps_elapsed + $assembly_elapsed,
      'peak' => (memory_get_peak_usage() - $baseline) / 1048576,
      'captured' => $captured,
      'encoded' => $this->encodedPixels($screenshot_context->gif),
      'bytes' => strlen($screenshot_context->gif),
    ];
  }

  /**
   * Format one measured run as a report row.
   *
   * @param array<string,float|int> $row
   *   Measurements for the run.
   *
   * @return string
   *   Report line.
   */
  protected function formatRow(array $row): string {
    return sprintf(
      '%-8d %-12s %9.2f %11.2f %8.2f %9.1f %14s %14s',
      $row['steps_count'],
      $row['tallest'] > self::VIEWPORT_HEIGHT ? 'long page' : 'viewport',
      $row['steps'],
      $row['assembly'],
      $row['total'],
      $row['peak'],
      number_format((float) $row['captured']),
      number_format((float) $row['encoded'])
    );
  }

  /**
   * Render a page of the given size.
   *
   * @param int $width
   *   Page width.
   * @param int $height
   *   Page height.
   *
   * @return string
   *   Binary PNG data.
   */
  protected function createPage(int $width, int $height): string {
    $image = imagecreatetruecolor(max(1, $width), max(1, $height));
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 250, 250, 252));
    imagefilledrectangle($image, 0, 0, $width - 1, 60, (int) imagecolorallocate($image, 28, 42, 88));

    $ink = (int) imagecolorallocate($image, 40, 44, 60);
    $muted = (int) imagecolorallocate($image, 150, 155, 170);

    for ($y = 96; $y < $height - 30; $y += 26) {
      $length = 300 + (($y * 7) % (int) ($width * 0.55));
      imagefilledrectangle($image, 40, $y, 40 + $length, $y + 11, $ink);
      imagefilledrectangle($image, 40, $y + 14, 40 + (int) ($length * 0.7), $y + 20, $muted);
    }

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
  }

  /**
   * Read the step counts to profile.
   *
   * @return array<int,int>
   *   Step counts.
   */
  protected function stepCounts(): array {
    $configured = getenv('BEHAT_SCREENSHOT_PROFILE_STEPS');
    if (!is_string($configured) || trim($configured) === '') {
      return self::DEFAULT_STEPS;
    }

    $counts = array_values(array_filter(array_map('intval', explode(',', $configured)), static fn(int $count): bool => $count > 0));

    return $counts === [] ? self::DEFAULT_STEPS : $counts;
  }

  /**
   * Write the report where CI collects it.
   *
   * @param string $report
   *   Report content.
   */
  protected function writeReport(string $report): void {
    $dir = dirname(__DIR__, 3) . '/.logs/profile';
    if (!is_dir($dir) && !mkdir($dir, 0755, TRUE) && !is_dir($dir)) {
      throw new \RuntimeException(sprintf('Unable to create the profile directory %s.', $dir));
    }

    $file = $dir . '/animation-assembly.txt';
    if (file_put_contents($file, $report) === FALSE) {
      throw new \RuntimeException(sprintf('Unable to write the profile report to %s.', $file));
    }

    // Diagnostics go to STDERR so the strict no-output-during-tests rule that
    // guards the default suite still holds.
    fwrite(STDERR, $report);
  }

  /**
   * Create a before scenario scope.
   *
   * @return \Behat\Behat\Hook\Scope\BeforeScenarioScope
   *   Before scenario scope.
   */
  protected function createBeforeScenarioScope(): BeforeScenarioScope {
    $feature_node = $this->createMock(FeatureNode::class);
    $feature_node->method('hasTag')->willReturn(FALSE);
    $scenario = $this->createMock(ScenarioInterface::class);
    $scenario->method('hasTag')->willReturn(FALSE);

    return new BeforeScenarioScope($this->createMock(Environment::class), $feature_node, $scenario);
  }

  /**
   * Create an after step scope for a passed step.
   *
   * @return \Behat\Behat\Hook\Scope\AfterStepScope
   *   After step scope.
   */
  protected function createAfterStepScope(): AfterStepScope {
    $result = $this->createMock(StepResult::class);
    $result->method('isPassed')->willReturn(TRUE);

    return new AfterStepScope($this->createMock(Environment::class), $this->createMock(FeatureNode::class), $this->createMock(StepNode::class), $result);
  }

  /**
   * Create an after scenario scope.
   *
   * @return \Behat\Behat\Hook\Scope\AfterScenarioScope
   *   After scenario scope.
   */
  protected function createAfterScenarioScope(): AfterScenarioScope {
    $feature_node = $this->createMock(FeatureNode::class);
    $feature_node->method('getFile')->willReturn('profile.feature');
    $scenario = $this->createMock(ScenarioInterface::class);
    $scenario->method('getLine')->willReturn(1);

    return new AfterScenarioScope($this->createMock(Environment::class), $feature_node, $scenario, $this->createMock(TestResult::class));
  }

}
