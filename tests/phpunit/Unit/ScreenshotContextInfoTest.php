<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatScreenshot\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext info methods.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextInfoTest extends TestCase {

  use ReflectionTrait;

  public function testRenderInfo(): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->appendInfo('Test Label', 'Test Value');
    $screenshot_context->appendInfo('Another Label', 'Another Value');

    $expected = "Test Label: Test Value\nAnother Label: Another Value";
    $this->assertSame($expected, $screenshot_context->renderInfo());
  }

  public function testRenderInfoWithEmptyInfo(): void {
    $screenshot_context = new ScreenshotContext();

    $this->assertSame('', $screenshot_context->renderInfo());
  }

  #[DataProvider('dataProviderCompileInfo')]
  public function testCompileInfo(array $info_types, array $expected_keys): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $feature_node->method('getTitle')->willReturn('Test Feature Title');
    $step_node = $this->createMock(StepNode::class);
    $step_node->method('getText')->willReturn('Test step text');
    $step_node->method('getLine')->willReturn(42);

    $scope = new BeforeStepScope($env, $feature_node, $step_node);

    $session = $this->createMock(Session::class);
    $session->method('getCurrentUrl')->willReturn('http://example.com/test');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession']);
    $screenshot_context->method('getSession')->willReturn($session);

    $screenshot_context->beforeStepInit($scope);
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      FALSE,
      FALSE,
      '{datetime:U}.test.{ext}',
      '{datetime:U}.{failed_prefix}test.{ext}',
      $info_types,
      []
    );

    $screenshot_context->renderInfo();

    $info = self::getProtectedValue($screenshot_context, 'info');
    $this->assertIsArray($info);

    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $info);
    }
  }

  public static function dataProviderCompileInfo(): array {
    return [
      [
        ['url'],
        ['Current URL'],
      ],
      [
        ['feature'],
        ['Feature'],
      ],
      [
        ['step'],
        ['Step'],
      ],
      [
        ['datetime'],
        ['Datetime'],
      ],
      [
        ['url', 'feature', 'step', 'datetime'],
        ['Current URL', 'Feature', 'Step', 'Datetime'],
      ],
    ];
  }

  public function testCompileInfoUrlException(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);
    $scope = new BeforeStepScope($env, $feature_node, $step_node);

    $session = $this->createMock(Session::class);
    $session->method('getCurrentUrl')->willThrowException(new \Exception('URL not available'));

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession']);
    $screenshot_context->method('getSession')->willReturn($session);

    $screenshot_context->beforeStepInit($scope);
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      FALSE,
      FALSE,
      '{datetime:U}.test.{ext}',
      '{datetime:U}.{failed_prefix}test.{ext}',
      ['url'],
      []
    );

    $screenshot_context->renderInfo();

    $info = self::getProtectedValue($screenshot_context, 'info');
    $this->assertIsArray($info);

    $this->assertArrayHasKey('Current URL', $info);
    $this->assertSame('not available', $info['Current URL']);
  }

  public function testScreenshotUnsupportedDriver(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'makeFileName',
      'saveScreenshotContent',
      'renderInfo',
    ]);

    $session = $this->createMock(Session::class);
    $driver = $this->createMock(DriverInterface::class);

    $driver->method('getContent')->willReturn('test-content');
    $driver->method('getScreenshot')->willThrowException(
      new UnsupportedDriverActionException('Not supported', $driver)
    );

    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('makeFileName')->willReturn('test-file-name');
    $screenshot_context->method('renderInfo')->willReturn('');

    // Only the HTML content is saved.
    $screenshot_context->expects($this->once())->method('saveScreenshotContent');

    $screenshot_context->screenshot();
  }

  public function testGetCurrentTime(): void {
    $screenshot_context = new ScreenshotContext();
    $time = self::callProtectedMethod($screenshot_context, 'getCurrentTime');
    $this->assertIsInt($time);
    $this->assertGreaterThan(0, $time);
  }

  public function testMakeFileNameWithHostReplacement(): void {
    $original_value = getenv('BEHAT_SCREENSHOT_TOKEN_HOST');

    try {
      putenv('BEHAT_SCREENSHOT_TOKEN_HOST=example.org');

      $env = $this->createMock(Environment::class);
      $feature_node = $this->createMock(FeatureNode::class);
      $feature_node->method('getFile')->willReturn('test-feature-file');
      $step_node = $this->createMock(StepNode::class);
      $step_node->method('getText')->willReturn('test-step');
      $step_node->method('getLine')->willReturn(123);
      $scope = new BeforeStepScope($env, $feature_node, $step_node);

      $session = $this->createMock(Session::class);
      $session->method('getCurrentUrl')->willReturn('http://localhost:8080/test-page');

      $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
        'getSession',
        'getBeforeStepScope',
        'getCurrentTime',
      ]);
      $screenshot_context->method('getSession')->willReturn($session);
      $screenshot_context->method('getBeforeStepScope')->willReturn($scope);
      $screenshot_context->method('getCurrentTime')->willReturn(12345678);

      $screenshot_context->setScreenshotParameters(
        'test-dir',
        FALSE,
        'failed_',
        FALSE,
        FALSE,
        '{url}.{ext}',
        '{failed_prefix}{url}.{ext}',
        [],
        []
      );

      $result = self::callProtectedMethod($screenshot_context, 'makeFileName', ['png', NULL, FALSE]);
      $this->assertIsString($result);

      // The Tokenizer collapses each run of characters other than word
      // characters and hyphens into a single underscore.
      $this->assertStringContainsString('example_org', $result);
      $this->assertStringNotContainsString('localhost', $result);
    }
    finally {
      if ($original_value !== FALSE) {
        putenv('BEHAT_SCREENSHOT_TOKEN_HOST=' . $original_value);
      }
      else {
        putenv('BEHAT_SCREENSHOT_TOKEN_HOST');
      }
    }
  }

}
