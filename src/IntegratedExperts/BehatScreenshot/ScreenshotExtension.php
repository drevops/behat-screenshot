<?php

/**
 * @file
 * Behat screenshot extension.
 */

namespace IntegratedExperts\BehatScreenshot;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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
     * Default base path.
     */
    const BASE_PATH = '%paths.base%';

    /**
     * Default screenshot dir.
     */
    const DEFAULT_SCREENSHOT_DIR = self::BASE_PATH.'/screenshot';

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
            ->scalarNode('dir')->defaultValue(self::DEFAULT_SCREENSHOT_DIR)->end()
            ->scalarNode('fail')->defaultFalse()->end()
            ->scalarNode('purge')->defaultTrue()->end();
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $definition = new Definition('IntegratedExperts\BehatScreenshot\Context\Initializer\ScreenshotContextInitializer', [
            $config,
        ]);
        $definition->addTag(ContextExtension::INITIALIZER_TAG, ['priority' => 0]);
        $container->setDefinition('integratedexperts_screenshot.screenshot_context_initializer', $definition);
    }
}
