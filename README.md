# Behat Screenshot Extension
Behat extension and a step definition to create HTML and image screenshots on demand or test fail.

[![CircleCI](https://circleci.com/gh/integratedexperts/behat-screenshot.svg?style=shield)](https://circleci.com/gh/integratedexperts/behat-screenshot)
[![Latest Stable Version](https://poser.pugx.org/integratedexperts/behat-screenshot/v/stable)](https://packagist.org/packages/integratedexperts/behat-screenshot)
[![Total Downloads](https://poser.pugx.org/integratedexperts/behat-screenshot/downloads)](https://packagist.org/packages/integratedexperts/behat-screenshot)
[![License](https://poser.pugx.org/integratedexperts/behat-screenshot/license)](https://packagist.org/packages/integratedexperts/behat-screenshot)

## Features
* Make screenshot using `I save screenshot` or `save screenshot` step definition.
* Make screenshot when test fails.
* Screnshot is saved as HTML page for Goutte driver.
* Screnshot is saved as PNG image for Selenium driver.
* Screenshot directory can be specified through environment variable `BEHAT_SCREENSHOT_DIR` - useful for CI systems to override values in `behat.yml`.

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

## Options

- `dir:path/to/dir`

  Path to directory to save scereenshots. Directory structure will be created if the directory does not exist.
  
- `fail`: `true`/`false`
  
  Prefix failed screenshots with 'fail_' string. Useful to distinguish failed and intended screenshots.
      
- `purge`: `false`/`false`
  
  Remove all files from the screenshots directory on each test run. Useful during debugging of tests.

## Local development
1. Install Docker.
2. Run `composer docker:start`.

### Running tests
```bash
composer test
```
### Cleanup an environment
```bash
composer cleanup
```
