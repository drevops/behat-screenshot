<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit\Context\Initializer;

use Behat\Behat\Context\Context;
use DrevOps\BehatScreenshotExtension\Context\Initializer\ScreenshotContextInitializer;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotAwareContextInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Test ScreenshotContextInitializer.
 */
#[CoversClass(ScreenshotContextInitializer::class)]
class ScreenshotContextInitializerTest extends TestCase {

  /**
   * Test initializeContext with a non-screenshot aware context.
   */
  public function testInitializeContextNonScreenshotAware(): void {
    $context = $this->createMock(Context::class);

    $initializer = new ScreenshotContextInitializer(
      'screenshots',
      TRUE,
      'failed_',
      TRUE,
      TRUE,
      'resize',
      '{datetime:U}.{ext}',
      '{datetime:U}.{failed_prefix}{ext}',
      []
    );

    // No error should be thrown, just verifying it runs without error.
    $initializer->initializeContext($context);

    // This is just to ensure the test isn't marked as risky.
    $this->assertInstanceOf(ScreenshotContextInitializer::class, $initializer);
  }

  /**
   * Test initializeContext with a screenshot aware context.
   */
  public function testInitializeContext(): void {
    $context = $this->createMock(ScreenshotAwareContextInterface::class);
    $context->expects($this->once())
      ->method('setScreenshotParameters')
      ->with(
        'screenshots',
        TRUE,
        'failed_',
        TRUE,
        'resize',
        '{datetime:U}.{ext}',
        '{datetime:U}.{failed_prefix}{ext}',
        []
      );

    $initializer = new ScreenshotContextInitializer(
      'screenshots',
      TRUE,
      'failed_',
      // don't purge.
      FALSE,
      TRUE,
      'resize',
      '{datetime:U}.{ext}',
      '{datetime:U}.{failed_prefix}{ext}',
      []
    );

    $initializer->initializeContext($context);
  }

  /**
   * Test initializeContext with ENV override.
   */
  public function testInitializeContextWithEnv(): void {
    $original_dir_value = getenv('BEHAT_SCREENSHOT_DIR');
    $original_purge_value = getenv('BEHAT_SCREENSHOT_PURGE');

    try {
      putenv('BEHAT_SCREENSHOT_DIR=custom-screenshots-dir');
      putenv('BEHAT_SCREENSHOT_PURGE=1');

      $context = $this->createMock(ScreenshotAwareContextInterface::class);
      $context->expects($this->once())
        ->method('setScreenshotParameters')
        ->with(
          // From ENV.
          'custom-screenshots-dir',
          TRUE,
          'failed_',
          TRUE,
          'resize',
          '{datetime:U}.{ext}',
          '{datetime:U}.{failed_prefix}{ext}',
          []
        );

      $filesystem = $this->createMock(Filesystem::class);
      $finder = $this->createMock(Finder::class);

      $filesystem->expects($this->once())
        ->method('exists')
        ->with('custom-screenshots-dir')
        ->willReturn(TRUE);

      $finder->expects($this->once())
        ->method('files')
        ->willReturnSelf();

      $finder->expects($this->once())
        ->method('in')
        ->with('custom-screenshots-dir')
        ->willReturnSelf();

      $filesystem->expects($this->once())
        ->method('remove')
        ->with($finder);

      // Create a partial mock of the initializer to override filesystem.
      $initializer = $this->getMockBuilder(ScreenshotContextInitializer::class)
        ->setConstructorArgs([
          'screenshots',
          TRUE,
          'failed_',
          // Not used due to ENV override.
          FALSE,
          TRUE,
          'resize',
          '{datetime:U}.{ext}',
          '{datetime:U}.{failed_prefix}{ext}',
          [],
        ])
        ->onlyMethods(['getFilesystem', 'getFinder'])
        ->getMock();

      $initializer->method('getFilesystem')->willReturn($filesystem);
      $initializer->method('getFinder')->willReturn($finder);

      $initializer->initializeContext($context);

      // Test that we don't purge on second call.
      $context2 = $this->createMock(ScreenshotAwareContextInterface::class);
      $context2->expects($this->once())
        ->method('setScreenshotParameters')
        ->with(
          'custom-screenshots-dir',
          TRUE,
          'failed_',
          TRUE,
          'resize',
          '{datetime:U}.{ext}',
          '{datetime:U}.{failed_prefix}{ext}',
          []
        );

      $initializer->initializeContext($context2);
    }
    finally {
      // Restore original env values.
      if ($original_dir_value !== FALSE) {
        putenv('BEHAT_SCREENSHOT_DIR=' . $original_dir_value);
      }
      else {
        putenv('BEHAT_SCREENSHOT_DIR');
      }

      if ($original_purge_value !== FALSE) {
        putenv('BEHAT_SCREENSHOT_PURGE=' . $original_purge_value);
      }
      else {
        putenv('BEHAT_SCREENSHOT_PURGE');
      }
    }
  }

}
