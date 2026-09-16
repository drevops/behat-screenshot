@behatcli
Feature: Screenshot context

  Scenario: Test Screenshot context with all parameters defined in the configuration
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'dir' => '%paths.base%/screenshots',
        'on_failed' => TRUE,
        'purge' => TRUE,
        'always_fullscreen' => FALSE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_7.html" should exist

  Scenario: Test Screenshot context with no parameters defined in the configuration
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension(new Extension(BehatScreenshotExtension::class));

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_7.html" should exist

  Scenario: Test Screenshot context with 'filename_pattern' override
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'filename_pattern' => '{datetime:U}.{feature_file}.feature_{step_line:%03d}.{ext}',
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_007.html" should exist

  Scenario: Test Screenshot context with environment variable BEHAT_SCREENSHOT_DIR set to custom dir
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension(new Extension(BehatScreenshotExtension::class));

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    And behat cli file wildcard "screenshots" should not exist
    And I add the environment variable "BEHAT_SCREENSHOT_DIR" with the value "screenshots_custom"

    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots_custom/*.failed_stub.feature_6.html" should exist

  Scenario: Test Screenshot context with 'dir' set to '%paths.base%/screenshots' and environment variable BEHAT_SCREENSHOT_DIR set to custom dir
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'dir' => '%paths.base%/screenshots',
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    And behat cli file wildcard "screenshots" should not exist
    And I add the environment variable "BEHAT_SCREENSHOT_DIR" with the value "screenshots_custom"

    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots_custom/*.failed_stub.feature_6.html" should exist

  Scenario: Test Screenshot context with 'on_failed' set to 'true' which will save screenshot on fail
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'on_failed' => TRUE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6.html" should exist

  Scenario: Test Screenshot context with 'on_failed' set to 'true' and there is no session so no content to save
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'on_failed' => TRUE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots" should not exist

  Scenario: Test Screenshot context with 'on_failed' set to 'false' which will not save screenshot on fail
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'on_failed' => FALSE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6.html" should not exist

  Scenario: Test Screenshot context with 'filename_pattern_failed' override and save screenshot on fail
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'on_failed' => TRUE,
        'filename_pattern_failed' => '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line:%03d}.{ext}',
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_006.html" should exist

  Scenario: Test Screenshot context with 'filename_pattern_failed' override and not save screenshot on fail
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'on_failed' => FALSE,
        'filename_pattern_failed' => '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line:%03d}.{ext}',
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6.html" should not exist
    And behat cli file wildcard "screenshots/*.failed_stub.feature_006.html" should not exist

  Scenario: Test Screenshot context with 'purge' set to 'false' which will not purge files between runs
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'purge' => FALSE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6.html" should exist
    # Run again, but with error on another line.
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    And behat cli file wildcard "screenshots/*.failed_stub.feature_7.html" should exist
    # Assert that the file from the previous run is still present.
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6.html" should exist

  Scenario: Test Screenshot context with 'purge' set to 'true' which will purge files between runs
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'purge' => TRUE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6.html" should exist
    # Run again, but with error on another line.
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    And behat cli file wildcard "screenshots/*.failed_stub.feature_7.html" should exist
    # Assert that the file from the previous run is not present.
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6.html" should not exist

  Scenario: Test Screenshot context with 'purge' set to 'false', but environment variable set to 'true' which will purge files between runs
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'purge' => FALSE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6.html" should exist
    # Run again, but with error on another line.
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And the response status code should be 404
      """
    When I add the environment variable "BEHAT_SCREENSHOT_PURGE" with the value "1"
    And I run "behat --no-colors --strict"
    And behat cli file wildcard "screenshots/*.failed_stub.feature_7.html" should exist
    # Assert that the file from the previous run is not present.
    And behat cli file wildcard "screenshots/*.failed_stub.feature_6.html" should not exist

  Scenario: Test Screenshot context with environment variable BEHAT_SCREENSHOT_PURGE set to '1' which will purge files between runs and environment variable BEHAT_SCREENSHOT_DIR set to 'screenshots_custom'
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension(new Extension(BehatScreenshotExtension::class));

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """

    And behat cli file wildcard "screenshots" should not exist
    And I add the environment variable "BEHAT_SCREENSHOT_DIR" with the value "screenshots_custom"
    And I add the environment variable "BEHAT_SCREENSHOT_PURGE" with the value "1"

    When I run "behat --no-colors --strict"
    Then it should fail
    And behat cli file wildcard "screenshots_custom/*.failed_stub.feature_6.html" should exist
    # Run again, but with error on another line.
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    And behat cli file wildcard "screenshots_custom/*.failed_stub.feature_7.html" should exist
    # Assert that the file from the previous run is not present.
    And behat cli file wildcard "screenshots_custom/*.failed_stub.feature_6.html" should not exist

  Scenario: Test Screenshot context with 'info_types' listing every type will output the URL, feature, step and datetime to screenshot files
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'purge' => TRUE,
        'info_types' => ['url', 'feature', 'step', 'datetime'],
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6.html" should contain:
      """
      Current URL: http://0.0.0.0:8888/screenshot.html
      """
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6.html" should contain:
      """
      Feature: Stub feature
      """
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6.html" should contain:
      """
      Step: the response status code should be 404 (line 6)
      """
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6.html" should contain:
      """
      Datetime:
      """

  Scenario: Test Screenshot context with 'info_types' not set will not output current URL to screenshot files
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'purge' => TRUE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 404
      """
    When I run "behat --no-colors --strict"
    Then it should fail
    And behat screenshot file matching "screenshots/*.failed_stub.feature_6.html" should not contain:
      """
      Current URL: http://0.0.0.0:8888/screenshot.html
      """

  Scenario: Test Screenshot context with the '@screenshots' tag captures a screenshot after every step
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'purge' => TRUE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver @screenshots":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_5.html" should exist
    And behat cli file wildcard "screenshots/*.stub.feature_6.html" should exist

  Scenario: Test Screenshot context without the '@screenshots' tag captures no screenshot after a passed step
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'sessions' => ['browserkit_http' => ['browserkit_http' => NULL]],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'purge' => TRUE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots" should not exist

  @selenium
  Scenario: Test Screenshot context with JavaScript and all parameters defined in the configuration
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'browser_name' => 'chrome',
        'javascript_session' => 'selenium2',
        'sessions' => [
          'browserkit_http' => ['browserkit_http' => NULL],
          'selenium2' => [
            'selenium2' => [
              'wd_host' => 'http://localhost:4444/wd/hub',
              'capabilities' => [
                'browser' => 'chrome',
                'extra_capabilities' => [
                  'goog:chromeOptions' => [
                    'args' => [
                      '--disable-gpu',
                      '--disable-extensions',
                      '--disable-infobars',
                      '--disable-popup-blocking',
                      '--disable-translate',
                      '--no-first-run',
                      '--test-type',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'dir' => '%paths.base%/screenshots',
        'on_failed' => TRUE,
        'purge' => TRUE,
        'always_fullscreen' => FALSE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver @javascript":
      """
      When I am on the phpserver test page
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_6.html" should exist
    And behat cli file wildcard "screenshots/*.stub.feature_6.png" should exist

  @selenium
  Scenario: Test Screenshot context with JavaScript fullscreen screenshot
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'browser_name' => 'chrome',
        'javascript_session' => 'selenium2',
        'sessions' => [
          'browserkit_http' => ['browserkit_http' => NULL],
          'selenium2' => [
            'selenium2' => [
              'wd_host' => 'http://localhost:4444/wd/hub',
              'capabilities' => [
                'browser' => 'chrome',
                'extra_capabilities' => [
                  'goog:chromeOptions' => [
                    'args' => [
                      '--disable-gpu',
                      '--disable-extensions',
                      '--disable-infobars',
                      '--disable-popup-blocking',
                      '--disable-translate',
                      '--no-first-run',
                      '--test-type',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'dir' => '%paths.base%/screenshots',
        'on_failed' => TRUE,
        'purge' => TRUE,
        'always_fullscreen' => FALSE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver @javascript":
      """
      When I am on the phpserver test page
      And I save fullscreen screenshot with name "fullscreen"
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/fullscreen.html" should exist
    And behat cli file wildcard "screenshots/fullscreen.png" should exist

  @selenium
  Scenario: Test Screenshot context records an animated GIF when tagged
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'browser_name' => 'chrome',
        'javascript_session' => 'selenium2',
        'sessions' => [
          'browserkit_http' => ['browserkit_http' => NULL],
          'selenium2' => [
            'selenium2' => [
              'wd_host' => 'http://localhost:4444/wd/hub',
              'capabilities' => [
                'browser' => 'chrome',
                'extra_capabilities' => [
                  'goog:chromeOptions' => [
                    'args' => [
                      '--disable-gpu',
                      '--disable-extensions',
                      '--disable-infobars',
                      '--disable-popup-blocking',
                      '--disable-translate',
                      '--no-first-run',
                      '--test-type',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'dir' => '%paths.base%/screenshots',
        'purge' => TRUE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver @javascript @screenshots:animated":
      """
      When I am on the phpserver test page
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.gif" should exist

  @selenium
  Scenario: Test Screenshot context skips an animated GIF when tagged to skip while animation is enabled globally
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'browser_name' => 'chrome',
        'javascript_session' => 'selenium2',
        'sessions' => [
          'browserkit_http' => ['browserkit_http' => NULL],
          'selenium2' => [
            'selenium2' => [
              'wd_host' => 'http://localhost:4444/wd/hub',
              'capabilities' => [
                'browser' => 'chrome',
                'extra_capabilities' => [
                  'goog:chromeOptions' => [
                    'args' => [
                      '--disable-gpu',
                      '--disable-extensions',
                      '--disable-infobars',
                      '--disable-popup-blocking',
                      '--disable-translate',
                      '--no-first-run',
                      '--test-type',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'dir' => '%paths.base%/screenshots',
        'purge' => TRUE,
        'animation' => ['enabled' => TRUE],
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver @javascript @screenshots:animated:skip":
      """
      When I am on the phpserver test page
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.gif" should not exist

  @selenium
  Scenario: Test Screenshot context skips an animated GIF for the whole suite when the environment variable is set
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'browser_name' => 'chrome',
        'javascript_session' => 'selenium2',
        'sessions' => [
          'browserkit_http' => ['browserkit_http' => NULL],
          'selenium2' => [
            'selenium2' => [
              'wd_host' => 'http://localhost:4444/wd/hub',
              'capabilities' => [
                'browser' => 'chrome',
                'extra_capabilities' => [
                  'goog:chromeOptions' => [
                    'args' => [
                      '--disable-gpu',
                      '--disable-extensions',
                      '--disable-infobars',
                      '--disable-popup-blocking',
                      '--disable-translate',
                      '--no-first-run',
                      '--test-type',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'dir' => '%paths.base%/screenshots',
        'purge' => TRUE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver @javascript @screenshots:animated":
      """
      When I am on the phpserver test page
      And I save screenshot
      """
    And I add the environment variable "BEHAT_SCREENSHOT_ANIMATION_SKIP" with the value "1"
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.png" should exist
    And behat cli file wildcard "screenshots/*.gif" should not exist

  @selenium
  Scenario: Test Screenshot context with JavaScript fullscreen short screenshot
    Given short screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'base_url' => 'http://0.0.0.0:8888',
        'browser_name' => 'chrome',
        'javascript_session' => 'selenium2',
        'sessions' => [
          'browserkit_http' => ['browserkit_http' => NULL],
          'selenium2' => [
            'selenium2' => [
              'wd_host' => 'http://localhost:4444/wd/hub',
              'capabilities' => [
                'browser' => 'chrome',
                'extra_capabilities' => [
                  'goog:chromeOptions' => [
                    'args' => [
                      '--disable-gpu',
                      '--disable-extensions',
                      '--disable-infobars',
                      '--disable-popup-blocking',
                      '--disable-translate',
                      '--no-first-run',
                      '--test-type',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'dir' => '%paths.base%/screenshots',
        'on_failed' => TRUE,
        'purge' => TRUE,
        'always_fullscreen' => FALSE,
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver @javascript":
      """
      When I am on the phpserver test page
      And I save fullscreen screenshot with name "fullscreen-short"
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/fullscreen-short.html" should exist
    And behat cli file wildcard "screenshots/fullscreen-short.png" should exist

  # Test for a headless browser using behat-chrome/behat-chrome-extension driver.
  # @see https://gitlab.com/behat-chrome/behat-chrome-extension
  # Note: this test does not use the Docker container. See CONTRIBUTING.md for more information.
  @headless
  Scenario: Test Screenshot context using behat-chrome/behat-chrome-extension
    Given screenshot fixture
    And behat configuration:
      """
      <?php

      declare(strict_types=1);

      use Behat\Config\Config;
      use Behat\Config\Extension;
      use Behat\Config\Profile;
      use Behat\Config\Suite;
      use Behat\MinkExtension\ServiceContainer\MinkExtension;
      use DMore\ChromeExtension\Behat\ServiceContainer\ChromeExtension;
      use DrevOps\BehatPhpServer\PhpServerContext;
      use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
      use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

      $suite = (new Suite('default'))
        ->addContext('FeatureContextTest')
        ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0'])
        ->addContext(ScreenshotContext::class);

      $mink = new Extension(MinkExtension::class, [
        'browser_name' => 'chrome',
        'base_url' => 'http://127.0.0.1:8888',
        'sessions' => [
          'default' => [
            'chrome' => [
              'api_url' => 'http://127.0.0.1:9222',
              'download_behavior' => 'allow',
              'download_path' => '/download',
              'validate_certificate' => FALSE,
            ],
          ],
        ],
      ]);

      $screenshot = new Extension(BehatScreenshotExtension::class, [
        'dir' => '%paths.base%/screenshots',
        'on_failed' => TRUE,
        'purge' => TRUE,
        'info_types' => ['url', 'feature', 'step', 'datetime'],
      ]);

      $profile = (new Profile('default'))
        ->withSuite($suite)
        ->withExtension(new Extension(ChromeExtension::class))
        ->withExtension($mink)
        ->withExtension($screenshot);

      return (new Config())->withProfile($profile);
      """
    And scenario steps tagged with "@phpserver":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_7.html" should exist

    # The `@skip-base-url-rewrite` tag skips the base_url rewrite used in
    # non-headless browser tests.
    And scenario steps tagged with "@phpserver @javascript @skip-base-url-rewrite":
      """
      When I am on the phpserver test page
      And the response status code should be 200
      # Deliberately empty line to assert for a newly created screenshot file on re-run.
      And I save screenshot
      """
    When I run "behat --no-colors --strict"
    Then it should pass
    And behat cli file wildcard "screenshots/*.stub.feature_8.html" should exist
    And behat cli file wildcard "screenshots/*.stub.feature_8.png" should exist
