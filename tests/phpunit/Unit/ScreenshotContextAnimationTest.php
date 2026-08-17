<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatScreenshot\Tests\Traits\BehatScopeTrait;
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

  use BehatScopeTrait;
  use ReflectionTrait;

  #[DataProvider('dataProviderBeforeScenarioCheckScreenshotsTagSetsFlagsFromTagsAndConfig')]
  public function testBeforeScenarioCheckScreenshotsTagSetsFlagsFromTagsAndConfig(array $scenario_tags, array $feature_tags, array $animation, bool $expected_screenshots, bool $expected_animated): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $feature_node->method('hasTag')->willReturnCallback(static fn(string $tag): bool => in_array($tag, $feature_tags, TRUE));
    $scenario = $this->createMock(ScenarioInterface::class);
    $scenario->method('hasTag')->willReturnCallback(static fn(string $tag): bool => in_array($tag, $scenario_tags, TRUE));

    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], $animation);
    self::setProtectedValue($screenshot_context, 'animationEncoder', new AnimatedGif());

    $screenshot_context->beforeScenarioCheckScreenshotsTag(new BeforeScenarioScope($env, $feature_node, $scenario));

    $this->assertSame($expected_screenshots, self::getProtectedValue($screenshot_context, 'scenarioHasScreenshotsTag'));
    $this->assertSame($expected_animated, self::getProtectedValue($screenshot_context, 'scenarioIsAnimated'));
    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public static function dataProviderBeforeScenarioCheckScreenshotsTagSetsFlagsFromTagsAndConfig(): array {
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

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot', 'isAnimatedGifSupported', 'getAnimatedGif']);
    $screenshot_context->expects($this->once())->method('screenshot');
    $screenshot_context->method('isAnimatedGifSupported')->willReturn(TRUE);
    $screenshot_context->expects($this->once())->method('getAnimatedGif')->willReturn($encoder);
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotData', 'png-bytes');

    $screenshot_context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));

    $this->assertSame($encoder, self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testCaptureScreenshotAfterStepReusesEncoderAcrossSteps(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->expects($this->exactly(2))->method('addFrame')->with('png-bytes');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot', 'isAnimatedGifSupported', 'getAnimatedGif']);
    $screenshot_context->method('isAnimatedGifSupported')->willReturn(TRUE);
    $screenshot_context->expects($this->once())->method('getAnimatedGif')->willReturn($encoder);
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotData', 'png-bytes');

    $screenshot_context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));
    $screenshot_context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));
  }

  public function testCaptureScreenshotAfterStepSkipsFrameWhenUnsupported(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot', 'isAnimatedGifSupported', 'getAnimatedGif']);
    $screenshot_context->expects($this->once())->method('screenshot');
    $screenshot_context->method('isAnimatedGifSupported')->willReturn(FALSE);
    $screenshot_context->expects($this->never())->method('getAnimatedGif');
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotData', 'png-bytes');

    $screenshot_context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testCaptureScreenshotAfterStepDoesNotCollectWhenNotAnimated(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot', 'getAnimatedGif']);
    $screenshot_context->expects($this->once())->method('screenshot');
    $screenshot_context->expects($this->never())->method('getAnimatedGif');
    self::setProtectedValue($screenshot_context, 'scenarioHasScreenshotsTag', TRUE);
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', FALSE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotData', 'png-bytes');

    $screenshot_context->captureScreenshotAfterStep($this->createAfterStepScope(TRUE));

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testCaptureScreenshotAfterStepSkipsFailedStep(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['screenshot', 'getAnimatedGif']);
    $screenshot_context->expects($this->never())->method('screenshot');
    $screenshot_context->expects($this->never())->method('getAnimatedGif');
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);

    $screenshot_context->captureScreenshotAfterStep($this->createAfterStepScope(FALSE));

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateRendersAndSaves(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->method('count')->willReturn(2);
    $encoder->expects($this->once())->method('render')->with(250)->willReturn('gif-data');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['makeAnimationFileName', 'saveScreenshotContent']);
    $screenshot_context->method('makeAnimationFileName')->willReturn('animation.gif');
    $screenshot_context->expects($this->once())->method('saveScreenshotContent')->with('animation.gif', 'gif-data');

    $screenshot_context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], ['enabled' => TRUE, 'frame_delay' => 250]);
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'animationEncoder', $encoder);

    $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateUsesDefaultDelay(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->method('count')->willReturn(1);
    $encoder->expects($this->once())->method('render')->with(500)->willReturn('gif-data');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['makeAnimationFileName', 'saveScreenshotContent']);
    $screenshot_context->method('makeAnimationFileName')->willReturn('animation.gif');
    $screenshot_context->expects($this->once())->method('saveScreenshotContent')->with('animation.gif', 'gif-data');

    $screenshot_context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], []);
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'animationEncoder', $encoder);

    $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());
  }

  public function testAfterScenarioAnimateSkipsWhenNotAnimated(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->expects($this->never())->method('render');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['saveScreenshotContent']);
    $screenshot_context->expects($this->never())->method('saveScreenshotContent');
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', FALSE);
    self::setProtectedValue($screenshot_context, 'animationEncoder', $encoder);

    $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateSkipsWhenNoEncoder(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['saveScreenshotContent']);
    $screenshot_context->expects($this->never())->method('saveScreenshotContent');
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);

    $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateSkipsWhenNoFrames(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->method('count')->willReturn(0);
    $encoder->expects($this->never())->method('render');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['saveScreenshotContent']);
    $screenshot_context->expects($this->never())->method('saveScreenshotContent');
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'animationEncoder', $encoder);

    $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateReleasesEncoderWhenRenderFails(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->method('count')->willReturn(1);
    $encoder->method('render')->willThrowException(new \RuntimeException('render failed'));

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['makeAnimationFileName', 'saveScreenshotContent']);
    $screenshot_context->expects($this->never())->method('saveScreenshotContent');

    $screenshot_context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], ['enabled' => TRUE]);
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'animationEncoder', $encoder);

    $thrown = NULL;

    try {
      $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());
    }
    catch (\Exception $exception) {
      $thrown = $exception;
    }

    $this->assertInstanceOf(\RuntimeException::class, $thrown);
    $this->assertSame('render failed', $thrown->getMessage());
    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testMakeAnimationFileNameCombinesTimestampFeatureAndLine(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getCurrentTime']);
    $screenshot_context->method('getCurrentTime')->willReturn(1700000000);

    $scope = $this->createAfterScenarioScope('path/to/login.feature', 7);
    $result = self::callProtectedMethod($screenshot_context, 'makeAnimationFileName', [$scope]);

    $this->assertSame('1700000000.login.feature_7.gif', $result);
  }

  public function testIsAnimatedGifSupportedReturnsTrueWhenGdIsAvailable(): void {
    $this->assertTrue(self::callProtectedMethod(new ScreenshotContext(), 'isAnimatedGifSupported'));
  }

  #[DataProvider('dataProviderGetAnimatedGifCreatesEncoderWithSizeCapsFromSettings')]
  public function testGetAnimatedGifCreatesEncoderWithSizeCapsFromSettings(array $animation, int $expected_max_width, int $expected_max_height): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], $animation);

    $encoder = self::callProtectedMethod($screenshot_context, 'getAnimatedGif');

    $this->assertInstanceOf(AnimatedGif::class, $encoder);
    $this->assertSame($expected_max_width, self::getProtectedValue($encoder, 'maxWidth'));
    $this->assertSame($expected_max_height, self::getProtectedValue($encoder, 'maxHeight'));
  }

  public static function dataProviderGetAnimatedGifCreatesEncoderWithSizeCapsFromSettings(): array {
    return [
      'no settings' => [[], 0, 0],
      'both caps set' => [['max_width' => 800, 'max_height' => 2000], 800, 2000],
      'width cap only' => [['max_width' => 640], 640, 0],
      'height cap only' => [['max_height' => 480], 0, 480],
      'numeric strings' => [['max_width' => '640', 'max_height' => '480'], 640, 480],
      'non-numeric values' => [['max_width' => 'wide', 'max_height' => NULL], 0, 0],
    ];
  }

  #[DataProvider('dataProviderAnimationSettingReturnsIntegerValueOrDefault')]
  public function testAnimationSettingReturnsIntegerValueOrDefault(array $animation, string $name, int $default, int $expected): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotParameters('test-dir', TRUE, 'failed_', FALSE, FALSE, '{ext}', '{ext}', [], $animation);

    $this->assertSame($expected, self::callProtectedMethod($screenshot_context, 'animationSetting', [$name, $default]));
  }

  public static function dataProviderAnimationSettingReturnsIntegerValueOrDefault(): array {
    return [
      'absent setting' => [[], 'frame_delay', 500, 500],
      'integer setting' => [['frame_delay' => 250], 'frame_delay', 500, 250],
      'numeric string setting' => [['frame_delay' => '120'], 'frame_delay', 500, 120],
      'float setting' => [['frame_delay' => 120.9], 'frame_delay', 500, 120],
      'non-numeric setting' => [['frame_delay' => 'fast'], 'frame_delay', 500, 500],
      'null setting' => [['frame_delay' => NULL], 'frame_delay', 500, 500],
    ];
  }

}
