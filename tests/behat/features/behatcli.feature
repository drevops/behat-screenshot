@behatcli
Feature: Behat CLI context

  Tests for BehatCliContext functionality that is used to test contexts
  by running Behat through CLI.

  - Assert that BehatCliContext context itself can be bootstrapped by Behat,
    including failed runs assertions.

  Background:
    Given a file named "features/bootstrap/FeatureContextTest.php" with:
      """
      <?php
       use Behat\Behat\Context\Context;
       use Behat\MinkExtension\Context\MinkContext;
       class FeatureContextTest extends MinkContext implements Context {
       /**
        * Go to the phpserver test page.
        *
        * @Given /^(?:|I )am on (?:|the )phpserver test page$/
        * @When /^(?:|I )go to (?:|the )phpserver test page$/
        */
        public function goToPhpServerTestPage()
        {
            $this->getSession()->visit('http://0.0.0.0:8888/screenshot.html');
        }

        /**
         * @Given I throw test exception with message :message
         */
        public function throwTestException($message) {
          throw new \RuntimeException($message);
        }
      }
      """
    And a file named "behat.yml" with:
      """
      default:
        suites:
          default:
            contexts:
              - FeatureContextTest
              - DrevOps\BehatPhpServer\PhpServerContext:
                - docroot: "%paths.base%/tests/behat/features/fixtures"
                  host: "0.0.0.0"
                  port: 8888
        extensions:
          Behat\MinkExtension:
            browserkit_http: ~
            selenium2: ~
            base_url: http://0.0.0.0:8888
      """
      And a file named "tests/behat/features/fixtures/screenshot.html" with:
      """
      <!DOCTYPE html>
        <html>
        <head>
          <title>Test page</title>
        </head>
        <body>
        Test page
        </body>
      </html>
      """

  Scenario: Test passes
    Given a file named "features/pass.feature" with:
      """
      Feature: Homepage
        @phpserver
        Scenario: Anonymous user visits screenshot test page
          Given I am on the phpserver test page
          And the response status code should be 200
      """

    When I run "behat --no-colors --strict"
    Then it should pass with:
      """
      Feature: Homepage

        @phpserver
        Scenario: Anonymous user visits screenshot test page # features/pass.feature:3
          Given I am on the phpserver test page              # FeatureContextTest::goToPhpServerTestPage()
          And the response status code should be 200         # FeatureContextTest::assertResponseStatus()

      1 scenario (1 passed)
      2 steps (2 passed)
      """

  Scenario: Test fails
    Given a file named "features/fail.feature" with:
      """
      Feature: Homepage
        @phpserver
        Scenario: Anonymous user visits screenshot test page
          Given I am on the phpserver test page
          And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail with:
      """
      Feature: Homepage

        @phpserver
        Scenario: Anonymous user visits screenshot test page # features/fail.feature:3
          Given I am on the phpserver test page              # FeatureContextTest::goToPhpServerTestPage()
          And the response status code should be 404         # FeatureContextTest::assertResponseStatus()
            Current response status code is 200, but 404 expected. (Behat\Mink\Exception\ExpectationException)

      --- Failed scenarios:

          features/fail.feature:3

      1 scenario (1 failed)
      2 steps (1 passed, 1 failed)
      """

  Scenario: Test fails with exception
    Given a file named "features/fail.feature" with:
      """
      Feature: Homepage
        @phpserver
        Scenario: Anonymous user visits screenshot test page
          Given I am on the phpserver test page
          Then I throw test exception with message "Intentional error"
          And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail with:
      """
      Feature: Homepage

        @phpserver
        Scenario: Anonymous user visits screenshot test page           # features/fail.feature:3
          Given I am on the phpserver test page                        # FeatureContextTest::goToPhpServerTestPage()
          Then I throw test exception with message "Intentional error" # FeatureContextTest::throwTestException()
            Intentional error (RuntimeException)
          And the response status code should be 404                   # FeatureContextTest::assertResponseStatus()

      --- Failed scenarios:

          features/fail.feature:3

      1 scenario (1 failed)
      3 steps (1 passed, 1 failed, 1 skipped)
      """
