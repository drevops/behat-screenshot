# Contributing

Follow this guide to set up the project, run its checks and tests and add a configuration option.

## Setup

To install the dependencies:

```shell
composer install
```

## Linting

To check or fix the code, run:

```shell
composer lint      # Check standards, run static analysis, lint feature files.
composer lint-fix  # Apply the fixes Rector and PHPCBF can make automatically.
```

## Tests

To run the unit tests, run:

```shell
composer test           # Run unit tests without coverage.
composer test-coverage  # Run unit tests with coverage.
```

### BDD tests

The Selenium tests need a Selenium Docker container, and the headless tests need a local Chromium with remote debugging on port 9222. To start both on macOS:

```shell
# Start Chromium in container for Selenium-based tests.
docker run -d -p 4444:4444 selenium/standalone-chromium

# Install Chromium with brew.
brew install --cask chromium
# Launch Chromium with remote debugging.
"$(brew --prefix)/bin/chromium" \
  --remote-debugging-address=127.0.0.1 \
  --remote-debugging-port=9222
```

Selenium reaches the test server at `http://host.docker.internal:8888`. On Linux, where that host doesn't resolve, set `BEHAT_JAVASCRIPT_BASE_URL` to your host's address, such as `http://172.17.0.1:8888`.

To run the BDD tests, run:

```shell
composer test-bdd  # Run BDD tests.

BEHAT_CLI_DEBUG=1 composer test-bdd  # Run BDD tests with debug output.

# Run only the BDD tests that need neither Selenium nor Chromium.
composer test-bdd -- --tags=~@selenium --tags=~@headless
```

The suite runs in strict mode, so a step without a matching definition fails the run.

### Behat 4

Both Behat majors read the suite configuration from `behat.php`, so suite changes go there. The steps of the `@behatcli` scenarios write a `behat.php` and run a second Behat process with it.

`composer.json` already allows the Mink extension 3.0 alpha that Behat 4 needs. To run the suites on Behat 4:

```shell
composer update --with=behat/behat:^4
composer test
composer test-bdd
```

To switch back, run `composer update --with=behat/behat:^3`.

Behat 4 ignores docblock annotations, so declare hooks and step definitions with PHP attributes such as `#[BeforeScenario]` and `#[When('I save screenshot')]`. Behat 4 also reports tag names with their leading `@`, so read tags through `ScreenshotContext::isTagged()` or a hook filter such as `#[BeforeScenario('@javascript')]`. Avoid `hasTag()`, which misses a tag reported with its `@`.

### Continuous integration

CI runs PHP 8.3, 8.4 and 8.5 against Behat 3 and Behat 4, with `normal` and `lowest` dependencies. Each job installs its Behat major with `composer update --with="behat/behat:^3"` or `--with="behat/behat:^4"`. Composer combines that `--with` constraint with the one in `composer.json`, so the `lowest` jobs still start from the `composer.json` minimums.

Jobs are named `PHP <version>, Behat <major>, Deps <dependencies>`, for example `PHP 8.4, Behat 4, Deps lowest`. The `main` branch ruleset requires a status check named after every job. A change to the matrix or a job name needs the same change to the ruleset's required status checks.

### Profiling animated GIF assembly

The extension builds a scenario's GIF in the `AfterScenario` handler, so the build shows up as a pause after the last step. The profiler runs scenarios through the `ScreenshotContext` hooks and reports:

- How long each phase took
- How much memory peaked
- How many pixels were encoded, compared to how many were captured

The profiler takes minutes, so `composer test` excludes it. To run it:

```shell
composer profile  # Profile 25, 50 and 100-step scenarios.

BEHAT_SCREENSHOT_PROFILE_STEPS=10,20 composer profile  # Profile other lengths.
```

The profiler prints the report and writes it to `.logs/profile/animation-assembly.txt`.

## Adding a configuration option

To add an option:

1. Add the node to `BehatScreenshotExtension::configure()`. Write the default as a literal in the node, and declare the values the option accepts.
2. Add a promoted property to `ScreenshotConfig` and map the key to it in `ScreenshotConfig::fromArray()`.
3. In `ScreenshotContext`, read the property through `getScreenshotConfig()`.
4. Document the option in the options table in `README.md` and add it to `behat.dist.php`.
5. If an environment variable overrides the option:
   1. Declare a public `ENV_*` constant for the variable on `ScreenshotContextInitializer`, named after the variable without its `BEHAT_SCREENSHOT_` prefix.
   2. Apply the variable in `ScreenshotContextInitializer::applyEnvironmentOverrides()`.
   3. Mention the variable in the option's row in `README.md`.

These tests fail until the option is wired through:

- `ScreenshotConfigTest` fails until `dataProviderFromArrayMapsKeyToProperty()` has a dataset for the new key and `fromArray()` maps the key.
- `BehatScreenshotExtensionTest` asserts the count of top-level options, so raise that count for a new top-level option.
- `BehatDistConfigTest` fails until `behat.dist.php` sets the new option.
- `EnvironmentVariableNamingTest` fails when a class passes `getenv()` anything other than 1 of its own `ENV_*` constants. Add the new variable to the test's `ScreenshotContextInitializer` dataset.
