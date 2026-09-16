<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension;

/**
 * Screenshot configuration passed to every screenshot-aware context.
 */
readonly class ScreenshotConfig {

  /**
   * ScreenshotConfig constructor.
   *
   * @param string $dir
   *   Directory to store screenshots.
   * @param bool $shouldCaptureOnFailed
   *   Whether to capture a screenshot after a failed step.
   * @param string $failedPrefix
   *   Filename prefix for a failed step screenshot.
   * @param bool $shouldPurge
   *   Whether to purge the screenshot directory before the test run starts.
   * @param bool $shouldAlwaysCaptureFullscreen
   *   Whether to capture every screenshot fullscreen.
   * @param bool $shouldCaptureOnEveryStep
   *   Whether to capture a screenshot after every step.
   * @param string $filenamePattern
   *   Filename pattern.
   * @param string $filenamePatternFailed
   *   Filename pattern for failed steps.
   * @param array<int,string> $infoTypes
   *   Information types to add to a screenshot, in rendering order.
   * @param bool $shouldAnimate
   *   Whether to build an animated GIF of each scenario from its per-step
   *   screenshots.
   * @param int $animationFrameDelay
   *   Delay between animated GIF frames, in milliseconds.
   * @param int $animationMaxWidth
   *   Maximum animated GIF frame width, in pixels; 0 leaves it unbounded.
   * @param int $animationMaxHeight
   *   Maximum animated GIF frame height, in pixels; 0 leaves it unbounded.
   */
  public function __construct(
    public string $dir,
    public bool $shouldCaptureOnFailed,
    public string $failedPrefix,
    public bool $shouldPurge,
    public bool $shouldAlwaysCaptureFullscreen,
    public bool $shouldCaptureOnEveryStep,
    public string $filenamePattern,
    public string $filenamePatternFailed,
    public array $infoTypes,
    public bool $shouldAnimate,
    public int $animationFrameDelay,
    public int $animationMaxWidth,
    public int $animationMaxHeight,
  ) {
  }

  /**
   * Create screenshot configuration from processed extension configuration.
   *
   * @param array<mixed> $config
   *   Configuration processed by the extension's configuration tree, keyed as
   *   in the Behat configuration.
   *
   * @return self
   *   Screenshot configuration.
   *
   * @throws \InvalidArgumentException
   *   When a key is missing or holds a value of a type its node rejects.
   */
  public static function fromArray(array $config): self {
    return new self(
      dir: self::readString($config, 'dir'),
      shouldCaptureOnFailed: self::readBool($config, 'on_failed'),
      failedPrefix: self::readString($config, 'failed_prefix'),
      shouldPurge: self::readBool($config, 'purge'),
      shouldAlwaysCaptureFullscreen: self::readBool($config, 'always_fullscreen'),
      shouldCaptureOnEveryStep: self::readBool($config, 'on_every_step'),
      filenamePattern: self::readString($config, 'filename_pattern'),
      filenamePatternFailed: self::readString($config, 'filename_pattern_failed'),
      infoTypes: self::readStringList($config, 'info_types'),
      shouldAnimate: self::readBool($config, 'animation.enabled'),
      animationFrameDelay: self::readInt($config, 'animation.frame_delay'),
      animationMaxWidth: self::readInt($config, 'animation.max_width'),
      animationMaxHeight: self::readInt($config, 'animation.max_height'),
    );
  }

  /**
   * Read a value by its key path.
   *
   * @param array<mixed> $config
   *   Processed configuration.
   * @param string $path
   *   Key path, with nested keys separated by dots.
   *
   * @return mixed
   *   Value at the key path.
   *
   * @throws \InvalidArgumentException
   *   When a key on the path is missing or a parent value is not an array.
   */
  protected static function readValue(array $config, string $path): mixed {
    $value = $config;
    $parents = [];

    foreach (explode('.', $path) as $key) {
      if (!is_array($value)) {
        throw self::createTypeException(implode('.', $parents), 'an array', $value);
      }

      if (!array_key_exists($key, $value)) {
        throw new \InvalidArgumentException(sprintf('Screenshot configuration "%s" is missing.', $path));
      }

      $parents[] = $key;
      $value = $value[$key];
    }

    return $value;
  }

  /**
   * Read a boolean value.
   *
   * @param array<mixed> $config
   *   Processed configuration.
   * @param string $path
   *   Key path, with nested keys separated by dots.
   *
   * @return bool
   *   Boolean value.
   *
   * @throws \InvalidArgumentException
   *   When the value is missing or not a boolean.
   */
  protected static function readBool(array $config, string $path): bool {
    $value = self::readValue($config, $path);

    if (!is_bool($value)) {
      throw self::createTypeException($path, 'a boolean', $value);
    }

    return $value;
  }

  /**
   * Read an integer value.
   *
   * @param array<mixed> $config
   *   Processed configuration.
   * @param string $path
   *   Key path, with nested keys separated by dots.
   *
   * @return int
   *   Integer value.
   *
   * @throws \InvalidArgumentException
   *   When the value is missing or not an integer.
   */
  protected static function readInt(array $config, string $path): int {
    $value = self::readValue($config, $path);

    if (!is_int($value)) {
      throw self::createTypeException($path, 'an integer', $value);
    }

    return $value;
  }

  /**
   * Read a scalar value as a string.
   *
   * @param array<mixed> $config
   *   Processed configuration.
   * @param string $path
   *   Key path, with nested keys separated by dots.
   *
   * @return string
   *   String value.
   *
   * @throws \InvalidArgumentException
   *   When the value is missing or not a scalar.
   */
  protected static function readString(array $config, string $path): string {
    $value = self::readValue($config, $path);

    if (!is_scalar($value)) {
      throw self::createTypeException($path, 'a scalar', $value);
    }

    return (string) $value;
  }

  /**
   * Read an array of scalar values as a list of strings.
   *
   * @param array<mixed> $config
   *   Processed configuration.
   * @param string $path
   *   Key path, with nested keys separated by dots.
   *
   * @return array<int,string>
   *   String values in their original order.
   *
   * @throws \InvalidArgumentException
   *   When the value is missing, not an array, or holds a non-scalar item.
   */
  protected static function readStringList(array $config, string $path): array {
    $value = self::readValue($config, $path);

    if (!is_array($value)) {
      throw self::createTypeException($path, 'an array', $value);
    }

    $list = [];

    foreach ($value as $key => $item) {
      if (!is_scalar($item)) {
        throw self::createTypeException($path . '.' . $key, 'a scalar', $item);
      }

      $list[] = (string) $item;
    }

    return $list;
  }

  /**
   * Create an exception for a value of the wrong type.
   *
   * @param string $path
   *   Key path of the value.
   * @param string $expected
   *   Expected type, with its article.
   * @param mixed $value
   *   Value that was found.
   *
   * @return \InvalidArgumentException
   *   Exception naming the key path and both types.
   */
  protected static function createTypeException(string $path, string $expected, mixed $value): \InvalidArgumentException {
    return new \InvalidArgumentException(sprintf('Screenshot configuration "%s" must be %s, %s given.', $path, $expected, get_debug_type($value)));
  }

}
