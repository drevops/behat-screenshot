Feature: Selenium screenshots

  Ensure that screenshots for Selenium driver can be captured.

  @phpserver @javascript
  Scenario: Capture a screenshot using Selenium driver
    When I am on the screenshot test page
    And save screenshot
    Then file wildcard "*.selenium.feature_8.png" should exist
    And file wildcard "*.selenium.feature_8.html" should exist
    And save 800 x 600 screenshot
    Then file wildcard "*.selenium.feature_11.png" should exist
    And save 1440 x 900 screenshot
    And file wildcard "*.selenium.feature_13.html" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot using Selenium driver
    When I am on the screenshot test page
    And I save screenshot with name "hello-selenium-screenshot"
    Then file wildcard "hello-selenium-screenshot.png" should exist
    And file wildcard "hello-selenium-screenshot.html" should exist
