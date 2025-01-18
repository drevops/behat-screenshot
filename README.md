<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=Behat+screenshot&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Behat screenshot logo"></a>
</p>

<h1 align="center">Behat Screenshot Extension</h1>

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

<p align="center"> Behat extension and step definitions to create HTML and image screenshots on demand or when tests fail.
    <br>
</p>

## Features

* Create a screenshot using `I save screenshot` or `save screenshot` step
  definition.
* Create a screenshot when test fails.
* Screenshot is saved as HTML page for Goutte driver.
* Screenshot is saved as both HTML and PNG image for Selenium driver.
* Screenshot directory can be specified through environment
  variable `BEHAT_SCREENSHOT_DIR` (useful for CI systems to override values
  in `behat.yml`).
* Screenshots can be purged after every test run by setting `purge: true` (
  useful during test debugging) or setting environment
  variable `BEHAT_SCREENSHOT_PURGE=1`.

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
      fail: true
      fail_prefix: 'failed_'
      purge: false
      filenamePattern: '{datetime:u}.{feature_file}.feature_{step_line}.{ext}'
      filenamePatternFailed: '{datetime:u}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}'
```

In your feature:

```gherkin
  Given I am on "http://google.com"
Then I save screenshot
```

You may optionally specify size of browser window in the screenshot step:

```gherkin
  Then I save 1440 x 900 screenshot
And I save 800 x 600 screenshot
```

## Options

| Name                    | Default value                                                        | Description                                                                                                                                        |
|-------------------------|----------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------|
| `dir`                   | `%paths.base%/screenshots`                                           | Path to directory to save screenshots. Directory structure will be created if the directory does not exist.                                        |
| `fail`                  | `true`                                                               | Capture screenshot on test failure.                                                                                                                |
| `fail_prefix`           | `failed_`                                                            | Prefix failed screenshots with `fail_` string. Useful to distinguish failed and intended screenshots.                                              |
| `purge`                 | `false`                                                              | Remove all files from the screenshots directory on each test run. Useful during debugging of tests.                                                |
| `info_types`            | `url`, `feature`, `step`, `datetime`                                 | Show additional information on screenshots. Comma-separated list of `url`, `feature`, `step`, `datetime`, or remove to disable. Ordered as listed. |
| `filenamePattern`       | `{datetime:u}.{feature_file}.feature_{step_line}.{ext}`              | File name pattern for successful assertions.                                                                                                       |
| `filenamePatternFailed` | `{datetime:u}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}` | File name pattern for failed assertions.                                                                                                           |
| `filenamePatternFailed` | `{datetime:u}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}` | File name pattern for failed assertions.                                                                                                           |

### Supported tokens

| Token              | Substituted with                                                                | Example value(s)                                                                                         |
|--------------------|---------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------|
| `{ext}`            | The extension of the file captured                                              | `html` or `png`                                                                                          |
| `{fail_prefix}`    | The value of fail_prefix from configuration                                     | `failed_`, `error_` (do include the `_` suffix, if required)                                             |
| `{url}`            | Full URL                                                                        | `http_example_com_mypath_subpath__query__myquery_1_plus_2_plus_3_and_another1_4__fragment__somefragment` |
| `{url_origin}`     | Scheme with domain                                                              | `http_example_com`                                                                                       |
| `{url_relative}`   | Path + query + fragment                                                         | `mypath_subpath__query__myquery_1_plus_2_plus_3_and_another1_4__fragment__somefragment`                  |
| `{url_domain}`     | Domain                                                                          | `example_com`                                                                                            |
| `{url_path}`       | Path                                                                            | `mypath_subpath`                                                                                         |
| `{url_query}`      | Query                                                                           | `myquery_1_plus_2_plus_3_and_another1_4`                                                                 |
| `{url_fragment}`   | Fragment                                                                        | `somefragment`                                                                                           |
| `{feature_file}`   | The filename of the `.feature` file currently being executed, without extension | `my_example.feature` -> `my_example`                                                                     |
| `{step_line}`      | Step line number                                                                | `1`, `10`, `100`                                                                                         |
| `{step_line:%03d}` | Step line number with leading zeros. Modifiers are from `sprintf()`.            | `001`, `010`, `100`                                                                                      |
| `{step_name}`      | Step name without `Given/When/Then` and lower-cased.                            | `i_am_on_the_test_page`                                                                                  |
| `{datetime}`       | Current date and time. defaults to `Ymd_His` format.                            | `20010310_171618`                                                                                        |
| `{datetime:U}`     | Current date and time as microtime. Modifiers are from `date()`.                | `1697490961192498`                                                                                       |

## Maintenance

### Local development setup

### Install dependencies.

```shell
composer install
```

### Lint code

```shell
composer lint
```

### Fix code style

```shell
composer lint-fix
```

### Run tests

#### Unit tests

```shell
composer test-unit # Run unit tests.
```

#### BDD tests

We have tests for Selenium and Headless drivers. Selenium requires a Docker
container and headless requires a Chromium browser (we will make this more
streamlined in the future).

```shell
# Start Chromium in container for Selenium-based tests.
docker run -d -p 4444:4444 -p 9222:9222 selenium/standalone-chromium
```

```shell
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
