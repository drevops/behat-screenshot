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

  Scenario: Test Screenshot context with 'info_types' set to 'true' will output current URL to screenshot files.
    Given screenshot context behat configuration with value:
      """
      DrevOps\BehatScreenshotExtension:
            purge: true
            info_types:
              - url
              - feature
              - step
              - datetime
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
      Current URL: http://0.0.0.0:8888/screenshot.html
      """
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6\.html" should contain:
      """
      Feature: Stub feature
      """
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6\.html" should contain:
      """
      Step: the response status code should be 404 (line 6)
      """
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6\.html" should contain:
      """
      Datetime:
      """

  Scenario: Test Screenshot context with 'info_types' set to 'false' will not output current URL to screenshot files.
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
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6\.html" should not contain:
      """
      Current URL: http://0.0.0.0:8888/screenshot.html
      """

  # Test for a headless browser using behat-chrome/behat-chrome-extension driver.
  # @see https://gitlab.com/behat-chrome/behat-chrome-extension
  # Install Chromium with brew: `brew cask install chromedriver`
  # Launch chrome: /opt/homebrew/Caskroom/chromium/latest/chromium.wrapper.sh --remote-debugging-address=0.0.0.0 --remote-debugging-port=9222
  @headless
  Scenario: Test Screenshot context using behat-chrome/behat-chrome-extension
    Given full behat configuration:
      """
      default:
        suites:
          default:
            contexts:
              - FeatureContextTest:
                  - screenshot_dir: '%paths.base%/screenshots'
              - DrevOps\BehatPhpServer\PhpServerContext:
                  webroot: '%paths.base%/tests/behat/fixtures'
                  host: 0.0.0.0
              - DrevOps\BehatScreenshotExtension\Context\ScreenshotContext
        extensions:
          DMore\ChromeExtension\Behat\ServiceContainer\ChromeExtension: ~
          Behat\MinkExtension:
            browser_name: chrome
            base_url: http://127.0.0.1:8888
            sessions:
              default:
                chrome:
                  api_url: "http://127.0.0.1:9222"
                  download_behavior: allow
                  download_path: /download
                  validate_certificate: false

          DrevOps\BehatScreenshotExtension:
            dir: "%paths.base%/screenshots"
            fail: true
            purge: true
            info_types:
              - url
              - feature
              - step
              - datetime
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

    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      # Deliberately empty line to assert for a newly created screenshot file on re-run.
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_8\.html" should exist
