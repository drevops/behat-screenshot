<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Traits;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\StepNode;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Tester\Result\TestResult;

/**
 * Provides methods to create Behat hook scopes backed by mocks.
 *
 * @phpstan-ignore trait.unused
 */
trait BehatScopeTrait {

  /**
   * Create an after step scope with the given result state.
   *
   * @param bool $passed
   *   Whether the step passed.
   *
   * @return \Behat\Behat\Hook\Scope\AfterStepScope
   *   After step scope.
   */
  protected function createAfterStepScope(bool $passed = TRUE): AfterStepScope {
    $result = $this->createMock(StepResult::class);
    $result->method('isPassed')->willReturn($passed);

    return new AfterStepScope($this->createMock(Environment::class), $this->createMock(FeatureNode::class), $this->createMock(StepNode::class), $result);
  }

  /**
   * Create an after scenario scope.
   *
   * @param string|null $feature_file
   *   Feature file path.
   * @param int $scenario_line
   *   Scenario line number.
   *
   * @return \Behat\Behat\Hook\Scope\AfterScenarioScope
   *   After scenario scope.
   */
  protected function createAfterScenarioScope(?string $feature_file = NULL, int $scenario_line = 0): AfterScenarioScope {
    $feature_node = $this->createMock(FeatureNode::class);
    $feature_node->method('getFile')->willReturn($feature_file);
    $scenario = $this->createMock(ScenarioInterface::class);
    $scenario->method('getLine')->willReturn($scenario_line);

    return new AfterScenarioScope($this->createMock(Environment::class), $feature_node, $scenario, $this->createMock(TestResult::class));
  }

}
