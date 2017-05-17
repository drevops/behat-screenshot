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
            purge: false
        - FeatureContext            
```

In your feature:
```
  Given I am on "http://google.com"  
  Then I save screenshot
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
