<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use Behat\Behat\Context\Annotation\DocBlockHelper;
use Behat\Behat\Context\Environment\UninitializedContextEnvironment;
use Behat\Behat\Context\Reader\AttributeContextReader;
use Behat\Behat\Definition\Call\RuntimeDefinition;
use Behat\Behat\Definition\Context\Attribute\DefinitionAttributeReader;
use Behat\Behat\Hook\Context\Attribute\HookAttributeReader;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Session;
use Behat\Testwork\Call\Callee;
use Behat\Testwork\Hook\Call\RuntimeHook;
use Behat\Testwork\Suite\GenericSuite;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotAwareContextInterface;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use DrevOps\BehatScreenshotExtension\Tests\Traits\BehatScopeTrait;
use DrevOps\BehatScreenshotExtension\Tests\Traits\EnvironmentVariableTrait;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ScreenshotConfigTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Test ScreenshotContext.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextTest extends TestCase {

  use BehatScopeTrait;
  use EnvironmentVariableTrait;
  use ReflectionTrait;
  use ScreenshotConfigTrait;

  public function testBehatRegistersHooksOnPhasePrefixedMethods(): void {
    $hooks = [];

    foreach ($this->readBehatCallees() as $callee) {
      if (!$callee instanceof RuntimeHook) {
        continue;
      }

      $method = $callee->getReflection()->getName();
      $phase = lcfirst($callee->getName());

      if ($phase === '') {
        $this->fail(sprintf('Hook method %s() is registered without a phase.', $method));
      }

      $this->assertStringStartsWith($phase, $method, sprintf('Hook method %s() does not start with its phase %s.', $method, $phase));
      $hooks[] = $method . ' ' . $callee;
    }

    sort($hooks);

    $this->assertSame([
      'afterScenarioAnimate AfterScenario',
      'afterStepCaptureFailedScreenshot AfterStep',
      'afterStepCaptureScreenshot AfterStep',
      'beforeScenarioCheckScreenshotsTag BeforeScenario',
      'beforeScenarioInit BeforeScenario @javascript',
      'beforeStepInit BeforeStep',
    ], $hooks);
  }

  public function testBehatRegistersStepDefinitions(): void {
    $definitions = [];

    foreach ($this->readBehatCallees() as $callee) {
      if ($callee instanceof RuntimeDefinition) {
        $definitions[] = $callee->getReflection()->getName() . ' ' . $callee;
      }
    }

    sort($definitions);

    $this->assertSame([
      'iSaveFullscreenScreenshot Then save fullscreen screenshot',
      'iSaveFullscreenScreenshot When I save fullscreen screenshot',
      'iSaveFullscreenScreenshotWithName Then save fullscreen screenshot with name :filename',
      'iSaveFullscreenScreenshotWithName When I save fullscreen screenshot with name :filename',
      'iSaveScreenshot Then save screenshot',
      'iSaveScreenshot When I save screenshot',
      'iSaveScreenshotWithName Then save screenshot with name :filename',
      'iSaveScreenshotWithName When I save screenshot with name :filename',
      'iSaveSizedScreenshot Then save :width x :height screenshot',
      'iSaveSizedScreenshot When I save :width x :height screenshot',
    ], $definitions);
  }

  public function testBehatRegistersOverridingMethodsWithoutAttributes(): void {
    $subclass = new class() extends ScreenshotContext {

      #[\Override]
      public function afterStepCaptureScreenshot(AfterStepScope $scope): void {
        $this->captureScreenshot();
      }

      #[\Override]
      public function iSaveScreenshot(): void {
        $this->captureScreenshot(['is_fullscreen' => TRUE]);
      }

    };

    $this->assertSame($this->describeBehatCallees(ScreenshotContext::class), $this->describeBehatCallees($subclass::class));
  }

  public function testDeclaresNoBehatAnnotations(): void {
    $annotations = [];
    $annotation_pattern = '/^\s*\*\s*(@(?:given|when|then|transform|(?:before|after)(?:suite|feature|scenario|step))\b.*)$/im';

    foreach ((new \ReflectionClass(ScreenshotContext::class))->getMethods() as $method) {
      if ($method->getDeclaringClass()->getName() !== ScreenshotContext::class) {
        continue;
      }

      preg_match_all($annotation_pattern, (string) $method->getDocComment(), $matches);

      if (!empty($matches[1])) {
        $annotations[$method->getName()] = $matches[1];
      }
    }

    $this->assertSame([], $annotations);
  }

  public function testPublicMethodsAreDeclaredByInterfaceOrRegisteredWithBehat(): void {
    $interface_reflection = new \ReflectionClass(ScreenshotAwareContextInterface::class);
    $interface_methods = array_map(static fn(\ReflectionMethod $method): string => $method->getName(), $interface_reflection->getMethods());
    sort($interface_methods);

    $this->assertSame([
      'appendInfo',
      'captureScreenshot',
      'getBeforeStepScope',
      'getScreenshot',
      'getScreenshotConfig',
      'getScreenshotFullscreen',
      'renderInfo',
      'setScreenshotConfig',
      'writeScreenshotContent',
    ], $interface_methods);

    $callee_methods = array_map(static fn(Callee $callee): string => $callee->getReflection()->getName(), $this->readBehatCallees());

    foreach ((new \ReflectionClass(ScreenshotContext::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
      // Mink's RawMinkContext declares its own public API.
      if ($method->getDeclaringClass()->getName() !== ScreenshotContext::class) {
        continue;
      }

      $name = $method->getName();
      $is_registered = in_array($name, $interface_methods, TRUE) || in_array($name, $callee_methods, TRUE);
      $this->assertTrue(
        $is_registered,
        sprintf(
          'Public method %s() is neither declared by %s nor registered with Behat as a hook or step definition.',
          $name,
          ScreenshotAwareContextInterface::class,
        ),
      );
    }
  }

  public function testGetScreenshotConfigReturnsConfigSetOnContext(): void {
    $config = self::createScreenshotConfig(['dir' => 'test-dir']);
    $screenshot_context = new ScreenshotContext();

    $this->assertSame($screenshot_context, $screenshot_context->setScreenshotConfig($config));
    $this->assertSame($config, $screenshot_context->getScreenshotConfig());
  }

  public function testHookThrowsWhenScreenshotConfigIsNotSet(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf(
      'Screenshot configuration has not been set on %s.'
      . ' Enable the DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension extension in the Behat configuration.',
      ScreenshotContext::class,
    ));

    (new ScreenshotContext())->beforeScenarioCheckScreenshotsTag($this->createBeforeScenarioScope());
  }

  #[DataProvider('dataProviderIsTaggedMatchesTagWithOrWithoutPrefix')]
  public function testIsTaggedMatchesTagWithOrWithoutPrefix(array $node_tags, string $tag, bool $expected): void {
    $node = $this->createStub(FeatureNode::class);
    $node->method('getTags')->willReturn($node_tags);

    $this->assertSame($expected, self::callProtectedMethod(new ScreenshotContext(), 'isTagged', [$node, $tag]));
  }

  public static function dataProviderIsTaggedMatchesTagWithOrWithoutPrefix(): array {
    return [
      'no tags' => [[], 'screenshots', FALSE],
      'tag without prefix' => [['screenshots'], 'screenshots', TRUE],
      'tag with prefix' => [['@screenshots'], 'screenshots', TRUE],
      'tag among other tags' => [['@smoke', '@screenshots', '@api'], 'screenshots', TRUE],
      'tag with colons and prefix' => [['@screenshots:animated:skip'], 'screenshots:animated:skip', TRUE],
      'different tag' => [['@javascript'], 'screenshots', FALSE],
      'longer tag starting with the name' => [['@screenshots:animated'], 'screenshots', FALSE],
      'longer tag ending with the name' => [['@no-screenshots'], 'screenshots', FALSE],
      'name in a different case' => [['@Screenshots'], 'screenshots', FALSE],
    ];
  }

  public function testBeforeScenarioInitPropagatesDriverStartException(): void {
    $session = $this->createStub(Session::class);
    $driver = $this->createStub(Selenium2Driver::class);
    $driver->method('start')->willThrowException(new \RuntimeException('Test Exception.'));
    $session->method('getDriver')->willReturn($driver);

    $this->expectException(\RuntimeException::class);

    $screenshot_context = $this->getStubBuilder(ScreenshotContext::class)->onlyMethods(['getSession'])->getStub();
    $screenshot_context->method('getSession')->willReturn($session);

    $scope = $this->createBeforeScenarioScope();
    $screenshot_context->beforeScenarioInit($scope);
  }

  public function testBeforeStepInitStoresScopeForLaterRetrieval(): void {
    $screenshot_context = new ScreenshotContext();
    $scope = $this->createBeforeStepScope();
    $screenshot_context->beforeStepInit($scope);
    $this->assertSame($scope, $screenshot_context->getBeforeStepScope());
  }

  #[DataProvider('dataProviderAfterStepHooksCaptureScreenshotFromStepResultAndConfig')]
  public function testAfterStepHooksCaptureScreenshotFromStepResultAndConfig(
    bool $is_passed,
    bool $should_capture_on_failed,
    bool $should_capture_on_every_step,
    bool $has_screenshots_tag,
    bool $is_animated,
    bool $should_always_capture_fullscreen,
    array $expected_configs,
  ): void {
    $scope = $this->createAfterStepScope($is_passed);

    $configs = [];
    $record_config = static function (array $config) use (&$configs): void {
      $configs[] = $config;
    };

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot']);
    $screenshot_context->expects($this->exactly(count($expected_configs)))->method('captureScreenshot')->willReturnCallback($record_config);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig([
      'on_failed' => $should_capture_on_failed,
      'always_fullscreen' => $should_always_capture_fullscreen,
      'on_every_step' => $should_capture_on_every_step,
    ]));
    self::setProtectedValue($screenshot_context, 'scenarioHasScreenshotsTag', $has_screenshots_tag);
    self::setProtectedValue($screenshot_context, 'scenarioIsAnimated', $is_animated);

    $screenshot_context->afterStepCaptureFailedScreenshot($scope);
    $screenshot_context->afterStepCaptureScreenshot($scope);

    $this->assertSame($expected_configs, $configs);
  }

  public static function dataProviderAfterStepHooksCaptureScreenshotFromStepResultAndConfig(): array {
    return [
      'passed step, nothing enabled' => [TRUE, FALSE, FALSE, FALSE, FALSE, FALSE, []],
      'passed step, on_failed' => [TRUE, TRUE, FALSE, FALSE, FALSE, FALSE, []],
      'passed step, on_every_step' => [TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, [['is_fullscreen' => FALSE]]],
      'passed step, screenshots tag' => [TRUE, FALSE, FALSE, TRUE, FALSE, FALSE, [['is_fullscreen' => FALSE]]],
      'passed step, animated' => [TRUE, FALSE, FALSE, FALSE, TRUE, FALSE, [['is_fullscreen' => FALSE]]],
      'passed step, on_every_step and always_fullscreen' => [TRUE, FALSE, TRUE, FALSE, FALSE, TRUE, [['is_fullscreen' => TRUE]]],
      'passed step, all triggers' => [TRUE, TRUE, TRUE, TRUE, TRUE, FALSE, [['is_fullscreen' => FALSE]]],
      'failed step, nothing enabled' => [FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, []],
      'failed step, on_failed' => [FALSE, TRUE, FALSE, FALSE, FALSE, FALSE, [['is_failed' => TRUE, 'is_fullscreen' => FALSE]]],
      'failed step, on_failed and always_fullscreen' => [FALSE, TRUE, FALSE, FALSE, FALSE, TRUE, [['is_failed' => TRUE, 'is_fullscreen' => TRUE]]],
      'failed step, per-step triggers only' => [FALSE, FALSE, TRUE, TRUE, TRUE, FALSE, []],
      'failed step, all triggers' => [FALSE, TRUE, TRUE, TRUE, TRUE, FALSE, [['is_failed' => TRUE, 'is_fullscreen' => FALSE]]],
    ];
  }

  public function testIsaveSizedScreenshotIgnoresUnsupportedResize(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession', 'captureScreenshot']);
    $session = $this->createStub(Session::class);
    $exception = new UnsupportedDriverActionException('Not supported', $this->createStub(Selenium2Driver::class));
    $session->method('resizeWindow')->willThrowException($exception);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->expects($this->once())->method('captureScreenshot');
    $screenshot_context->iSaveSizedScreenshot();
  }

  public function testIsaveScreenshotWithNameDelegatesToCaptureScreenshot(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot']);
    $screenshot_context->expects($this->once())->method('captureScreenshot');
    $screenshot_context->iSaveScreenshotWithName('test-filename');
  }

  public function testIsaveFullscreenScreenshotWithNamePassesNameAndFullscreen(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot']);
    $screenshot_context->expects($this->once())->method('captureScreenshot')->with(['filename' => 'test-fullscreen-name', 'is_fullscreen' => TRUE]);
    $screenshot_context->iSaveFullscreenScreenshotWithName('test-fullscreen-name');
  }

  public function testIsaveFullscreenScreenshotRequestsFullscreenCapture(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot']);
    $screenshot_context->expects($this->once())->method('captureScreenshot')->with(['is_fullscreen' => TRUE]);
    $screenshot_context->iSaveFullscreenScreenshot();
  }

  #[DataProvider('dataProviderCaptureScreenshotWritesContentAndSetsLastScreenshotContent')]
  public function testCaptureScreenshotWritesContentAndSetsLastScreenshotContent(
    bool $is_page_loaded,
    bool $is_image_supported,
    array $expected_writes,
    ?string $expected_content,
  ): void {
    $driver = $this->createStub(Selenium2Driver::class);

    if ($is_page_loaded) {
      $driver->method('getContent')->willReturn('test-html-content');
    }
    else {
      $driver->method('getContent')->willThrowException(new DriverException('Test Exception.'));
    }

    if ($is_image_supported) {
      $driver->method('getScreenshot')->willReturn('test-png-content');
    }
    else {
      $driver->method('getScreenshot')->willThrowException(new UnsupportedDriverActionException('Not supported', $driver));
    }

    $session = $this->createStub(Session::class);
    $session->method('getDriver')->willReturn($driver);

    $writes = [];
    $record_write = static function (string $filename, string $content) use (&$writes): void {
      $writes[] = [$filename, $content];
    };

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession', 'makeFilename', 'writeScreenshotContent']);
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('makeFilename')->willReturnCallback(static fn(string $ext): string => 'test.' . $ext);
    $screenshot_context->expects($this->exactly(count($expected_writes)))->method('writeScreenshotContent')->willReturnCallback($record_write);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());

    // Seed an earlier capture's content, so a missing reset is detected.
    self::setProtectedValue($screenshot_context, 'lastScreenshotContent', 'test-previous-png-content');

    $screenshot_context->captureScreenshot();

    $this->assertSame($expected_writes, $writes);
    $this->assertSame($expected_content, self::getProtectedValue($screenshot_context, 'lastScreenshotContent'));
  }

  public static function dataProviderCaptureScreenshotWritesContentAndSetsLastScreenshotContent(): array {
    return [
      'page loaded, image supported' => [TRUE, TRUE, [['test.html', 'test-html-content'], ['test.png', 'test-png-content']], 'test-png-content'],
      'page loaded, image unsupported' => [TRUE, FALSE, [['test.html', 'test-html-content']], NULL],
      'page not loaded, image supported' => [FALSE, TRUE, [], NULL],
      'page not loaded, image unsupported' => [FALSE, FALSE, [], NULL],
    ];
  }

  public function testCaptureScreenshotNamesHtmlAndPngFromOneTimestamp(): void {
    $driver = $this->createStub(Selenium2Driver::class);
    $driver->method('getContent')->willReturn('test-html-content');
    $driver->method('getScreenshot')->willReturn('test-png-content');

    $session = $this->createStub(Session::class);
    $session->method('getDriver')->willReturn($driver);

    $writes = [];
    $record_write = static function (string $filename, string $content) use (&$writes): void {
      $writes[] = [$filename, $content];
    };

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession', 'getCurrentTime', 'writeScreenshotContent']);
    $screenshot_context->method('getSession')->willReturn($session);
    // The second value models a capture that crosses a second boundary.
    $screenshot_context->method('getCurrentTime')->willReturnOnConsecutiveCalls(1700000000, 1700000001);
    $screenshot_context->expects($this->exactly(2))->method('writeScreenshotContent')->willReturnCallback($record_write);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    $screenshot_context->beforeStepInit($this->createBeforeStepScope('path/to/test.feature', 12));

    $screenshot_context->captureScreenshot();

    $this->assertSame([['1700000000.test.feature_12.html', 'test-html-content'], ['1700000000.test.feature_12.png', 'test-png-content']], $writes);
  }

  #[DataProvider('dataProviderCaptureScreenshotRejectsUnsupportedConfigKeys')]
  public function testCaptureScreenshotRejectsUnsupportedConfigKeys(array $config, string $expected_message): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession']);
    $screenshot_context->expects($this->never())->method('getSession');

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected_message);

    $screenshot_context->captureScreenshot($config);
  }

  public static function dataProviderCaptureScreenshotRejectsUnsupportedConfigKeys(): array {
    return [
      'unsupported key' => [
        ['fullscreen' => TRUE],
        'Unsupported screenshot configuration keys: fullscreen. Supported keys: filename, is_failed, is_fullscreen.',
      ],
      'unsupported keys among supported ones' => [
        ['filename' => 'test', 'size' => 1, 'is_failed' => TRUE, 'mode' => 'test'],
        'Unsupported screenshot configuration keys: size, mode. Supported keys: filename, is_failed, is_fullscreen.',
      ],
      'positional value' => [['test'], 'Unsupported screenshot configuration keys: 0. Supported keys: filename, is_failed, is_fullscreen.'],
    ];
  }

  #[DataProvider('dataProviderWriteScreenshotContentCreatesDirectoryAndWritesFile')]
  public function testWriteScreenshotContentCreatesDirectoryAndWritesFile(string $filename, string $content): void {
    $dir = dirname(__DIR__, 3) . '/.logs/unit';

    if (!is_dir($dir) && !mkdir($dir, 0755, TRUE) && !is_dir($dir)) {
      throw new \RuntimeException(sprintf('Unable to create the directory %s.', $dir));
    }

    $filesystem = $this->createMock(Filesystem::class);
    $filesystem->expects($this->once())->method('mkdir')->with($dir, 0755);

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['createFilesystem']);
    $screenshot_context->expects($this->once())->method('createFilesystem')->willReturn($filesystem);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['dir' => $dir]));

    $screenshot_context->writeScreenshotContent($filename, $content);

    $filepath = $dir . DIRECTORY_SEPARATOR . $filename;
    $this->assertFileExists($filepath);
    $this->assertSame($content, file_get_contents($filepath));

    unlink($filepath);
  }

  public static function dataProviderWriteScreenshotContentCreatesDirectoryAndWritesFile(): array {
    return [
      'first file' => ['test-save-screenshot-1.txt', 'test-content-1'],
      'second file' => ['test-save-screenshot-2.txt', 'test-content-2'],
    ];
  }

  public function testCreateFilesystemCreatesNewInstanceOnEveryCall(): void {
    $screenshot_context = new ScreenshotContext();

    $first = self::callProtectedMethod($screenshot_context, 'createFilesystem');
    $second = self::callProtectedMethod($screenshot_context, 'createFilesystem');

    $this->assertInstanceOf(Filesystem::class, $first);
    $this->assertInstanceOf(Filesystem::class, $second);
    $this->assertNotSame($first, $second);
  }

  public function testGetCurrentTimeReturnsPositiveInteger(): void {
    $screenshot_context = new ScreenshotContext();
    $time = self::callProtectedMethod($screenshot_context, 'getCurrentTime');
    $this->assertIsInt($time);
    $this->assertGreaterThan(0, $time);
  }

  #[DataProvider('dataProviderMakeFilenameReplacesTokensInPatterns')]
  public function testMakeFilenameReplacesTokensInPatterns(
    string $ext,
    mixed $filename,
    bool $is_failed,
    mixed $url,
    int $timestamp,
    string $step_text,
    int $step_line,
    string $feature_file,
    string $failed_prefix,
    string $filename_pattern,
    string $filename_pattern_failed,
    string $expected,
  ): void {
    $screenshot_context = $this->getStubBuilder(ScreenshotContext::class)->onlyMethods(['getBeforeStepScope', 'getSession'])->getStub();
    $session = $this->createStub(Session::class);

    if ($url instanceof \Exception) {
      $session->method('getCurrentUrl')->willThrowException($url);
    }
    else {
      $session->method('getCurrentUrl')->willReturn($url);
    }

    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('getBeforeStepScope')->willReturn($this->createBeforeStepScope($feature_file, $step_line, $step_text));

    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig([
      'failed_prefix' => $failed_prefix,
      'filename_pattern' => $filename_pattern,
      'filename_pattern_failed' => $filename_pattern_failed,
    ]));

    $filename_processed = self::callProtectedMethod($screenshot_context, 'makeFilename', [$ext, $timestamp, $filename, $is_failed]);

    $this->assertSame($expected, $filename_processed);
  }

  public static function dataProviderMakeFilenameReplacesTokensInPatterns(): array {
    return [
      'no filename uses default pattern' => [
        'html',
        NULL,
        FALSE,
        'test-url',
        1721791661,
        'test-step-name',
        12,
        'test-feature-file',
        'failed_',
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.test-feature-file.feature_12.html',
      ],
      'custom pattern with step name' => [
        'png',
        '{datetime:U}.{feature_file}.feature_{step_name}.feature_{step_line}.{ext}',
        FALSE,
        'test-url',
        1721791661,
        'test-step-name',
        12,
        'test-feature-file',
        'failed_',
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.test-feature-file.feature_test-step-name.feature_12.png',
      ],
      'pattern without ext token' => [
        'png',
        '{datetime:U}.{feature_file}.feature_{step_name}.feature_{step_line}',
        FALSE,
        'test-url',
        1721791661,
        'test-step-name',
        12,
        'test-feature-file',
        'failed_',
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.test-feature-file.feature_test-step-name.feature_12.png',
      ],
      'failed step uses failed pattern' => [
        'png',
        '{datetime:U}.{feature_file}.feature_{step_name}.feature_{step_line}',
        TRUE,
        'test-url',
        1721791661,
        'test-step-name',
        12,
        'test-feature-file',
        'failed_',
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.failed_test-feature-file.feature_12.png',
      ],
      'url unavailable' => [
        'png',
        '{datetime:U}.{feature_file}.feature_{step_name}.feature_{step_line}',
        FALSE,
        new \Exception('test'),
        1721791661,
        'test-step-name',
        12,
        'test-feature-file',
        'failed_',
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        '1721791661.test-feature-file.feature_test-step-name.feature_12.png',
      ],
    ];
  }

  public function testMakeFilenameReplacesUrlHostFromEnvironment(): void {
    $this->setEnvironmentVariable(ScreenshotContext::ENV_TOKEN_HOST, 'example.org');

    $scope = $this->createBeforeStepScope('test-feature-file', 123, 'test-step');

    $session = $this->createStub(Session::class);
    $session->method('getCurrentUrl')->willReturn('http://localhost:8080/test-page');

    $screenshot_context = $this->getStubBuilder(ScreenshotContext::class)->onlyMethods(['getSession', 'getBeforeStepScope'])->getStub();
    $screenshot_context->method('getSession')->willReturn($session);
    $screenshot_context->method('getBeforeStepScope')->willReturn($scope);

    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig([
      'filename_pattern' => '{url}.{ext}',
      'filename_pattern_failed' => '{failed_prefix}{url}.{ext}',
    ]));

    $result = self::callProtectedMethod($screenshot_context, 'makeFilename', ['png', 12345678, NULL, FALSE]);
    $this->assertIsString($result);

    // The {url} token collapses each run of characters other than word
    // characters and hyphens into a single underscore.
    $this->assertStringContainsString('example_org', $result);
    $this->assertStringNotContainsString('localhost', $result);
  }

  /**
   * Read the hooks and step definitions Behat registers for a context.
   *
   * @param class-string<\DrevOps\BehatScreenshotExtension\Context\ScreenshotContext> $class
   *   Context class to read.
   *
   * @return array<int,\Behat\Testwork\Call\Callee>
   *   Hook and step definition callees.
   */
  protected function readBehatCallees(string $class = ScreenshotContext::class): array {
    $reader = new AttributeContextReader();
    $reader->registerAttributeReader(new HookAttributeReader(new DocBlockHelper()));
    $reader->registerAttributeReader(new DefinitionAttributeReader(new DocBlockHelper()));

    return $reader->readContextCallees(new UninitializedContextEnvironment(new GenericSuite('default', [])), $class);
  }

  /**
   * Describe the hooks and step definitions Behat registers for a context.
   *
   * @param class-string<\DrevOps\BehatScreenshotExtension\Context\ScreenshotContext> $class
   *   Context class to read.
   *
   * @return array<int,string>
   *   Method names followed by their hook or step definition, sorted.
   */
  protected function describeBehatCallees(string $class): array {
    $descriptions = [];

    foreach ($this->readBehatCallees($class) as $callee) {
      if ($callee instanceof RuntimeHook || $callee instanceof RuntimeDefinition) {
        $descriptions[] = $callee->getReflection()->getName() . ' ' . $callee;
      }
    }

    sort($descriptions);

    return $descriptions;
  }

}
