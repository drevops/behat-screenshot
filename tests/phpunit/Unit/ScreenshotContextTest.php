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
use DrevOps\BehatScreenshot\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextTest extends TestCase {

  use ReflectionTrait;

  public function testBeforeScenarioInitPropagatesDriverStartException(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $scenario = $this->createMock(ScenarioInterface::class);
    $scenario->method('hasTag')->with('javascript')->willReturn(TRUE);
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

  public function testBeforeStepInitStoresScopeForLaterRetrieval(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);

    $feature_node->method('getFile')->willReturn(TRUE);
    $screenshot_context = new ScreenshotContext();
    $scope = new BeforeStepScope($env, $feature_node, $step_node);
    $screenshot_context->beforeStepInit($scope);
    $this->assertSame($scope, $screenshot_context->getBeforeStepScope());
  }

  public function testPrintLastResponseOnErrorTakesScreenshotOnFailedStep(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);
    $result = $this->createMock(StepResult::class);
    $result->method('isPassed')->willReturn(FALSE);
    $scope = new AfterStepScope($env, $feature_node, $step_node, $result);

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      FALSE,
      FALSE,
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      [],
      []
    );
    $screenshot_context->expects($this->once())->method('screenshot');
    $screenshot_context->printLastResponseOnError($scope);
  }

  public function testIsaveSizedScreenshotIgnoresUnsupportedResize(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession', 'screenshot']);
    $session = $this->createMock(Session::class);
    $exception = new UnsupportedDriverActionException('Not supported', $this->createMock(Selenium2Driver::class));
    $session->method('resizeWindow')->willThrowException($exception);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->expects($this->once())->method('screenshot');
    $screenshot_context->iSaveSizedScreenshot();
  }

  public function testIsaveScreenshotWithNameDelegatesToScreenshot(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $screenshot_context->expects($this->once())->method('screenshot');
    $screenshot_context->iSaveScreenshotWithName('test-file-name');
  }

  public function testIsaveFullscreenScreenshotWithNamePassesNameAndFullscreen(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $screenshot_context->expects($this->once())
      ->method('screenshot')
      ->with(['filename' => 'test-fullscreen-name', 'fullscreen' => TRUE]);
    $screenshot_context->iSaveFullscreenScreenshotWithName('test-fullscreen-name');
  }

  public function testIsaveFullscreenScreenshotRequestsFullscreenCapture(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $screenshot_context->expects($this->once())
      ->method('screenshot')
      ->with(['fullscreen' => TRUE]);
    $screenshot_context->iSaveFullscreenScreenshot();
  }

  public function testScreenshotSavesHtmlAndPngContent(): void {
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

  public function testScreenshotSavesNothingWhenDriverHasNoContent(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'makeFileName',
      'saveScreenshotContent',
    ]);
    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);
    $exception = new DriverException('Test Exception.');
    $driver->method('getContent')->willThrowException($exception);
    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('makeFileName')->willReturn('test-file-name');

    $screenshot_context->expects($this->never())->method('saveScreenshotContent');
    $screenshot_context->screenshot();
  }

  #[DataProvider('dataProviderSaveScreenshotContentWritesDataToFile')]
  public function testSaveScreenshotContentWritesDataToFile(string $filename, string $data): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      FALSE,
      FALSE,
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      [],
      []
    );
    self::callProtectedMethod($screenshot_context, 'saveScreenshotContent', [$filename, $data]);
    $filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    $this->assertFileExists($filepath);
    $this->assertSame($data, file_get_contents($filepath));

    unlink($filepath);
  }

  public static function dataProviderSaveScreenshotContentWritesDataToFile(): array {
    return [
      'first file' => ['test-save-screenshot-1.txt', 'test-data-1'],
      'second file' => ['test-save-screenshot-2.txt', 'test-data-2'],
    ];
  }

  #[DataProvider('dataProviderMakeFileNameReplacesTokensInPatterns')]
  public function testMakeFileNameReplacesTokensInPatterns(
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
      $filename_pattern,
      $filename_pattern_failed,
      [],
      []
    );

    $filename_processed = self::callProtectedMethod($screenshot_context, 'makeFileName', [$ext, $filename, $on_failed]);

    $this->assertSame($expected, $filename_processed);
  }

  public static function dataProviderMakeFileNameReplacesTokensInPatterns(): array {
    return [
      'no filename uses default pattern' => [
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
      'custom pattern with step name' => [
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
      'pattern without ext token' => [
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
      'failed step uses failed pattern' => [
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
      'url unavailable' => [
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
