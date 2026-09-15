<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use DrevOps\BehatScreenshotExtension\Tests\Traits\EnvironmentVariableTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * Test EnvironmentVariableTrait.
 */
#[CoversNothing]
class EnvironmentVariableTraitTest extends TestCase {

  use EnvironmentVariableTrait;

  /**
   * Variable that holds a value before the test changes it.
   */
  protected const SET_VARIABLE = 'ENVIRONMENT_VARIABLE_TRAIT_TEST_SET';

  /**
   * Variable that is unset before the test changes it.
   */
  protected const UNSET_VARIABLE = 'ENVIRONMENT_VARIABLE_TRAIT_TEST_UNSET';

  public function testSetEnvironmentVariableChangesVariables(): void {
    putenv(static::SET_VARIABLE . '=original');
    putenv(static::UNSET_VARIABLE);

    $this->setEnvironmentVariable(static::SET_VARIABLE, NULL);
    $this->setEnvironmentVariable(static::UNSET_VARIABLE, 'first');
    $this->setEnvironmentVariable(static::UNSET_VARIABLE, 'second');

    $this->assertFalse(getenv(static::SET_VARIABLE));
    $this->assertSame('second', getenv(static::UNSET_VARIABLE));
  }

  #[Depends('testSetEnvironmentVariableChangesVariables')]
  public function testChangedVariablesAreRestoredAfterTest(): void {
    $message = 'The trait did not restore the variables after the previous test. A hook method named like a private TestCase method runs that method instead.';

    $this->assertSame('original', getenv(static::SET_VARIABLE), $message);
    $this->assertFalse(getenv(static::UNSET_VARIABLE), $message);

    putenv(static::SET_VARIABLE);
  }

}
