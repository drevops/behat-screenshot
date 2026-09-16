<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Traits;

use DrevOps\BehatScreenshotExtension\ScreenshotConfig;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

/**
 * Provides methods to build screenshot configuration through the extension.
 *
 * @phpstan-ignore trait.unused
 */
trait ScreenshotConfigTrait {

  /**
   * Create screenshot configuration from the extension's configuration tree.
   *
   * @param array<string,mixed> $config
   *   Configuration keyed as in the Behat configuration.
   *
   * @return \DrevOps\BehatScreenshotExtension\ScreenshotConfig
   *   Screenshot configuration with the tree's defaults applied.
   */
  protected static function createScreenshotConfig(array $config = []): ScreenshotConfig {
    return ScreenshotConfig::fromArray(self::processScreenshotConfig($config));
  }

  /**
   * Process configuration through the extension's configuration tree.
   *
   * @param array<string,mixed> $config
   *   Configuration keyed as in the Behat configuration.
   *
   * @return array<mixed>
   *   Processed configuration with the tree's defaults applied.
   */
  protected static function processScreenshotConfig(array $config = []): array {
    return (new Processor())->process(self::buildScreenshotConfigTree(), [$config]);
  }

  /**
   * Build the extension's configuration tree.
   *
   * @return \Symfony\Component\Config\Definition\NodeInterface
   *   Root node of the configuration tree.
   */
  protected static function buildScreenshotConfigTree(): NodeInterface {
    $tree_builder = new TreeBuilder(BehatScreenshotExtension::MOD_ID);
    (new BehatScreenshotExtension())->configure($tree_builder->getRootNode());

    return $tree_builder->buildTree();
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
