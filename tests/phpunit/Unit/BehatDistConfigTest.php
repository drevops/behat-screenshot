<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use Behat\Config\Config;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ScreenshotConfigTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Test the reference configuration in behat.dist.php.
 */
#[CoversNothing]
class BehatDistConfigTest extends TestCase {

  use ScreenshotConfigTrait;

  public function testSetsEveryOption(): void {
    $expected = self::collectLeafKeyPaths(self::buildScreenshotConfigTree());
    $actual = self::collectSettingKeyPaths(self::getExtensionSettings());
    sort($expected);
    sort($actual);

    $this->assertSame($expected, $actual);
  }

  /**
   * Load the configuration that behat.dist.php returns.
   *
   * @return array<mixed>
   *   The configuration as an array.
   */
  protected static function loadPhpConfig(): array {
    $config = require dirname(__DIR__, 3) . '/behat.dist.php';

    if (!$config instanceof Config) {
      self::fail('behat.dist.php does not return a Behat configuration.');
    }

    return $config->toArray();
  }

  /**
   * Get the settings behat.dist.php passes to the extension.
   *
   * @return array<mixed>
   *   The extension settings, keyed by option name.
   */
  protected static function getExtensionSettings(): array {
    $settings = self::loadPhpConfig();

    foreach (['default', 'extensions', BehatScreenshotExtension::class] as $key) {
      if (!is_array($settings) || !isset($settings[$key])) {
        self::fail(sprintf('behat.dist.php has no "%s" key on the path to the extension settings.', $key));
      }

      $settings = $settings[$key];
    }

    if (!is_array($settings)) {
      self::fail('behat.dist.php does not hold the extension settings as a map.');
    }

    return $settings;
  }

  /**
   * Collect the key paths a settings array sets.
   *
   * @param array<mixed> $settings
   *   The settings, keyed by option name.
   * @param string $prefix
   *   Key path of the parent setting, with a trailing dot.
   *
   * @return array<int,string>
   *   Key paths, with nested keys separated by dots.
   */
  protected static function collectSettingKeyPaths(array $settings, string $prefix = ''): array {
    $paths = [];

    foreach ($settings as $name => $value) {
      if (is_array($value) && !array_is_list($value)) {
        $paths = [...$paths, ...self::collectSettingKeyPaths($value, $prefix . $name . '.')];

        continue;
      }

      $paths[] = $prefix . $name;
    }

    return $paths;
  }

}
