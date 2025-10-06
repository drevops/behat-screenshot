<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\StepNode;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextTest extends TestCase {

  public function testBeforeScenarioInit(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $scenario = $this->createMock(ScenarioInterface::class);
    $scenario
      ->method('hasTag')->with('javascript')->willReturn(TRUE);
    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);
    $driver->method('start')->willThrowException(new \RuntimeException('Test Exception.'));
    $session->method('getDriver')->willReturn($driver);

    $this->expectException(\RuntimeException::class);

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession']);
    $screenshot_context->method('getSession')->willReturn($session);

    $scope = new BeforeScenarioScope($env, $feature_node, $scenario);
    $screenshot_context->beforeScenarioInit($scope);
  }

  public function testBeforeStepInit(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);

    $feature_node->method('getFile')->willReturn(TRUE);
    $screenshot_context = new ScreenshotContext();
    $scope = new BeforeStepScope($env, $feature_node, $step_node);
    $screenshot_context->beforeStepInit($scope);
    $this->assertEquals($scope, $screenshot_context->getBeforeStepScope());
  }

  public function testPrintLastResponseOnError(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);
    $result = $this->createMock(StepResult::class);
    $result->method('isPassed')->willReturn(FALSE);
    $scope = new AfterStepScope($env, $feature_node, $step_node, $result);

    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      FALSE,
      FALSE,
      'stitch',
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      []
    );
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      FALSE,
      FALSE,
      'stitch',
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      [],
    );
    $screenshot_context->expects($this->once())->method('screenshot');
    $screenshot_context->printLastResponseOnError($scope);
  }

  public function testIsaveSizedScreenshot(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession', 'screenshot']);
    $session = $this->createMock(Session::class);
    $exception = $this->createMock(UnsupportedDriverActionException::class);
    $session->method('resizeWindow')->willThrowException($exception);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->expects($this->once())->method('screenshot');
    $screenshot_context->iSaveSizedScreenshot();
  }

  public function testIsaveSizedScreenshotWithName(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $screenshot_context->expects($this->once())->method('screenshot');
    $screenshot_context->iSaveScreenshotWithName('test-file-name');
  }

  public function testIsSaveFullscreenScreenshotWithName(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $screenshot_context->expects($this->once())
      ->method('screenshot')
      ->with(['filename' => 'test-fullscreen-name', 'fullscreen' => TRUE]);
    $screenshot_context->iSaveFullscreenScreenshotWithName('test-fullscreen-name');
  }

  public function testIsSaveFullscreenScreenshot(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $screenshot_context->expects($this->once())
      ->method('screenshot')
      ->with(['fullscreen' => TRUE]);
    $screenshot_context->iSaveFullscreenScreenshot();
  }

  public function testScreenshot(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'makeFileName',
      'saveScreenshotContent',
    ]);
    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);
    $driver->method('getContent')->willReturn('test-content');
    $driver->method('getScreenshot')->willReturn('test-content');
    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('makeFileName')->willReturn('test-file-name');

    $screenshot_context->expects($this->exactly(2))->method('saveScreenshotContent');
    $screenshot_context->screenshot();
  }

  public function testScreenshotThrowException(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'makeFileName',
      'saveScreenshotContent',
    ]);
    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);
    $exception = $this->createMock(DriverException::class);
    $driver->method('getContent')->willThrowException($exception);
    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('makeFileName')->willReturn('test-file-name');

    $screenshot_context->expects($this->never())->method('saveScreenshotContent');
    $screenshot_context->screenshot();
  }

  #[DataProvider('saveScreenshotDataDataProvider')]
  public function testSaveScreenshotData(string $filename, string $data): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      FALSE,
      FALSE,
      'stitch',
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      [],
    );
    $screenshot_context_reflection = new \ReflectionClass($screenshot_context);
    $method = $screenshot_context_reflection->getMethod('saveScreenshotContent');
    $method->setAccessible(TRUE);
    $method->invokeArgs($screenshot_context, [$filename, $data]);
    $filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    $this->assertFileExists($filepath);
    $this->assertEquals(file_get_contents($filepath), $data);

    unlink($filepath);
  }

  /**
   * Data provider for testSaveScreenshotData method.
   */
  public static function saveScreenshotDataDataProvider(): array {
    return [
      ['test-save-screenshot-1.txt', 'test-data-1'],
      ['test-save-screenshot-2.txt', 'test-data-2'],
    ];
  }

  #[DataProvider('makeFileNameProvider')]
  public function testMakeFileName(
    string $ext,
    mixed $filename,
    bool $on_failed,
    mixed $url,
    int $current_time,
    string $step_text,
    int $step_line,
    string $feature_file,
    string $failed_prefix,
    string $filename_pattern,
    string $filename_pattern_failed,
    string $expected,
  ): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getBeforeStepScope',
      'getSession',
      'getCurrentTime',
    ]);
    $session = $this->createMock(Session::class);

    if ($url instanceof \Exception) {
      $session->method('getCurrentUrl')->willThrowException($url);
    }
    else {
      $session->method('getCurrentUrl')->willReturn($url);
    }

    $screenshot_context->method('getCurrentTime')->willReturn($current_time);
    $screenshot_context->method('getSession')->willReturn($session);
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);
    $step_node->method('getText')->willReturn($step_text);
    $step_node->method('getLine')->willReturn($step_line);
    $feature_node->method('getFile')->willReturn($feature_file);
    $scope = new BeforeStepScope($env, $feature_node, $step_node);
    $screenshot_context->method('getBeforeStepScope')->willReturn($scope);

    $screenshot_context->setScreenshotParameters(
      'test-dir',
      $on_failed,
      $failed_prefix,
      FALSE,
      FALSE,
      'stitch',
      $filename_pattern,
      $filename_pattern_failed,
      [],
    );

    $screenshot_context_reflection = new \ReflectionClass($screenshot_context);
    $method = $screenshot_context_reflection->getMethod('makeFileName');
    $method->setAccessible(TRUE);
    $filename_processed = $method->invokeArgs($screenshot_context, [$ext, $filename, $on_failed]);

    $this->assertEquals($expected, $filename_processed);
  }

  public static function makeFileNameProvider(): array {
    return [
      [
        'html',
        NULL,
        FALSE,
        'test-url',
        1721791661,
        'test-step-name',
        12,
        'test-feature-file',
        'failed_',
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.test-feature-file.feature_12.html',
      ],
      [
        'png',
        '{datetime:U}.{feature_file}.feature_{step_name}.feature_{step_line}.{ext}',
        FALSE,
        'test-url',
        1721791661,
        'test-step-name',
        12,
        'test-feature-file',
        'failed_',
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.test-feature-file.feature_test-step-name.feature_12.png',
      ],
      [
        'png',
        '{datetime:U}.{feature_file}.feature_{step_name}.feature_{step_line}',
        FALSE,
        'test-url',
        1721791661,
        'test-step-name',
        12,
        'test-feature-file',
        'failed_',
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.test-feature-file.feature_test-step-name.feature_12.png',
      ],
      [
        'png',
        '{datetime:U}.{feature_file}.feature_{step_name}.feature_{step_line}',
        TRUE,
        'test-url',
        1721791661,
        'test-step-name',
        12,
        'test-feature-file',
        'failed_',
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.failed_test-feature-file.feature_12.png',
      ],
      [
        'png',
        '{datetime:U}.{feature_file}.feature_{step_name}.feature_{step_line}',
        FALSE,
        new \Exception('test'),
        1721791661,
        'test-step-name',
        12,
        'test-feature-file',
        'failed_',
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.test-feature-file.feature_test-step-name.feature_12.png',
      ],
    ];
  }

}
