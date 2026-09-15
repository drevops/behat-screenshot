<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ScreenshotConfigTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext info methods.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextInfoTest extends TestCase {

  use ReflectionTrait;
  use ScreenshotConfigTrait;

  public function testRenderInfoJoinsLabelValuePairsWithNewlines(): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    $screenshot_context->appendInfo('Test Label', 'Test Value');
    $screenshot_context->appendInfo('Another Label', 'Another Value');

    $expected = "Test Label: Test Value\nAnother Label: Another Value";
    $this->assertSame($expected, $screenshot_context->renderInfo());
  }

  public function testRenderInfoReturnsEmptyStringWhenNothingAppended(): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());

    $this->assertSame('', $screenshot_context->renderInfo());
  }

  #[DataProvider('dataProviderRenderInfoCompilesConfiguredInfoTypes')]
  public function testRenderInfoCompilesConfiguredInfoTypes(array $info_types, array $expected_info): void {
    $env = $this->createStub(Environment::class);
    $feature_node = $this->createStub(FeatureNode::class);
    $feature_node->method('getTitle')->willReturn('Test Feature Title');
    $step_node = $this->createStub(StepNode::class);
    $step_node->method('getText')->willReturn('Test step text');
    $step_node->method('getLine')->willReturn(42);

    $scope = new BeforeStepScope($env, $feature_node, $step_node);

    $session = $this->createStub(Session::class);
    $session->method('getCurrentUrl')->willReturn('http://example.com/test');

    $screenshot_context = $this->getStubBuilder(ScreenshotContext::class)->onlyMethods(['getSession', 'getCurrentTime'])->getStub();
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('getCurrentTime')->willReturn(1700000000);

    $screenshot_context->beforeStepInit($scope);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['info_types' => $info_types]));

    $screenshot_context->renderInfo();

    $this->assertSame($expected_info, self::getProtectedValue($screenshot_context, 'info'));
  }

  public static function dataProviderRenderInfoCompilesConfiguredInfoTypes(): array {
    $datetime = date('Y-m-d H:i:s', 1700000000);

    return [
      'url only' => [
        ['url'],
        ['Current URL' => 'http://example.com/test'],
      ],
      'feature only' => [
        ['feature'],
        ['Feature' => 'Test Feature Title'],
      ],
      'step only' => [
        ['step'],
        ['Step' => 'Test step text (line 42)'],
      ],
      'datetime only' => [
        ['datetime'],
        ['Datetime' => $datetime],
      ],
      'all info types' => [
        ['url', 'feature', 'step', 'datetime'],
        ['Current URL' => 'http://example.com/test', 'Feature' => 'Test Feature Title', 'Step' => 'Test step text (line 42)', 'Datetime' => $datetime],
      ],
    ];
  }

  public function testRenderInfoMarksUrlNotAvailableOnException(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);
    $scope = new BeforeStepScope($env, $feature_node, $step_node);

    $session = $this->createMock(Session::class);
    $session->method('getCurrentUrl')->willThrowException(new \Exception('URL not available'));

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession']);
    $screenshot_context->method('getSession')->willReturn($session);

    $screenshot_context->beforeStepInit($scope);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['info_types' => ['url']]));

    $screenshot_context->renderInfo();

    $info = self::getProtectedValue($screenshot_context, 'info');
    $this->assertIsArray($info);

    $this->assertArrayHasKey('Current URL', $info);
    $this->assertSame('not available', $info['Current URL']);
  }

  public function testCaptureScreenshotWritesOnlyHtmlWhenImageUnsupported(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'makeFilename',
      'writeScreenshotContent',
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
    $screenshot_context->method('makeFilename')->willReturn('test-filename');
    $screenshot_context->method('renderInfo')->willReturn('');
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());

    // Only the HTML content is saved.
    $screenshot_context->expects($this->once())->method('writeScreenshotContent');

    $screenshot_context->captureScreenshot();
  }

  public function testGetCurrentTimeReturnsPositiveInteger(): void {
    $screenshot_context = new ScreenshotContext();
    $time = self::callProtectedMethod($screenshot_context, 'getCurrentTime');
    $this->assertIsInt($time);
    $this->assertGreaterThan(0, $time);
  }

  public function testMakeFilenameReplacesUrlHostFromEnvironment(): void {
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
      ]);
      $screenshot_context->method('getSession')->willReturn($session);
      $screenshot_context->method('getBeforeStepScope')->willReturn($scope);

      $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['filename_pattern' => '{url}.{ext}', 'filename_pattern_failed' => '{failed_prefix}{url}.{ext}']));

      $result = self::callProtectedMethod($screenshot_context, 'makeFilename', ['png', 12345678, NULL, FALSE]);
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
