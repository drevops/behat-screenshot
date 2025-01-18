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
    $this->assertEquals('drevops_screenshot', $extension->getConfigKey());
  }

  public function testLoad(): void {
    $container = new ContainerBuilder();
    $config = [
      'dir' => '%paths.base%/screenshots',
      'fail' => TRUE,
      'fail_prefix' => 'failed_',
      'purge' => FALSE,
      'filenamePattern' => '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      'filenamePatternFailed' => '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
      'info_types' => FALSE,
    ];

    $extension = new BehatScreenshotExtension();
    $extension->load($container, $config);

    $this->assertTrue($container->hasDefinition('drevops_screenshot.screenshot_context_initializer'));

    $definition = $container->getDefinition('drevops_screenshot.screenshot_context_initializer');
    $this->assertEquals(ScreenshotContextInitializer::class, $definition->getClass());
    $this->assertEquals(
      [
        $config['dir'],
        $config['fail'],
        $config['fail_prefix'],
        $config['purge'],
        $config['filenamePattern'],
        $config['filenamePatternFailed'],
        $config['info_types'],
      ],
      $definition->getArguments()
    );
  }

  public function testConfigure(): void {
    $builder = new ArrayNodeDefinition('root');

    $extension = new BehatScreenshotExtension();
    $extension->configure($builder);

    $this->assertCount(7, $builder->getChildNodeDefinitions());
  }

}
