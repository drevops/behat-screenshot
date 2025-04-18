<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext fullscreen resize algorithm.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextResizeTest extends TestCase {

  /**
   * Test the getScreenshotFullscreenWithResize method.
   */
  public function testGetScreenshotFullscreenWithResize(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'getScreenshot',
    ]);

    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);

    // Mock the JavaScript evaluation to return document dimensions.
    $session->method('evaluateScript')
      ->willReturn([
        'scrollWidth' => 1440,
        'scrollHeight' => 2000,
      ]);

    // Cannot mock getWindowSize in PHPUnit 11, so we'll rely on default values.
    $session->expects($this->once())
      ->method('resizeWindow')
      ->with(1440, 2200, 'current');

    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);

    // Mock the screenshot result.
    $screenshot_context->method('getScreenshot')->willReturn('test-screenshot-data');

    // Create reflection to test protected method.
    $reflection = new \ReflectionClass($screenshot_context);
    $method = $reflection->getMethod('getScreenshotFullscreenWithResize');
    $method->setAccessible(TRUE);

    $result = $method->invoke($screenshot_context);
    $this->assertEquals('test-screenshot-data', $result);
  }

  /**
   * Test resize algorithm when JavaScript does not return valid dimensions.
   */
  public function testGetScreenshotFullscreenWithResizeInvalidDimensions(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'getScreenshot',
    ]);

    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);

    // Mock the JavaScript evaluation to return invalid dimensions.
    $session->method('evaluateScript')
      ->willReturn([
        'scrollWidth' => 0,
        'scrollHeight' => 0,
      ]);

    $session->expects($this->never())->method('resizeWindow');

    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);

    // Mock the screenshot result.
    $screenshot_context->method('getScreenshot')->willReturn('test-screenshot-data');

    // Create reflection to test protected method.
    $reflection = new \ReflectionClass($screenshot_context);
    $method = $reflection->getMethod('getScreenshotFullscreenWithResize');
    $method->setAccessible(TRUE);

    $result = $method->invoke($screenshot_context);
    $this->assertEquals('test-screenshot-data', $result);
  }

  /**
   * Test the getScreenshotFullscreen method using the resize algorithm.
   */
  public function testGetScreenshotFullscreenUsingResizeAlgorithm(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getScreenshotFullscreenWithResize',
    ]);

    // Set the fullscreen algorithm to 'resize'.
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      TRUE,
      'resize',
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      [],
    );

    // Mock the resize method to return test data.
    $screenshot_context->method('getScreenshotFullscreenWithResize')
      ->willReturn('test-resize-screenshot-data');

    // Create reflection to access protected method.
    $reflection = new \ReflectionClass($screenshot_context);
    $method = $reflection->getMethod('getScreenshotFullscreen');
    $method->setAccessible(TRUE);

    $result = $method->invoke($screenshot_context);
    $this->assertEquals('test-resize-screenshot-data', $result);
  }

  /**
   * Test full screenshot with resize in the screenshot method.
   */
  public function testScreenshotWithResizeAlgorithm(): void {
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

    // Set screenshot parameters with resize algorithm.
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
    // always_fullscreen = TRUE.
      TRUE,
      'resize',
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      [],
    );

    // Mock the fullscreen screenshot method.
    $screenshot_context->method('getScreenshotFullscreen')
      ->willReturn('test-fullscreen-screenshot-data');

    // In PHPUnit 11, we can't use withConsecutive, so we test calls separately.
    $screenshot_context->expects($this->exactly(2))
      ->method('saveScreenshotContent');

    $screenshot_context->screenshot();
  }

}
