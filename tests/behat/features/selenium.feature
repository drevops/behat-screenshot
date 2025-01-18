@selenium
Feature: Selenium screenshots

  Ensure that screenshots for Selenium driver can be captured.

  @phpserver @javascript
  Scenario: Capture a screenshot using Selenium driver
    Given I am on the screenshot test page
    When save screenshot
    Then file wildcard "*.selenium.feature_9.png" should exist
    And file wildcard "*.selenium.feature_9.html" should exist
    When save 800 x 600 screenshot
    Then file wildcard "*.selenium.feature_12.png" should exist
    When save 1440 x 900 screenshot
    And file wildcard "*.selenium.feature_14.html" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver with a custom name
    Given I am on the screenshot test page
    When I save screenshot with name "hello-selenium-screenshot"
    Then file wildcard "hello-selenium-screenshot.png" should exist
    And file wildcard "hello-selenium-screenshot.html" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver with a custom name containing URL domain and path tokens
    Given I am on the screenshot test page
    When I save screenshot with name "test.{url_domain}.{url_path}.{ext}"
    Then file wildcard "test.example_com.screenshot_html.html" should exist
    And file wildcard "test.example_com.screenshot_html.png" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver with a custom name containing URL origin token
    Given I am on the screenshot test page
    When I save screenshot with name "test.{url_origin}.{ext}"
    Then file wildcard "test.http_example_com.html" should exist
    And file wildcard "test.http_example_com.png" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver with a custom name containing URL string token
    Given I am on the screenshot test page
    When I save screenshot with name "test.{url}.{ext}"
    Then file wildcard "test.http_example_com_8888_screenshot_html.html" should exist
    And file wildcard "test.http_example_com_8888_screenshot_html.png" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver with a custom name containing URL query and fragment tokens
    Given I am on the screenshot test page with query "foo=test-foo" and fragment "foo-fragment"
    When I save screenshot with name "test.{url_query}.{url_fragment}.{ext}"
    Then file wildcard "test.foo_test-foo.foo-fragment.html" should exist
    And file wildcard "test.foo_test-foo.foo-fragment.png" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver with a custom name containing URL relative path token
    Given I am on the screenshot test page with query "foo=test-foo" and fragment "foo-fragment"
    When I save screenshot with name "test.{url_relative}.{ext}"
    Then file wildcard "test.screenshot_html_foo_test-foo_foo-fragment.html" should exist
    And file wildcard "test.screenshot_html_foo_test-foo_foo-fragment.png" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver with a custom name containing URL query and fragment in a URL token
    Given I am on the screenshot test page with query "foo=test-foo" and fragment "foo-fragment"
    When I save screenshot with name "test.{url}.{ext}"
    Then file wildcard "test.http_example_com_8888_screenshot_html_foo_test-foo_foo-fragment.html" should exist
    And file wildcard "test.http_example_com_8888_screenshot_html_foo_test-foo_foo-fragment.png" should exist

  @phpserver @javascript
  Scenario: Capture a screenshot with name using Selenium driver with a custom name containing step line and name tokens
    Given I am on the screenshot test page with query "foo=test-foo" and fragment "foo-fragment"
    When I save screenshot with name "test.{step_line}.{step_name}.{ext}"
    Then file wildcard "*.I_save_screenshot_with_name_test.{step_line}.{step_name}.{ext}.png" should exist
    And file wildcard "*.I_save_screenshot_with_name_test.{step_line}.{step_name}.{ext}.html" should exist
