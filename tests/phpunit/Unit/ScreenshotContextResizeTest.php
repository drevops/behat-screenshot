<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Session;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ScreenshotConfigTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext fullscreen resize algorithm.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextResizeTest extends TestCase {

  use ReflectionTrait;
  use ScreenshotConfigTrait;

  public function testGetScreenshotFullscreenWithResizeResizesThenRestoresWindow(): void {
    $screenshot_context = $this->getStubBuilder(ScreenshotContext::class)->onlyMethods(['getSession', 'getScreenshot'])->getStub();

    $session = $this->createMock(Session::class);
    $driver = $this->createStub(Selenium2Driver::class);

    $session->method('evaluateScript')
      ->willReturnOnConsecutiveCalls(
        [
          'width' => 1440,
          'height' => 900,
        ],
        [
          'scrollWidth' => 1440,
          'scrollHeight' => 2000,
        ]
      );

    $session->expects($this->exactly(2))
      ->method('resizeWindow')
      ->willReturnCallback(function ($width, $height, $name): void {
        static $call_count = 0;
        $call_count++;

        if ($call_count === 1) {
          $this->assertSame(1440, $width);
          $this->assertSame(2200, $height);
          $this->assertSame('current', $name);
        }
        elseif ($call_count === 2) {
          $this->assertSame(1440, $width);
          $this->assertSame(900, $height);
          $this->assertSame('current', $name);
        }
      });

    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);

    $screenshot_context->method('getScreenshot')->willReturn('test-screenshot-content');

    $result = self::callProtectedMethod($screenshot_context, 'getScreenshotFullscreenWithResize');
    $this->assertSame('test-screenshot-content', $result);
  }

  public function testGetScreenshotFullscreenWithResizeSkipsResizeOnInvalidDimensions(): void {
    $screenshot_context = $this->getStubBuilder(ScreenshotContext::class)->onlyMethods(['getSession', 'getScreenshot'])->getStub();

    $session = $this->createMock(Session::class);
    $driver = $this->createStub(Selenium2Driver::class);

    $session->method('evaluateScript')
      ->willReturnOnConsecutiveCalls(
        [
          'width' => 1440,
          'height' => 900,
        ],
        [
          'scrollWidth' => 0,
          'scrollHeight' => 0,
        ]
      );

    $session->expects($this->never())->method('resizeWindow');

    $session->method('getDriver')->willReturn($driver);
    $screenshot_context->method('getSession')->willReturn($session);

    $screenshot_context->method('getScreenshot')->willReturn('test-screenshot-content');

    $result = self::callProtectedMethod($screenshot_context, 'getScreenshotFullscreenWithResize');
    $this->assertSame('test-screenshot-content', $result);
  }

  public function testGetScreenshotFullscreenDelegatesToResizeAlgorithm(): void {
    $screenshot_context = $this->getStubBuilder(ScreenshotContext::class)->onlyMethods(['getScreenshotFullscreenWithResize'])->getStub();

    $screenshot_context->method('getScreenshotFullscreenWithResize')
      ->willReturn('test-resize-screenshot-content');

    $this->assertSame('test-resize-screenshot-content', $screenshot_context->getScreenshotFullscreen());
  }

  #[DataProvider('dataProviderCaptureScreenshotCapturesFullscreenWhenRequestedOrConfigured')]
  public function testCaptureScreenshotCapturesFullscreenWhenRequestedOrConfigured(bool $should_always_capture_fullscreen, array $config, string $expected_png_content): void {
    $driver = $this->createStub(Selenium2Driver::class);
    $driver->method('getContent')->willReturn('test-html-content');

    $session = $this->createStub(Session::class);
    $session->method('getDriver')->willReturn($driver);

    $writes = [];
    $record_write = static function (string $filename, string $content) use (&$writes): void {
      $writes[] = [$filename, $content];
    };

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession', 'makeFilename', 'getScreenshot', 'getScreenshotFullscreen', 'writeScreenshotContent']);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('makeFilename')->willReturnCallback(static fn(string $ext): string => 'test.' . $ext);
    $screenshot_context->method('getScreenshot')->willReturn('test-png-content');
    $screenshot_context->method('getScreenshotFullscreen')->willReturn('test-fullscreen-png-content');
    $screenshot_context->expects($this->exactly(2))->method('writeScreenshotContent')->willReturnCallback($record_write);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['always_fullscreen' => $should_always_capture_fullscreen]));

    $screenshot_context->captureScreenshot($config);

    $this->assertSame([['test.html', 'test-html-content'], ['test.png', $expected_png_content]], $writes);
  }

  public static function dataProviderCaptureScreenshotCapturesFullscreenWhenRequestedOrConfigured(): array {
    return [
      'not requested, not configured' => [FALSE, [], 'test-png-content'],
      'requested, not configured' => [FALSE, ['is_fullscreen' => TRUE], 'test-fullscreen-png-content'],
      'declined, not configured' => [FALSE, ['is_fullscreen' => FALSE], 'test-png-content'],
      'not requested, configured' => [TRUE, [], 'test-fullscreen-png-content'],
      'requested, configured' => [TRUE, ['is_fullscreen' => TRUE], 'test-fullscreen-png-content'],
      'declined, configured' => [TRUE, ['is_fullscreen' => FALSE], 'test-fullscreen-png-content'],
    ];
  }

}
