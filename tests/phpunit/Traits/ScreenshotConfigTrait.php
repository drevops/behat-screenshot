<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Traits;

use DrevOps\BehatScreenshotExtension\ScreenshotConfig;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor;

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

}
