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
      '/tmp',
      TRUE,
      'failed_',
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}'
    );
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['iSaveScreenshot']);
    $screenshot_context->setScreenshotParameters(
      '/tmp',
      TRUE,
      'failed_',
      '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
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

}
