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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Test collaborator creation.
 */
#[CoversNothing]
class CollaboratorCreationTest extends TestCase {

  /**
   * Classes created inline because their instances hold data only.
   */
  protected const VALUE_CLASSES = [Definition::class, ScreenshotConfig::class];

  #[DataProvider('dataProviderCollaboratorsAreCreatedInFactoryMethods')]
  public function testCollaboratorsAreCreatedInFactoryMethods(string $class, array $expected_factories): void {
    if (!class_exists($class)) {
      $this->fail(sprintf('Class %s does not exist.', $class));
    }

    $reflection = new \ReflectionClass($class);
    $factories = [];

    foreach ($this->readCreatedClasses($reflection) as [$line, $created_class]) {
      // Exceptions and value objects have no side effects to substitute.
      if (is_a($created_class, \Throwable::class, TRUE) || in_array($created_class, self::VALUE_CLASSES, TRUE)) {
        continue;
      }

      $method = $this->findMethodAtLine($reflection, $line);

      if (!$method instanceof \ReflectionMethod) {
        $this->fail(sprintf('%s creates %s outside a method on line %d.', $class, $created_class, $line));
      }

      $return_type = $method->getReturnType();
      $is_factory = $method->isProtected() && str_starts_with($method->getName(), 'create') && $return_type instanceof \ReflectionNamedType && $return_type->getName() === $created_class;
      $this->assertTrue($is_factory, sprintf('%s::%s() creates %s on line %d. Create collaborators in a protected create*() method that returns them.', $class, $method->getName(), $created_class, $line));

      $factories[$method->getName()] = $created_class;
    }

    ksort($factories);

    $this->assertSame($expected_factories, $factories);
  }

  public static function dataProviderCollaboratorsAreCreatedInFactoryMethods(): array {
    return [
      'AnimatedGifEncoder' => [AnimatedGifEncoder::class, []],
      'BehatScreenshotExtension' => [BehatScreenshotExtension::class, []],
      'ScreenshotConfig' => [ScreenshotConfig::class, []],
      'ScreenshotContext' => [ScreenshotContext::class, ['createAnimatedGifEncoder' => AnimatedGifEncoder::class, 'createFilesystem' => Filesystem::class]],
      'ScreenshotContextInitializer' => [ScreenshotContextInitializer::class, ['createFilesystem' => Filesystem::class, 'createFinder' => Finder::class]],
      'Tokenizer' => [Tokenizer::class, []],
    ];
  }

  /**
   * Read every class that a class file creates with the "new" operator.
   *
   * @param \ReflectionClass<object> $reflection
   *   Class to read.
   *
   * @return array<int,array{int,string}>
   *   Line number and fully qualified class name of each creation.
   */
  protected function readCreatedClasses(\ReflectionClass $reflection): array {
    $source = (string) file_get_contents((string) $reflection->getFileName());
    $imports = $this->readImports($source);

    // Comments and whitespace are dropped, so the token after "new" is the
    // class name when there is one.
    $tokens = array_values(array_filter(\PhpToken::tokenize($source), static fn(\PhpToken $token): bool => !$token->isIgnorable()));

    $created = [];

    foreach ($tokens as $index => $token) {
      if (!$token->is(T_NEW)) {
        continue;
      }

      $name = $tokens[$index + 1] ?? NULL;

      if (!$name instanceof \PhpToken || !$name->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STATIC])) {
        $this->fail(sprintf('%s creates an object on line %d from an expression that does not name a class.', $reflection->getName(), $token->line));
      }

      $created[] = [$token->line, $this->resolveClassName($name->text, $reflection, $imports)];
    }

    return $created;
  }

  /**
   * Read the class imports of a file.
   *
   * @param string $source
   *   File source.
   *
   * @return array<string,string>
   *   Fully qualified class names keyed by the alias the file uses for them.
   */
  protected function readImports(string $source): array {
    preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $source, $matches, PREG_SET_ORDER);

    $imports = [];

    foreach ($matches as $match) {
      $segments = explode('\\', $match[1]);
      $imports[$match[2] ?? array_pop($segments)] = $match[1];
    }

    return $imports;
  }

  /**
   * Resolve a class name as written in a class file to its qualified form.
   *
   * @param string $name
   *   Class name as written after "new".
   * @param \ReflectionClass<object> $reflection
   *   Class whose file contains the name.
   * @param array<string,string> $imports
   *   Fully qualified class names keyed by alias.
   *
   * @return string
   *   Fully qualified class name without a leading backslash.
   */
  protected function resolveClassName(string $name, \ReflectionClass $reflection, array $imports): string {
    if (str_starts_with($name, '\\')) {
      return substr($name, 1);
    }

    if (in_array(strtolower($name), ['self', 'static'], TRUE)) {
      return $reflection->getName();
    }

    $segments = explode('\\', $name);
    $alias = array_shift($segments);

    if (isset($imports[$alias])) {
      return implode('\\', [$imports[$alias], ...$segments]);
    }

    return $reflection->getNamespaceName() . '\\' . $name;
  }

  /**
   * Find the method of a class that spans a line of its file.
   *
   * @param \ReflectionClass<object> $reflection
   *   Class to search.
   * @param int $line
   *   Line number in the class file.
   *
   * @return \ReflectionMethod|null
   *   Method spanning the line, or NULL when no method spans it.
   */
  protected function findMethodAtLine(\ReflectionClass $reflection, int $line): ?\ReflectionMethod {
    foreach ($reflection->getMethods() as $method) {
      if ($method->getFileName() === $reflection->getFileName() && $method->getStartLine() <= $line && $line <= $method->getEndLine()) {
        return $method;
      }
    }

    return NULL;
  }

}
