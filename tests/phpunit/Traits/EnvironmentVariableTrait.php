<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Traits;

use PHPUnit\Framework\Attributes\After;

/**
 * Provides methods to change environment variables for a single test.
 *
 * @phpstan-ignore trait.unused
 */
trait EnvironmentVariableTrait {

  /**
   * Original values of the changed environment variables, keyed by name.
   *
   * FALSE marks a variable that was not set.
   *
   * @var array<string,string|false>
   */
  protected array $originalEnvironmentVariables = [];

  /**
   * Set or unset an environment variable until the test finishes.
   *
   * @param string $name
   *   Environment variable name.
   * @param string|null $value
   *   Value to set, or NULL to unset the variable.
   */
  protected function setEnvironmentVariable(string $name, ?string $value): void {
    if (!array_key_exists($name, $this->originalEnvironmentVariables)) {
      $this->originalEnvironmentVariables[$name] = getenv($name);
    }

    putenv($value === NULL ? $name : $name . '=' . $value);
  }

  /**
   * Restore the environment variables changed during the test.
   */
  #[After]
  protected function restoreChangedEnvironmentVariables(): void {
    foreach ($this->originalEnvironmentVariables as $name => $value) {
      putenv($value === FALSE ? $name : $name . '=' . $value);
    }

    $this->originalEnvironmentVariables = [];
  }

}
