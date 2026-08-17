<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use DrevOps\BehatScreenshotExtension\Context\Initializer\ScreenshotContextInitializer;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test BehatScreenshotExtension.
 */
#[CoversClass(BehatScreenshotExtension::class)]
class BehatScreenshotExtensionTest extends TestCase {

  public function testGetConfigKey(): void {
    $extension = new BehatScreenshotExtension();
    $this->assertSame('drevops_behat_screenshot', $extension->getConfigKey());
  }

  public function testLoad(): void {
    $container = new ContainerBuilder();
    $config = [
      'dir' => '%paths.base%/screenshots',
      'on_failed' => TRUE,
      'failed_prefix' => 'failed_',
      'purge' => FALSE,
      'always_fullscreen' => FALSE,
      'on_every_step' => FALSE,
      'filename_pattern' => '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
      'filename_pattern_failed' => '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
      'info_types' => FALSE,
      'animation' => ['enabled' => FALSE, 'frame_delay' => 500],
    ];

    $extension = new BehatScreenshotExtension();
    $extension->load($container, $config);

    $this->assertTrue($container->hasDefinition('drevops_behat_screenshot.screenshot_context_initializer'));

    $definition = $container->getDefinition('drevops_behat_screenshot.screenshot_context_initializer');
    $this->assertSame(ScreenshotContextInitializer::class, $definition->getClass());
    $this->assertSame(
      [
        $config['dir'],
        $config['on_failed'],
        $config['failed_prefix'],
        $config['purge'],
        $config['always_fullscreen'],
        $config['on_every_step'],
        $config['filename_pattern'],
        $config['filename_pattern_failed'],
        $config['info_types'],
        $config['animation'],
      ],
      $definition->getArguments()
    );
  }

  public function testConfigure(): void {
    $builder = new ArrayNodeDefinition('root');

    $extension = new BehatScreenshotExtension();
    $extension->configure($builder);

    $this->assertCount(10, $builder->getChildNodeDefinitions());
  }

  #[DataProvider('dataProviderConfigureAcceptsOnlyBooleans')]
  public function testConfigureAcceptsOnlyBooleans(string $key, mixed $value, bool $is_accepted): void {
    $tree_builder = new TreeBuilder('root');

    $extension = new BehatScreenshotExtension();
    $extension->configure($tree_builder->getRootNode());

    if (!$is_accepted) {
      $this->expectException(InvalidTypeException::class);
    }

    $processed = (new Processor())->process($tree_builder->buildTree(), [[$key => $value]]);

    if ($is_accepted) {
      $this->assertSame($value, $processed[$key]);
    }
  }

  public static function dataProviderConfigureAcceptsOnlyBooleans(): array {
    return [
      'on_failed enabled' => ['on_failed', TRUE, TRUE],
      'on_failed disabled' => ['on_failed', FALSE, TRUE],
      'purge disabled' => ['purge', FALSE, TRUE],
      'always_fullscreen enabled' => ['always_fullscreen', TRUE, TRUE],
      'on_every_step enabled' => ['on_every_step', TRUE, TRUE],
      // A quoted 'false' used to pass through as a truthy string.
      'quoted false' => ['on_failed', 'false', FALSE],
      'quoted true' => ['on_failed', 'true', FALSE],
      'integer zero' => ['purge', 0, FALSE],
      'integer one' => ['purge', 1, FALSE],
    ];
  }

}
