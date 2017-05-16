Feature: Selenium screenshots

  Ensure that screenshots for Selenium driver can be captured.

  @phpserver @javascript
  Scenario: Capture a screenshot using Selenium driver
    Given I remove all files from screenshot directory
    When I am on the screenshot test page
    And I save screenshot
    Then file wildcard "*.selenium.feature_[\9\]\.png" should exist
