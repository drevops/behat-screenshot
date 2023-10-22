<?php

/**
 * @file
 * Behat screenshot extension.
 */

namespace DrevOps\BehatScreenshotExtension\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeParentInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Class ScreenshotExtension
 */
class BehatScreenshotExtension implements ExtensionInterface
{

    /**
     * Extension configuration ID.
     */
    const MOD_ID = 'drevops_screenshot';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigKey(): string
    {
        return self::MOD_ID;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder): void
    {
        $definitionChildren = $builder->children();
        if ($definitionChildren instanceof NodeBuilder) {
            // @phpstan-ignore-next-line
            $definitionChildren
                ->scalarNode('dir')->cannotBeEmpty()->defaultValue('%paths.base%/screenshots')->end()
                ->scalarNode('fail')->cannotBeEmpty()->defaultValue(true)->end()
                ->scalarNode('fail_prefix')->cannotBeEmpty()->defaultValue('failed_')->end()
                ->scalarNode('filename_pattern')->cannotBeEmpty()->defaultValue('{microtime}.{prefix}{feature_file}_{step_line}.{ext}')->end()
                ->scalarNode('purge')->cannotBeEmpty()->defaultValue(false)->end();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config): void
    {
        $definition = new Definition('DrevOps\BehatScreenshotExtension\Context\Initializer\ScreenshotContextInitializer', [
            $config['dir'],
            $config['fail'],
            $config['fail_prefix'],
            $config['purge'],
            $config['filename_pattern'],
        ]);
        $definition->addTag(ContextExtension::INITIALIZER_TAG, ['priority' => 0]);
        $container->setDefinition('drevops_screenshot.screenshot_context_initializer', $definition);
    }
}
