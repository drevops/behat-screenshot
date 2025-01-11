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
    $driver->method('start')->willThrowException(new \Exception('Test Exception.'));
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

  public function testBeforeStepInitThrowError(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);

    $feature_node->method('getFile')->willReturn(FALSE);
    $this->expectException(\RuntimeException::class);

    $screenshot_context = new ScreenshotContext();
    $scope = new BeforeStepScope($env, $feature_node, $step_node);
    $screenshot_context->beforeStepInit($scope);
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
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
      TRUE
    );
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['iSaveScreenshot']);
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
      TRUE,
    );
    $screenshot_context->expects($this->once())->method('iSaveScreenshot');
    $screenshot_context->printLastResponseOnError($scope);
  }

  public function testIsaveSizedScreenshot(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession', 'iSaveScreenshot']);
    $session = $this->createMock(Session::class);
    $exception = $this->createMock(UnsupportedDriverActionException::class);
    $session->method('resizeWindow')->willThrowException($exception);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->expects($this->once())->method('iSaveScreenshot');
    $screenshot_context->iSaveSizedScreenshot();
  }

  public function testIsaveSizedScreenshotWithName(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['iSaveScreenshot']);
    $screenshot_context->expects($this->once())->method('iSaveScreenshot');
    $screenshot_context->iSaveScreenshotWithName('test-file-name');
  }

  public function testIsaveScreenshot(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'makeFileName',
      'saveScreenshotData',
    ]);
    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);
    $driver->method('getContent')->willReturn('test-content');
    $driver->method('getScreenshot')->willReturn('test-content');
    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('makeFileName')->willReturn('test-file-name');

    $screenshot_context->expects($this->exactly(2))->method('saveScreenshotData');
    $screenshot_context->iSaveScreenshot();
  }

  public function testIsaveScreenshotThrowException(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'makeFileName',
      'saveScreenshotData',
    ]);
    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);
    $exception = $this->createMock(DriverException::class);
    $driver->method('getContent')->willThrowException($exception);
    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('makeFileName')->willReturn('test-file-name');

    $screenshot_context->expects($this->never())->method('saveScreenshotData');
    $screenshot_context->iSaveScreenshot();
  }

  #[DataProvider('saveScreenshotDataDataProvider')]
  public function testSaveScreenshotData(string $filename, string $data): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
      TRUE,
    );
    $screenshot_context_reflection = new \ReflectionClass($screenshot_context);
    $method = $screenshot_context_reflection->getMethod('saveScreenshotData');
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

  /**
   * Test make file name.
   *
   * @param string $ext
   *   Ext.
   * @param mixed $filename
   *   Filename.
   * @param bool $fail
   *   Fail.
   * @param mixed $url
   *   URL.
   * @param int $current_time
   *   Current time.
   * @param string $step_text
   *   Step text.
   * @param int $step_line
   *   Step line.
   * @param string $feature_file
   *   Feature file.
   * @param string $fail_prefix
   *   Fail prefix.
   * @param string $file_name_pattern
   *   File name pattern.
   * @param string $file_name_pattern_failed
   *   File name pattern failed.
   * @param string $filename_expected
   *   File name expected.
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   * @throws \ReflectionException
   */
  #[DataProvider('makeFileNameProvider')]
  public function testMakeFileName(
    string $ext,
    mixed $filename,
    bool $fail,
    mixed $url,
    int $current_time,
    string $step_text,
    int $step_line,
    string $feature_file,
    string $fail_prefix,
    string $file_name_pattern,
    string $file_name_pattern_failed,
    string $filename_expected,
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
      $fail,
      $fail_prefix,
      $file_name_pattern,
      $file_name_pattern_failed,
      TRUE,
    );

    $screenshot_context_reflection = new \ReflectionClass($screenshot_context);
    $method = $screenshot_context_reflection->getMethod('makeFileName');
    $method->setAccessible(TRUE);
    $filename_processed = $method->invokeArgs($screenshot_context, [$ext, $filename, $fail]);
    $this->assertEquals($filename_expected, $filename_processed);
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
        '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
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
        '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
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
        '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
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
        '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
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
        '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.test-feature-file.feature_test-step-name.feature_12.png',
      ],
    ];
  }

}
