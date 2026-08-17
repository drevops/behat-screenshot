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

[![Vortex Ecosystem](https://img.shields.io/badge/%F0%9F%8C%80-Vortex%20Ecosystem-2C5A68?style=for-the-badge&labelColor=65ACBC)](https://github.com/drevops/vortex)
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
* Records an animated GIF of a scenario from its per-step screenshots.

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
      failed_prefix: 'failed_'
      filename_pattern: '{datetime:U}.{feature_file}.feature_{step_line}.{ext}'
      filename_pattern_failed: '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}'
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

Fullscreen screenshots work by temporarily resizing the browser window to the
full height of the page to capture everything in one screenshot.

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

### Capturing Screenshots After Every Step

To automatically capture a screenshot after every step, you can either:

1. **Enable globally** in configuration:

```yaml
default:
  extensions:
    DrevOps\BehatScreenshotExtension:
      on_every_step: true
```

2. **Enable per-scenario** using the `@screenshots` tag:

```gherkin
@screenshots
Scenario: My scenario with automatic screenshots
  Given I am on "http://example.com"
  When I click "Login"
  Then I should see "Welcome"
  # Screenshots will be captured after each of these steps
```

The `@screenshots` tag takes precedence over the global configuration, allowing you to enable this feature for specific scenarios even when it's disabled globally.

**Note**: When both `on_every_step` and `on_failed` are enabled, only one screenshot is captured for failed steps (the failed screenshot) to avoid duplicates.

### Recording an animated GIF

To record an animated GIF of a scenario from its per-step screenshots, you can either:

1. **Enable globally** in configuration:

```yaml
default:
  extensions:
    DrevOps\BehatScreenshotExtension:
      animation:
        enabled: true
        frame_delay: 500
```

2. **Enable per-scenario** using the `@screenshots:animated` tag:

```gherkin
@javascript @screenshots:animated
Scenario: My scenario recorded as an animated GIF
  Given I am on "http://example.com"
  When I click "Login"
  Then I should see "Welcome"
  # An animated GIF is written when the scenario finishes.
```

When animation is enabled, a screenshot is taken after every passed step, and the frames are combined into a single GIF - named after the feature file and scenario line - when the scenario finishes. `frame_delay` sets the delay between frames in milliseconds.

The `@screenshots:animated` tag is read at both the scenario and feature level. Animation requires the `gd` PHP extension and a driver that can capture screenshots (such as a real browser via `@javascript`); without GD, the animated GIF is skipped while the per-step screenshots are still written.

Each frame keeps the size it was captured at. Frames smaller than the tallest or widest frame of the scenario are shown at the top-left of the animation against a white background, rather than being scaled up or stretched.

#### Skipping animation for a scenario

Animation captures a screenshot after every passed step, so its cost grows with the number of steps in a scenario. To keep animation on across the suite while excluding individual scenarios, use the `@screenshots:animated:skip` tag:

```gherkin
@javascript @screenshots:animated:skip
Scenario: Long workflow that should not be recorded
  Given I am on "http://example.com"
  # No animated GIF is written, and no per-step screenshots are captured for it.
```

To turn animation off for a whole run without editing feature files or configuration - on a CI job that only needs the per-step screenshots, for example - set the `BEHAT_SCREENSHOT_ANIMATION_SKIP` environment variable to a truthy value:

```bash
BEHAT_SCREENSHOT_ANIMATION_SKIP=1 vendor/bin/behat
```

This overrides everything below it, including `@screenshots:animated` tags, so no scenario in the run is animated.

Tags are resolved from the most specific scope down, so a scenario tag decides on its own, a feature tag applies only when the scenario carries neither tag, and `animation.enabled` applies only when neither scope is tagged:

| `BEHAT_SCREENSHOT_ANIMATION_SKIP` | Scenario tag                   | Feature tag                    | `animation.enabled` | Animated |
|-----------------------------------|--------------------------------|--------------------------------|---------------------|----------|
| `1`                               | `@screenshots:animated`        | -                              | `false`             | No       |
| unset                             | `@screenshots:animated:skip`   | -                              | `true`              | No       |
| unset                             | `@screenshots:animated`        | `@screenshots:animated:skip`   | `false`             | Yes      |
| unset                             | `@screenshots:animated:skip`   | `@screenshots:animated`        | `true`              | No       |
| unset                             | -                              | `@screenshots:animated:skip`   | `true`              | No       |
| unset                             | -                              | -                              | `true`              | Yes      |

A scenario or feature carrying both tags at once is not animated - the skip tag wins within a scope.

Skipping animation does not disable the per-step screenshots requested by `on_every_step` or the `@screenshots` tag; those are captured independently. It removes only the captures that animation itself requires.

#### Limiting frame size

With `always_fullscreen: true` every frame is as tall as the page it captured, so one long page - an admin listing, a search result set - produces very large frames and a correspondingly large GIF. Cap them with `max_width` and `max_height`:

```yaml
default:
  extensions:
    DrevOps\BehatScreenshotExtension:
      animation:
        enabled: true
        max_height: 2000
```

Frames larger than the cap are cropped to it before being encoded, keeping the top-left of the page; frames already within it are untouched. Each axis is capped on its own, so a `max_height` alone never changes a frame's width - the retained area keeps its captured resolution and every frame in the animation still shares the same width. Both caps default to `0`, which leaves the frame size unbounded. The per-step PNG screenshots are always written at full size, so capping affects the animation only.

## Options

| Name                      | Default value                                                          | Description                                                                                                                                                                                                                                                                                     |
|---------------------------|------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `dir`                     | `%paths.base%/screenshots`                                             | Path to directory to save screenshots. Directory structure will be created if the directory does not exist. Override with `BEHAT_SCREENSHOT_DIR` env var.                                                                                                                                       |
| `on_failed`               | `true`                                                                 | Capture screenshot on failed test.                                                                                                                                                                                                                                                              |
| `on_every_step`           | `false`                                                                | Automatically capture screenshots after every step. Can be enabled globally via config or per-scenario using the `@screenshots` tag. Only captures on passed steps to avoid duplicates with `on_failed`.                                                                                        |
| `animation.enabled`       | `false`                                                                | Build an animated GIF per scenario from the per-step screenshots, automatically enabling per-step capture. Can be enabled per-scenario with the `@screenshots:animated` tag and disabled per-scenario with the `@screenshots:animated:skip` tag (both read at scenario or feature level, and both taking precedence over this setting). Disable for the whole run with the `BEHAT_SCREENSHOT_ANIMATION_SKIP` env var, which overrides both the tags and this setting. Requires the `gd` PHP extension. |
| `animation.frame_delay`   | `500`                                                                  | Delay between animated GIF frames, in milliseconds.                                                                                                                                                                                                                                             |
| `animation.max_width`     | `0`                                                                    | Maximum animated GIF frame width, in pixels. Wider frames are cropped to it, keeping the left-hand side. `0` leaves the width unbounded.                                                                                                                                                        |
| `animation.max_height`    | `0`                                                                    | Maximum animated GIF frame height, in pixels. Taller frames are cropped to it, keeping the top of the page at full resolution and full width. `0` leaves the height unbounded. Useful with `always_fullscreen`, where frame height follows the page height.                                      |
| `purge`                   | `false`                                                                | Remove all files from the screenshots directory on each test run. Useful during debugging of tests.                                                                                                                                                                                             |
| `always_fullscreen`       | `false`                                                                | Always use fullscreen screenshot capture for all screenshot steps, including regular screenshot steps. When enabled, all `I save screenshot` steps will behave like `I save fullscreen screenshot`.                                                                                             |
| `info_types`              | none                                                                   | List of additional information types to show on screenshots: `url`, `feature`, `step`, `datetime`. Rendered in the order listed. No information is added unless this option is set.                                                                                                            |
| `failed_prefix`           | `failed_`                                                              | Prefix failed screenshots with `failed_` string. Useful to distinguish failed and intended screenshots.                                                                                                                                                                                         |
| `filename_pattern`        | `{datetime:U}.{feature_file}.feature_{step_line}.{ext}`                | File name pattern for successful assertions.                                                                                                                                                                                                                                                    |
| `filename_pattern_failed` | `{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}` | File name pattern for failed assertions.                                                                                                                                                                                                                                                        |

### File name tokens

Every character other than a letter, digit, underscore or hyphen is replaced with an underscore, and consecutive replacements collapse into one. The URL examples below are for a page at `http://example.com/mypath/subpath?myquery=1#somefragment`.

| Token              | Substituted with                                                                | Example value(s)                                             |
|--------------------|---------------------------------------------------------------------------------|--------------------------------------------------------------|
| `{ext}`            | The extension of the file captured                                              | `html` or `png`                                              |
| `{failed_prefix}`  | The value of failed_prefix from configuration                                   | `failed_`, `error_` (do include the `_` suffix, if required) |
| `{url}`            | Full URL                                                                        | `http_example_com_mypath_subpath_myquery_1_somefragment`     |
| `{url_origin}`     | Scheme with domain                                                              | `http_example_com`                                           |
| `{url_relative}`   | Path + query + fragment                                                         | `mypath_subpath_myquery_1_somefragment`                      |
| `{url_domain}`     | Domain                                                                          | `example_com`                                                |
| `{url_path}`       | Path                                                                            | `mypath_subpath`                                             |
| `{url_query}`      | Query                                                                           | `myquery_1`                                                  |
| `{url_fragment}`   | Fragment                                                                        | `somefragment`                                               |
| `{feature_file}`   | The filename of the `.feature` file currently being executed, without extension | `my_example.feature` -> `my_example`                         |
| `{step_line}`      | Step line number                                                                | `1`, `10`, `100`                                             |
| `{step_line:%03d}` | Step line number with leading zeros. Modifiers are from `sprintf()`.            | `001`, `010`, `100`                                          |
| `{step_name}`      | Step name without `Given/When/Then`, with spaces replaced by underscores        | `I_am_on_the_test_page`                                      |
| `{datetime}`       | Current date and time. Defaults to the `Ymd_His` format.                        | `20010310_171618`                                            |
| `{datetime:U}`     | Current date and time as a Unix timestamp. Modifiers are from `date()`.         | `1697490961`                                                 |

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

The `info_types` option controls which built-in information is added to screenshots, and nothing is added unless it is set. The order of the types is the order of the information displayed on the screenshot.

```yaml
default:
  extensions:
    DrevOps\BehatScreenshotExtension:
      info_types:
        - url
        - feature
        - step
        - datetime
```

With all four types enabled, the information is prepended to the captured HTML:

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

Custom entries can be added from your own context class with `appendInfo()`. They are rendered alongside the entries produced by `info_types`, and are rendered whether or not `info_types` is set.

```php
/**
 * @BeforeScenario
 */
public function beforeScenarioAddInfo(BeforeScenarioScope $scope): void {
  $environment = $scope->getEnvironment();
  if ($environment instanceof InitializedContextEnvironment) {
    foreach ($environment->getContexts() as $context) {
      if ($context instanceof ScreenshotContext) {
        $context->appendInfo('Custom info', 'My custom info');
      }
    }
  }
}
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for local setup, linting, unit and BDD tests, and the animated GIF assembly profiler.

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
