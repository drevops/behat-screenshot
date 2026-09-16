<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
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
    $env = $this->createStub(Environment::class);
    $feature_node = $this->createStub(FeatureNode::class);
    $step_node = $this->createStub(StepNode::class);
    $scope = new BeforeStepScope($env, $feature_node, $step_node);

    $session = $this->createStub(Session::class);
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

}
