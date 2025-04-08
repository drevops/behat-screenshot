# Claude Code Configuration

## Standard Operations

These are the standard operations that should be performed when working with this codebase:

### Code Quality Checks
```
composer lint       # Run all linting tools
composer lint-fix   # Automatically fix linting issues 
composer test       # Run PHPUnit tests without coverage
```

### Coding Standards
- Follow Drupal coding standards
- Use snake_case for variable names (e.g., `$file_path` not `$filePath`)
- Use TRUE/FALSE constants (uppercase) rather than true/false
- Use NULL constant (uppercase) rather than null
- Maintain proper docblock annotations

### PHPUnit Configuration
- Uses PHPUnit 11.5 with configuration in phpunit.xml
- Coverage reports are generated in .logs/coverage directory

## Recent Improvements
- Added support for fullscreen screenshots with two algorithms:
  - Stitch algorithm (default): Takes multiple screenshots while scrolling and stitches them together
  - Resize algorithm: Temporarily resizes browser window to capture full page
- Updated autoloader from PSR-0 to PSR-4
- Made constants public as per PHP 8.2+ standards
- Improved error messages for file operations
- Optimized token replacement logic
- Updated PHPUnit configuration to remove deprecated attributes
- Ensured all variables follow Drupal's snake_case naming convention

## Code Structure
The Behat Screenshot extension provides functionality to capture screenshots during Behat test runs. Its main components are:

1. **BehatScreenshotExtension**: Handles configuration and service container integration
2. **ScreenshotContext**: Provides Behat steps and screenshot capabilities, including fullscreen screenshot functionality with both stitch and resize algorithms
3. **Tokenizer**: Processes dynamic filename generation with tokens

## Best Practices for Contributing
1. Always run tests before and after changes
2. Maintain existing code style and standards
3. Fix PHPUnit deprecations as they arise
4. Use verbose error messages to aid debugging