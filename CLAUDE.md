# Claude Code Configuration

## Standard Operations

These are the standard operations that should be performed when working with this codebase:

### Code Quality Checks

```
composer lint           # Run all linting tools
composer lint-fix       # Automatically fix linting issues
composer test           # Run PHPUnit tests without coverage
composer test-coverage  # Run PHPUnit tests with coverage
```

See `CONTRIBUTING.md` for the BDD test suite and the animation profiler.

### Coding Standards

- Follow Drupal coding standards
- Use snake_case for variable names (e.g., `$file_path` not `$filePath`)
- Use TRUE/FALSE constants (uppercase) rather than true/false
- Use NULL constant (uppercase) rather than null
- Name boolean properties as predicates that describe state: `should` for behaviour the configuration turns on (e.g., `$shouldPurge`), `is` or `has` for state set during a run (e.g., `$hasPurged`, `$scenarioIsAnimated`)
- Declare every public `ScreenshotContext` method on `ScreenshotAwareContextInterface`, except the hooks and step definitions Behat calls
- Create collaborators with side effects, such as `Filesystem`, `Finder` and `AnimatedGifEncoder`, in a protected `create*()` method that returns the new instance, and read the clock through `getCurrentTime()`; create value objects (`ScreenshotConfig`, a container `Definition`) and exceptions inline
- Declare each configuration default as a literal in its `BehatScreenshotExtension::configure()` node, and name each environment variable a class reads with a public `ENV_*` constant on that class, formed from the variable name without its `BEHAT_SCREENSHOT_` prefix and read with `getenv(self::ENV_*)`
- Declare hooks and step definitions with PHP attributes such as `#[BeforeScenario]` and `#[When('I save screenshot')]`, never with docblock annotations, which Behat 4 doesn't read
- Read tags through `ScreenshotContext::isTagged()` or a hook filter string such as `#[BeforeScenario('@javascript')]`, never through `hasTag()`, which misses a tag reported with its leading `@`
- Name Behat extensions by their full class name in every configuration, and keep the suite configuration in `behat.php`, which both Behat majors read
- Maintain proper docblock annotations

### PHPUnit Configuration

- Uses PHPUnit 12.5 with configuration in phpunit.xml
- Coverage reports are generated in .logs/coverage/phpunit

### Continuous Integration

- `.github/workflows/test.yml` runs PHP 8.3, 8.4 and 8.5 against Behat 3 and Behat 4 with `normal` and `lowest` dependencies; Behat 4 jobs remove `dmore/behat-chrome-extension` and skip the `@headless` scenarios
- Jobs are named `PHP <version>, Behat <major>, Deps <dependencies>`, and the `main` branch ruleset requires every job by that name, so a change to the matrix or the job name needs the same change to the ruleset

## Code Structure

The Behat Screenshot extension provides functionality to capture screenshots during Behat test runs. Its main components are:

1. **BehatScreenshotExtension**: Defines the configuration schema and registers the initializer with the service container
2. **ScreenshotConfig**: Holds the typed configuration, mapped from the processed configuration tree by `fromArray()`
3. **ScreenshotContextInitializer**: Applies the `BEHAT_SCREENSHOT_DIR` and `BEHAT_SCREENSHOT_PURGE` overrides, passes a `ScreenshotConfig` to every screenshot-aware context and purges the screenshot directory when enabled
4. **ScreenshotContext**: Provides the Behat steps and hooks; fullscreen capture temporarily resizes the browser window to the full page height
5. **AnimatedGifEncoder**: Assembles a scenario's captured frames into a single animated GIF
6. **Tokenizer**: Expands the tokens used in filename patterns

## Best Practices for Contributing

1. Always run tests before and after changes
2. Maintain existing code style and standards
3. Fix PHPUnit deprecations as they arise
4. Use verbose error messages to aid debugging
