@behatcli
Feature: Behat CLI Trait context

  Tests for an additional BehatCliTrait functionality that is used to test Behat Steps traits
  by running Behat through CLI.

  - Assert that BehatCliTrait trait context can be bootstrapped by Behat and that custom step
  definitions work as expected.

  Background:

    Given some behat configuration
    And screenshot fixture

  Scenario: Test passes
    Given scenario steps tagged with "@phpserver":
      """
      Given I am on the phpserver test page
      And the response status code should be 200
      """
    When I run "behat --no-colors --strict"
    Then it should pass

  Scenario: Test fails
    Given scenario steps tagged with "@phpserver":
      """
      Given I am on the phpserver test page
      And the response status code should be 400
      """
    When I run "behat --no-colors --strict"
    Then it should fail with an error:
      """
      Current response status code is 200, but 400 expected.
      """

  Scenario: Test fails with exception
    Given scenario steps tagged with "@phpserver":
      """
      Given I am on the phpserver test page
      Then I throw test exception with message "Intentional error"
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail with an exception:
      """
      Intentional error
      """

  Scenario: Test environment variables added in separate steps are all passed to Behat
    Given scenario steps:
      """
      Then the environment variable "BEHAT_CLI_TEST_FIRST" should have the value "first"
      And the environment variable "BEHAT_CLI_TEST_SECOND" should have the value "second"
      """
    And I add the environment variable "BEHAT_CLI_TEST_FIRST" with the value "first"
    And I add the environment variable "BEHAT_CLI_TEST_SECOND" with the value "second"
    When I run "behat --no-colors --strict"
    Then it should pass

  Scenario: Test adding an environment variable again replaces its value
    Given scenario steps:
      """
      Then the environment variable "BEHAT_CLI_TEST_VARIABLE" should have the value "second"
      """
    And I add the environment variable "BEHAT_CLI_TEST_VARIABLE" with the value "first"
    And I add the environment variable "BEHAT_CLI_TEST_VARIABLE" with the value "second"
    When I run "behat --no-colors --strict"
    Then it should pass

  Scenario: Test an environment variable that was not added is not passed to Behat
    Given scenario steps:
      """
      Then the environment variable "BEHAT_CLI_TEST_MISSING" should have the value "value"
      """
    When I run "behat --no-colors --strict"
    Then it should fail with an error:
      """
      The environment variable "BEHAT_CLI_TEST_MISSING" is not set.
      """
