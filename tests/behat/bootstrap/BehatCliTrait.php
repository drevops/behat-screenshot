<?php

declare(strict_types=1);

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use PHPUnit\Framework\Assert;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Trait BehatCliTrait.
 *
 * Additional shortcut steps for BehatCliContext.
 */
trait BehatCliTrait {

  /**
   * @BeforeScenario
   */
  public function behatCliBeforeScenario(BeforeScenarioScope $scope): void {
    if ($scope->getFeature()->hasTag('behatcli')) {
      $traits = [
        'tests/behat/bootstrap/ScreenshotTrait.php' => 'ScreenshotTrait',
      ];
      $this->behatCliWriteFeatureContextFile($traits);
    }
  }

  /**
   * @AfterScenario @behatcli
   */
  public function behatCliAfterScenarioPrintOutput(AfterScenarioScope $scope): void {
    // Copy the screenshots to the working directory.
    $context = $scope->getEnvironment()->getContext(ScreenshotContext::class);
    if ($context instanceof ScreenshotContext) {
      $src = $this->workingDir . DIRECTORY_SEPARATOR . 'screenshots';
      if (is_dir($src)) {
        $dst = $context->getDir() . '/behatcli_screenshots';
        if (!is_readable($dst)) {
          mkdir($dst, 0777, TRUE);
        }

        $finder = new Finder();
        $fs = new Filesystem();

        foreach ($finder->in($src)->files() as $file) {
          $fs->copy($file->getRealPath(), $dst . DIRECTORY_SEPARATOR . $file->getFilename());
        }
      }
    }

    if (static::behatCliIsDebug()) {
      print "-------------------- OUTPUT START --------------------" . PHP_EOL;
      print PHP_EOL;
      print $this->getOutput();
      print PHP_EOL;
      print "-------------------- OUTPUT FINISH -------------------" . PHP_EOL;
    }
  }

  /**
   * Create FeatureContext.php file.
   *
   * @param array $traits
   *   Optional array of trait classes.
   *
   * @return string
   *   Path to written file.
   */
  public function behatCliWriteFeatureContextFile(array $traits = []): string {
    $tokens = [
      '{{USE_DECLARATION}}' => '',
      '{{USE_IN_CLASS}}' => '',
    ];
    foreach ($traits as $path => $trait) {
      $trait_name = $trait;
      if (strpos($trait, '\\') !== FALSE) {
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
use Behat\MinkExtension\Context\MinkContext;
use Behat\MinkExtension\Context\RawMinkContext;

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

    // Set the screenshot token host to override any real host.
    putenv('BEHAT_SCREENSHOT_TOKEN_HOST=example.com');
    // Set the JavaScript override base URL.
    $this->javascriptBaseUrl = getenv('BEHAT_JAVASCRIPT_BASE_URL') ?: 'http://host.docker.internal:8888';
  }

  /**
   * Update base URL for JavaScript scenarios.
   *
   * @BeforeScenario
   */
  public function beforeScenarioUpdateBaseUrl(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('javascript') && !$scope->getScenario()->hasTag('skip-base-url-rewrite')) {
      $environment = $scope->getEnvironment();
      if ($environment instanceof InitializedContextEnvironment) {
        foreach ($environment->getContexts() as $context) {
          if ($context instanceof RawMinkContext) {
            $context->setMinkParameter('base_url', $this->javascriptBaseUrl);
          }
        }
      }
    }
  }

  /**
   * Go to the phpserver test page.
   *
   * @Given /^(?:|I )am on (?:|the )phpserver test page$/
   * @When /^(?:|I )go to (?:|the )phpserver test page$/
   */
  public function goToPhpServerTestPage()
  {
    $this->visitPath('/screenshot.html');
  }

  /**
   * @Given I throw test exception with message :message
   */
  public function throwTestException($message) {
    throw new \RuntimeException($message);
  }

}
EOL;

