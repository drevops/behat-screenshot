<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatScreenshotExtension\Context\Initializer\ScreenshotContextInitializer;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Class ScreenshotExtension.
 */
class BehatScreenshotExtension implements ExtensionInterface {

  /**
   * Extension configuration ID.
   */
  public const MOD_ID = 'drevops_behat_screenshot';

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function process(ContainerBuilder $container): void {
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigKey(): string {
    return self::MOD_ID;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function initialize(ExtensionManager $extensionManager): void {
  }

  /**
   * {@inheritdoc}
   */
  public function configure(ArrayNodeDefinition $builder): void {
    // @phpcs:disable Drupal.WhiteSpace.ObjectOperatorIndent.Indent
    // @formatter:off
    // @phpstan-ignore-next-line
    $builder->children()
      ->scalarNode('dir')
        ->cannotBeEmpty()
        ->defaultValue('%paths.base%/screenshots')
      ->end()
      ->scalarNode('on_failed')
        ->cannotBeEmpty()
        ->defaultValue(TRUE)
      ->end()
      ->scalarNode('failed_prefix')
        ->cannotBeEmpty()
        ->defaultValue('failed_')
      ->end()
      ->scalarNode('purge')
        ->cannotBeEmpty()
        ->defaultValue(FALSE)
      ->end()
      ->scalarNode('always_fullscreen')
        ->cannotBeEmpty()
        ->defaultValue(FALSE)
      ->end()
      ->enumNode('fullscreen_algorithm')
        ->values(['stitch', 'resize'])
        ->defaultValue('resize')
      ->end()
      ->scalarNode('filename_pattern')
        ->cannotBeEmpty()
        ->defaultValue('{datetime:U}.{feature_file}.feature_{step_line}.{ext}')
      ->end()
      ->scalarNode('filename_pattern_failed')
        ->cannotBeEmpty()
        ->defaultValue('{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}')
      ->end()
      ->arrayNode('info_types')
        ->defaultValue([])
        ->prototype('scalar')
      ->end();
    // @formatter:on
    // @phpcs:enable Drupal.WhiteSpace.ObjectOperatorIndent.Indent
  }

  /**
   * {@inheritdoc}
   */
  public function load(ContainerBuilder $container, array $config): void {
    $definition = new Definition(ScreenshotContextInitializer::class, [
      $config['dir'],
      $config['on_failed'],
      $config['failed_prefix'],
      $config['purge'],
      $config['always_fullscreen'],
      $config['fullscreen_algorithm'],
      $config['filename_pattern'],
      $config['filename_pattern_failed'],
      $config['info_types'],
    ]);
    $definition->addTag(ContextExtension::INITIALIZER_TAG, ['priority' => 0]);
    $container->setDefinition(static::MOD_ID . '.screenshot_context_initializer', $definition);
  }

}
