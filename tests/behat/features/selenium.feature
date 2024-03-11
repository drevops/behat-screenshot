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
  Scenario: Capture a screenshot with name using Selenium driver
    When I am on the screenshot test page
    And I save screenshot with name "hello-selenium-screenshot"
    Then file wildcard "hello-selenium-screenshot.png" should exist
    And file wildcard "hello-selenium-screenshot.html" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver
    When I am on the screenshot test page
    And I save screenshot with name "{datetime:U}.{url_domain}.{url_path}.{ext}"
    Then file wildcard "*.phpserver.screenshot.html.png" should exist
    Then file wildcard "*.phpserver.screenshot.html.html" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver
    When I am on the screenshot test page
    And I save screenshot with name "{datetime:U}.{url_origin}.{ext}"
    Then file wildcard "*.http%3A%2F%2Fphpserver.png" should exist
    Then file wildcard "*.http%3A%2F%2Fphpserver.html" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver
    When I am on the screenshot test page
    And I save screenshot with name "{datetime:U}.{url}.{ext}"
    Then file wildcard "*.http%3A%2F%2Fphpserver%3A8888%2Fscreenshot.html.png" should exist
    Then file wildcard "*.http%3A%2F%2Fphpserver%3A8888%2Fscreenshot.html.html" should exist
