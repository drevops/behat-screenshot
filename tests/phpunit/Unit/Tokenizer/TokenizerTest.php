<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit\Tokenizer;

use DrevOps\BehatScreenshot\Tests\Traits\ReflectionTrait;
use DrevOps\BehatScreenshotExtension\Tokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test Tokenizer.
 */
#[CoversClass(Tokenizer::class)]
class TokenizerTest extends TestCase {

  use ReflectionTrait;

  #[DataProvider('dataProviderScanTokens')]
  public function testScanTokens(string $textContainsTokens, array $expectedTokens): void {
    $tokens = Tokenizer::scanTokens($textContainsTokens);
    $this->assertEquals($expectedTokens, $tokens);
  }

  public static function dataProviderScanTokens(): array {
    return [
      [
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        [
          '{datetime:U}' => 'datetime:U',
          '{feature_file}' => 'feature_file',
          '{step_line}' => 'step_line',
          '{ext}' => 'ext',
        ],
      ],
      [
        '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
        [
          '{datetime:U}' => 'datetime:U',
          '{fail_prefix}' => 'fail_prefix',
          '{feature_file}' => 'feature_file',
          '{step_line}' => 'step_line',
          '{ext}' => 'ext',
        ],
      ],
    ];
  }

  #[DataProvider('dataProviderReplaceExtToken')]
  public function testReplaceExtToken(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void {
    $replacement = $this->callProtectedMethod(Tokenizer::class, 'replaceExtToken', [$token, $name, $qualifier, $format, $data]);
    $this->assertEquals($expected, $replacement);
  }

  public static function dataProviderReplaceExtToken(): array {
    return [
      ['{ext}', 'ext', NULL, NULL, [], 'html'],
      ['{ext}', 'ext', NULL, NULL, ['ext' => 'html'], 'html'],
      ['{ext}', 'ext', NULL, NULL, ['ext' => 'png'], 'png'],
      ['{ext}', 'ext', NULL, NULL, ['ext' => ''], 'html'],
    ];
  }

  #[DataProvider('dataProviderReplaceStepToken')]
  public function testReplaceStepToken(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void {
    $replacement = $this->callProtectedMethod(Tokenizer::class, 'replaceStepToken', [$token, $name, $qualifier, $format, $data]);
    $this->assertEquals($replacement, $expected);
  }

  public static function dataProviderReplaceStepToken(): array {
    return [
      ['{step}', 'step', NULL, NULL, [], '{step}'],
      ['{step}', 'step', NULL, NULL, ['step_name' => 'Hello step'], 'Hello_step'],

      ['{step_name}', 'step', 'other', NULL, [], '{step_name}'],
      ['{step_name}', 'step', 'other', NULL, ['step_name' => 'Hello step'], 'Hello_step'],
      ['{step_name}', 'step', 'name', NULL, ['step_name' => 'Hello step'], 'Hello_step'],

      ['{step_line}', 'step', 'other', NULL, [], '{step_line}'],
      ['{step_line}', 'step', 'other', NULL, ['step_line' => 6], '{step_line}'],
      ['{step_line}', 'step', 'line', NULL, ['step_line' => 6], '6'],
      ['{step_line}', 'step', 'line', NULL, ['step_line' => '6'], '6'],
      ['{step_line}', 'step', 'line', '%03d', ['step_line' => '6'], '006'],
    ];
  }

  #[DataProvider('dataProviderReplaceDatetimeToken')]
  public function testReplaceDatetimeToken(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected, ?string $exception = NULL): void {
    if ($exception) {
      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage($exception);
    }

    $replacement = $this->callProtectedMethod(Tokenizer::class, 'replaceDatetimeToken', [$token, $name, $qualifier, $format, $data]);

    if (!$exception) {
      $this->assertEquals($replacement, $expected);
    }
  }

  public static function dataProviderReplaceDatetimeToken(): array {
    return [
      ['{datetime}', 'datetime', NULL, NULL, [], '{datetime}'],
      ['{datetime}', 'datetime', NULL, 'U', ['timestamp' => strtotime('Tuesday, 12 March 2024 00:00:00')], '1710201600'],
      ['{datetime}', 'datetime', NULL, 'Y-m-d', ['timestamp' => strtotime('Tuesday, 12 March 2024 00:00:00')], '2024-03-12'],
      ['{datetime}', 'datetime', NULL, 'Y-m-d H:i:s', ['timestamp' => strtotime('Tuesday, 12 March 2024 00:00:00')], '2024-03-12 00:00:00'],
      ['{datetime}', 'datetime', NULL, NULL, ['timestamp' => strtotime('Tuesday, 12 March 2024 00:00:00')], '20240312_000000'],
      ['{datetime}', 'datetime', NULL, NULL, ['timestamp' => '2'], '19700101_000002'],
      ['{datetime}', 'datetime', NULL, NULL, ['timestamp' => 'foo'], '0', 'Timestamp must be greater than 0.'],
      ['{datetime}', 'datetime', NULL, NULL, ['timestamp' => ['foo']], '', 'Timestamp must be numeric.'],
    ];
  }

  #[DataProvider('dataProviderReplaceFeatureToken')]
  public function testReplaceFeatureToken(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void {
    $replacement = $this->callProtectedMethod(Tokenizer::class, 'replaceFeatureToken', [$token, $name, $qualifier, $format, $data]);
    $this->assertEquals($expected, $replacement);
  }

  public static function dataProviderReplaceFeatureToken(): array {
    return [
      ['{feature}', 'feature', 'file', NULL, [], '{feature}'],
      ['{feature}', 'feature', 'file', NULL, ['feature_file' => NULL], '{feature}'],
      ['{feature}', 'feature', 'file', NULL, ['feature_file' => ''], '{feature}'],
      ['{feature}', 'feature', 'file', NULL, ['feature_file' => 'stub-file.feature'], 'stub-file'],
      ['{feature}', 'feature', 'file', NULL, ['feature_file' => 'path/example/stub-file.feature'], 'stub-file'],
    ];
  }

  #[DataProvider('dataProviderReplaceFailToken')]
  public function testReplaceFailToken(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void {
    $replacement = $this->callProtectedMethod(Tokenizer::class, 'replaceFailToken', [$token, $name, $qualifier, $format, $data]);
    $this->assertEquals($expected, $replacement);
  }

  public static function dataProviderReplaceFailToken(): array {
    return [
      ['{fail}', 'fail', NULL, NULL, [], '{fail}'],
      ['{fail}', 'fail', NULL, NULL, ['fail_prefix' => ''], '{fail}'],
      ['{fail}', 'fail', NULL, NULL, ['fail_prefix' => 'HelloFail_'], 'HelloFail_'],
    ];
  }

  #[DataProvider('dataProviderReplaceUrlToken')]
  public function testReplaceUrlToken(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected, ?string $exception = NULL): void {
    if ($exception) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($exception);
    }

    $replacement = $this->callProtectedMethod(Tokenizer::class, 'replaceUrlToken', [$token, $name, $qualifier, $format, $data]);
    $this->assertEquals($expected, $replacement);
  }

  public static function dataProviderReplaceUrlToken(): array {
    $url = 'http://example.com/foo?foo=foo-value#hello-fragment';

    return [
      ['{url}', 'url', NULL, NULL, [], '{url}'],
      ['{url}', 'url', NULL, NULL, ['url' => $url], urlencode($url)],
      ['{url}', 'url', NULL, 'relative', ['url' => $url], urlencode($url)],
      ['{url}', 'url', NULL, NULL, ['url' => 'http:///example.com'], '', 'Could not parse URL "http:///example.com".'],

      ['{url_relative}', 'url', 'relative', NULL, ['url' => $url], urlencode('foo?foo=foo-value#hello-fragment')],
      ['{url_relative}', 'url', NULL, NULL, ['url' => $url], urlencode($url)],
      ['{url_relative}', 'url', NULL, NULL, [], '{url_relative}'],

      ['{url_origin}', 'url', 'origin', NULL, ['url' => $url], urlencode('http://example.com')],
      ['{url_origin}', 'url', NULL, NULL, ['url' => $url], urlencode($url)],
      ['{url_origin}', 'url', NULL, NULL, [], '{url_origin}'],

      ['{url_domain}', 'url', 'domain', NULL, ['url' => $url], urlencode('example.com')],
      ['{url_domain}', 'url', NULL, NULL, ['url' => $url], urlencode($url)],
      ['{url_domain}', 'url', NULL, NULL, [], '{url_domain}'],

      ['{url_path}', 'url', 'path', NULL, ['url' => 'http://example.com/foo?foo=foo-value#hello-fragment'], 'foo'],
      ['{url_path}', 'url', NULL, NULL, ['url' => 'http://example.com/foo?foo=foo-value#hello-fragment'], urlencode($url)],
      ['{url_path}', 'url', NULL, NULL, [], '{url_path}'],

      ['{url_query}', 'url', 'query', NULL, ['url' => $url], urlencode('foo=foo-value')],
      ['{url_query}', 'url', NULL, NULL, ['url' => $url], urlencode($url)],
      ['{url_query}', 'url', NULL, NULL, [], '{url_query}'],

      ['{url_fragment}', 'url', 'fragment', NULL, ['url' => $url], 'hello-fragment'],
      ['{url_fragment}', 'url', NULL, NULL, ['url' => $url], urlencode($url)],
      ['{url_fragment}', 'url', NULL, NULL, [], '{url_fragment}'],
    ];
  }

  #[DataProvider('dataProviderReplaceTokens')]
  public function testReplaceTokens(string $stringContainsTokens, array $data, string $expected): void {
    $replacement = Tokenizer::replaceTokens($stringContainsTokens, $data);
    $this->assertEquals($expected, $replacement);
  }

  public static function dataProviderReplaceTokens(): array {
    $data = [
      'fail_prefix' => 'foo-fail_',
      'timestamp' => 1710219423,
      'ext' => 'png',
      'url' => 'http://example.com/foo?foo=foo-value#hello-fragment',
      'feature_file' => 'path/to/foo-file.feature',
      'step_line' => 6,
      'step_name' => 'Foo step name',
    ];

    return [
      [
        'somestring',
        $data,
        'somestring',
      ],
      [
        '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
        $data,
        '1710219423.foo-fail_foo-file.feature_6.png',
      ],
      [
        '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}_{step_name}.{ext}',
        $data,
        '1710219423.foo-fail_foo-file.feature_6_Foo_step_name.png',
      ],
      [
        '{datetime}.{fail_prefix}{feature_file}.feature_{step_line}_{step_name}.{ext}',
        $data,
        '20240312_045703.foo-fail_foo-file.feature_6_Foo_step_name.png',
      ],
      [
        '{url}.{ext}',
        $data,
        'http%3A%2F%2Fexample.com%2Ffoo%3Ffoo%3Dfoo-value%23hello-fragment.png',
      ],
      [
        '{nontoken}.{ext}',
        $data,
        '{nontoken}.png',
      ],
    ];
  }

}
