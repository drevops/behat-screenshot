<?php

declare(strict_types=1);

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use PHPUnit\Framework\Assert;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Additional shortcut steps for BehatCliContext.
 */
trait BehatCliTrait {

  /**
   * Write the feature context used by the inner run.
   */
  #[BeforeScenario('@behatcli')]
  public function behatCliBeforeScenarioWriteFeatureContext(): void {
    $traits = [
      'tests/behat/bootstrap/ScreenshotTrait.php' => 'ScreenshotTrait',
    ];
    $this->behatCliWriteFeatureContextFile($traits);
  }

  /**
   * Copy the inner run's screenshots and print its output in debug mode.
   */
  #[AfterScenario('@behatcli')]
  public function behatCliAfterScenarioPrintOutput(AfterScenarioScope $scope): void {
    $this->behatCliCopyScreenshots($scope);

    if (self::behatCliIsDebug()) {
      print '-------------------- OUTPUT START --------------------' . PHP_EOL;
      print PHP_EOL;
      print $this->getOutput();
      print PHP_EOL;
      print '-------------------- OUTPUT FINISH -------------------' . PHP_EOL;
    }
  }

  /**
   * Copy the inner Behat run's screenshots into the outer run's directory.
   */
  protected function behatCliCopyScreenshots(AfterScenarioScope $scope): void {
    $context = $scope->getEnvironment()->getContext(ScreenshotContext::class);
    $src = $this->workingDir . DIRECTORY_SEPARATOR . 'screenshots';

    if (!$context instanceof ScreenshotContext || !is_dir($src)) {
      return;
    }

    $dst = $context->getScreenshotConfig()->dir . '/behatcli_screenshots';

    if (!is_readable($dst)) {
      mkdir($dst, 0777, TRUE);
    }

    $finder = Finder::create();
    $filesystem = new Filesystem();

    foreach ($finder->in($src)->files() as $file) {
      $filesystem->copy($file->getRealPath(), $dst . DIRECTORY_SEPARATOR . $file->getFilename());
    }
  }

  /**
   * Create FeatureContextTest.php file.
   *
   * @param array $traits
   *   Optional array of trait classes.
   *
   * @return string
   *   Path to written file.
   */
  protected function behatCliWriteFeatureContextFile(array $traits = []): string {
    $tokens = [
      '{{USE_DECLARATION}}' => '',
      '{{USE_IN_CLASS}}' => '',
    ];

    foreach ($traits as $path => $trait) {
      $trait_name = $trait;

      if (str_contains($trait, '\\')) {
        $tokens['{{USE_DECLARATION}}'] .= sprintf('use %s;' . PHP_EOL, $trait);
        $trait_name_parts = explode('\\', $trait);
        $trait_name = end($trait_name_parts);
      }

      $tokens['{{USE_IN_CLASS}}'] .= sprintf('use %s;' . PHP_EOL, $trait_name);

      if (is_string($path) && file_exists($path)) {
        $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'features/bootstrap/' . basename($path);
        $this->createFile($filename, file_get_contents($path));
      }
    }

    $content = <<<'EOL'
<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\MinkExtension\Context\MinkContext;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;

{{USE_DECLARATION}}

class FeatureContextTest extends MinkContext implements Context {
  {{USE_IN_CLASS}}

  /**
   * Base URL for JavaScript scenarios.
   */
  protected string $javascriptBaseUrl;

  /**
   * FeatureContext constructor.
   *
   * @param array $parameters Array of parameters from config.
   */
  public function __construct($parameters) {
    $this->screenshotInitParams($parameters);

    // Override any real host in the screenshot token.
    putenv(ScreenshotContext::ENV_TOKEN_HOST . '=example.com');
    $this->javascriptBaseUrl = getenv('BEHAT_JAVASCRIPT_BASE_URL') ?: 'http://host.docker.internal:8888';
  }

  /**
   * Update base URL for JavaScript scenarios.
   */
  #[BeforeScenario('@javascript&&~@skip-base-url-rewrite')]
  public function beforeScenarioUpdateBaseUrl(BeforeScenarioScope $scope): void {
    $environment = $scope->getEnvironment();

    if (!$environment instanceof InitializedContextEnvironment) {
      return;
    }

    foreach ($environment->getContexts() as $context) {
      if ($context instanceof RawMinkContext) {
        $context->setMinkParameter('base_url', $this->javascriptBaseUrl);
      }
    }
  }

  /**
   * Go to the phpserver test page.
   */
  #[Given('/^(?:|I )am on (?:|the )phpserver test page$/')]
  #[When('/^(?:|I )go to (?:|the )phpserver test page$/')]
  public function goToPhpServerTestPage()
  {
    $this->visitPath('/screenshot.html');
  }

  /**
   * Throw an exception with the given message.
   */
  #[Given('I throw test exception with message :message')]
  public function throwTestException($message) {
    throw new \RuntimeException($message);
  }

  /**
   * Assert that an environment variable holds the given value.
   */
  #[Then('the environment variable :name should have the value :value')]
  public function assertEnvironmentVariableValue($name, $value) {
    $actual = getenv($name);

    if ($actual === FALSE) {
      throw new \Exception(sprintf('The environment variable "%s" is not set.', $name));
    }

    if ($actual !== $value) {
      throw new \Exception(sprintf('The environment variable "%s" has the value "%s", but "%s" was expected.', $name, $actual, $value));
    }
  }

}
EOL;

    $content = strtr($content, $tokens);
    $content = preg_replace('/\{\{[^\}]+\}\}/', '', $content);

    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'features/bootstrap/FeatureContextTest.php';
    $this->createFile($filename, $content);

    if (self::behatCliIsDebug()) {
      self::behatCliPrintFileContents($filename, 'FeatureContextTest.php');
    }

    return $filename;
  }

