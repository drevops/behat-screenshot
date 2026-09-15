<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use DrevOps\BehatScreenshot\Tests\Traits\ScreenshotConfigTrait;
use DrevOps\BehatScreenshotExtension\ScreenshotConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

/**
 * Test ScreenshotConfig.
 */
#[CoversClass(ScreenshotConfig::class)]
class ScreenshotConfigTest extends TestCase {

  use ScreenshotConfigTrait;

  public function testFromArrayMapsConfigTreeDefaults(): void {
    $expected = new ScreenshotConfig(
      dir: '%paths.base%/screenshots',
      shouldCaptureOnFailed: TRUE,
      failedPrefix: 'failed_',
      shouldPurge: FALSE,
      shouldAlwaysCaptureFullscreen: FALSE,
      shouldCaptureOnEveryStep: FALSE,
      filenamePattern: '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      filenamePatternFailed: '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      infoTypes: [],
      shouldAnimate: FALSE,
      animationFrameDelay: 500,
      animationMaxWidth: 0,
      animationMaxHeight: 0,
    );

    $this->assertEquals($expected, self::createScreenshotConfig());
  }

  #[DataProvider('dataProviderFromArrayMapsKeyToProperty')]
  public function testFromArrayMapsKeyToProperty(array $config, string $property, mixed $value): void {
    $default_values = get_object_vars(self::createScreenshotConfig());
    $this->assertNotSame($default_values[$property], $value, 'The dataset value must differ from the default, or the mapping is not exercised.');

    $expected_values = $default_values;
    $expected_values[$property] = $value;

    $this->assertSame($expected_values, get_object_vars(self::createScreenshotConfig($config)));
  }

  public static function dataProviderFromArrayMapsKeyToProperty(): array {
    return [
      'dir' => [['dir' => 'test-dir'], 'dir', 'test-dir'],
      'on_failed' => [['on_failed' => FALSE], 'shouldCaptureOnFailed', FALSE],
      'failed_prefix' => [['failed_prefix' => 'test_failed_'], 'failedPrefix', 'test_failed_'],
      'purge' => [['purge' => TRUE], 'shouldPurge', TRUE],
      'always_fullscreen' => [['always_fullscreen' => TRUE], 'shouldAlwaysCaptureFullscreen', TRUE],
      'on_every_step' => [['on_every_step' => TRUE], 'shouldCaptureOnEveryStep', TRUE],
      'filename_pattern' => [['filename_pattern' => 'test-pattern.{ext}'], 'filenamePattern', 'test-pattern.{ext}'],
      'filename_pattern_failed' => [['filename_pattern_failed' => 'test-pattern-failed.{ext}'], 'filenamePatternFailed', 'test-pattern-failed.{ext}'],
      'info_types' => [['info_types' => ['url', 'step']], 'infoTypes', ['url', 'step']],
      'animation.enabled' => [['animation' => ['enabled' => TRUE]], 'shouldAnimate', TRUE],
      'animation.frame_delay' => [['animation' => ['frame_delay' => 250]], 'animationFrameDelay', 250],
      'animation.max_width' => [['animation' => ['max_width' => 800]], 'animationMaxWidth', 800],
      'animation.max_height' => [['animation' => ['max_height' => 2000]], 'animationMaxHeight', 2000],
    ];
  }

  public function testKeyMappingCoversEveryConfigTreeKeyAndProperty(): void {
    $datasets = self::dataProviderFromArrayMapsKeyToProperty();

    $tree_keys = self::collectLeafKeyPaths(self::buildScreenshotConfigTree());
    $mapped_keys = array_keys($datasets);
    sort($tree_keys);
    sort($mapped_keys);

    $this->assertSame($tree_keys, $mapped_keys, 'Every configuration tree key needs a dataset in dataProviderFromArrayMapsKeyToProperty().');

    $properties = array_map(static fn(\ReflectionProperty $property): string => $property->getName(), (new \ReflectionClass(ScreenshotConfig::class))->getProperties());
    $mapped_properties = array_column($datasets, 1);
    sort($properties);
    sort($mapped_properties);

    $this->assertSame($properties, $mapped_properties, 'Every ScreenshotConfig property needs a dataset in dataProviderFromArrayMapsKeyToProperty().');
  }

