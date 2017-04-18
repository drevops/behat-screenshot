Feature: Screenshot.

  Ensure that Behat is capable of starting PHP server and asserting the content
  on the test page and make screenshot it.

  @phpserver
  Scenario: Visit test page served by PHP Server
    Given I am on the phpserver test page
    Then the response status code should be 200
    And I save screenshot