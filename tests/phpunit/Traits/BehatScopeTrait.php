<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Traits;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
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
   * Create a before scenario scope whose nodes carry the given tags.
   *
   * @param array<int,string> $scenario_tags
   *   Tags of the scenario, as the Gherkin parser returns them.
   * @param array<int,string> $feature_tags
   *   Tags of the feature, as the Gherkin parser returns them.
   *
   * @return \Behat\Behat\Hook\Scope\BeforeScenarioScope
   *   Before scenario scope.
   */
  protected function createBeforeScenarioScope(array $scenario_tags = [], array $feature_tags = []): BeforeScenarioScope {
    $feature_node = $this->createStub(FeatureNode::class);
    $feature_node->method('getTags')->willReturn($feature_tags);
    $scenario = $this->createStub(ScenarioInterface::class);
    $scenario->method('getTags')->willReturn($scenario_tags);

    return new BeforeScenarioScope($this->createStub(Environment::class), $feature_node, $scenario);
  }

  /**
   * Create a before step scope for the given feature file and step.
   *
   * @param string|null $feature_file
   *   Feature file path.
   * @param int $step_line
   *   Step line number.
   * @param string $step_text
   *   Step text.
   * @param string|null $feature_title
   *   Feature title.
   *
   * @return \Behat\Behat\Hook\Scope\BeforeStepScope
   *   Before step scope.
   */
  protected function createBeforeStepScope(?string $feature_file = NULL, int $step_line = 0, string $step_text = '', ?string $feature_title = NULL): BeforeStepScope {
    $feature_node = $this->createStub(FeatureNode::class);
    $feature_node->method('getFile')->willReturn($feature_file);
    $feature_node->method('getTitle')->willReturn($feature_title);
    $step_node = $this->createStub(StepNode::class);
    $step_node->method('getLine')->willReturn($step_line);
    $step_node->method('getText')->willReturn($step_text);

    return new BeforeStepScope($this->createStub(Environment::class), $feature_node, $step_node);
  }

  /**
   * Create an after step scope with the given result state.
   *
   * @param bool $is_passed
   *   Whether the step passed.
   *
   * @return \Behat\Behat\Hook\Scope\AfterStepScope
   *   After step scope.
   */
  protected function createAfterStepScope(bool $is_passed = TRUE): AfterStepScope {
    $step_result = $this->createStub(StepResult::class);
    $step_result->method('isPassed')->willReturn($is_passed);

    return new AfterStepScope($this->createStub(Environment::class), $this->createStub(FeatureNode::class), $this->createStub(StepNode::class), $step_result);
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
    $feature_node = $this->createStub(FeatureNode::class);
    $feature_node->method('getFile')->willReturn($feature_file);
    $scenario = $this->createStub(ScenarioInterface::class);
    $scenario->method('getLine')->willReturn($scenario_line);

    return new AfterScenarioScope($this->createStub(Environment::class), $feature_node, $scenario, $this->createStub(TestResult::class));
  }

}
