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
    self::setProtectedValue($context, 'animationEncoder', new AnimatedGif());

    $context->beforeScenarioCheckScreenshotsTag(new BeforeScenarioScope($environment, $feature, $scenario));

    $this->assertSame($expected_screenshots, $this->getProtectedProperty($context, 'scenarioHasScreenshotsTag'));
    $this->assertSame($expected_animated, $this->getProtectedProperty($context, 'scenarioIsAnimated'));
    $this->assertNull($this->getProtectedProperty($context, 'animationEncoder'));
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
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->expects($this->once())->method('addFrame')->with('png-bytes');

    $context = $this->createPartialMock(ScreenshotContext::class, ['screenshot', 'isAnimatedGifSupported', 'getAnimatedGif']);
    $context->expects($this->once())->method('screenshot');
    $context->method('isAnimatedGifSupported')->willReturn(TRUE);
    $context->expects($this->once())->method('getAnimatedGif')->willReturn($encoder);
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'lastScreenshotData', 'png-bytes');

    $context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));

    $this->assertSame($encoder, $this->getProtectedProperty($context, 'animationEncoder'));
  }

  public function testCaptureScreenshotAfterStepReusesEncoderAcrossSteps(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->expects($this->exactly(2))->method('addFrame')->with('png-bytes');

    $context = $this->createPartialMock(ScreenshotContext::class, ['screenshot', 'isAnimatedGifSupported', 'getAnimatedGif']);
    $context->method('isAnimatedGifSupported')->willReturn(TRUE);
    $context->expects($this->once())->method('getAnimatedGif')->willReturn($encoder);
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'lastScreenshotData', 'png-bytes');

    $context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));
    $context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));
  }

  public function testCaptureScreenshotAfterStepSkipsFrameWhenUnsupported(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['screenshot', 'isAnimatedGifSupported', 'getAnimatedGif']);
    $context->expects($this->once())->method('screenshot');
    $context->method('isAnimatedGifSupported')->willReturn(FALSE);
    $context->expects($this->never())->method('getAnimatedGif');
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'lastScreenshotData', 'png-bytes');

    $context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));

    $this->assertNull($this->getProtectedProperty($context, 'animationEncoder'));
  }

  public function testCaptureScreenshotAfterStepDoesNotCollectWhenNotAnimated(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['screenshot', 'getAnimatedGif']);
    $context->expects($this->once())->method('screenshot');
    $context->expects($this->never())->method('getAnimatedGif');
    self::setProtectedValue($context, 'scenarioHasScreenshotsTag', TRUE);
    self::setProtectedValue($context, 'scenarioIsAnimated', FALSE);
    self::setProtectedValue($context, 'lastScreenshotData', 'png-bytes');

    $context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));

    $this->assertNull($this->getProtectedProperty($context, 'animationEncoder'));
  }

  public function testCaptureScreenshotAfterStepSkipsFailedStep(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['screenshot', 'getAnimatedGif']);
    $context->expects($this->never())->method('screenshot');
    $context->expects($this->never())->method('getAnimatedGif');
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);

    $context->captureScreenshotAfterStep($this->createAfterStepScope(FALSE));

    $this->assertNull($this->getProtectedProperty($context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateRendersAndSaves(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->method('count')->willReturn(2);
    $encoder->expects($this->once())->method('render')->with(250)->willReturn('gif-data');

    $context = $this->createPartialMock(ScreenshotContext::class, ['makeAnimationFileName', 'saveScreenshotContent']);
    $context->method('makeAnimationFileName')->willReturn('animation.gif');
    $context->expects($this->once())->method('saveScreenshotContent')->with('animation.gif', 'gif-data');

    $context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], ['enabled' => TRUE, 'frame_delay' => 250]);
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'animationEncoder', $encoder);

    $context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull($this->getProtectedProperty($context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateUsesDefaultDelay(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->method('count')->willReturn(1);
    $encoder->expects($this->once())->method('render')->with(500)->willReturn('gif-data');

    $context = $this->createPartialMock(ScreenshotContext::class, ['makeAnimationFileName', 'saveScreenshotContent']);
    $context->method('makeAnimationFileName')->willReturn('animation.gif');
    $context->expects($this->once())->method('saveScreenshotContent')->with('animation.gif', 'gif-data');

    $context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], []);
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'animationEncoder', $encoder);

    $context->afterScenarioAnimate($this->createAfterScenarioScope());
  }

  public function testAfterScenarioAnimateSkipsWhenNotAnimated(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->expects($this->never())->method('render');

    $context = $this->createPartialMock(ScreenshotContext::class, ['saveScreenshotContent']);
    $context->expects($this->never())->method('saveScreenshotContent');
    self::setProtectedValue($context, 'scenarioIsAnimated', FALSE);
    self::setProtectedValue($context, 'animationEncoder', $encoder);

    $context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull($this->getProtectedProperty($context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateSkipsWhenNoEncoder(): void {
    $context = $this->createPartialMock(ScreenshotContext::class, ['saveScreenshotContent']);
    $context->expects($this->never())->method('saveScreenshotContent');
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);

    $context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull($this->getProtectedProperty($context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateSkipsWhenNoFrames(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->method('count')->willReturn(0);
    $encoder->expects($this->never())->method('render');

    $context = $this->createPartialMock(ScreenshotContext::class, ['saveScreenshotContent']);
    $context->expects($this->never())->method('saveScreenshotContent');
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'animationEncoder', $encoder);

    $context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull($this->getProtectedProperty($context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateReleasesEncoderWhenRenderFails(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->method('count')->willReturn(1);
    $encoder->method('render')->willThrowException(new \RuntimeException('render failed'));

    $context = $this->createPartialMock(ScreenshotContext::class, ['makeAnimationFileName', 'saveScreenshotContent']);
    $context->expects($this->never())->method('saveScreenshotContent');

    $context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], ['enabled' => TRUE]);
    self::setProtectedValue($context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($context, 'animationEncoder', $encoder);

    $thrown = NULL;

    try {
      $context->afterScenarioAnimate($this->createAfterScenarioScope());
    }
    catch (\Exception $exception) {
      $thrown = $exception;
    }

    $this->assertInstanceOf(\RuntimeException::class, $thrown);
    $this->assertSame('render failed', $thrown->getMessage());
    $this->assertNull($this->getProtectedProperty($context, 'animationEncoder'));
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

  #[DataProvider('dataProviderGetAnimatedGif')]
  public function testGetAnimatedGif(array $animation, int $expected_max_width, int $expected_max_height): void {
    $context = new ScreenshotContext();
    $context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], $animation);

    $encoder = self::callProtectedMethod($context, 'getAnimatedGif');

    $this->assertInstanceOf(AnimatedGif::class, $encoder);
    $this->assertSame($expected_max_width, $this->getProtectedProperty($encoder, 'maxWidth'));
    $this->assertSame($expected_max_height, $this->getProtectedProperty($encoder, 'maxHeight'));
  }

  public static function dataProviderGetAnimatedGif(): array {
    return [
      'no settings' => [[], 0, 0],
      'both caps set' => [['max_width' => 800, 'max_height' => 2000], 800, 2000],
      'width cap only' => [['max_width' => 640], 640, 0],
      'height cap only' => [['max_height' => 480], 0, 480],
      'numeric strings' => [['max_width' => '640', 'max_height' => '480'], 640, 480],
      'non-numeric values' => [['max_width' => 'wide', 'max_height' => NULL], 0, 0],
    ];
  }

  #[DataProvider('dataProviderAnimationSetting')]
  public function testAnimationSetting(array $animation, string $name, int $default, int $expected): void {
    $context = new ScreenshotContext();
    $context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], $animation);

    $this->assertSame($expected, self::callProtectedMethod($context, 'animationSetting', [$name, $default]));
  }

  public static function dataProviderAnimationSetting(): array {
    return [
      'absent setting' => [[], 'frame_delay', 500, 500],
      'integer setting' => [['frame_delay' => 250], 'frame_delay', 500, 250],
      'numeric string setting' => [['frame_delay' => '120'], 'frame_delay', 500, 120],
      'float setting' => [['frame_delay' => 120.9], 'frame_delay', 500, 120],
      'non-numeric setting' => [['frame_delay' => 'fast'], 'frame_delay', 500, 500],
      'null setting' => [['frame_delay' => NULL], 'frame_delay', 500, 500],
    ];
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
