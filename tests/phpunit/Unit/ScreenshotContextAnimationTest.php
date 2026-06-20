<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\StepNode;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Tester\Result\TestResult;
use DrevOps\BehatScreenshot\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\AnimatedGif;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext animated GIF behaviour.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextAnimationTest extends TestCase {

  use ReflectionTrait;

  #[DataProvider('dataProviderBeforeScenarioCheckScreenshotsTag')]
  public function testBeforeScenarioCheckScreenshotsTag(array $scenario_tags, array $feature_tags, array $animation, bool $expected_screenshots, bool $expected_animated): void {
    $environment = $this->createMock(Environment::class);
    $feature = $this->createMock(FeatureNode::class);
    $feature->method('hasTag')->willReturnCallback(static fn(string $tag): bool => in_array($tag, $feature_tags, TRUE));
    $scenario = $this->createMock(ScenarioInterface::class);
    $scenario->method('hasTag')->willReturnCallback(static fn(string $tag): bool => in_array($tag, $scenario_tags, TRUE));

    $context = new ScreenshotContext();
    $context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], $animation);
    self::setProtectedValue($context, 'animationFrames', ['stale-frame']);

    $context->beforeScenarioCheckScreenshotsTag(new BeforeScenarioScope($environment, $feature, $scenario));

    $this->assertSame($expected_screenshots, $this->getProtectedProperty($context, 'scenarioHasScreenshotsTag'));
    $this->assertSame($expected_animated, $this->getProtectedProperty($context, 'scenarioIsAnimated'));
    $this->assertSame([], $this->getProtectedProperty($context, 'animationFrames'));
  }

  public static function dataProviderBeforeScenarioCheckScreenshotsTag(): array {
    return [
      'no tags, no config' => [[], [], [], FALSE, FALSE],
      'scenario screenshots tag' => [['screenshots'], [], [], TRUE, FALSE],
      'feature screenshots tag' => [[], ['screenshots'], [], TRUE, FALSE],
      'scenario animated tag' => [['screenshots:animated'], [], [], FALSE, TRUE],
      'feature animated tag' => [[], ['screenshots:animated'], [], FALSE, TRUE],
      'animation enabled via config' => [[], [], ['enabled' => TRUE], FALSE, TRUE],
      'animation disabled via config' => [[], [], ['enabled' => FALSE], FALSE, FALSE],
    ];
  }

  public function testCaptureScreenshotAfterStepCollectsAnimationFrame(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $context->expects($this->once())->method('screenshot');
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'lastScreenshotData', 'png-bytes');

    $context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));

    $this->assertSame(['png-bytes'], $this->getProtectedProperty($context, 'animationFrames'));
  }

  public function testCaptureScreenshotAfterStepDoesNotCollectWhenNotAnimated(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $context->expects($this->once())->method('screenshot');
    self::setProtectedValue($context, 'scenarioHasScreenshotsTag', TRUE);
    self::setProtectedValue($context, 'scenarioIsAnimated', FALSE);
    self::setProtectedValue($context, 'lastScreenshotData', 'png-bytes');

    $context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));

    $this->assertSame([], $this->getProtectedProperty($context, 'animationFrames'));
  }

  public function testCaptureScreenshotAfterStepSkipsFailedStep(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['screenshot']);
    $context->expects($this->never())->method('screenshot');
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);

    $context->captureScreenshotAfterStep($this->createAfterStepScope(FALSE));

    $this->assertSame([], $this->getProtectedProperty($context, 'animationFrames'));
  }

  public function testAfterScenarioAnimateEncodesAndSaves(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->expects($this->once())->method('encode')->with(['frame-1', 'frame-2'], 250)->willReturn('gif-data');

    $context = $this->createPartialMock(ScreenshotContext::class, ['isAnimatedGifSupported', 'getAnimatedGif', 'makeAnimationFileName', 'saveScreenshotContent']);
    $context->method('isAnimatedGifSupported')->willReturn(TRUE);
    $context->method('getAnimatedGif')->willReturn($encoder);
    $context->method('makeAnimationFileName')->willReturn('animation.gif');
    $context->expects($this->once())->method('saveScreenshotContent')->with('animation.gif', 'gif-data');

    $context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], ['enabled' => TRUE, 'frame_delay' => 250]);
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'animationFrames', ['frame-1', 'frame-2']);

    $context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertSame([], $this->getProtectedProperty($context, 'animationFrames'));
  }

  public function testAfterScenarioAnimateUsesDefaultDelay(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->expects($this->once())->method('encode')->with(['frame-1'], 500)->willReturn('gif-data');

    $context = $this->createPartialMock(ScreenshotContext::class, ['isAnimatedGifSupported', 'getAnimatedGif', 'makeAnimationFileName', 'saveScreenshotContent']);
    $context->method('isAnimatedGifSupported')->willReturn(TRUE);
    $context->method('getAnimatedGif')->willReturn($encoder);
    $context->method('makeAnimationFileName')->willReturn('animation.gif');
    $context->expects($this->once())->method('saveScreenshotContent')->with('animation.gif', 'gif-data');

    $context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], []);
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'animationFrames', ['frame-1']);

    $context->afterScenarioAnimate($this->createAfterScenarioScope());
  }

  public function testAfterScenarioAnimateSkipsWhenNotAnimated(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['getAnimatedGif', 'saveScreenshotContent']);
    $context->expects($this->never())->method('getAnimatedGif');
    $context->expects($this->never())->method('saveScreenshotContent');
    self::setProtectedValue($context, 'scenarioIsAnimated', FALSE);
    self::setProtectedValue($context, 'animationFrames', ['frame-1']);

    $context->afterScenarioAnimate($this->createAfterScenarioScope());
  }

  public function testAfterScenarioAnimateSkipsWhenNoFrames(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['getAnimatedGif', 'saveScreenshotContent']);
    $context->expects($this->never())->method('getAnimatedGif');
    $context->expects($this->never())->method('saveScreenshotContent');
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'animationFrames', []);

    $context->afterScenarioAnimate($this->createAfterScenarioScope());
  }

  public function testAfterScenarioAnimateSkipsWhenUnsupported(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['isAnimatedGifSupported', 'getAnimatedGif', 'saveScreenshotContent']);
    $context->method('isAnimatedGifSupported')->willReturn(FALSE);
    $context->expects($this->never())->method('getAnimatedGif');
    $context->expects($this->never())->method('saveScreenshotContent');
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'animationFrames', ['frame-1']);

    $context->afterScenarioAnimate($this->createAfterScenarioScope());
  }

  public function testMakeAnimationFileName(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['getCurrentTime']);
    $context->method('getCurrentTime')->willReturn(1700000000);

    $scope = $this->createAfterScenarioScope('path/to/login.feature', 7);
    $result = self::callProtectedMethod($context, 'makeAnimationFileName', [$scope]);

    $this->assertSame('1700000000.login.feature_7.gif', $result);
  }

  public function testIsAnimatedGifSupported(): void {
    $this->assertTrue(self::callProtectedMethod(new ScreenshotContext(), 'isAnimatedGifSupported'));
  }

  public function testGetAnimatedGif(): void {
    $this->assertInstanceOf(AnimatedGif::class, self::callProtectedMethod(new ScreenshotContext(), 'getAnimatedGif'));
  }

  /**
   * Read a protected property value from an object.
   *
   * @param object $object
   *   Object to read from.
   * @param string $property
   *   Property name.
   *
   * @return mixed
   *   Property value.
   */
  protected function getProtectedProperty(object $object, string $property): mixed {
    $reflection = new \ReflectionProperty($object, $property);
    $reflection->setAccessible(TRUE);

    return $reflection->getValue($object);
  }

  /**
   * Create an after step scope with the given result state.
   *
   * @param bool $passed
   *   Whether the step passed.
   *
   * @return \Behat\Behat\Hook\Scope\AfterStepScope
   *   After step scope.
   */
  protected function createAfterStepScope(bool $passed): AfterStepScope {
    $result = $this->createMock(StepResult::class);
    $result->method('isPassed')->willReturn($passed);

    return new AfterStepScope($this->createMock(Environment::class), $this->createMock(FeatureNode::class), $this->createMock(StepNode::class), $result);
  }

  /**
   * Create an after scenario scope.
   *
   * @param string|null $feature_file
   *   Feature file path.
   * @param int $scenario_line
   *   Scenario line number.
   *
   * @return \Behat\Behat\Hook\Scope\AfterScenarioScope
   *   After scenario scope.
   */
  protected function createAfterScenarioScope(?string $feature_file = NULL, int $scenario_line = 0): AfterScenarioScope {
    $feature = $this->createMock(FeatureNode::class);
    $feature->method('getFile')->willReturn($feature_file);
    $scenario = $this->createMock(ScenarioInterface::class);
    $scenario->method('getLine')->willReturn($scenario_line);

    return new AfterScenarioScope($this->createMock(Environment::class), $feature, $scenario, $this->createMock(TestResult::class));
  }

}