  /**
   * Write a stub feature with the given scenario steps.
   */
  #[Given('/^scenario steps(?: tagged with "([^"]*)")?:$/')]
  public function behatCliWriteScenarioSteps(PyStringNode $content, string $tags = ''): void {
    $content = strtr((string) $content, ["'''" => '"""']);

    $content_lines = explode(PHP_EOL, $content);

    foreach ($content_lines as $k => $content_line) {
      $content_lines[$k] = str_repeat(' ', 4) . trim($content_line);
    }

    $content = implode(PHP_EOL, $content_lines);

    $tokens = [
      '{{SCENARIO_CONTENT}}' => $content,
      '{{ADDITIONAL_TAGS}}' => $tags,
    ];

    $content = <<<'EOL'
@behatcli
Feature: Stub feature
  {{ADDITIONAL_TAGS}}
  Scenario: Stub scenario title
{{SCENARIO_CONTENT}}
EOL;

    $content = strtr($content, $tokens);
    $content = preg_replace('/\{\{[^\}]+\}\}/', '', $content);

    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'features/stub.feature';
    $this->createFile($filename, $content);

    if (self::behatCliIsDebug()) {
      self::behatCliPrintFileContents($filename, 'Feature Stub');
    }
  }

  /**
   * Write the given content as the Behat configuration of the inner run.
   */
  #[Given('behat configuration:')]
  public function behatCliWriteBehatConfig(PyStringNode $content): void {
    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'behat.php';
    $this->createFile($filename, (string) $content);

    if (self::behatCliIsDebug()) {
      self::behatCliPrintFileContents($filename, 'Behat Config');
    }
  }

  /**
   * Copy the screenshot fixture page into the working directory.
   */
  #[Given('screenshot fixture')]
  public function behatCliWriteScreenshotFixture(): void {
    $filename = 'tests/behat/fixtures/screenshot.html';
    $src = __DIR__ . '/../fixtures/screenshot.html';

    $this->createFile($this->workingDir . '/' . $filename, file_get_contents($src));
  }

  /**
   * Copy the short screenshot fixture page into the working directory.
   */
  #[Given('short screenshot fixture')]
  public function behatCliWriteScreenshotShortFixture(): void {
    $filename = 'tests/behat/fixtures/screenshot.html';
    $src = __DIR__ . '/../fixtures/screenshot_short.html';

    $this->createFile($this->workingDir . '/' . $filename, file_get_contents($src));
  }

  /**
   * Assert that the run failed with an assertion error message.
   */
  #[Then('it should fail with an error:')]
  public function behatCliAssertFailWithError(PyStringNode $message): void {
    $this->itShouldFail('fail');
    Assert::assertStringContainsString(trim((string) $message), $this->getOutput());
    // Enforce \Exception for all assertion exceptions. Non-assertion
    // exceptions should be thrown as \RuntimeException.
    Assert::assertStringContainsString('Exception)', $this->getOutput());
    Assert::assertStringNotContainsString('(RuntimeException)', $this->getOutput());
  }

