<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use DrevOps\BehatScreenshotExtension\AnimatedGifEncoder;
use DrevOps\BehatScreenshotExtension\Context\Initializer\ScreenshotContextInitializer;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use DrevOps\BehatScreenshotExtension\ScreenshotConfig;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;
use DrevOps\BehatScreenshotExtension\Tokenizer;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test boolean property naming.
 */
#[CoversNothing]
class BooleanPropertyNamingTest extends TestCase {

  #[DataProvider('dataProviderBooleanPropertiesAreNamedAsPredicates')]
  public function testBooleanPropertiesAreNamedAsPredicates(string $class, array $expected_properties): void {
    if (!class_exists($class)) {
      $this->fail(sprintf('Class %s does not exist.', $class));
    }

    $properties = [];

    foreach ((new \ReflectionClass($class))->getProperties() as $property) {
      $type = $property->getType();

      // Inherited properties belong to other packages such as Mink, so only
      // the ones the class declares are checked.
      if ($property->getDeclaringClass()->getName() !== $class || !$type instanceof \ReflectionNamedType || $type->getName() !== 'bool') {
        continue;
      }

      $name = $property->getName();
      $this->assertMatchesRegularExpression(
        '/^(?:should|is|has|[a-z]+(?:Is|Has))[A-Z]/',
        $name,
        sprintf('Boolean property %s::$%s does not start with "should", "is" or "has", or with a subject followed by "Is" or "Has".', $class, $name),
      );
      $properties[] = $name;
    }

    sort($properties);

    $this->assertSame($expected_properties, $properties);
  }

  public static function dataProviderBooleanPropertiesAreNamedAsPredicates(): array {
    return [
      'AnimatedGifEncoder' => [AnimatedGifEncoder::class, []],
      'BehatScreenshotExtension' => [BehatScreenshotExtension::class, []],
      'ScreenshotConfig' => [
        ScreenshotConfig::class,
        ['shouldAlwaysCaptureFullscreen', 'shouldAnimate', 'shouldCaptureOnEveryStep', 'shouldCaptureOnFailed', 'shouldPurge'],
      ],
      'ScreenshotContext' => [ScreenshotContext::class, ['scenarioHasScreenshotsTag', 'scenarioIsAnimated']],
      'ScreenshotContextInitializer' => [ScreenshotContextInitializer::class, ['hasPurged']],
      'Tokenizer' => [Tokenizer::class, []],
    ];
  }

}
