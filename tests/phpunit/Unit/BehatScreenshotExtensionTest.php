<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use DrevOps\BehatScreenshotExtension\Context\Initializer\ScreenshotContextInitializer;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test BehatScreenshotExtension.
 */
#[CoversClass(BehatScreenshotExtension::class)]
class BehatScreenshotExtensionTest extends TestCase {

  public function testGetConfigKey(): void {
    $extension = new BehatScreenshotExtension();
    $this->assertEquals('drevops_behat_screenshot', $extension->getConfigKey());
  }

  public function testLoad(): void {
    $container = new ContainerBuilder();
    $config = [
      'dir' => '%paths.base%/screenshots',
      'on_failed' => TRUE,
      'failed_prefix' => 'failed_',
      'purge' => FALSE,
      'always_fullscreen' => FALSE,
      'fullscreen_algorithm' => 'resize',
      'filename_pattern' => '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      'filename_pattern_failed' => '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      'info_types' => FALSE,
    ];

    $extension = new BehatScreenshotExtension();
    $extension->load($container, $config);

    $this->assertTrue($container->hasDefinition('drevops_behat_screenshot.screenshot_context_initializer'));

    $definition = $container->getDefinition('drevops_behat_screenshot.screenshot_context_initializer');
    $this->assertEquals(ScreenshotContextInitializer::class, $definition->getClass());
    $this->assertEquals(
      [
        $config['dir'],
        $config['on_failed'],
        $config['failed_prefix'],
        $config['purge'],
        $config['always_fullscreen'],
        $config['fullscreen_algorithm'],
        $config['filename_pattern'],
        $config['filename_pattern_failed'],
        $config['info_types'],
      ],
      $definition->getArguments()
    );
  }

  public function testConfigure(): void {
    $builder = new ArrayNodeDefinition('root');

    $extension = new BehatScreenshotExtension();
    $extension->configure($builder);

    $this->assertCount(9, $builder->getChildNodeDefinitions());
  }

}
