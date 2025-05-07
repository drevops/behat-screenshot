<div align="center">
  <a href="" rel="noopener">
  <img width=150px height=150px src="logo.png" alt="Behat screenshot logo"></a>
</div>

<h1 align="center">Behat extension to create screenshots</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/drevops/behat-screenshot.svg)](https://github.com/drevops/behat-screenshot/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/drevops/behat-screenshot.svg)](https://github.com/drevops/behat-screenshot/pulls)
[![Test](https://github.com/drevops/behat-screenshot/actions/workflows/test.yml/badge.svg)](https://github.com/drevops/behat-screenshot/actions/workflows/test.yml)
[![codecov](https://codecov.io/gh/drevops/behat-screenshot/graph/badge.svg?token=UN930S8FGC)](https://codecov.io/gh/drevops/behat-screenshot)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/behat-screenshot)
![LICENSE](https://img.shields.io/github/license/drevops/behat-screenshot)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

[![Total Downloads](https://poser.pugx.org/drevops/behat-screenshot/downloads)](https://packagist.org/packages/drevops/behat-screenshot)

</div>

---

## Features

* Captures a screenshot using the `I save screenshot` step.
* Captures fullscreen screenshots with the `I save fullscreen screenshot` step.
* Automatically captures a screenshot when a test fails.
* Supports both HTML and PNG screenshots.
* Supports Selenium and Headless drivers.
* Configurable screenshot directory.
* Automatically purges screenshots after each test run.
* Adds additional information to screenshots.

## Installation

```shell
composer require --dev drevops/behat-screenshot
```

## Usage

Example `behat.yml` with default parameters:

```yaml
default:
  suites:
    default:
      contexts:
        - DrevOps\BehatScreenshotExtension\Context\ScreenshotContext
        - FeatureContext
  extensions:
    DrevOps\BehatScreenshotExtension: ~
```

or with parameters:

```yaml
default:
  suites:
    default:
      contexts:
        - DrevOps\BehatScreenshotExtension\Context\ScreenshotContext
        - FeatureContext
  extensions:
    DrevOps\BehatScreenshotExtension:
      dir: '%paths.base%/screenshots'
      on_failed: true
      purge: false
      always_fullscreen: false
      fullscreen_algorithm: resize # Options: 'stitch' or 'resize'
      failed_prefix: 'failed_'
      filename_pattern: '{datetime:u}.{feature_file}.feature_{step_line}.{ext}'
      filename_pattern_failed: '{datetime:u}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}'
```

In your feature:

```gherkin
Given I am on "http://google.com"
Then I save screenshot
```

You can capture fullscreen screenshots:

```gherkin
Given I am on "http://google.com"
Then I save fullscreen screenshot
```

There are two algorithms available for capturing fullscreen screenshots:

1. **Resize** (default): Temporarily resizes the browser window to the full height of the
   page to capture everything in one screenshot. This is faster, but may cause
   layout issues on some pages.

2. **Stitch**: Takes multiple screenshots while scrolling the page and
   stitches them together. This produces high-quality results with proper
   content rendering but requires the GD extension.

You can configure which algorithm to use via the `fullscreen_algorithm` option:

```yaml
default:
  extensions:
    DrevOps\BehatScreenshotExtension:
      fullscreen_algorithm: resize # Options: 'stitch' or 'resize'
```

You may optionally specify the size of the browser window in the screenshot
step:

```gherkin
Then I save 1440 x 900 screenshot
# Or with fullscreen
Then I save fullscreen 1440 x 900 screenshot
```

or a file name:

```gherkin
Then I save screenshot with name "my_screenshot.png"
# Or with fullscreen
Then I save fullscreen screenshot with name "my_screenshot.png"
```

To always capture fullscreen screenshots, even without explicitly using the
`fullscreen` keyword, set the `always_fullscreen` configuration option to
`true`:

```yaml
default:
  extensions:
    DrevOps\BehatScreenshotExtension:
      always_fullscreen: true
```

## Options

| Name                      | Default value                                                          | Description                                                                                                                                                                                                                                                                                     |
|---------------------------|------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `dir`                     | `%paths.base%/screenshots`                                             | Path to directory to save screenshots. Directory structure will be created if the directory does not exist. Override with `BEHAT_SCREENSHOT_DIR` env var.                                                                                                                                       |
| `on_failed`               | `true`                                                                 | Capture screenshot on failed test.                                                                                                                                                                                                                                                              |
| `purge`                   | `false`                                                                | Remove all files from the screenshots directory on each test run. Useful during debugging of tests.                                                                                                                                                                                             |
| `always_fullscreen`       | `false`                                                                | Always use fullscreen screenshot capture for all screenshot steps, including regular screenshot steps. When enabled, all `I save screenshot` steps will behave like `I save fullscreen screenshot`.                                                                                             |
| `fullscreen_algorithm`    | `resize`                                                               | Algorithm to use for fullscreen screenshots. Options: `resize` (temporarily resizes browser window to full page height) or `stitch` (captures multiple screenshots while scrolling and stitches them together). The stitch algorithm requires GD extension but produces higher quality results. |
| `info_types`              | `url`, `feature`, `step`, `datetime`                                   | Show additional information on screenshots. Comma-separated list of `url`, `feature`, `step`, `datetime`, or remove to disable. Ordered as listed.                                                                                                                                              |
| `failed_prefix`           | `failed_`                                                              | Prefix failed screenshots with `failed_` string. Useful to distinguish failed and intended screenshots.                                                                                                                                                                                         |
| `filename_pattern`        | `{datetime:u}.{feature_file}.feature_{step_line}.{ext}`                | File name pattern for successful assertions.                                                                                                                                                                                                                                                    |
| `filename_pattern_failed` | `{datetime:u}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}` | File name pattern for failed assertions.                                                                                                                                                                                                                                                        |

### File name tokens

| Token              | Substituted with                                                                | Example value(s)                                                  |
|--------------------|---------------------------------------------------------------------------------|-------------------------------------------------------------------|
| `{ext}`            | The extension of the file captured                                              | `html` or `png`                                                   |
| `{failed_prefix}`  | The value of failed_prefix from configuration                                   | `failed_`, `error_` (do include the `_` suffix, if required)      |
| `{url}`            | Full URL                                                                        | `http_example_com_mypath_subpath_query_myquery_1_plus_2_fragment` |
| `{url_origin}`     | Scheme with domain                                                              | `http_example_com`                                                |
| `{url_relative}`   | Path + query + fragment                                                         | `mypath_subpath_query_myquery_1_plus_2_fragment`                  |
| `{url_domain}`     | Domain                                                                          | `example_com`                                                     |
| `{url_path}`       | Path                                                                            | `mypath_subpath`                                                  |
| `{url_query}`      | Query                                                                           | `myquery_1_plus_2`                                                |
| `{url_fragment}`   | Fragment                                                                        | `somefragment`                                                    |
| `{feature_file}`   | The filename of the `.feature` file currently being executed, without extension | `my_example.feature` -> `my_example`                              |
| `{step_line}`      | Step line number                                                                | `1`, `10`, `100`                                                  |
| `{step_line:%03d}` | Step line number with leading zeros. Modifiers are from `sprintf()`.            | `001`, `010`, `100`                                               |
| `{step_name}`      | Step name without `Given/When/Then` and lower-cased.                            | `i_am_on_the_test_page`                                           |
| `{datetime}`       | Current date and time. defaults to `Ymd_His` format.                            | `20010310_171618`                                                 |
| `{datetime:U}`     | Current date and time as microtime. Modifiers are from `date()`.                | `1697490961192498`                                                |

## Auto-purge

By default, the `purge` option is disabled. This means that the screenshot
directory will not be cleared after each test run. This is useful when you want
to keep the screenshots for debugging purposes.

If you want to clear the directory after each test run, you can enable the
`purge` option in the configuration.

```yaml
default:
  extensions:
    DrevOps\BehatScreenshotExtension:
      purge: true
```

Alternatively, you can use `BEHAT_SCREENSHOT_PURGE` environment variable to
enable the auto-purge feature for a specific test run.

```shell
BEHAT_SCREENSHOT_PURGE=1 vendor/bin/behat
```

## Additional information on screenshots

You can enable additional information on screenshots by setting `info_types` in
the configuration. The order of the types will be the order of the information
displayed on the screenshot.

By default, the information displayed is the URL, feature file name, step line:

```html
Current URL: http://example.com<br/>
Feature: My feature<br/>
Step: I save screenshot (line 8)<br/>
Datetime: 2025-01-19 00:01:10
<hr/>
<!DOCTYPE html>
<html>
...
</html>
```

More information can be added by setting the `info_types` configuration option
and using `addInfo()` method in your context class.

```php
/**
 * @BeforeScenario
 */
public function beforeScenarioUpdateBaseUrl(BeforeScenarioScope $scope): void {
  $environment = $scope->getEnvironment();
  if ($environment instanceof InitializedContextEnvironment) {
    foreach ($environment->getContexts() as $context) {
      if ($context instanceof ScreenshotContext) {
        $context->addInfo('Custom info', 'My custom info');
      }
    }
  }
}
```

## Maintenance

```shell
composer install
composer lint
composer lint-fix
composer test-unit
composer test-bdd
```

### BDD tests

There are tests for Selenium and Headless drivers. Selenium requires a Docker
container and headless requires a Chromium browser (we will make this more
streamlined in the future).

```shell
# Start Chromium in container for Selenium-based tests.
docker run -d -p 4444:4444 -p 9222:9222 selenium/standalone-chromium

# Install Chromium with brew.
brew cask install chromedriver
# Launch Chromium with remote debugging.
/opt/homebrew/Caskroom/chromium/latest/chromium.wrapper.sh --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222
```

```shell
composer test-bdd  # Run BDD tests.

BEHAT_CLI_DEBUG=1 composer test-bdd  # Run BDD tests with debug output.
```

---
_Repository created using https://getscaffold.dev/ project scaffold template_
