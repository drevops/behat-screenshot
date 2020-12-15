# Behat Screenshot Extension
Behat extension and a step definition to create HTML and image screenshots on demand or test fail.

[![CircleCI](https://circleci.com/gh/integratedexperts/behat-screenshot.svg?style=shield)](https://circleci.com/gh/integratedexperts/behat-screenshot)
[![Latest Stable Version](https://poser.pugx.org/integratedexperts/behat-screenshot/v/stable)](https://packagist.org/packages/integratedexperts/behat-screenshot)
[![Total Downloads](https://poser.pugx.org/integratedexperts/behat-screenshot/downloads)](https://packagist.org/packages/integratedexperts/behat-screenshot)
[![License](https://poser.pugx.org/integratedexperts/behat-screenshot/license)](https://packagist.org/packages/integratedexperts/behat-screenshot)

## Features
* Create a screenshot using `I save screenshot` or `save screenshot` step definition.
* Create a screenshot when test fails.
* Screenshot is saved as HTML page for Goutte driver.
* Screenshot is saved as both HTML and PNG image for Selenium driver.
* Screenshot directory can be specified through environment variable `BEHAT_SCREENSHOT_DIR` (useful for CI systems to override values in `behat.yml`).
* Screenshots can be purged after every test run by setting `purge: true` (useful during test debugging). 

## Installation
`composer require integratedexperts/behat-screenshot`

## Usage
Example `behat.yml`:
```yaml
default:
  suites:
    default:
      contexts:
        - IntegratedExperts\BehatScreenshotExtension\Context\ScreenshotContext
        - FeatureContext
  extensions:
    IntegratedExperts\BehatScreenshotExtension:
      dir: %paths.base%/screenshots
      fail: true
      purge: false
```

In your feature:
```
  Given I am on "http://google.com"  
  Then I save screenshot
```

You may optionally specify size of browser window in the screenshot step:

```
  Then I save 1440 x 900 screenshot
  And I save 800 x 600 screenshot
```

## Options

- `dir:` `path/to/dir`

  Path to directory to save scereenshots. Directory structure will be created if the directory does not exist.
  
- `fail:` `true` or `false`
  
  Prefix failed screenshots with 'fail_' string. Useful to distinguish failed and intended screenshots.
      
- `purge:` `false` or `false`
  
  Remove all files from the screenshots directory on each test run. Useful during debugging of tests.

## Maintenance

### Local development setup
1. Install Docker.
2. Start environment: `composer docker:start`.
3. Install dependencies: `composer docker:cli -- composer install --ansi --no-suggest`.

### Lint code
```bash
composer docker:cli -- composer lint

```
### Run tests
```bash
composer docker:cli -- composer test
```

### Cleanup an environment
```bash
composer cleanup
```
