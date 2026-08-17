# Contributing

Thank you for considering a contribution to this project. This guide covers setting up a local environment and running the linting and tests.

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
  --remote-debugging-address=0.0.0.0 \
  --remote-debugging-port=9222
```

```shell
composer test-bdd  # Run BDD tests.

BEHAT_CLI_DEBUG=1 composer test-bdd  # Run BDD tests with debug output.
```

### Profiling animated GIF assembly

Building a scenario's animated GIF happens in the `AfterScenario` handler, so its cost lands as a pause after the scenario's last step rather than as slower steps. The profiler replays a scenario of a given length through the real hooks and reports how long each phase took, how much memory peaked, and how many pixels were encoded compared to how many were captured.

It is excluded from `composer test` because it takes minutes to run.

```shell
composer profile  # Profile 25, 50 and 100-step scenarios.

BEHAT_SCREENSHOT_PROFILE_STEPS=10,20 composer profile  # Profile other lengths.
```

The report is printed and written to `.logs/profile/animation-assembly.txt`, which CI collects as a build artifact.
