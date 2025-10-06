<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use Behat\Mink\Driver\DriverInterface;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext info methods.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextInfoTest extends TestCase {

  /**
   * Test renderInfo and appendInfo methods.
   */
  public function testRenderInfo(): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->appendInfo('Test Label', 'Test Value');
    $screenshot_context->appendInfo('Another Label', 'Another Value');

    $expected = "Test Label: Test Value\nAnother Label: Another Value";
    $this->assertEquals($expected, $screenshot_context->renderInfo());
  }

  /**
   * Test compileInfo method with different info types.
   *
   * @param array $info_types
   *   The info types to compile.
   * @param array $expected_keys
   *   The expected info keys.
   */
  #[DataProvider('compileInfoDataProvider')]
  public function testCompileInfo(array $info_types, array $expected_keys): void {
    // Setup mocks.
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

    // Initialize.
    $screenshot_context->beforeStepInit($scope);
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      FALSE,
      FALSE,
      'stitch',
      '{datetime:U}.test.{ext}',
      '{datetime:U}.{failed_prefix}test.{ext}',
      $info_types
    );

    // Get the info.
    $screenshot_context->renderInfo();

    // Use reflection to access protected property.
    $reflection = new \ReflectionObject($screenshot_context);
    $info_property = $reflection->getProperty('info');
    $info_property->setAccessible(TRUE);
    $info = $info_property->getValue($screenshot_context);
    $this->assertIsArray($info);

    // Check that all expected keys exist.
    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $info);
    }
  }

  /**
   * Test compileInfo with URL exception.
   */
  public function testCompileInfoUrlException(): void {
    // Setup mocks.
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);
    $scope = new BeforeStepScope($env, $feature_node, $step_node);

    $session = $this->createMock(Session::class);
    $session->method('getCurrentUrl')->willThrowException(new \Exception('URL not available'));

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession']);
    $screenshot_context->method('getSession')->willReturn($session);

    // Initialize.
    $screenshot_context->beforeStepInit($scope);
    $screenshot_context->setScreenshotParameters(
      sys_get_temp_dir(),
      TRUE,
      'failed_',
      FALSE,
      FALSE,
      'stitch',
      '{datetime:U}.test.{ext}',
      '{datetime:U}.{failed_prefix}test.{ext}',
      ['url']
    );

    // Get the info.
    $screenshot_context->renderInfo();

    // Use reflection to access protected property.
    $reflection = new \ReflectionObject($screenshot_context);
    $info_property = $reflection->getProperty('info');
    $info_property->setAccessible(TRUE);
    $info = $info_property->getValue($screenshot_context);
    $this->assertIsArray($info);

    $this->assertArrayHasKey('Current URL', $info);
    $this->assertEquals('not available', $info['Current URL']);
  }

  /**
   * Test iSaveScreenshot with UnsupportedDriverActionException.
   */
  public function testSaveScreenshotUnsupportedDriver(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getSession',
      'makeFileName',
      'saveScreenshotContent',
      'renderInfo',
    ]);

    $session = $this->createMock(Session::class);
    $driver = $this->createMock(DriverInterface::class);

    // Setup driver to return content but throw exception on screenshot.
    $driver->method('getContent')->willReturn('test-content');
    $driver->method('getScreenshot')->willThrowException(
      new UnsupportedDriverActionException('Not supported', $driver)
    );

    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('makeFileName')->willReturn('test-file-name');
    $screenshot_context->method('renderInfo')->willReturn('');

    // Expect saveScreenshotContent to be called exactly once (for HTML only)
    $screenshot_context->expects($this->exactly(1))->method('saveScreenshotContent');

    $screenshot_context->screenshot();
  }

  /**
   * Test getCurrentTime method.
   */
  public function testGetCurrentTime(): void {
    $screenshot_context = new ScreenshotContext();
    $reflection = new \ReflectionObject($screenshot_context);
    $method = $reflection->getMethod('getCurrentTime');
    $method->setAccessible(TRUE);

    $time = $method->invoke($screenshot_context);
    $this->assertIsInt($time);
    $this->assertGreaterThan(0, $time);
  }

  /**
   * Test makeFileName with BEHAT_SCREENSHOT_TOKEN_HOST environment variable.
   */
  public function testMakeFileNameWithHostReplacement(): void {
    // Store original env value if it exists.
    $original_value = getenv('BEHAT_SCREENSHOT_TOKEN_HOST');

    try {
      // Set environment variable.
      putenv('BEHAT_SCREENSHOT_TOKEN_HOST=example.org');

      // Setup mocks.
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
        'stitch',
        '{url}.{ext}',
        '{failed_prefix}{url}.{ext}',
        []
      );

      // Access protected method.
      $reflection = new \ReflectionObject($screenshot_context);
      $method = $reflection->getMethod('makeFileName');
      $method->setAccessible(TRUE);

      $result = $method->invokeArgs($screenshot_context, ['png', NULL, FALSE]);
      $this->assertIsString($result);

      // The Tokenizer replaces non-alphanumeric characters with underscores.
      $this->assertStringContainsString('example_org', $result);
      $this->assertStringNotContainsString('localhost', $result);
    }
    finally {
      // Restore original env value.
      if ($original_value !== FALSE) {
        putenv('BEHAT_SCREENSHOT_TOKEN_HOST=' . $original_value);
      }
      else {
        putenv('BEHAT_SCREENSHOT_TOKEN_HOST');
      }
    }
  }

  /**
   * Data provider for testCompileInfo.
   */
  public static function compileInfoDataProvider(): array {
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

}
