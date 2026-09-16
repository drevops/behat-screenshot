# Contributing

Thank you for considering a contribution to this project. This guide covers setting up a local environment, running the linting and tests, and adding a configuration option.

## Setup

```shell
composer install
```

## Linting

```shell
composer lint      # Check standards, run static analysis, lint feature files.
composer lint-fix  # Apply the fixes Rector and PHPCBF can make automatically.
```

## Tests

```shell
composer test           # Run unit tests without coverage.
composer test-coverage  # Run unit tests with coverage.
```

### BDD tests

There are tests for Selenium and Headless drivers. Selenium requires a Docker container and headless requires a Chromium browser.

```shell
# Start Chromium in container for Selenium-based tests.
docker run -d -p 4444:4444 -p 9222:9222 selenium/standalone-chromium

# Install Chromium with brew.
brew install --cask chromium
# Launch Chromium with remote debugging.
"$(brew --prefix)/bin/chromium" \
  --remote-debugging-address=127.0.0.1 \
  --remote-debugging-port=9222
```

Selenium reaches the test server at `http://host.docker.internal:8888`. Where that host does not resolve, such as Docker on Linux, set `BEHAT_JAVASCRIPT_BASE_URL`, for example to `http://172.17.0.1:8888`.

```shell
composer test-bdd  # Run BDD tests.

BEHAT_CLI_DEBUG=1 composer test-bdd  # Run BDD tests with debug output.

# Run only the BDD tests that need neither Selenium nor Chromium.
composer test-bdd -- --tags=~@selenium --tags=~@headless
```

The suite runs in strict mode, so a step without a matching definition fails the run.

### Behat 4

The suite is configured in `behat.php`, which both Behat majors read, so a change to the suite goes in that one file. The `@behatcli` scenarios write a `behat.php` of their own for each inner run.

`dmore/behat-chrome-extension` 1.x requires Behat 3, so remove it before switching and skip the `@headless` scenarios:

```shell
composer remove --dev --no-update dmore/behat-chrome-extension
composer update --with=behat/behat:^4
composer test
composer test-bdd -- --tags=~@headless
```

To switch back, restore `dmore/behat-chrome-extension` in `composer.json` and run `composer update --with=behat/behat:^3`.

Behat 4 doesn't read docblock annotations, so hooks and step definitions are declared with PHP attributes such as `#[BeforeScenario]` and `#[When('I save screenshot')]`. It also reports tag names with their leading `@`, so tags are read through `ScreenshotContext::isTagged()` or a hook filter such as `#[BeforeScenario('@javascript')]` rather than `hasTag()`.

### Continuous integration

CI runs PHP 8.3, 8.4 and 8.5 against Behat 3 and Behat 4, with `normal` and `lowest` dependencies. Each job picks its Behat major with `composer update --with="behat/behat:^3"` or `^4`. Composer combines that temporary constraint with the one in `composer.json` rather than replacing it, so the `lowest` jobs still start from the `composer.json` floors.

Jobs are named `PHP <version>, Behat <major>, Deps <dependencies>`, for example `PHP 8.4, Behat 4, Deps lowest`. The `main` branch ruleset requires every job by that name, so a change to the matrix or to the job name needs the same change to the ruleset's required status checks.

### Profiling animated GIF assembly

Building a scenario's animated GIF happens in the `AfterScenario` handler, so its cost lands as a pause after the scenario's last step rather than as slower steps. The profiler replays a scenario of a given length through the real hooks and reports how long each phase took, how much memory peaked, and how many pixels were encoded compared to how many were captured.

It is excluded from `composer test` because it takes minutes to run.

```shell
composer profile  # Profile 25, 50 and 100-step scenarios.

BEHAT_SCREENSHOT_PROFILE_STEPS=10,20 composer profile  # Profile other lengths.
```

The report is printed and written to `.logs/profile/animation-assembly.txt`.

## Adding a configuration option

1. Add the node to `BehatScreenshotExtension::configure()`. The node holds the option's default, written as a literal, and the values it accepts.
2. Add a promoted property to `ScreenshotConfig` and map the key to it in `ScreenshotConfig::fromArray()`.
3. Read the property through `getScreenshotConfig()` where `ScreenshotContext` uses it.
4. Document the option in the options table in `README.md` and add it to `behat.dist.php`.
5. If an environment variable overrides the option, declare a public `ENV_*` constant for it on `ScreenshotContextInitializer`, named after the variable without its `BEHAT_SCREENSHOT_` prefix, apply it in `applyEnvironmentOverrides()`, and mention the variable in the option's row in `README.md`.

`ScreenshotConfigTest` fails until the new key and property have a dataset in `dataProviderFromArrayMapsKeyToProperty()`, and that dataset fails unless `fromArray()` maps the key to the property. A new top-level option also changes the option count that `BehatScreenshotExtensionTest` asserts. `BehatDistConfigTest` fails until `behat.dist.php` sets the new option. `EnvironmentVariableNamingTest` fails when a class passes `getenv()` anything other than one of its own `ENV_*` constants, and until the class's dataset lists the new variable.
