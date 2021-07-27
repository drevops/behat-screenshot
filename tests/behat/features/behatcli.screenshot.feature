@behatcli
Feature: Screenshot context

  Background:
    Given screenshot fixture

  Scenario: Test Screenshot context with all parameters defined in behat.yml
    Given screenshot context behat configuration with value:
      """
      IntegratedExperts\BehatScreenshotExtension:
            dir: "%paths.base%/screenshots"
            fail: true
            purge: true
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_7\.html" should exist

  Scenario: Test Screenshot context with no parameters defined in behat.yml
    Given screenshot context behat configuration with value:
      """
      IntegratedExperts\BehatScreenshotExtension: ~
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_7\.html" should exist

  Scenario: Test Screenshot context with 'fail' set to 'true' which will save screenshot on fail
    Given screenshot context behat configuration with value:
      """
      IntegratedExperts\BehatScreenshotExtension:
            fail: true
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6\.html" should exist

  Scenario: Test Screenshot context with 'fail' set to 'false' which will not save screenshot on fail
    Given screenshot context behat configuration with value:
      """
      IntegratedExperts\BehatScreenshotExtension:
            fail: false
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6\.html" should not exist

  Scenario: Test Screenshot context with 'purge' set to 'false' which will not purge files between runs
    Given screenshot context behat configuration with value:
      """
      IntegratedExperts\BehatScreenshotExtension:
            purge: false
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6\.html" should exist
    # Run again, but with error on another line.
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    And behat cli file wildcard "screenshots/*.failed_stub.feature_7\.html" should exist
    # Assert that the file from the previous run is still present.
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6\.html" should exist

  Scenario: Test Screenshot context with 'purge' set to 'true' which will purge files between runs
    Given screenshot context behat configuration with value:
      """
      IntegratedExperts\BehatScreenshotExtension:
            purge: true
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6\.html" should exist
    # Run again, but with error on another line.
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    And behat cli file wildcard "screenshots/*.failed_stub.feature_7\.html" should exist
    # Assert that the file from the previous run is not present.
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6\.html" should not exist

  Scenario: Test Screenshot context with 'purge' set to 'false', but env variable set to 'true' which will purge files between runs.
    Given screenshot context behat configuration with value:
      """
      IntegratedExperts\BehatScreenshotExtension:
            purge: false
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6\.html" should exist
    # Run again, but with error on another line.
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And the response status code should be 404
      """
    When "BEHAT_SCREENSHOT_PURGE" environment variable is set to "1"
    And I run "behat --no-colors --strict"
    And behat cli file wildcard "screenshots/*.failed_stub.feature_7\.html" should exist
    # Assert that the file from the previous run is not present.
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6\.html" should not exist