    $content = strtr($content, $tokens);
    $content = preg_replace('/\{\{[^\}]+\}\}/', '', $content);

    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'features/bootstrap/FeatureContextTest.php';
    $this->createFile($filename, $content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'FeatureContextTest.php');
    }

    return $filename;
  }

  /**
   * @Given /^scenario steps(?: tagged with "([^"]*)")?:$/
   */
  public function behatCliWriteScenarioSteps(PyStringNode $content, string $tags = ''): void {
    $content = strtr((string) $content, ["'''" => '"""']);

    // Make sure that indentation in provided content is accurate.
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

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'Feature Stub');
    }
  }

  /**
   * @Given full behat configuration:
   */
  public function behatCliWriteFullBehatYml(PyStringNode $content): void {
    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'behat.yml';
    $this->createFile($filename, $content->getRaw());

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'Behat Config');
    }
  }

  /**
   * @Given some behat configuration
   */
  public function behatCliWriteBehatYml(): void {
    $content = <<<'EOL'
default:
  suites:
    default:
      contexts:
        - FeatureContextTest:
          - screenshot_dir: '%paths.base%/screenshots'
        - DrevOps\BehatPhpServer\PhpServerContext:
            webroot: '%paths.base%/tests/behat/fixtures'
            host: 0.0.0.0
  extensions:
    Behat\MinkExtension:
      browserkit_http: ~
      selenium2: ~
      base_url: http://0.0.0.0:8888
EOL;

    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'behat.yml';
    $this->createFile($filename, $content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'Behat Config');
    }
  }

  /**
   * @Given screenshot context behat configuration with value:
   */
  public function behatCliWriteScreenshotContextBehatYml(PyStringNode $value): void {
    $content = <<<'EOL'
default:
  suites:
    default:
      contexts:
        - FeatureContextTest:
          - screenshot_dir: '%paths.base%/screenshots'
        - DrevOps\BehatPhpServer\PhpServerContext:
            webroot: '%paths.base%/tests/behat/fixtures'
            host: 0.0.0.0
        - DrevOps\BehatScreenshotExtension\Context\ScreenshotContext
  extensions:
    Behat\MinkExtension:
      browserkit_http: ~
      base_url: http://0.0.0.0:8888
      browser_name: chrome
      javascript_session: selenium2
      selenium2:
        wd_host: "http://localhost:4444/wd/hub"
        capabilities:
          browser: chrome
          extra_capabilities:
            "goog:chromeOptions":
              args:
                - '--disable-gpu'            # Disables hardware acceleration required in containers and cloud-based instances (like CI runners) where GPU is not available.
                # Options to increase stability and speed.
                - '--disable-extensions'     # Disables all installed Chrome extensions. Useful in testing environments to avoid interference from extensions.
                - '--disable-infobars'       # Hides the infobar that Chrome displays for various notifications, like warnings when opening multiple tabs.
                - '--disable-popup-blocking' # Disables the popup blocker, allowing all popups to appear. Useful in testing scenarios where popups are expected.
                - '--disable-translate'      # Disables the built-in translation feature, preventing Chrome from offering to translate pages.
                - '--no-first-run'           # Skips the initial setup screen that Chrome typically shows when running for the first time.
                - '--test-type'              # Disables certain security features and UI components that are unnecessary for automated testing, making Chrome more suitable for test environments.

EOL;

    $content .= PHP_EOL . '    ' . trim((string) $value);
    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'behat.yml';
    $this->createFile($filename, $content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'Behat Config');
    }
  }

  /**
   * @Given screenshot fixture
   */
  public function behatCliWriteScreenshotFixture(): void {
    $filename = 'tests/behat/fixtures/screenshot.html';
    $src = __DIR__ . '/../fixtures/screenshot.html';

    $this->createFile($this->workingDir . '/' . $filename, file_get_contents($src));
  }

  /**
   * @Given short screenshot fixture
   */
  public function behatCliWriteScreenshotShortFixture(): void {
    $filename = 'tests/behat/fixtures/screenshot.html';
    $src = __DIR__ . '/../fixtures/screenshot_short.html';

    $this->createFile($this->workingDir . '/' . $filename, file_get_contents($src));
  }

  /**
   * @Given screenshot test context:
   */
  public function behatCliWriteScreenshotTestContext(PyStringNode $content): void {
    $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'features/bootstrap/FullscreenTestContext.php';
    $this->createFile($filename, $content);

    if (static::behatCliIsDebug()) {
      static::behatCliPrintFileContents($filename, 'FullscreenTestContext');
    }

    // Update the behat.yml to include this context.
    $behat_yml_path = $this->workingDir . DIRECTORY_SEPARATOR . 'behat.yml';
    if (file_exists($behat_yml_path)) {
      $behat_yml = file_get_contents($behat_yml_path);
      if (strpos($behat_yml, 'FullscreenTestContext') === FALSE) {
        $behat_yml = str_replace(
          'ScreenshotContext',
          "ScreenshotContext\n        - FullscreenTestContext",
          $behat_yml
        );
        file_put_contents($behat_yml_path, $behat_yml);
      }
    }
  }

  /**
   * @Then it should fail with an error:
   */
  public function behatCliAssertFailWithError(PyStringNode $message): void {
    $this->itShouldFail('fail');
    Assert::assertStringContainsString(trim((string) $message), $this->getOutput());
    // Enforce \Exception for all assertion exceptions. Non-assertion
    // exceptions should be thrown as \RuntimeException.
    Assert::assertStringContainsString('Exception)', $this->getOutput());
    Assert::assertStringNotContainsString('(RuntimeException)', $this->getOutput());
  }

  /**
   * @Then it should fail with an exception:
   */
  public function behatCliAssertFailWithException(PyStringNode $message): void {
    $this->itShouldFail('fail');
    Assert::assertStringContainsString(trim((string) $message), $this->getOutput());
    // Enforce \RuntimeException for all non-assertion exceptions. Assertion
    // exceptions should be thrown as \Exception.
    Assert::assertStringContainsString('(RuntimeException)', $this->getOutput());
  }

  /**
   * Sets specified ENV variable.
   *
   * @When :name environment variable is set to :value
   */
  public function behatCliSetEnvironmentVariable(string $name, string $value): void {
    $this->env[$name] = $value;
  }

  /**
   * Helper to print file comments.
   */
  protected static function behatCliPrintFileContents(string $filename, $title = '') {
    if (!is_readable($filename)) {
      throw new \RuntimeException(sprintf('Unable to access file "%s"', $filename));
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
   * @return bool
   *   TRUE to see debug messages for this trait.
   */
  protected static function behatCliIsDebug(): string|false {
    return getenv('BEHAT_CLI_DEBUG');
  }

  /**
   * Checks whether a file wildcard at provided path exists.
   *
   * @param string $wildcard
   *   File name with a wildcard.
   *
   * @Given /^behat cli file wildcard "([^"]*)" should exist$/
   */
  public function behatCliAssertFileShouldExist(string $wildcard): void {
    $wildcard = $this->workingDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      $finder = Finder::create();
      $files = PHP_EOL . implode(PHP_EOL, \iterator_to_array($finder->in($this->workingDir)));
      throw new \Exception(sprintf("Unable to find files matching wildcard '%s'. Found files: %s", $wildcard, $files));
    }
  }

  /**
   * Checks whether a screenshot file matching pattern exists and contains text.
   *
   * @param string $wildcard
   *   File name with a wildcard.
   * @param \Behat\Gherkin\Node\PyStringNode $text
   *   Text in the file.
   *
   * @Given /^behat screenshot file matching "([^"]*)" should contain:$/
   */
  public function behatCliAssertFileShouldContain(string $wildcard, PyStringNode $text): void {
    $wildcard = $this->workingDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      throw new \Exception(sprintf("Unable to find screenshot file matching wildcard '%s'.", $wildcard));
    }

    $path = $matches[0];
    $file_content = trim(file_get_contents($path));

    // Normalize the line endings in the output.
    if ("\n" !== PHP_EOL) {
      $file_content = str_replace(PHP_EOL, "\n", $file_content);
    }

    Assert::assertStringContainsString($this->getExpectedOutput($text), $file_content);
  }

  /**
   * Checks whether a screenshot file exists and does not contain given text.
   *
   * @param string $wildcard
   *   File name with a wildcard.
   * @param \Behat\Gherkin\Node\PyStringNode $text
   *   Text in the file.
   *
   * @Given /^behat screenshot file matching "([^"]*)" should not contain:$/
   */
  public function behatCliAssertFileNotShouldContain(string $wildcard, PyStringNode $text): void {
    $wildcard = $this->workingDir . DIRECTORY_SEPARATOR . $wildcard;

    $matches = glob($wildcard);
    if (empty($matches)) {
      throw new \Exception(sprintf("Unable to find screenshot file matching wildcard '%s'.", $wildcard));
    }

    $path = $matches[0];
    $file_content = trim(file_get_contents($path));

    // Normalize the line endings in the output.
    if ("\n" !== PHP_EOL) {
      $file_content = str_replace(PHP_EOL, "\n", $file_content);
    }

    Assert::assertStringNotContainsString($this->getExpectedOutput($text), $file_content);
  }

  /**
   * Checks whether a file wildcard at provided path does not exist.
   *
   * @param string $wildcard
   *   File name with a wildcard.
   *
   * @Given /^behat cli file wildcard "([^"]*)" should not exist$/
   */
  public function behatCliAssertFileShouldNotExist(string $wildcard): void {
    $wildcard = $this->workingDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (!empty($matches)) {
      $finder = Finder::create();
      $files = PHP_EOL . implode(PHP_EOL, \iterator_to_array($finder->in($this->workingDir)));
      throw new \Exception(sprintf("Files matching wildcard '%s' were found, but were not supposed to. Found files: %s", $wildcard, $files));
    }
  }

}
