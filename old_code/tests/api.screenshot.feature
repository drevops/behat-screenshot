@testapi
Feature: Behat screenshots

  Ensure that Behat is capable of taking screenshots.

  @phpserver
  Scenario: Make HTML screenshot of the test page
    Given I am on the screenshot test page
    When I save screenshot
    Then file wildcard "*_make_html_screenshot_of_the_test_page_00.html" should exist
