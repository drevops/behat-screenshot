<div align="center">
  <a href="" rel="noopener">
  <img width="420" src="logo.png" alt="Behat screenshot logo"></a>
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

Behat Screenshot is a Behat extension that saves the page your test is on as an HTML file and a PNG image. With a driver that can't capture images, such as BrowserKit, it writes the HTML file only.

## Features

* Captures a screenshot with the `I save screenshot` step.
* Captures the whole page with the `I save fullscreen screenshot` step.
* Captures a screenshot when a step fails.
* Captures a screenshot after every step, for the suite or for tagged scenarios.
* Works with Selenium and headless Chrome drivers.
* Saves screenshots to a directory you configure, and can purge it at the start of each run.
* Adds the URL, feature, step and time to the HTML screenshot.
* Records an animated GIF of a scenario from its per-step screenshots.

## Installation

To add the extension to your project, run:

```shell
composer require --dev drevops/behat-screenshot
```

The extension supports both Behat majors:

| Behat     | PHP     | Configuration file              |
|-----------|---------|---------------------------------|
| `^3.34.0` | `>=8.3` | `behat.php` or `behat.dist.php` |
| `^4.0`    | `>=8.3` | `behat.php` or `behat.dist.php` |

Composer installs Behat 3 by default, because the only Mink extension release that supports Behat 4 is `friends-of-behat/mink-extension` 3.0 alpha. To use Behat 4, require it together with that release:

```shell
composer require --dev \
  behat/behat:^4.0 \
  friends-of-behat/mink-extension:^3.0@alpha \
  drevops/behat-screenshot
```

## Usage

Behat 4 reads `behat.php`, or `behat.dist.php` when there is no `behat.php`. Behat 3 reads the same files, but a YAML configuration in the project root, such as `behat.yml`, takes precedence over them. On a Behat 3 project that keeps a root YAML configuration, add the same settings to its profile. `ScreenshotContext` captures through Mink, so add these settings to a profile that already registers `MinkExtension` with a driver session. To use the default options, add `ScreenshotContext` to your suite and register the extension:

```php
<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

$suite = (new Suite('default'))
  ->withContexts(ScreenshotContext::class, 'FeatureContext');

$profile = (new Profile('default'))
  ->withSuite($suite)
  ->withExtension(new Extension(BehatScreenshotExtension::class));

return (new Config())->withProfile($profile);
```

To set options, pass them to the extension:

```php
<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

$suite = (new Suite('default'))
  ->withContexts(ScreenshotContext::class, 'FeatureContext');

$extension = new Extension(BehatScreenshotExtension::class, [
  'dir' => '%paths.base%/screenshots',
  'on_failed' => TRUE,
  'purge' => FALSE,
  'always_fullscreen' => FALSE,
]);

$profile = (new Profile('default'))
  ->withSuite($suite)
  ->withExtension($extension);

return (new Config())->withProfile($profile);
```

Register the extension by its full class name, which both Behat majors accept. Behat 3 also reads the short `DrevOps\BehatScreenshotExtension` name, but Behat 4 rejects it.

