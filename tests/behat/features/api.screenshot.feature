@testapi
Feature: Behat screenshots

  Ensure that Behat is capable of taking screenshots.

  @phpserver
  Scenario: Make HTML phpserver of the test page
    Given I am on the phpserver test page
    Then the response status code should be 200
    And I save screenshot
    Then file wildcard "*_make_html_phpserver_of_the_test_page_00.html" should exist