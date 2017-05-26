<?php

/**
 * @file
 * Behat screenshot extension.
 */

namespace IntegratedExperts\Behat\Screenshot;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Class ScreenshotExtension
 */
class ScreenshotExtension implements ExtensionInterface
{

    /**
     * Extension configuration ID.
     */
    const MOD_ID = 'integratedexperts_screenshot';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigKey()
    {
        return self::MOD_ID;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder->children()
            ->scalarNode('dir')->cannotBeEmpty()->end()
            ->scalarNode('fail')->cannotBeEmpty()->end()
            ->scalarNode('purge')->cannotBeEmpty()->end();
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        if (!isset($config['dir'])) {
            throw new RuntimeException('Parameter dir is not determine in behat config.');
        } elseif (!isset($config['fail'])) {
            throw new RuntimeException('Parameter fail is not determine in behat config.');
        } elseif (!isset($config['purge'])) {
            throw new RuntimeException('Parameter purge is not determine in behat config.');
        } else {
            $definition = new Definition('IntegratedExperts\Behat\Screenshot\Context\Initializer\ScreenshotContextInitializer', [
                $config['dir'],
                $config['fail'],
                $config['purge'],
            ]);
            $definition->addTag(ContextExtension::INITIALIZER_TAG, ['priority' => 0]);
            $container->setDefinition('integratedexperts_screenshot.screenshot_context_initializer', $definition);
        }
    }
}