  /**
   * Assert that the run failed with a runtime exception message.
   */
  #[Then('it should fail with an exception:')]
  public function behatCliAssertFailWithException(PyStringNode $message): void {
    $this->itShouldFail('fail');
    Assert::assertStringContainsString(trim((string) $message), $this->getOutput());
    // Enforce \RuntimeException for all non-assertion exceptions. Assertion
    // exceptions should be thrown as \Exception.
    Assert::assertStringContainsString('(RuntimeException)', $this->getOutput());
  }

  /**
   * Adds an environment variable to the inner Behat run.
   */
  #[When('I add the environment variable :name with the value :value')]
  public function behatCliAddEnvironmentVariable(string $name, string $value): void {
    $this->env[$name] = $value;
  }

  /**
   * Helper to print file contents.
   */
  protected static function behatCliPrintFileContents(string $filename, string $title = ''): void {
    if (!is_readable($filename)) {
      throw new \RuntimeException(sprintf('Unable to access file "%s".', $filename));
    }

    $content = file_get_contents($filename);

    print sprintf('-------------------- %s START --------------------', $title) . PHP_EOL;
    print $filename . PHP_EOL;
    print_r($content);
    print PHP_EOL;
    print sprintf('-------------------- %s FINISH --------------------', $title) . PHP_EOL;
  }

  /**
   * Helper to check if debug mode is enabled.
   *
   * @return string|false
   *   Value of the BEHAT_CLI_DEBUG environment variable, or FALSE when it is
   *   not set.
   */
  protected static function behatCliIsDebug(): string|false {
    return getenv('BEHAT_CLI_DEBUG');
  }

  /**
   * Checks whether a file wildcard at provided path exists.
   *
   * @param string $wildcard
   *   Filename with a wildcard.
   */
  #[Given('/^behat cli file wildcard "([^"]*)" should exist$/')]
  public function behatCliAssertFileShouldExist(string $wildcard): void {
    $wildcard = $this->workingDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      $finder = Finder::create();
      $files = PHP_EOL . implode(PHP_EOL, iterator_to_array($finder->in($this->workingDir)));
      throw new \Exception(sprintf("Unable to find files matching wildcard '%s'. Found files: %s.", $wildcard, $files));
    }
  }

  /**
   * Checks whether a screenshot file matching pattern exists and contains text.
   *
   * @param string $wildcard
   *   Filename with a wildcard.
   * @param \Behat\Gherkin\Node\PyStringNode $text
   *   Text in the file.
   */
  #[Given('/^behat screenshot file matching "([^"]*)" should contain:$/')]
  public function behatCliAssertFileShouldContain(string $wildcard, PyStringNode $text): void {
    $wildcard = $this->workingDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      throw new \Exception(sprintf("Unable to find screenshot file matching wildcard '%s'.", $wildcard));
    }

    $path = $matches[0];
    $file_content = trim(file_get_contents($path));

    if ("\n" !== PHP_EOL) {
      $file_content = str_replace(PHP_EOL, "\n", $file_content);
    }

    Assert::assertStringContainsString($this->getExpectedOutput($text), $file_content);
  }

  /**
   * Checks whether a screenshot file exists and does not contain given text.
   *
   * @param string $wildcard
   *   Filename with a wildcard.
   * @param \Behat\Gherkin\Node\PyStringNode $text
   *   Text in the file.
   */
  #[Given('/^behat screenshot file matching "([^"]*)" should not contain:$/')]
  public function behatCliAssertFileShouldNotContain(string $wildcard, PyStringNode $text): void {
    $wildcard = $this->workingDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      throw new \Exception(sprintf("Unable to find screenshot file matching wildcard '%s'.", $wildcard));
    }

    $path = $matches[0];
    $file_content = trim(file_get_contents($path));

    if ("\n" !== PHP_EOL) {
      $file_content = str_replace(PHP_EOL, "\n", $file_content);
    }

    Assert::assertStringNotContainsString($this->getExpectedOutput($text), $file_content);
  }

  /**
   * Checks whether a file wildcard at provided path does not exist.
   *
   * @param string $wildcard
   *   Filename with a wildcard.
   */
  #[Given('/^behat cli file wildcard "([^"]*)" should not exist$/')]
  public function behatCliAssertFileShouldNotExist(string $wildcard): void {
    $wildcard = $this->workingDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (!empty($matches)) {
      $finder = Finder::create();
      $files = PHP_EOL . implode(PHP_EOL, iterator_to_array($finder->in($this->workingDir)));
      throw new \Exception(sprintf("Files matching wildcard '%s' were found, but were not supposed to. Found files: %s.", $wildcard, $files));
    }
  }

}
