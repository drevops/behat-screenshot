<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use DrevOps\BehatScreenshotExtension\AnimatedGifEncoder;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use DrevOps\BehatScreenshotExtension\Tests\Traits\BehatScopeTrait;
use DrevOps\BehatScreenshotExtension\Tests\Traits\EnvironmentVariableTrait;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ScreenshotConfigTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext animated GIF behaviour.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextAnimationTest extends TestCase {

  use BehatScopeTrait;
  use EnvironmentVariableTrait;
  use ReflectionTrait;
  use ScreenshotConfigTrait;

  #[DataProvider('dataProviderBeforeScenarioCheckScreenshotsTagSetsFlagsFromTagsAndConfig')]
  public function testBeforeScenarioCheckScreenshotsTagSetsFlagsFromTagsAndConfig(array $scenario_tags, array $feature_tags, array $animation, bool $expected_screenshots, bool $expected_animated): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['animation' => $animation]));
    self::setProtectedValue($screenshot_context, 'animationEncoder', new AnimatedGifEncoder());

    $screenshot_context->beforeScenarioCheckScreenshotsTag($this->createBeforeScenarioScope($scenario_tags, $feature_tags));

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
      'feature animated tag over disabled config' => [[], ['screenshots:animated'], ['enabled' => FALSE], FALSE, TRUE],
      'scenario skip tag over enabled config' => [['screenshots:animated:skip'], [], ['enabled' => TRUE], FALSE, FALSE],
      'feature skip tag over enabled config' => [[], ['screenshots:animated:skip'], ['enabled' => TRUE], FALSE, FALSE],
      'scenario skip tag with disabled config' => [['screenshots:animated:skip'], [], ['enabled' => FALSE], FALSE, FALSE],
      'scenario skip tag over scenario animated tag' => [['screenshots:animated', 'screenshots:animated:skip'], [], [], FALSE, FALSE],
      'feature skip tag over feature animated tag' => [[], ['screenshots:animated', 'screenshots:animated:skip'], [], FALSE, FALSE],
      'scenario animated tag over feature skip tag' => [['screenshots:animated'], ['screenshots:animated:skip'], ['enabled' => FALSE], FALSE, TRUE],
      'scenario skip tag over feature animated tag' => [['screenshots:animated:skip'], ['screenshots:animated'], ['enabled' => TRUE], FALSE, FALSE],
      'scenario screenshots tag with prefix' => [['@screenshots'], [], [], TRUE, FALSE],
      'feature screenshots tag with prefix' => [[], ['@screenshots'], [], TRUE, FALSE],
      'scenario animated tag with prefix' => [['@screenshots:animated'], [], [], FALSE, TRUE],
      'feature animated tag with prefix' => [[], ['@screenshots:animated'], [], FALSE, TRUE],
      'scenario skip tag with prefix over enabled config' => [['@screenshots:animated:skip'], [], ['enabled' => TRUE], FALSE, FALSE],
      'feature skip tag with prefix over feature animated tag with prefix' => [[], ['@screenshots:animated', '@screenshots:animated:skip'], [], FALSE, FALSE],
      'scenario animated tag with prefix over feature skip tag with prefix' => [['@screenshots:animated'], ['@screenshots:animated:skip'], ['enabled' => FALSE], FALSE, TRUE],
      'screenshots tag with prefix among other tags' => [['@smoke', '@screenshots'], ['@api'], [], TRUE, FALSE],
      'tags that only contain a screenshot tag name' => [['@screenshots-extra', 'my-screenshots:animated'], ['@no-screenshots:animated:skip'], [], FALSE, FALSE],
    ];
  }

  #[DataProvider('dataProviderBeforeScenarioCheckScreenshotsTagHonoursSuiteEnvironmentVariable')]
  public function testBeforeScenarioCheckScreenshotsTagHonoursSuiteEnvironmentVariable(?string $env_value, array $scenario_tags, array $feature_tags, array $animation, bool $expected_animated): void {
    $this->setEnvironmentVariable(ScreenshotContext::ENV_ANIMATION_SKIP, $env_value);

    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['animation' => $animation]));

    $screenshot_context->beforeScenarioCheckScreenshotsTag($this->createBeforeScenarioScope($scenario_tags, $feature_tags));

    $this->assertSame($expected_animated, self::getProtectedValue($screenshot_context, 'scenarioIsAnimated'));
  }

  public static function dataProviderBeforeScenarioCheckScreenshotsTagHonoursSuiteEnvironmentVariable(): array {
    return [
      'variable unset with config enabled' => [NULL, [], [], ['enabled' => TRUE], TRUE],
      'variable set with config enabled' => ['1', [], [], ['enabled' => TRUE], FALSE],
      'variable set with config disabled' => ['1', [], [], ['enabled' => FALSE], FALSE],
      'variable set over scenario animated tag' => ['1', ['screenshots:animated'], [], [], FALSE],
      'variable set over feature animated tag' => ['1', [], ['screenshots:animated'], [], FALSE],
      'variable set over scenario animated tag with prefix' => ['1', ['@screenshots:animated'], [], [], FALSE],
      'variable empty with config enabled' => ['', [], [], ['enabled' => TRUE], TRUE],
      'variable zero with config enabled' => ['0', [], [], ['enabled' => TRUE], TRUE],
      'variable zero with scenario animated tag' => ['0', ['screenshots:animated'], [], [], TRUE],
    ];
  }

  public function testAfterStepCaptureScreenshotCollectsAnimationFrame(): void {
    $encoder = $this->createMock(AnimatedGifEncoder::class);
    $encoder->expects($this->once())->method('addFrame')->with('png-bytes');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot', 'isAnimatedGifSupported', 'createAnimatedGifEncoder']);
    $screenshot_context->expects($this->once())->method('captureScreenshot');
    $screenshot_context->method('isAnimatedGifSupported')->willReturn(TRUE);
    $screenshot_context->expects($this->once())->method('createAnimatedGifEncoder')->willReturn($encoder);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotContent', 'png-bytes');

    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(TRUE));

    $this->assertSame($encoder, self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterStepCaptureScreenshotReusesEncoderAcrossSteps(): void {
    $encoder = $this->createMock(AnimatedGifEncoder::class);
    $encoder->expects($this->exactly(2))->method('addFrame')->with('png-bytes');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot', 'isAnimatedGifSupported', 'createAnimatedGifEncoder']);
    $screenshot_context->method('isAnimatedGifSupported')->willReturn(TRUE);
    $screenshot_context->expects($this->once())->method('createAnimatedGifEncoder')->willReturn($encoder);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotContent', 'png-bytes');

    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(TRUE));
    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(TRUE));
  }

  public function testAfterStepCaptureScreenshotSkipsFrameWhenUnsupported(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot', 'isAnimatedGifSupported', 'createAnimatedGifEncoder']);
    $screenshot_context->expects($this->once())->method('captureScreenshot');
    $screenshot_context->method('isAnimatedGifSupported')->willReturn(FALSE);
    $screenshot_context->expects($this->never())->method('createAnimatedGifEncoder');
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotContent', 'png-bytes');

    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(TRUE));

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterStepCaptureScreenshotDoesNotCollectWhenNotAnimated(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot', 'createAnimatedGifEncoder']);
    $screenshot_context->expects($this->once())->method('captureScreenshot');
    $screenshot_context->expects($this->never())->method('createAnimatedGifEncoder');
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioHasScreenshotsTag', TRUE);
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', FALSE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotContent', 'png-bytes');

    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(TRUE));

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterStepCaptureScreenshotSkipsFailedStep(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot', 'createAnimatedGifEncoder']);
    $screenshot_context->expects($this->never())->method('captureScreenshot');
    $screenshot_context->expects($this->never())->method('createAnimatedGifEncoder');
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);

    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(FALSE));

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateRendersAndSaves(): void {
    $encoder = $this->createMock(AnimatedGifEncoder::class);
    $encoder->method('count')->willReturn(2);
    $encoder->expects($this->once())->method('render')->with(250)->willReturn('gif-content');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['makeAnimationFilename', 'writeScreenshotContent']);
    $screenshot_context->method('makeAnimationFilename')->willReturn('animation.gif');
    $screenshot_context->expects($this->once())->method('writeScreenshotContent')->with('animation.gif', 'gif-content');

    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['animation' => ['enabled' => TRUE, 'frame_delay' => 250]]));
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'animationEncoder', $encoder);

    $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateUsesDefaultDelay(): void {
    $encoder = $this->createMock(AnimatedGifEncoder::class);
    $encoder->method('count')->willReturn(1);
    $encoder->expects($this->once())->method('render')->with(500)->willReturn('gif-content');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['makeAnimationFilename', 'writeScreenshotContent']);
    $screenshot_context->method('makeAnimationFilename')->willReturn('animation.gif');
    $screenshot_context->expects($this->once())->method('writeScreenshotContent')->with('animation.gif', 'gif-content');

    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'animationEncoder', $encoder);

    $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());
  }

  public function testAfterScenarioAnimateSkipsWhenNotAnimated(): void {
    $encoder = $this->createMock(AnimatedGifEncoder::class);
    $encoder->expects($this->never())->method('render');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['writeScreenshotContent']);
    $screenshot_context->expects($this->never())->method('writeScreenshotContent');
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', FALSE);
    self::setProtectedValue($screenshot_context, 'animationEncoder', $encoder);

    $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateSkipsWhenNoEncoder(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['writeScreenshotContent']);
    $screenshot_context->expects($this->never())->method('writeScreenshotContent');
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);

    $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateSkipsWhenNoFrames(): void {
    $encoder = $this->createMock(AnimatedGifEncoder::class);
    $encoder->method('count')->willReturn(0);
    $encoder->expects($this->never())->method('render');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['writeScreenshotContent']);
    $screenshot_context->expects($this->never())->method('writeScreenshotContent');
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'animationEncoder', $encoder);

    $screenshot_context->afterScenarioAnimate($this->createAfterScenarioScope());

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateReleasesEncoderWhenRenderFails(): void {
    $encoder = $this->createMock(AnimatedGifEncoder::class);
    $encoder->method('count')->willReturn(1);
    $encoder->method('render')->willThrowException(new \RuntimeException('render failed'));

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['makeAnimationFilename', 'writeScreenshotContent']);
    $screenshot_context->expects($this->never())->method('writeScreenshotContent');

    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['animation' => ['enabled' => TRUE]]));
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

  public function testMakeAnimationFilenameCombinesTimestampFeatureAndLine(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getCurrentTime']);
    $screenshot_context->method('getCurrentTime')->willReturn(1700000000);

    $scope = $this->createAfterScenarioScope('path/to/login.feature', 7);
    $result = self::callProtectedMethod($screenshot_context, 'makeAnimationFilename', [$scope]);

    $this->assertSame('1700000000.login.feature_7.gif', $result);
  }

  #[RequiresFunction('imagecreatefromstring')]
  #[RequiresFunction('imagegif')]
  public function testIsAnimatedGifSupportedReturnsTrueWhenGdIsAvailable(): void {
    $this->assertTrue(self::callProtectedMethod(new ScreenshotContext(), 'isAnimatedGifSupported'));
  }

  #[DataProvider('dataProviderCreateAnimatedGifEncoderReturnsNewEncoderWithSizeCapsFromConfig')]
  public function testCreateAnimatedGifEncoderReturnsNewEncoderWithSizeCapsFromConfig(array $animation, int $expected_max_width, int $expected_max_height): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['animation' => $animation]));

    $encoder = self::callProtectedMethod($screenshot_context, 'createAnimatedGifEncoder');

    $this->assertInstanceOf(AnimatedGifEncoder::class, $encoder);
    $this->assertSame($expected_max_width, self::getProtectedValue($encoder, 'maxWidth'));
    $this->assertSame($expected_max_height, self::getProtectedValue($encoder, 'maxHeight'));
    $this->assertNotSame($encoder, self::callProtectedMethod($screenshot_context, 'createAnimatedGifEncoder'));
  }

  public static function dataProviderCreateAnimatedGifEncoderReturnsNewEncoderWithSizeCapsFromConfig(): array {
    return [
      'no config' => [[], 0, 0],
      'both caps set' => [['max_width' => 800, 'max_height' => 2000], 800, 2000],
      'width cap only' => [['max_width' => 640], 640, 0],
      'height cap only' => [['max_height' => 480], 0, 480],
    ];
  }

}
