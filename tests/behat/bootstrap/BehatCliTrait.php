<?php

declare(strict_types=1);

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use PHPUnit\Framework\Assert;
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
   * @AfterScenario
   */
  public function behatCliAfterScenarioPrintOutput(AfterScenarioScope $scope): void {
    if ($scope->getFeature()->hasTag('behatcli') && static::behatCliIsDebug()) {
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
      $traitName = $trait;
      if (strpos($trait, '\\') !== FALSE) {
        $tokens['{{USE_DECLARATION}}'] .= sprintf('use %s;' . PHP_EOL, $trait);
        $traitNameParts = explode('\\', $trait);
        $traitName = end($traitNameParts);
      }
      $tokens['{{USE_IN_CLASS}}'] .= sprintf('use %s;' . PHP_EOL, $traitName);

      if (is_string($path) && file_exists($path)) {
        $filename = $this->workingDir . DIRECTORY_SEPARATOR . 'features/bootstrap/' . basename($path);
        $this->createFile($filename, file_get_contents($path));
      }
    }

    $content = <<<'EOL'
<?php

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;

{{USE_DECLARATION}}

class FeatureContextTest extends MinkContext implements Context {
  {{USE_IN_CLASS}}

   /**
    * FeatureContext constructor.
    *
    * @param array $parameters Array of parameters from config.
    */
   public function __construct($parameters)
   {
       $this->screenshotInitParams($parameters);
   }

   /**
    * Go to the phpserver test page.
    *
    * @Given /^(?:|I )am on (?:|the )phpserver test page$/
    * @When /^(?:|I )go to (?:|the )phpserver test page$/
    */
   public function goToPhpServerTestPage()
   {
       $this->getSession()->visit('http://0.0.0.0:8888/screenshot.html');
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
  public function behatCliWriteScenarioSteps(PyStringNode $content, $tags = ''): void {
    $content = strtr((string) $content, ["'''" => '"""']);

    // Make sure that indentation in provided content is accurate.
    $contentLines = explode(PHP_EOL, $content);
    foreach ($contentLines as $k => $contentLine) {
      $contentLines[$k] = str_repeat(' ', 4) . trim($contentLine);
    }
    $content = implode(PHP_EOL, $contentLines);

    $tokens = [
      '{{SCENARIO_CONTENT}}' => $content,
      '{{ADDITIONAL_TAGS}}' => $tags,
    ];

    $content = <<<'EOL'
@behatcli
Feature: Stub feature';
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
      base_url: http://nginx:8080
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
      selenium2: ~
      base_url: http://0.0.0.0:8888
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

    $content = <<<'EOL'
<!DOCTYPE html>
  <html>
  <head>
    <title>Test page</title>
  </head>
  <body>
  Test page
  </body>
</html>
EOL;
    $this->createFile($this->workingDir . '/' . $filename, $content);
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
  public function behatCliSetEnvironmentVariable($name, $value): void {
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
  public function behatCliAssertFileShouldExist($wildcard): void {
    $wildcard = $this->workingDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (empty($matches)) {
      $finder = Finder::create();
      $files = PHP_EOL . implode(PHP_EOL, \iterator_to_array($finder->in($this->workingDir)));
      throw new \Exception(sprintf("Unable to find files matching wildcard '%s'. Found files: %s", $wildcard, $files));
    }
  }

  /**
   * Checks whether a file wildcard at provided path does not exist.
   *
   * @param string $wildcard
   *   File name with a wildcard.
   *
   * @Given /^behat cli file wildcard "([^"]*)" should not exist$/
   */
  public function behatCliAssertFileShouldNotExist($wildcard): void {
    $wildcard = $this->workingDir . DIRECTORY_SEPARATOR . $wildcard;
    $matches = glob($wildcard);

    if (!empty($matches)) {
      $finder = Finder::create();
      $files = PHP_EOL . implode(PHP_EOL, \iterator_to_array($finder->in($this->workingDir)));
      throw new \Exception(sprintf("Files matching wildcard '%s' were found, but were not supposed to. Found files: %s", $wildcard, $files));
    }
  }

}
