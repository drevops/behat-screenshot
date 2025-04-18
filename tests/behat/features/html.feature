@html
Feature: HTML screenshots

  Ensure that screenshots for HTML-base driver can be captured.

  @phpserver
  Scenario: Capture a screenshot using HTML-based driver
    When I am on the screenshot test page
    And the response status code should be 200
    And I save screenshot
    Then file wildcard "*.html.feature_10\.html" should exist
    And file wildcard "*.html.feature_10\.png" should not exist

  @phpserver
  Scenario: Capture a screenshot with name using HTML-based driver
    When I am on the screenshot test page
    And the response status code should be 200
    And I save screenshot with name "hello-screenshot"
    Then file wildcard "hello-screenshot\.html" should exist
    And file wildcard "hello-screenshot\.png" should not exist
