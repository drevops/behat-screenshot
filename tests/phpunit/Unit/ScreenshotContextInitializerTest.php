<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

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

  public function testInitializeContextIgnoresNonScreenshotAwareContext(): void {
    $context = $this->createMock(Context::class);

    $initializer = new ScreenshotContextInitializer(
      'screenshots',
      TRUE,
      'failed_',
      TRUE,
      TRUE,
      FALSE,
      '{datetime:U}.{ext}',
      '{datetime:U}.{failed_prefix}{ext}',
      [],
      []
    );

    $initializer->initializeContext($context);

    // The assertion keeps the test from being marked as risky.
    $this->assertInstanceOf(ScreenshotContextInitializer::class, $initializer);
  }

  public function testInitializeContextPassesParametersToContext(): void {
    $context = $this->createMock(ScreenshotAwareContextInterface::class);
    $context->expects($this->once())
      ->method('setScreenshotParameters')
      ->with(
        'screenshots',
        TRUE,
        'failed_',
        TRUE,
        FALSE,
        '{datetime:U}.{ext}',
        '{datetime:U}.{failed_prefix}{ext}',
        [],
        []
      );

    $initializer = new ScreenshotContextInitializer(
      'screenshots',
      TRUE,
      'failed_',
      // Do not purge.
      FALSE,
      TRUE,
      FALSE,
      '{datetime:U}.{ext}',
      '{datetime:U}.{failed_prefix}{ext}',
      [],
      []
    );

    $initializer->initializeContext($context);
  }

  public function testInitializeContextAppliesEnvOverridesAndPurgesOnce(): void {
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
          FALSE,
          '{datetime:U}.{ext}',
          '{datetime:U}.{failed_prefix}{ext}',
          [],
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

      $initializer = $this->getMockBuilder(ScreenshotContextInitializer::class)
        ->setConstructorArgs([
          'screenshots',
          TRUE,
          'failed_',
          // Not used due to ENV override.
          FALSE,
          TRUE,
          FALSE,
          '{datetime:U}.{ext}',
          '{datetime:U}.{failed_prefix}{ext}',
          [],
          [],
        ])
        ->onlyMethods(['getFilesystem', 'getFinder'])
        ->getMock();

      $initializer->method('getFilesystem')->willReturn($filesystem);
      $initializer->method('getFinder')->willReturn($finder);

      $initializer->initializeContext($context);

      // The second call must not purge again.
      $context2 = $this->createMock(ScreenshotAwareContextInterface::class);
      $context2->expects($this->once())
        ->method('setScreenshotParameters')
        ->with(
          'custom-screenshots-dir',
          TRUE,
          'failed_',
          TRUE,
          FALSE,
          '{datetime:U}.{ext}',
          '{datetime:U}.{failed_prefix}{ext}',
          [],
          []
        );

      $initializer->initializeContext($context2);
    }
    finally {
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
