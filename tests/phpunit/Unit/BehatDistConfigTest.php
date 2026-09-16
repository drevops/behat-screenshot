<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use Behat\Config\Config;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

/**
 * Test the reference configuration in behat.dist.php.
 */
#[CoversNothing]
class BehatDistConfigTest extends TestCase {

  public function testSetsEveryOption(): void {
    $tree_builder = new TreeBuilder('root');
    (new BehatScreenshotExtension())->configure($tree_builder->getRootNode());
    $tree = $tree_builder->buildTree();

    if (!$tree instanceof ArrayNode) {
      $this->fail('The extension configuration tree is not an array node.');
    }

    $expected = self::getNodeOptionNames($tree);
    $actual = self::getSettingOptionNames(self::getExtensionSettings());
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
   * Get the option names a configuration node defines.
   *
   * @param \Symfony\Component\Config\Definition\ArrayNode $node
   *   The configuration node.
   * @param string $prefix
   *   Prefix of the parent option, ending with a dot.
   *
   * @return array<int,string>
   *   Option names, with a nested option joined to its parent by a dot.
   */
  protected static function getNodeOptionNames(ArrayNode $node, string $prefix = ''): array {
    $names = [];

    foreach ($node->getChildren() as $name => $child) {
      if ($child instanceof ArrayNode && !$child instanceof PrototypedArrayNode) {
        $names = [...$names, ...self::getNodeOptionNames($child, $prefix . $name . '.')];
        continue;
      }

      $names[] = $prefix . $name;
    }

    return $names;
  }

  /**
   * Get the option names a settings array sets.
   *
   * @param array<mixed> $settings
   *   The settings, keyed by option name.
   * @param string $prefix
   *   Prefix of the parent option, ending with a dot.
   *
   * @return array<int,string>
   *   Option names, with a nested option joined to its parent by a dot.
   */
  protected static function getSettingOptionNames(array $settings, string $prefix = ''): array {
    $names = [];

    foreach ($settings as $name => $value) {
      if (is_array($value) && !array_is_list($value)) {
        $names = [...$names, ...self::getSettingOptionNames($value, $prefix . $name . '.')];
        continue;
      }

      $names[] = $prefix . $name;
    }

    return $names;
  }

}
