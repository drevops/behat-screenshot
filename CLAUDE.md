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
- Maintain proper docblock annotations

### PHPUnit Configuration
- Uses PHPUnit 11.5 with configuration in phpunit.xml
- Coverage reports are generated in .logs/coverage/phpunit

## Code Structure
The Behat Screenshot extension provides functionality to capture screenshots during Behat test runs. Its main components are:

1. **BehatScreenshotExtension**: Defines the configuration schema and registers the initializer with the service container
2. **ScreenshotContextInitializer**: Passes the resolved configuration to every screenshot-aware context and purges the screenshot directory when enabled
3. **ScreenshotContext**: Provides the Behat steps and hooks; fullscreen capture temporarily resizes the browser window to the full page height
4. **AnimatedGif**: Assembles a scenario's captured frames into a single animated GIF
5. **Tokenizer**: Expands the tokens used in filename patterns

## Best Practices for Contributing
1. Always run tests before and after changes
2. Maintain existing code style and standards
3. Fix PHPUnit deprecations as they arise
4. Use verbose error messages to aid debugging
