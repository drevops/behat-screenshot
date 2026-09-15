<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use Behat\Behat\Context\Context;
use DrevOps\BehatScreenshot\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\Context\Initializer\ScreenshotContextInitializer;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotAwareContextInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Test ScreenshotContextInitializer.
 */
#[CoversClass(ScreenshotContextInitializer::class)]
class ScreenshotContextInitializerTest extends TestCase {

  use ReflectionTrait;

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

  public function testInitializeContextPassesConfigToContext(): void {
    $context = $this->createMock(ScreenshotAwareContextInterface::class);
    $context->expects($this->once())
      ->method('setScreenshotConfig')
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

  #[DataProvider('dataProviderInitializeContextPurgesDirOnceWhenEnabled')]
  public function testInitializeContextPurgesDirOnceWhenEnabled(bool $should_purge, ?string $env_purge, ?string $env_dir, bool $dir_exists, string $expected_dir, int $expected_exists_calls, int $expected_remove_calls, bool $expected_has_purged): void {
    $original_env_purge = getenv('BEHAT_SCREENSHOT_PURGE');
    $original_env_dir = getenv('BEHAT_SCREENSHOT_DIR');

    try {
      putenv($env_purge === NULL ? 'BEHAT_SCREENSHOT_PURGE' : 'BEHAT_SCREENSHOT_PURGE=' . $env_purge);
      putenv($env_dir === NULL ? 'BEHAT_SCREENSHOT_DIR' : 'BEHAT_SCREENSHOT_DIR=' . $env_dir);

      $finder = $this->createMock(Finder::class);
      $finder->expects($this->exactly($expected_remove_calls))->method('files')->willReturnSelf();
      $finder->expects($this->exactly($expected_remove_calls))->method('in')->with($expected_dir)->willReturnSelf();

      $filesystem = $this->createMock(Filesystem::class);
      $filesystem->expects($this->exactly($expected_exists_calls))->method('exists')->with($expected_dir)->willReturn($dir_exists);
      $filesystem->expects($this->exactly($expected_remove_calls))->method('remove')->with($finder);

      $initializer = $this->getStubBuilder(ScreenshotContextInitializer::class)
        ->setConstructorArgs(['screenshots', TRUE, 'failed_', $should_purge, TRUE, FALSE, '{datetime:U}.{ext}', '{datetime:U}.{failed_prefix}{ext}', [], []])
        ->onlyMethods(['getFilesystem', 'getFinder'])
        ->getStub();
      $initializer->method('getFilesystem')->willReturn($filesystem);
      $initializer->method('getFinder')->willReturn($finder);

      // Behat initializes contexts for every scenario, so the second pass
      // checks that a run purges at most once.
      for ($scenario = 1; $scenario <= 2; $scenario++) {
        $context = $this->createMock(ScreenshotAwareContextInterface::class);
        $context->expects($this->once())->method('setScreenshotConfig')->with($expected_dir, TRUE, 'failed_', TRUE, FALSE, '{datetime:U}.{ext}', '{datetime:U}.{failed_prefix}{ext}', [], []);
        $initializer->initializeContext($context);
      }

      $this->assertSame($expected_has_purged, self::getProtectedValue($initializer, 'hasPurged'));
    }
    finally {
      putenv($original_env_purge === FALSE ? 'BEHAT_SCREENSHOT_PURGE' : 'BEHAT_SCREENSHOT_PURGE=' . $original_env_purge);
      putenv($original_env_dir === FALSE ? 'BEHAT_SCREENSHOT_DIR' : 'BEHAT_SCREENSHOT_DIR=' . $original_env_dir);
    }
  }

  public static function dataProviderInitializeContextPurgesDirOnceWhenEnabled(): array {
    return [
      'purge disabled' => [FALSE, NULL, NULL, TRUE, 'screenshots', 0, 0, FALSE],
      'purge enabled in config' => [TRUE, NULL, NULL, TRUE, 'screenshots', 1, 1, TRUE],
      'purge enabled in environment' => [FALSE, '1', NULL, TRUE, 'screenshots', 1, 1, TRUE],
      'purge enabled in config and environment' => [TRUE, '1', NULL, TRUE, 'screenshots', 1, 1, TRUE],
      'purge disabled, environment zero' => [FALSE, '0', NULL, TRUE, 'screenshots', 0, 0, FALSE],
      'purge enabled in config, environment zero' => [TRUE, '0', NULL, TRUE, 'screenshots', 1, 1, TRUE],
      'purge enabled, dir missing' => [TRUE, NULL, NULL, FALSE, 'screenshots', 1, 0, TRUE],
      'purge disabled, dir from environment' => [FALSE, NULL, 'custom-screenshots-dir', TRUE, 'custom-screenshots-dir', 0, 0, FALSE],
      'purge enabled, dir from environment' => [TRUE, NULL, 'custom-screenshots-dir', TRUE, 'custom-screenshots-dir', 1, 1, TRUE],
      'purge enabled in environment, dir from environment' => [FALSE, '1', 'custom-screenshots-dir', TRUE, 'custom-screenshots-dir', 1, 1, TRUE],
      'purge enabled, empty dir in environment' => [TRUE, NULL, '', TRUE, 'screenshots', 1, 1, TRUE],
    ];
  }

}
