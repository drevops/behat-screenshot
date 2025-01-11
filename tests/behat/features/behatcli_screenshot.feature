@behatcli
Feature: Screenshot context

  Background:
    Given screenshot fixture

  Scenario: Test Screenshot context with all parameters defined in behat.yml
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension:
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
      DrevOps\BehatScreenshotExtension: ~
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

  Scenario: Test Screenshot context with 'filenamePattern' override
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension:
            filenamePattern: "{datetime:U}.{feature_file}.feature_{step_line:%03d}.{ext}"
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_007\.html" should exist

  Scenario: Test Screenshot context with env variable BEHAT_SCREENSHOT_DIR set to custom dir.
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension: ~
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    And behat cli file wildcard "screenshots" should not exist
    And "BEHAT_SCREENSHOT_DIR" environment variable is set to "screenshots_custom"

    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots_custom/*.failed_stub.feature_6\.html" should exist

  Scenario: Test Screenshot context with 'dir' set to '%paths.base%/screenshots' env variable BEHAT_SCREENSHOT_DIR set to custom dir.
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension:
            dir: "%paths.base%/screenshots"
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    And behat cli file wildcard "screenshots" should not exist
    And "BEHAT_SCREENSHOT_DIR" environment variable is set to "screenshots_custom"

    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots_custom/*.failed_stub.feature_6\.html" should exist

  Scenario: Test Screenshot context with 'fail' set to 'true' which will save screenshot on fail
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension:
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
      DrevOps\BehatScreenshotExtension:
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

  Scenario: Test Screenshot context with 'filenamePatternFailed' override & save screenshot on fail
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension:
            fail: true
            filenamePatternFailed: "{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line:%03d}.{ext}"
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_006\.html" should exist

  Scenario: Test Screenshot context with 'filenamePatternFailed' override & not save screenshot on fail
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension:
            fail: false
            filenamePatternFailed: "{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line:%03d}.{ext}"
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6\.html" should not exist
    And behat cli file wildcard "screenshots/*.failed_stub.feature_006\.html" should not exist

  Scenario: Test Screenshot context with 'purge' set to 'false' which will not purge files between runs
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension:
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
      DrevOps\BehatScreenshotExtension:
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
      DrevOps\BehatScreenshotExtension:
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

  Scenario: Test Screenshot context with env variable BEHAT_SCREENSHOT_PURGE set to '1' which will purge files between
    runs and env variable BEHAT_SCREENSHOT_DIR set to 'screenshots_custom'.
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension: ~
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """

    And behat cli file wildcard "screenshots" should not exist
    And "BEHAT_SCREENSHOT_DIR" environment variable is set to "screenshots_custom"
    And "BEHAT_SCREENSHOT_PURGE" environment variable is set to "1"

    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots_custom/*.failed_stub.feature_6\.html" should exist
    # Run again, but with error on another line.
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    And behat cli file wildcard "screenshots_custom/*.failed_stub.feature_7\.html" should exist
    # Assert that the file from the previous run is not present.
    And behat cli file wildcard "screenshots_custom/*.failed_stub.feature_6\.html" should not exist

  Scenario: Test Screenshot context with 'show_path' set to 'true' will output current path to screenshot files.
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension:
            purge: true
            show_path: true
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6\.html" should contain:
      """
      Current path: http://0.0.0.0:8888/screenshot.html
      """

  Scenario: Test Screenshot context with 'show_path' set to 'false' will not output current path to screenshot files.
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension:
            purge: true
            show_path: false
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6\.html" should not contain:
      """
      Current path: http://0.0.0.0:8888/screenshot.html
      """
