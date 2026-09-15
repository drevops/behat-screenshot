<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use Behat\Behat\Context\Annotation\DocBlockHelper;
use Behat\Behat\Context\Environment\UninitializedContextEnvironment;
use Behat\Behat\Context\Reader\AnnotatedContextReader;
use Behat\Behat\Definition\Context\Annotation\DefinitionAnnotationReader;
use Behat\Behat\Hook\Context\Annotation\HookAnnotationReader;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\StepNode;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Session;
use Behat\Testwork\Call\Callee;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Hook\Call\RuntimeHook;
use Behat\Testwork\Suite\GenericSuite;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotAwareContextInterface;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
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
      $this->assertTrue(str_starts_with($method, $phase), sprintf('Hook method %s() does not start with its phase %s.', $method, $phase));
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

  public function testPublicMethodsAreDeclaredByInterfaceOrRegisteredWithBehat(): void {
    $interface_methods = array_map(static fn(\ReflectionMethod $method): string => $method->getName(), (new \ReflectionClass(ScreenshotAwareContextInterface::class))->getMethods());
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
      $this->assertTrue(in_array($name, $interface_methods, TRUE) || in_array($name, $callee_methods, TRUE), sprintf('Public method %s() is neither declared by %s nor registered with Behat as a hook or step definition.', $name, ScreenshotAwareContextInterface::class));
    }
  }

  public function testGetScreenshotConfigReturnsConfigSetOnContext(): void {
    $config = self::createScreenshotConfig(['dir' => 'test-dir']);
    $screenshot_context = new ScreenshotContext();

    $this->assertSame($screenshot_context, $screenshot_context->setScreenshotConfig($config));
    $this->assertSame($config, $screenshot_context->getScreenshotConfig());
  }

  public function testHookThrowsWhenScreenshotConfigIsNotSet(): void {
    $feature_node = $this->createStub(FeatureNode::class);
    $feature_node->method('hasTag')->willReturn(FALSE);
    $scenario = $this->createStub(ScenarioInterface::class);
    $scenario->method('hasTag')->willReturn(FALSE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('Screenshot configuration has not been set on %s. Enable the DrevOps\BehatScreenshotExtension extension in behat.yml.', ScreenshotContext::class));

    (new ScreenshotContext())->beforeScenarioCheckScreenshotsTag(new BeforeScenarioScope($this->createStub(Environment::class), $feature_node, $scenario));
  }

  public function testBeforeScenarioInitPropagatesDriverStartException(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $scenario = $this->createMock(ScenarioInterface::class);
    $scenario->method('hasTag')->with('javascript')->willReturn(TRUE);
    $session = $this->createMock(Session::class);
    $driver = $this->createMock(Selenium2Driver::class);
    $driver->method('start')->willThrowException(new \RuntimeException('Test Exception.'));
    $session->method('getDriver')->willReturn($driver);

    $this->expectException(\RuntimeException::class);

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession']);
    $screenshot_context->method('getSession')->willReturn($session);

    $scope = new BeforeScenarioScope($env, $feature_node, $scenario);
    $screenshot_context->beforeScenarioInit($scope);
  }

  public function testBeforeStepInitStoresScopeForLaterRetrieval(): void {
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);

    $feature_node->method('getFile')->willReturn(TRUE);
    $screenshot_context = new ScreenshotContext();
    $scope = new BeforeStepScope($env, $feature_node, $step_node);
    $screenshot_context->beforeStepInit($scope);
    $this->assertSame($scope, $screenshot_context->getBeforeStepScope());
  }

  #[DataProvider('dataProviderAfterStepHooksCaptureScreenshotFromStepResultAndConfig')]
  public function testAfterStepHooksCaptureScreenshotFromStepResultAndConfig(bool $passed, bool $should_capture_on_failed, bool $should_capture_on_every_step, bool $has_screenshots_tag, bool $is_animated, bool $should_always_capture_fullscreen, array $expected_configs): void {
    $result = $this->createStub(StepResult::class);
    $result->method('isPassed')->willReturn($passed);
    $scope = new AfterStepScope($this->createStub(Environment::class), $this->createStub(FeatureNode::class), $this->createStub(StepNode::class), $result);

    $configs = [];
    $record_config = static function (array $config) use (&$configs): void {
      $configs[] = $config;
    };

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot']);
    $screenshot_context->expects($this->exactly(count($expected_configs)))->method('captureScreenshot')->willReturnCallback($record_config);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['on_failed' => $should_capture_on_failed, 'always_fullscreen' => $should_always_capture_fullscreen, 'on_every_step' => $should_capture_on_every_step]));
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
      'passed step, on_every_step' => [TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, [['fullscreen' => FALSE]]],
      'passed step, screenshots tag' => [TRUE, FALSE, FALSE, TRUE, FALSE, FALSE, [['fullscreen' => FALSE]]],
      'passed step, animated' => [TRUE, FALSE, FALSE, FALSE, TRUE, FALSE, [['fullscreen' => FALSE]]],
      'passed step, on_every_step and always_fullscreen' => [TRUE, FALSE, TRUE, FALSE, FALSE, TRUE, [['fullscreen' => TRUE]]],
      'passed step, all triggers' => [TRUE, TRUE, TRUE, TRUE, TRUE, FALSE, [['fullscreen' => FALSE]]],
      'failed step, nothing enabled' => [FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, []],
      'failed step, on_failed' => [FALSE, TRUE, FALSE, FALSE, FALSE, FALSE, [['is_failed' => TRUE, 'fullscreen' => FALSE]]],
      'failed step, on_failed and always_fullscreen' => [FALSE, TRUE, FALSE, FALSE, FALSE, TRUE, [['is_failed' => TRUE, 'fullscreen' => TRUE]]],
      'failed step, per-step triggers only' => [FALSE, FALSE, TRUE, TRUE, TRUE, FALSE, []],
      'failed step, all triggers' => [FALSE, TRUE, TRUE, TRUE, TRUE, FALSE, [['is_failed' => TRUE, 'fullscreen' => FALSE]]],
    ];
  }

  public function testIsaveSizedScreenshotIgnoresUnsupportedResize(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession', 'captureScreenshot']);
    $session = $this->createMock(Session::class);
    $exception = new UnsupportedDriverActionException('Not supported', $this->createMock(Selenium2Driver::class));
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
    $screenshot_context->expects($this->once())
      ->method('captureScreenshot')
      ->with(['filename' => 'test-fullscreen-name', 'fullscreen' => TRUE]);
    $screenshot_context->iSaveFullscreenScreenshotWithName('test-fullscreen-name');
  }

  public function testIsaveFullscreenScreenshotRequestsFullscreenCapture(): void {
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['captureScreenshot']);
    $screenshot_context->expects($this->once())
      ->method('captureScreenshot')
      ->with(['fullscreen' => TRUE]);
    $screenshot_context->iSaveFullscreenScreenshot();
  }

  #[DataProvider('dataProviderCaptureScreenshotWritesContentAndSetsLastScreenshotContent')]
  public function testCaptureScreenshotWritesContentAndSetsLastScreenshotContent(
    bool $page_loaded,
    bool $image_supported,
    array $expected_writes,
    ?string $expected_content,
  ): void {
    $driver = $this->createStub(Selenium2Driver::class);

    if ($page_loaded) {
      $driver->method('getContent')->willReturn('test-html-content');
    }
    else {
      $driver->method('getContent')->willThrowException(new DriverException('Test Exception.'));
    }

    if ($image_supported) {
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

    $feature_node = $this->createStub(FeatureNode::class);
    $feature_node->method('getFile')->willReturn('path/to/test.feature');
    $step_node = $this->createStub(StepNode::class);
    $step_node->method('getLine')->willReturn(12);

    $writes = [];
    $record_write = static function (string $filename, string $content) use (&$writes): void {
      $writes[] = [$filename, $content];
    };

    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, ['getSession', 'getCurrentTime', 'writeScreenshotContent']);
    $screenshot_context->method('getSession')->willReturn($session);
    // The second value models a capture that crosses a second boundary.
    $screenshot_context->method('getCurrentTime')->willReturn(1700000000, 1700000001);
    $screenshot_context->expects($this->exactly(2))->method('writeScreenshotContent')->willReturnCallback($record_write);
    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig());
    $screenshot_context->beforeStepInit(new BeforeStepScope($this->createStub(Environment::class), $feature_node, $step_node));

    $screenshot_context->captureScreenshot();

    $this->assertSame([['1700000000.test.feature_12.html', 'test-html-content'], ['1700000000.test.feature_12.png', 'test-png-content']], $writes);
  }

  #[DataProvider('dataProviderWriteScreenshotContentCreatesDirectoryAndWritesFile')]
  public function testWriteScreenshotContentCreatesDirectoryAndWritesFile(string $filename, string $content): void {
    $dir = sys_get_temp_dir();
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
    $screenshot_context = $this->createPartialMock(ScreenshotContext::class, [
      'getBeforeStepScope',
      'getSession',
    ]);
    $session = $this->createMock(Session::class);

    if ($url instanceof \Exception) {
      $session->method('getCurrentUrl')->willThrowException($url);
    }
    else {
      $session->method('getCurrentUrl')->willReturn($url);
    }

    $screenshot_context->method('getSession')->willReturn($session);
    $env = $this->createMock(Environment::class);
    $feature_node = $this->createMock(FeatureNode::class);
    $step_node = $this->createMock(StepNode::class);
    $step_node->method('getText')->willReturn($step_text);
    $step_node->method('getLine')->willReturn($step_line);
    $feature_node->method('getFile')->willReturn($feature_file);
    $scope = new BeforeStepScope($env, $feature_node, $step_node);
    $screenshot_context->method('getBeforeStepScope')->willReturn($scope);

    $screenshot_context->setScreenshotConfig(self::createScreenshotConfig(['failed_prefix' => $failed_prefix, 'filename_pattern' => $filename_pattern, 'filename_pattern_failed' => $filename_pattern_failed]));

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

  /**
   * Read the hooks and step definitions Behat registers for ScreenshotContext.
   *
   * @return array<int,\Behat\Testwork\Call\Callee>
   *   Hook and step definition callees.
   */
  protected function readBehatCallees(): array {
    $reader = new AnnotatedContextReader(new DocBlockHelper());
    $reader->registerAnnotationReader(new HookAnnotationReader());
    $reader->registerAnnotationReader(new DefinitionAnnotationReader());

    return $reader->readContextCallees(new UninitializedContextEnvironment(new GenericSuite('default', [])), ScreenshotContext::class);
  }

}
