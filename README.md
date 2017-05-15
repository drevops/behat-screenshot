# behat-screenshot
Behat context to make screenshots

[![CircleCI](https://circleci.com/gh/integratedexperts/behat-screenshot.svg?style=shield)](https://circleci.com/gh/integratedexperts/behat-screenshot)
[![Latest Stable Version](https://poser.pugx.org/integratedexperts/behat-screenshot/v/stable)](https://packagist.org/packages/integratedexperts/behat-screenshot)
[![Total Downloads](https://poser.pugx.org/integratedexperts/behat-screenshot/downloads)](https://packagist.org/packages/integratedexperts/behat-screenshot)
[![License](https://poser.pugx.org/integratedexperts/behat-screenshot/license)](https://packagist.org/packages/integratedexperts/behat-screenshot)

## Features
* Make screenshot using `I save screenshot` step definition.
* Make screenshot when test fails.
* Screnshot is saved as HTML page for Goutte driver.
* Screnshot is saved as PNG image for Selenium driver.

## Installation
`composer require integratedexperts/behat-screenshot`

## Usage
Example `behat.yml`:
```yaml
default:
  suites:
    default:
      contexts:
        - IntegratedExperts\BehatScreenshot\ScreenshotContext:
          -
            dir: %paths.base%/screenshots
            fail: true
            date_format: Ymh_His
        - FeatureContext            
```

In your feature:
```
  Given I am on "http://google.com"  
  Then I save screenshot
```

test feature:
```
@testapi
Feature: Behat screenshots

  Ensure that Behat is capable of taking screenshots.

  @phpserver
  Scenario: Make HTML screenshot of the test page
    Given I am on the screenshot test page
    When I save screenshot
    Then file wildcard "*.api.screenshot.feature_\[9\]\.html" should exist
```

## Local development
### Preparing local environment
1. Install [Vagrant](https://www.vagrantup.com/downloads.html) and [VirtualBox](https://www.virtualbox.org/wiki/Downloads) and [Composer](https://getcomposer.org/).
2. Install all dependencies: `composer install`
3. Provision local VM: `vagrant up`

### Running tests
```bash
vagrant ssh
scripts/selenium-install.sh
scripts/selenium-start.sh
composer test
```
### Cleanup an environment
```bash
composer cleanup
```
