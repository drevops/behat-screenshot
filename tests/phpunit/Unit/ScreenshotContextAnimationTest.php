<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatScreenshot\Tests\Traits\BehatScopeTrait;
use DrevOps\BehatScreenshot\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshot\Tests\Traits\ScreenshotConfigTrait;
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
  use ScreenshotConfigTrait;

  #[DataProvider('dataProviderBeforeScenarioCheckScreenshotsTagSetsFlagsFromTagsAndConfig')]
  public function testBeforeScenarioCheckScreenshotsTagSetsFlagsFromTagsAndConfig(array $scenario_tags, array $feature_tags, array $animation, bool $expected_screenshots, bool $expected_animated): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $feature_node->method('hasTag')->willReturnCallback(static fn(string $tag): bool => in_array($tag, $feature_tags, TRUE));
    $scenario = $this->createMock(ScenarioInterface::class);
    $scenario->method('hasTag')->willReturnCallback(static fn(string $tag): bool => in_array($tag, $scenario_tags, TRUE));

    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['animation' => $animation]));
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
      'feature animated tag over disabled config' => [[], ['screenshots:animated'], ['enabled' => FALSE], FALSE, TRUE],
      'scenario skip tag over enabled config' => [['screenshots:animated:skip'], [], ['enabled' => TRUE], FALSE, FALSE],
      'feature skip tag over enabled config' => [[], ['screenshots:animated:skip'], ['enabled' => TRUE], FALSE, FALSE],
      'scenario skip tag with disabled config' => [['screenshots:animated:skip'], [], ['enabled' => FALSE], FALSE, FALSE],
      'scenario skip tag over scenario animated tag' => [['screenshots:animated', 'screenshots:animated:skip'], [], [], FALSE, FALSE],
      'feature skip tag over feature animated tag' => [[], ['screenshots:animated', 'screenshots:animated:skip'], [], FALSE, FALSE],
      'scenario animated tag over feature skip tag' => [['screenshots:animated'], ['screenshots:animated:skip'], ['enabled' => FALSE], FALSE, TRUE],
      'scenario skip tag over feature animated tag' => [['screenshots:animated:skip'], ['screenshots:animated'], ['enabled' => TRUE], FALSE, FALSE],
    ];
  }

  #[DataProvider('dataProviderBeforeScenarioCheckScreenshotsTagHonoursSuiteEnvironmentVariable')]
  public function testBeforeScenarioCheckScreenshotsTagHonoursSuiteEnvironmentVariable(?string $env_value, array $scenario_tags, array $feature_tags, array $animation, bool $expected_animated): void {
    $original_value = getenv(ScreenshotContext::ENV_ANIMATION_SKIP);

    try {
      if ($env_value === NULL) {
        putenv(ScreenshotContext::ENV_ANIMATION_SKIP);
      }
      else {
        putenv(ScreenshotContext::ENV_ANIMATION_SKIP . '=' . $env_value);
      }

      $env = $this->createMock(Environment::class);
      $feature_node = $this->createMock(FeatureNode::class);
      $feature_node->method('hasTag')->willReturnCallback(static fn(string $tag): bool => in_array($tag, $feature_tags, TRUE));
      $scenario = $this->createMock(ScenarioInterface::class);
      $scenario->method('hasTag')->willReturnCallback(static fn(string $tag): bool => in_array($tag, $scenario_tags, TRUE));

      $screenshot_context = new ScreenshotContext();
      $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['animation' => $animation]));

      $screenshot_context->beforeScenarioCheckScreenshotsTag(new BeforeScenarioScope($env, $feature_node, $scenario));

      $this->assertSame($expected_animated, self::getProtectedValue($screenshot_context, 'scenarioIsAnimated'));
    }
    finally {
      if ($original_value !== FALSE) {
        putenv(ScreenshotContext::ENV_ANIMATION_SKIP . '=' . $original_value);
      }
      else {
        putenv(ScreenshotContext::ENV_ANIMATION_SKIP);
      }
    }
  }

  public static function dataProviderBeforeScenarioCheckScreenshotsTagHonoursSuiteEnvironmentVariable(): array {
    return [
      'variable unset with config enabled' => [NULL, [], [], ['enabled' => TRUE], TRUE],
      'variable set with config enabled' => ['1', [], [], ['enabled' => TRUE], FALSE],
      'variable set with config disabled' => ['1', [], [], ['enabled' => FALSE], FALSE],
      'variable set over scenario animated tag' => ['1', ['screenshots:animated'], [], [], FALSE],
      'variable set over feature animated tag' => ['1', [], ['screenshots:animated'], [], FALSE],
      'variable empty with config enabled' => ['', [], [], ['enabled' => TRUE], TRUE],
      'variable zero with config enabled' => ['0', [], [], ['enabled' => TRUE], TRUE],
      'variable zero with scenario animated tag' => ['0', ['screenshots:animated'], [], [], TRUE],
    ];
  }

  public function testAfterStepCaptureScreenshotCollectsAnimationFrame(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->expects($this->once())->method('addFrame')->with('png-bytes');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot', 'isAnimatedGifSupported', 'createAnimatedGif']);
    $screenshot_context->expects($this->once())->method('captureScreenshot');
    $screenshot_context->method('isAnimatedGifSupported')->willReturn(TRUE);
    $screenshot_context->expects($this->once())->method('createAnimatedGif')->willReturn($encoder);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotContent', 'png-bytes');

    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(TRUE));

    $this->assertSame($encoder, self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterStepCaptureScreenshotReusesEncoderAcrossSteps(): void {
    $encoder = $this->createMock(AnimatedGif::class);
    $encoder->expects($this->exactly(2))->method('addFrame')->with('png-bytes');

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot', 'isAnimatedGifSupported', 'createAnimatedGif']);
    $screenshot_context->method('isAnimatedGifSupported')->willReturn(TRUE);
    $screenshot_context->expects($this->once())->method('createAnimatedGif')->willReturn($encoder);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotContent', 'png-bytes');

    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(TRUE));
    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(TRUE));
  }

  public function testAfterStepCaptureScreenshotSkipsFrameWhenUnsupported(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot', 'isAnimatedGifSupported', 'createAnimatedGif']);
    $screenshot_context->expects($this->once())->method('captureScreenshot');
    $screenshot_context->method('isAnimatedGifSupported')->willReturn(FALSE);
    $screenshot_context->expects($this->never())->method('createAnimatedGif');
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotContent', 'png-bytes');

    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(TRUE));

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterStepCaptureScreenshotDoesNotCollectWhenNotAnimated(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot', 'createAnimatedGif']);
    $screenshot_context->expects($this->once())->method('captureScreenshot');
    $screenshot_context->expects($this->never())->method('createAnimatedGif');
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioHasScreenshotsTag', TRUE);
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', FALSE);
    self::setProtectedValue($screenshot_context, 'lastScreenshotContent', 'png-bytes');

    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(TRUE));

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterStepCaptureScreenshotSkipsFailedStep(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot', 'createAnimatedGif']);
    $screenshot_context->expects($this->never())->method('captureScreenshot');
    $screenshot_context->expects($this->never())->method('createAnimatedGif');
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', TRUE);

    $screenshot_context->afterStepCaptureScreenshot($this->createAfterStepScope(FALSE));

    $this->assertNull(self::getProtectedValue($screenshot_context, 'animationEncoder'));
  }

  public function testAfterScenarioAnimateRendersAndSaves(): void {
    $encoder = $this->createMock(AnimatedGif::class);
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
    $encoder = $this->createMock(AnimatedGif::class);
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
    $encoder = $this->createMock(AnimatedGif::class);
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
    $encoder = $this->createMock(AnimatedGif::class);
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
    $encoder = $this->createMock(AnimatedGif::class);
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

  public function testIsAnimatedGifSupportedReturnsTrueWhenGdIsAvailable(): void {
    $this->assertTrue(self::callProtectedMethod(new ScreenshotContext(), 'isAnimatedGifSupported'));
  }

  #[DataProvider('dataProviderCreateAnimatedGifCreatesNewEncoderWithSizeCapsFromConfig')]
  public function testCreateAnimatedGifCreatesNewEncoderWithSizeCapsFromConfig(array $animation, int $expected_max_width, int $expected_max_height): void {
    $screenshot_context = new ScreenshotContext();
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['animation' => $animation]));

    $encoder = self::callProtectedMethod($screenshot_context, 'createAnimatedGif');

    $this->assertInstanceOf(AnimatedGif::class, $encoder);
    $this->assertSame($expected_max_width, self::getProtectedValue($encoder, 'maxWidth'));
    $this->assertSame($expected_max_height, self::getProtectedValue($encoder, 'maxHeight'));
    $this->assertNotSame($encoder, self::callProtectedMethod($screenshot_context, 'createAnimatedGif'));
  }

  public static function dataProviderCreateAnimatedGifCreatesNewEncoderWithSizeCapsFromConfig(): array {
    return [
      'no config' => [[], 0, 0],
      'both caps set' => [['max_width' => 800, 'max_height' => 2000], 800, 2000],
      'width cap only' => [['max_width' => 640], 640, 0],
      'height cap only' => [['max_height' => 480], 0, 480],
    ];
  }

}