  #[DataProvider('dataProviderFromArrayCastsScalarNodeValuesToStrings')]
  public function testFromArrayCastsScalarNodeValuesToStrings(array $config, string $property, mixed $expected): void {
    $this->assertSame($expected, get_object_vars(self::createScreenshotConfig($config))[$property]);
  }

  public static function dataProviderFromArrayCastsScalarNodeValuesToStrings(): array {
    return [
      'integer dir' => [['dir' => 123], 'dir', '123'],
      'float failed prefix' => [['failed_prefix' => 1.5], 'failedPrefix', '1.5'],
      'boolean filename pattern' => [['filename_pattern' => TRUE], 'filenamePattern', '1'],
      'integer info type' => [['info_types' => ['url', 7]], 'infoTypes', ['url', '7']],
      'keyed info types' => [['info_types' => ['first' => 'url', 'second' => 'step']], 'infoTypes', ['url', 'step']],
    ];
  }

  #[DataProvider('dataProviderFromArrayRejectsMissingOrInvalidValue')]
  public function testFromArrayRejectsMissingOrInvalidValue(array $config, string $expected_message): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected_message);

    ScreenshotConfig::fromArray($config);
  }

  public static function dataProviderFromArrayRejectsMissingOrInvalidValue(): array {
    $config = self::processScreenshotConfig();

    $without_dir = $config;
    unset($without_dir['dir']);

    return [
      'missing key' => [$without_dir, 'Screenshot configuration "dir" is missing.'],
      'missing nested key' => [['animation' => ['frame_delay' => 500, 'max_width' => 0, 'max_height' => 0]] + $config, 'Screenshot configuration "animation.enabled" is missing.'],
      'nested key parent not an array' => [['animation' => 'enabled'] + $config, 'Screenshot configuration "animation" must be an array, string given.'],
      'boolean as string' => [['on_failed' => 'false'] + $config, 'Screenshot configuration "on_failed" must be a boolean, string given.'],
      'boolean as integer' => [['purge' => 1] + $config, 'Screenshot configuration "purge" must be a boolean, int given.'],
      'integer as numeric string' => [['animation' => ['enabled' => FALSE, 'frame_delay' => '250', 'max_width' => 0, 'max_height' => 0]] + $config, 'Screenshot configuration "animation.frame_delay" must be an integer, string given.'],
      'integer as float' => [['animation' => ['enabled' => FALSE, 'frame_delay' => 500, 'max_width' => 0, 'max_height' => 1.5]] + $config, 'Screenshot configuration "animation.max_height" must be an integer, float given.'],
      'scalar as array' => [['dir' => ['screenshots']] + $config, 'Screenshot configuration "dir" must be a scalar, array given.'],
      'scalar as null' => [['failed_prefix' => NULL] + $config, 'Screenshot configuration "failed_prefix" must be a scalar, null given.'],
      'list as string' => [['info_types' => 'url'] + $config, 'Screenshot configuration "info_types" must be an array, string given.'],
      'list item as array' => [['info_types' => ['url', ['step']]] + $config, 'Screenshot configuration "info_types.1" must be a scalar, array given.'],
    ];
  }

  /**
   * Collect the key paths of every leaf node in a configuration tree.
   *
   * @param \Symfony\Component\Config\Definition\NodeInterface $node
   *   Configuration tree node.
   * @param string $prefix
   *   Key path of the node's parent, with a trailing dot.
   *
   * @return array<int,string>
   *   Key paths, with nested keys separated by dots.
   */
  protected static function collectLeafKeyPaths(NodeInterface $node, string $prefix = ''): array {
    if (!$node instanceof ArrayNode) {
      return [];
    }

    $paths = [];

    foreach ($node->getChildren() as $name => $child) {
      // A prototyped array is a list value, not a set of named keys.
      if ($child instanceof ArrayNode && !$child instanceof PrototypedArrayNode) {
        $paths = [...$paths, ...self::collectLeafKeyPaths($child, $prefix . $name . '.')];

        continue;
      }

      $paths[] = $prefix . $name;
    }

    return $paths;
  }

}
