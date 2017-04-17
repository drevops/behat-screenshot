Feature: Working with PHP server.

  Ensure that Behat is capable of starting PHP server and asserting the content
  on the test page.

  @phpserver
  Scenario: Visit test page served by PHP Server
    Given I am on the phpserver test page
    Then the response status code should be 200
    And I save screenshot