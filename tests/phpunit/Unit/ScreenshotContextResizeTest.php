<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatScreenshot\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext fullscreen resize algorithm.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextResizeTest extends TestCase {

  use ReflectionTrait;

  public function testGetScreenshotFullscreenWithResizeResizesThenRestoresWindow(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'getScreenshot',
    ]);

    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);

    $session->method('evaluateScript')
      ->willReturnOnConsecutiveCalls(
        // First call: get original window dimensions.
        [
          'width' => 1440,
          'height' => 900,
        ],
        // Second call: get document scroll dimensions.
        [
          'scrollWidth' => 1440,
          'scrollHeight' => 2000,
        ]
      );

    $session->expects($this->exactly(2))
      ->method('resizeWindow')
      ->willReturnCallback(function ($width, $height, $name): void {
        static $call_count = 0;
        $call_count++;

        if ($call_count === 1) {
          // First call: resize to fullscreen.
          $this->assertSame(1440, $width);
          $this->assertSame(2200, $height);
          $this->assertSame('current', $name);
        }
        elseif ($call_count === 2) {
          // Second call: restore to original.
          $this->assertSame(1440, $width);
          $this->assertSame(900, $height);
          $this->assertSame('current', $name);
        }
      });

    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);

    $screenshot_context->method('getScreenshot')->willReturn('test-screenshot-data');

    $result = self::callProtectedMethod($screenshot_context, 'getScreenshotFullscreenWithResize');
    $this->assertSame('test-screenshot-data', $result);
  }

  public function testGetScreenshotFullscreenWithResizeSkipsResizeOnInvalidDimensions(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'getScreenshot',
    ]);

    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);

    // Mock the JavaScript evaluation to return invalid scroll dimensions on
    // the second call.
    $session->method('evaluateScript')
      ->willReturnOnConsecutiveCalls(
        // First call: get original window dimensions.
        [
          'width' => 1440,
          'height' => 900,
        ],
        // Second call: get document scroll dimensions (invalid).
        [
          'scrollWidth' => 0,
          'scrollHeight' => 0,
        ]
      );

    $session->expects($this->never())->method('resizeWindow');

    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);

    $screenshot_context->method('getScreenshot')->willReturn('test-screenshot-data');

    $result = self::callProtectedMethod($screenshot_context, 'getScreenshotFullscreenWithResize');
    $this->assertSame('test-screenshot-data', $result);
  }

  public function testGetScreenshotFullscreenDelegatesToResizeAlgorithm(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getScreenshotFullscreenWithResize',
    ]);

    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      TRUE,
      FALSE,
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      [],
      []
    );

    $screenshot_context->method('getScreenshotFullscreenWithResize')
      ->willReturn('test-resize-screenshot-data');

    $result = self::callProtectedMethod($screenshot_context, 'getScreenshotFullscreen');
    $this->assertSame('test-resize-screenshot-data', $result);
  }

  public function testScreenshotSavesHtmlAndImageWhenFullscreenEnabled(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);
    $feature_node->method('getFile')->willReturn('test-feature.php');
    $step_node->method('getLine')->willReturn(42);
    $step_node->method('getText')->willReturn('Test step');

    $scope = new BeforeStepScope($env, $feature_node, $step_node);

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'getBeforeStepScope',
      'getScreenshotFullscreen',
      'saveScreenshotContent',
      'getCurrentTime',
    ]);

    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);

    $driver->method('getContent')->willReturn('<html>Test content</html>');
    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('getBeforeStepScope')->willReturn($scope);
    $screenshot_context->method('getCurrentTime')->willReturn(1234567890);

    // Set screenshot parameters with always_fullscreen = TRUE.
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      TRUE,
      FALSE,
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      [],
      []
    );

    $screenshot_context->method('getScreenshotFullscreen')
      ->willReturn('test-fullscreen-screenshot-data');

    // PHPUnit 11 has no withConsecutive(), so only the call count is
    // asserted.
    $screenshot_context->expects($this->exactly(2))
      ->method('saveScreenshotContent');

    $screenshot_context->screenshot();
  }

}
