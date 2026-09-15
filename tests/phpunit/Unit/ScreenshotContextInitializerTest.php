<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use Behat\Behat\Context\Context;
use DrevOps\BehatScreenshotExtension\Context\Initializer\ScreenshotContextInitializer;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotAwareContextInterface;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ScreenshotConfigTrait;
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
  use ScreenshotConfigTrait;

  public function testInitializeContextIgnoresNonScreenshotAwareContext(): void {
    // An empty configuration makes ScreenshotConfig::fromArray() throw, so the
    // test fails if the initializer calls it.
    $initializer = new ScreenshotContextInitializer([]);

    $initializer->initializeContext($this->createStub(Context::class));

    $this->assertFalse(self::getProtectedValue($initializer, 'hasPurged'));
  }

  #[DataProvider('dataProviderInitializeContextAppliesEnvironmentAndPurgesOnce')]
  public function testInitializeContextAppliesEnvironmentAndPurgesOnce(bool $should_purge, ?string $env_purge, ?string $env_dir, bool $dir_exists, string $expected_dir, bool $expected_purge, int $expected_exists_calls, int $expected_remove_calls): void {
    $original_env_purge = getenv('BEHAT_SCREENSHOT_PURGE');
    $original_env_dir = getenv('BEHAT_SCREENSHOT_DIR');

    try {
      putenv($env_purge === NULL ? 'BEHAT_SCREENSHOT_PURGE' : 'BEHAT_SCREENSHOT_PURGE=' . $env_purge);
      putenv($env_dir === NULL ? 'BEHAT_SCREENSHOT_DIR' : 'BEHAT_SCREENSHOT_DIR=' . $env_dir);

      // Keys the environment does not override have non-default values, so
      // the assertion shows they are passed to the context unchanged.
      $config = ['dir' => 'screenshots', 'purge' => $should_purge, 'on_failed' => FALSE, 'info_types' => ['url'], 'animation' => ['enabled' => TRUE]];
      $expected_config = self::createScreenshotConfig(['dir' => $expected_dir, 'purge' => $expected_purge] + $config);

      $finder = $this->createMock(Finder::class);
      $finder->expects($this->exactly($expected_remove_calls))->method('files')->willReturnSelf();
      $finder->expects($this->exactly($expected_remove_calls))->method('in')->with($expected_dir)->willReturnSelf();

      $filesystem = $this->createMock(Filesystem::class);
      $filesystem->expects($this->exactly($expected_exists_calls))->method('exists')->with($expected_dir)->willReturn($dir_exists);
      $filesystem->expects($this->exactly($expected_remove_calls))->method('remove')->with($finder);

      $initializer = $this->getStubBuilder(ScreenshotContextInitializer::class)
        ->setConstructorArgs([self::processScreenshotConfig($config)])
        ->onlyMethods(['createFilesystem', 'createFinder'])
        ->getStub();
      $initializer->method('createFilesystem')->willReturn($filesystem);
      $initializer->method('createFinder')->willReturn($finder);

      // Behat initializes contexts for every scenario, so the second pass
      // checks that a run purges at most once.
      for ($scenario = 1; $scenario <= 2; $scenario++) {
        $context = $this->createMock(ScreenshotAwareContextInterface::class);
        $context->expects($this->once())->method('setScreenshotConfig')->with($expected_config);
        $initializer->initializeContext($context);
      }

      $this->assertSame($expected_purge, self::getProtectedValue($initializer, 'hasPurged'));
    }
    finally {
      putenv($original_env_purge === FALSE ? 'BEHAT_SCREENSHOT_PURGE' : 'BEHAT_SCREENSHOT_PURGE=' . $original_env_purge);
      putenv($original_env_dir === FALSE ? 'BEHAT_SCREENSHOT_DIR' : 'BEHAT_SCREENSHOT_DIR=' . $original_env_dir);
    }
  }

  public static function dataProviderInitializeContextAppliesEnvironmentAndPurgesOnce(): array {
    return [
      'purge disabled' => [FALSE, NULL, NULL, TRUE, 'screenshots', FALSE, 0, 0],
      'purge enabled in config' => [TRUE, NULL, NULL, TRUE, 'screenshots', TRUE, 1, 1],
      'purge enabled in environment' => [FALSE, '1', NULL, TRUE, 'screenshots', TRUE, 1, 1],
      'purge enabled in config and environment' => [TRUE, '1', NULL, TRUE, 'screenshots', TRUE, 1, 1],
      'purge disabled, environment zero' => [FALSE, '0', NULL, TRUE, 'screenshots', FALSE, 0, 0],
      'purge enabled in config, environment zero' => [TRUE, '0', NULL, TRUE, 'screenshots', TRUE, 1, 1],
      'purge enabled, dir missing' => [TRUE, NULL, NULL, FALSE, 'screenshots', TRUE, 1, 0],
      'purge disabled, dir from environment' => [FALSE, NULL, 'custom-screenshots-dir', TRUE, 'custom-screenshots-dir', FALSE, 0, 0],
      'purge enabled, dir from environment' => [TRUE, NULL, 'custom-screenshots-dir', TRUE, 'custom-screenshots-dir', TRUE, 1, 1],
      'purge enabled in environment, dir from environment' => [FALSE, '1', 'custom-screenshots-dir', TRUE, 'custom-screenshots-dir', TRUE, 1, 1],
      'purge enabled, empty dir in environment' => [TRUE, NULL, '', TRUE, 'screenshots', TRUE, 1, 1],
      'purge enabled, zero dir in environment' => [TRUE, NULL, '0', TRUE, 'screenshots', TRUE, 1, 1],
    ];
  }

  #[DataProvider('dataProviderFactoryMethodsCreateNewInstanceOnEveryCall')]
  public function testFactoryMethodsCreateNewInstanceOnEveryCall(string $method, string $expected_class): void {
    $initializer = new ScreenshotContextInitializer([]);

    $first = self::callProtectedMethod($initializer, $method);
    $second = self::callProtectedMethod($initializer, $method);

    $this->assertSame($expected_class, get_debug_type($first));
    $this->assertSame($expected_class, get_debug_type($second));
    $this->assertNotSame($first, $second);
  }

  public static function dataProviderFactoryMethodsCreateNewInstanceOnEveryCall(): array {
    return [
      'filesystem' => ['createFilesystem', Filesystem::class],
      'finder' => ['createFinder', Finder::class],
    ];
  }

}
