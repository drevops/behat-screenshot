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
 * Test environment variable naming.
 */
#[CoversNothing]
class EnvironmentVariableNamingTest extends TestCase {

  /**
   * Prefix of every environment variable the extension reads.
   */
  protected const VARIABLE_PREFIX = 'BEHAT_SCREENSHOT_';

  #[DataProvider('dataProviderEnvironmentVariablesAreReadThroughClassConstants')]
  public function testEnvironmentVariablesAreReadThroughClassConstants(string $class, array $expected_variables): void {
    if (!class_exists($class)) {
      $this->fail(sprintf('Class %s does not exist.', $class));
    }

    $reflection = new \ReflectionClass($class);
    $read_constants = $this->readGetenvConstants($reflection);

    $variables = [];

    foreach ($reflection->getReflectionConstants() as $constant) {
      $name = $constant->getName();

      if ($constant->getDeclaringClass()->getName() !== $class || !str_starts_with($name, 'ENV_')) {
        continue;
      }

      $value = $constant->getValue();

      if (!is_string($value)) {
        $this->fail(sprintf('%s::%s does not hold a string.', $class, $name));
      }

      $this->assertTrue($constant->isPublic(), sprintf('%s::%s is not public.', $class, $name));
      $this->assertContains($name, $read_constants, sprintf('%s declares %s but does not pass it to getenv().', $class, $name));
      $this->assertStringStartsWith(
        self::VARIABLE_PREFIX,
        $value,
        sprintf('%s::%s holds %s, which does not start with %s.', $class, $name, $value, self::VARIABLE_PREFIX),
      );

      $expected_name = 'ENV_' . substr($value, strlen(self::VARIABLE_PREFIX));
      $this->assertSame($expected_name, $name, sprintf('%s::%s holds %s, so it must be named %s.', $class, $name, $value, $expected_name));

      $variables[$name] = $value;
    }

    foreach ($read_constants as $name) {
      $this->assertArrayHasKey($name, $variables, sprintf('%s passes self::%s to getenv() without declaring it.', $class, $name));
    }

    ksort($variables);

    $this->assertSame($expected_variables, $variables);
  }

  public static function dataProviderEnvironmentVariablesAreReadThroughClassConstants(): array {
    return [
      'AnimatedGifEncoder' => [AnimatedGifEncoder::class, []],
      'BehatScreenshotExtension' => [BehatScreenshotExtension::class, []],
      'ScreenshotConfig' => [ScreenshotConfig::class, []],
      'ScreenshotContext' => [
        ScreenshotContext::class,
        ['ENV_ANIMATION_SKIP' => 'BEHAT_SCREENSHOT_ANIMATION_SKIP', 'ENV_TOKEN_HOST' => 'BEHAT_SCREENSHOT_TOKEN_HOST'],
      ],
      'ScreenshotContextInitializer' => [ScreenshotContextInitializer::class, ['ENV_DIR' => 'BEHAT_SCREENSHOT_DIR', 'ENV_PURGE' => 'BEHAT_SCREENSHOT_PURGE']],
      'Tokenizer' => [Tokenizer::class, []],
    ];
  }

  /**
   * Read the constants that a class passes to getenv().
   *
   * @param \ReflectionClass<object> $reflection
   *   Class to read.
   *
   * @return array<int,string>
   *   Constant names in the order of the calls.
   */
  protected function readGetenvConstants(\ReflectionClass $reflection): array {
    $source = (string) file_get_contents((string) $reflection->getFileName());

    // Comments and whitespace are dropped, so the joined text of a call has no
    // spacing for the pattern to allow for.
    $tokens = array_values(array_filter(\PhpToken::tokenize($source), static fn(\PhpToken $token): bool => !$token->isIgnorable()));

    $constants = [];

    foreach ($tokens as $index => $token) {
      if (!$token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) || strtolower(ltrim($token->text, '\\')) !== 'getenv') {
        continue;
      }

      $call = '';
      $depth = 0;

      foreach (array_slice($tokens, $index + 1) as $call_token) {
        $call .= $call_token->text;
        $depth += match ($call_token->text) {
          '(' => 1,
          ')' => -1,
          default => 0,
        };

        if ($depth === 0) {
          break;
        }
      }

      if (!preg_match('/^\(self::(ENV_[A-Z0-9_]+)\)$/', $call, $matches)) {
        $this->fail(sprintf(
          '%s calls getenv%s on line %d. Pass a public ENV_* constant of the class as self::ENV_*.',
          $reflection->getName(),
          $call,
          $token->line,
        ));
      }

      $constants[] = $matches[1];
    }

    return $constants;
  }

}
