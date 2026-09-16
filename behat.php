<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Formatter\JUnitFormatter;
use Behat\Config\Formatter\PrettyFormatter;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\Config\TesterOptions;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use DrevOps\BehatPhpServer\PhpServerContext;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;
use DVDoug\Behat\CodeCoverage\Extension as CodeCoverageExtension;

$suite = (new Suite('default'))
  ->withPaths('%paths.base%/tests/behat/features')
  ->addContext('FeatureContext', [['screenshot_dir' => '%paths.base%/.logs/screenshots']])
  ->addContext('BehatCliContext')
  ->addContext(ScreenshotContext::class)
  ->addContext(PhpServerContext::class, ['webroot' => '%paths.base%/tests/behat/fixtures', 'host' => '0.0.0.0']);

$mink = new Extension(MinkExtension::class, [
  'files_path' => '%paths.base%/tests/behat/fixtures',
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
                // Containers and CI runners have no GPU.
                '--disable-gpu',
                // The rest increase stability and speed.
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
  'dir' => '%paths.base%/.logs/screenshots',
  'on_failed' => TRUE,
  'purge' => TRUE,
  'info_types' => ['url', 'feature', 'step', 'datetime'],
]);

$coverage = new Extension(CodeCoverageExtension::class, [
  'filter' => ['include' => ['directories' => ['src' => NULL]]],
  'reports' => [
    'text' => ['showColors' => TRUE, 'showOnlySummary' => TRUE],
    'html' => ['target' => '.logs/coverage/behat/.coverage-html'],
    'cobertura' => ['target' => '.logs/coverage/behat/cobertura.xml'],
  ],
]);

$profile = (new Profile('default', ['autoload' => ['%paths.base%/tests/behat/bootstrap']]))
  ->withTesterOptions((new TesterOptions())->withStrictResultInterpretation())
  ->withSuite($suite)
  ->withExtension($mink)
  ->withExtension($screenshot)
  ->withExtension($coverage)
  ->withFormatter(new PrettyFormatter())
  ->withFormatter((new JUnitFormatter())->withOutputPath('%paths.base%/.logs/test_results/behat'));

return (new Config())->withProfile($profile);