The [options](#options) table describes every option, and [`behat.dist.php`](behat.dist.php) sets all of them.

To capture a screenshot, add the `I save screenshot` step to a scenario:

```gherkin
Given I am on "http://google.com"
Then I save screenshot
```

To capture the whole page, use the `I save fullscreen screenshot` step:

```gherkin
Given I am on "http://google.com"
Then I save fullscreen screenshot
```

The extension resizes the browser window to the page's full height for that capture, then restores the window.

To capture at a set window size, give the width and height in pixels. The window keeps that size for the rest of the scenario:

```gherkin
Then I save 1440 x 900 screenshot
```

To name the file, pass the name to the step. The name can contain [filename tokens](#filename-tokens), and the extension appends `.html` or `.png` to it:

```gherkin
Then I save screenshot with name "my_screenshot"
# Or with fullscreen
Then I save fullscreen screenshot with name "my_screenshot"
```

Each step also matches without the leading `I`, such as `Then save screenshot`.

To make every screenshot fullscreen, including the ones `I save screenshot` captures, set `always_fullscreen` to `TRUE`:

```php
$extension = new Extension(BehatScreenshotExtension::class, [
  'always_fullscreen' => TRUE,
]);
```

### Capturing screenshots after every step

To capture a screenshot after every passed step, turn capture on in either of these places:

- For every scenario, set `on_every_step` to `TRUE`:

  ```php
  $extension = new Extension(BehatScreenshotExtension::class, [
    'on_every_step' => TRUE,
  ]);
  ```

- For 1 scenario or feature, tag it with `@screenshots`:

  ```gherkin
  @screenshots
  Scenario: My scenario with automatic screenshots
    Given I am on "http://example.com"
    When I click "Login"
    Then I should see "Welcome"
    # Screenshots will be captured after each of these steps
  ```

The `@screenshots` tag can turn capture on but not off, so it has no effect when `on_every_step` is `TRUE`.

Per-step capture covers passed steps only. A failed step gets only the `on_failed` screenshot.

### Recording an animated GIF

To record an animated GIF of a scenario, turn animation on in either of these places:

- For every scenario, set `animation.enabled` to `TRUE`:

  ```php
  $extension = new Extension(BehatScreenshotExtension::class, [
    'animation' => [
      'enabled' => TRUE,
      'frame_delay' => 500,
    ],
  ]);
  ```

- For 1 scenario or feature, tag it with `@screenshots:animated`:

  ```gherkin
  @javascript @screenshots:animated
  Scenario: My scenario recorded as an animated GIF
    Given I am on "http://example.com"
    When I click "Login"
    Then I should see "Welcome"
    # An animated GIF is written when the scenario finishes.
  ```

The extension captures a screenshot after every passed step of an animated scenario. When the scenario finishes, the extension combines those screenshots into 1 GIF. `frame_delay` sets the delay between frames, in milliseconds.

The GIF is named `{datetime:U}.{feature_file}.feature_<scenario line>.gif`. That's the default `filename_pattern` with the scenario's line in place of `{step_line}`, so the GIF name follows the format of the per-step screenshot names. A custom `filename_pattern` leaves the GIF name unchanged.

Animation requires the `gd` PHP extension and a driver that captures PNG screenshots, such as the Selenium driver in a `@javascript` scenario. Without `gd`, the extension still writes the per-step screenshots and skips the GIF.

Each frame keeps the size it was captured at. The GIF is as wide as the scenario's widest frame and as tall as its tallest frame. A smaller frame sits at the top-left on a white background.

#### Skipping animation for a scenario

Animation adds a capture after every passed step, so its cost grows with the length of a scenario. To keep animation on for the suite and leave out 1 scenario, tag the scenario with `@screenshots:animated:skip`:

```gherkin
@javascript @screenshots:animated:skip
Scenario: Long workflow that should not be recorded
  Given I am on "http://example.com"
  # No animated GIF is written, and no per-step screenshots are captured for it.
```

To turn animation off for 1 run, such as a CI job that needs only the per-step screenshots, set `BEHAT_SCREENSHOT_ANIMATION_SKIP` to a truthy value:

```bash
BEHAT_SCREENSHOT_ANIMATION_SKIP=1 vendor/bin/behat
```

The variable overrides every tag and `animation.enabled`, so no scenario in the run is animated.

Without the variable, the extension resolves animation starting from the most specific scope:

- A scenario tag decides for its scenario.
- A feature tag applies when the scenario carries neither tag.
- `animation.enabled` applies when neither the scenario nor the feature is tagged.

For example:

| `BEHAT_SCREENSHOT_ANIMATION_SKIP` | Scenario tag                   | Feature tag                    | `animation.enabled` | Animated |
|-----------------------------------|--------------------------------|--------------------------------|---------------------|----------|
| `1`                               | `@screenshots:animated`        | -                              | `false`             | No       |
| unset                             | `@screenshots:animated:skip`   | -                              | `true`              | No       |
| unset                             | `@screenshots:animated`        | `@screenshots:animated:skip`   | `false`             | Yes      |
| unset                             | `@screenshots:animated:skip`   | `@screenshots:animated`        | `true`              | No       |
| unset                             | -                              | `@screenshots:animated:skip`   | `true`              | No       |
| unset                             | -                              | -                              | `true`              | Yes      |

When the same scenario or the same feature has both tags, the skip tag wins for it.

Skipping animation removes only the captures that animation adds. `on_every_step` and the `@screenshots` tag still capture their per-step screenshots.

#### Limiting frame size

With `always_fullscreen` set to `TRUE`, every frame is as tall as its page. A long page, such as a search results listing, makes a large frame and a large GIF. To cap the frame size, set `max_width` and `max_height`:

```php
$extension = new Extension(BehatScreenshotExtension::class, [
  'animation' => [
    'enabled' => TRUE,
    'max_height' => 2000,
  ],
]);
```

The extension crops a frame larger than a cap to the top-left of the page, at its captured resolution. Each cap applies to its own axis, so `max_height` alone leaves every frame's width as captured. Both caps default to `0`, which leaves the size unbounded. The per-step PNG screenshots keep their full size, so the caps affect the GIF only.

## Options

The extension accepts the following options. The `animation.*` options go in the `animation` array:

| Name | Default value | Description |
|---|---|---|
| `dir` | `%paths.base%/screenshots` | Directory to save screenshots to, created when missing. A non-empty `BEHAT_SCREENSHOT_DIR` environment variable replaces this value. |
| `on_failed` | `true` | If `true`, captures a screenshot when a step fails. |
| `on_every_step` | `false` | If `true`, captures a screenshot after every passed step. The `@screenshots` tag turns it on for 1 scenario or feature. |
| `animation.enabled` | `false` | If `true`, builds an animated GIF of each scenario from a screenshot after every passed step. The `@screenshots:animated` and `@screenshots:animated:skip` tags take precedence, and a truthy `BEHAT_SCREENSHOT_ANIMATION_SKIP` environment variable overrides the tags and this option. Requires the `gd` PHP extension. |
| `animation.frame_delay` | `500` | Delay between GIF frames, in milliseconds. |
| `animation.max_width` | `0` | Maximum GIF frame width, in pixels. Wider frames are cropped to their left-hand side. `0` leaves the width unbounded. |
| `animation.max_height` | `0` | Maximum GIF frame height, in pixels. Taller frames are cropped to the top of the page at full resolution, and this cap leaves their width unchanged. `0` leaves the height unbounded. Useful with `always_fullscreen`, where the frame height follows the page height. |
| `purge` | `false` | If `true`, removes every file in `dir` at the start of each run. A truthy `BEHAT_SCREENSHOT_PURGE` environment variable turns it on for 1 run. Useful while debugging tests. |
| `always_fullscreen` | `false` | If `true`, captures every screenshot fullscreen, including the ones `I save screenshot` captures. |
| `info_types` | None | Information to add to the HTML screenshot, in the order listed: any of `url`, `feature`, `step` and `datetime`. With no types listed, the extension adds none. |
| `failed_prefix` | `failed_` | Value of the `{failed_prefix}` token, which the default `filename_pattern_failed` puts before the feature filename. Use it to tell failed screenshots apart from requested ones. |
| `filename_pattern` | `{datetime:U}.{feature_file}.feature_{step_line}.{ext}` | Filename pattern for every screenshot except failed and named ones. |
| `filename_pattern_failed` | `{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}` | Filename pattern for screenshots of failed steps. |

### Filename tokens

Filename patterns and screenshot names accept the following tokens. In URL token values, the extension replaces every run of characters other than letters, digits, underscores and hyphens with 1 underscore. The URL examples are for a page at `http://example.com/mypath/subpath?myquery=1#somefragment`:

| Token              | Substituted with                                                                | Example value(s)                                             |
|--------------------|---------------------------------------------------------------------------------|--------------------------------------------------------------|
| `{ext}`            | The extension of the file captured                                              | `html` or `png`                                              |
| `{failed_prefix}`  | The value of the `failed_prefix` option                                         | `failed_`, `error_` (include the `_` suffix if you want it)  |
| `{url}`            | Full URL                                                                        | `http_example_com_mypath_subpath_myquery_1_somefragment`     |
| `{url_origin}`     | Scheme with domain                                                              | `http_example_com`                                           |
| `{url_relative}`   | Path + query + fragment                                                         | `mypath_subpath_myquery_1_somefragment`                      |
| `{url_domain}`     | Domain                                                                          | `example_com`                                                |
| `{url_path}`       | Path                                                                            | `mypath_subpath`                                             |
| `{url_query}`      | Query                                                                           | `myquery_1`                                                  |
| `{url_fragment}`   | Fragment                                                                        | `somefragment`                                               |
| `{feature_file}`   | The filename of the running `.feature` file, without its extension              | `my_example.feature` -> `my_example`                         |
| `{feature}`        | Alias of `{feature_file}`                                                       | `my_example.feature` -> `my_example`                         |
| `{step_line}`      | Step line number                                                                | `1`, `10`, `100`                                             |
| `{step_line:%03d}` | Step line number with leading zeros. Modifiers are from `sprintf()`.            | `001`, `010`, `100`                                          |
| `{step_name}`      | Step name without `Given/When/Then`, with spaces replaced by underscores and double quotes removed | `I_am_on_the_test_page`                   |
| `{datetime}`       | Current date and time. Defaults to the `Ymd_His` format.                        | `20010310_171618`                                            |
| `{datetime:U}`     | Current date and time as a Unix timestamp. Modifiers are from `date()`.         | `1697490961`                                                 |

To keep the URL tokens the same across environments that serve the site under different hosts, such as a local machine and CI, set `BEHAT_SCREENSHOT_TOKEN_HOST`. The URL tokens then use its value in place of the current URL's host:

```shell
BEHAT_SCREENSHOT_TOKEN_HOST=example.com vendor/bin/behat
```

## Auto-purge

Screenshots from earlier runs stay in the screenshot directory by default. To remove them at the start of every run, set `purge` to `TRUE`:

```php
$extension = new Extension(BehatScreenshotExtension::class, [
  'purge' => TRUE,
]);
```

To purge for 1 run, set the `BEHAT_SCREENSHOT_PURGE` environment variable to a truthy value:

```shell
BEHAT_SCREENSHOT_PURGE=1 vendor/bin/behat
```

## Additional information on screenshots

To add the page's URL, feature, step and time to every HTML screenshot, list them in `info_types`. The information appears in the order listed, and the PNG image is unchanged:

```php
$extension = new Extension(BehatScreenshotExtension::class, [
  'info_types' => ['url', 'feature', 'step', 'datetime'],
]);
```

With all 4 types listed, the extension adds this text to the top of the captured HTML:

```html
Current URL: http://example.com<br />
Feature: My feature<br />
Step: I save screenshot (line 8)<br />
Datetime: 2025-01-19 00:01:10<hr/>
<!DOCTYPE html>
<html>
...
</html>
```

To add an entry of your own, call `appendInfo()` on `ScreenshotContext` from your context class. Your entries appear even when `info_types` is empty:

```php
#[\Behat\Hook\BeforeScenario]
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

[CONTRIBUTING.md](CONTRIBUTING.md) covers local setup, linting, the tests, running the suites on Behat 4, the animated GIF profiler and adding a configuration option.

---
_This repository was created using the [Scaffold](https://getscaffold.dev/) project template_
