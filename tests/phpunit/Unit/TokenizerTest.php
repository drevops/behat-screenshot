<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

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

  #[DataProvider('dataProviderScanTokensMapsFullTokensToInnerNames')]
  public function testScanTokensMapsFullTokensToInnerNames(string $text_contains_tokens, array $expected_tokens): void {
    $tokens = Tokenizer::scanTokens($text_contains_tokens);
    $this->assertSame($expected_tokens, $tokens);
  }

  public static function dataProviderScanTokensMapsFullTokensToInnerNames(): array {
    return [
      'separated tokens' => [
        '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
        [
          '{datetime:U}' => 'datetime:U',
          '{feature_file}' => 'feature_file',
          '{step_line}' => 'step_line',
          '{ext}' => 'ext',
        ],
      ],
      'adjacent tokens' => [
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        [
          '{datetime:U}' => 'datetime:U',
          '{failed_prefix}' => 'failed_prefix',
          '{feature_file}' => 'feature_file',
          '{step_line}' => 'step_line',
          '{ext}' => 'ext',
        ],
      ],
    ];
  }

  #[DataProvider('dataProviderReplaceExtTokenUsesExtOrDefaultsToHtml')]
  public function testReplaceExtTokenUsesExtOrDefaultsToHtml(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void {
    $replacement = self::callProtectedMethod(Tokenizer::class, 'replaceExtToken', [$token, $name, $qualifier, $format, $data]);
    $this->assertSame($expected, $replacement);
  }

  public static function dataProviderReplaceExtTokenUsesExtOrDefaultsToHtml(): array {
    return [
      'missing ext defaults to html' => ['{ext}', 'ext', NULL, NULL, [], 'html'],
      'html ext' => ['{ext}', 'ext', NULL, NULL, ['ext' => 'html'], 'html'],
      'png ext' => ['{ext}', 'ext', NULL, NULL, ['ext' => 'png'], 'png'],
      'empty ext defaults to html' => ['{ext}', 'ext', NULL, NULL, ['ext' => ''], 'html'],
    ];
  }

  #[DataProvider('dataProviderReplaceStepTokenResolvesLineQualifierOrStepName')]
  public function testReplaceStepTokenResolvesLineQualifierOrStepName(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void {
    $replacement = self::callProtectedMethod(Tokenizer::class, 'replaceStepToken', [$token, $name, $qualifier, $format, $data]);
    $this->assertSame($expected, $replacement);
  }

  public static function dataProviderReplaceStepTokenResolvesLineQualifierOrStepName(): array {
    return [
      'no data leaves token' => ['{step}', 'step', NULL, NULL, [], '{step}'],
      'step name gets underscored' => ['{step}', 'step', NULL, NULL, ['step_name' => 'Hello step'], 'Hello_step'],

      'unknown qualifier, no name data' => ['{step_name}', 'step', 'other', NULL, [], '{step_name}'],
      'unknown qualifier falls back to name' => ['{step_name}', 'step', 'other', NULL, ['step_name' => 'Hello step'], 'Hello_step'],
      'name qualifier' => ['{step_name}', 'step', 'name', NULL, ['step_name' => 'Hello step'], 'Hello_step'],

      'unknown qualifier, no line data' => ['{step_line}', 'step', 'other', NULL, [], '{step_line}'],
      'line data without line qualifier' => ['{step_line}', 'step', 'other', NULL, ['step_line' => 6], '{step_line}'],
      'line qualifier with int line' => ['{step_line}', 'step', 'line', NULL, ['step_line' => 6], '6'],
      'line qualifier with string line' => ['{step_line}', 'step', 'line', NULL, ['step_line' => '6'], '6'],
      'line qualifier with format' => ['{step_line}', 'step', 'line', '%03d', ['step_line' => '6'], '006'],
    ];
  }

  #[DataProvider('dataProviderReplaceDatetimeTokenFormatsAndValidatesTimestamp')]
  public function testReplaceDatetimeTokenFormatsAndValidatesTimestamp(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected, ?string $exception = NULL): void {
    if ($exception) {
      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage($exception);
    }

    $replacement = self::callProtectedMethod(Tokenizer::class, 'replaceDatetimeToken', [$token, $name, $qualifier, $format, $data]);

    if (!$exception) {
      $this->assertSame($expected, $replacement);
    }
  }

  public static function dataProviderReplaceDatetimeTokenFormatsAndValidatesTimestamp(): array {
    return [
      'no timestamp leaves token' => ['{datetime}', 'datetime', NULL, NULL, [], '{datetime}'],
      'unix timestamp format' => ['{datetime}', 'datetime', NULL, 'U', ['timestamp' => strtotime('Tuesday, 12 March 2024 00:00:00')], '1710201600'],
      'date format' => ['{datetime}', 'datetime', NULL, 'Y-m-d', ['timestamp' => strtotime('Tuesday, 12 March 2024 00:00:00')], '2024-03-12'],
      'date and time format' => ['{datetime}', 'datetime', NULL, 'Y-m-d H:i:s', ['timestamp' => strtotime('Tuesday, 12 March 2024 00:00:00')], '2024-03-12 00:00:00'],
      'default format' => ['{datetime}', 'datetime', NULL, NULL, ['timestamp' => strtotime('Tuesday, 12 March 2024 00:00:00')], '20240312_000000'],
      'numeric string timestamp' => ['{datetime}', 'datetime', NULL, NULL, ['timestamp' => '2'], '19700101_000002'],
      'non-numeric string throws' => ['{datetime}', 'datetime', NULL, NULL, ['timestamp' => 'foo'], '0', 'Timestamp must be greater than 0.'],
      'array timestamp throws' => ['{datetime}', 'datetime', NULL, NULL, ['timestamp' => ['foo']], '', 'Timestamp must be numeric.'],
    ];
  }

  #[DataProvider('dataProviderReplaceFeatureTokenUsesFeatureFileBasename')]
  public function testReplaceFeatureTokenUsesFeatureFileBasename(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void {
    $replacement = self::callProtectedMethod(Tokenizer::class, 'replaceFeatureToken', [$token, $name, $qualifier, $format, $data]);
    $this->assertSame($expected, $replacement);
  }

  public static function dataProviderReplaceFeatureTokenUsesFeatureFileBasename(): array {
    return [
      'no feature file leaves token' => ['{feature}', 'feature', 'file', NULL, [], '{feature}'],
      'null feature file leaves token' => ['{feature}', 'feature', 'file', NULL, ['feature_file' => NULL], '{feature}'],
      'empty feature file leaves token' => ['{feature}', 'feature', 'file', NULL, ['feature_file' => ''], '{feature}'],
      'bare file name' => ['{feature}', 'feature', 'file', NULL, ['feature_file' => 'stub-file.feature'], 'stub-file'],
      'file name with path' => ['{feature}', 'feature', 'file', NULL, ['feature_file' => 'path/example/stub-file.feature'], 'stub-file'],
    ];
  }

  #[DataProvider('dataProviderReplaceFailedPrefixTokenResolvesOnlyWhenPrefixSet')]
  public function testReplaceFailedPrefixTokenResolvesOnlyWhenPrefixSet(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void {
    $replacement = self::callProtectedMethod(Tokenizer::class, 'replaceFailedPrefixToken', [$token, $name, $qualifier, $format, $data]);
    $this->assertSame($expected, $replacement);
  }

  public static function dataProviderReplaceFailedPrefixTokenResolvesOnlyWhenPrefixSet(): array {
    return [
      'no prefix leaves token' => ['{failed_prefix}', 'failed_prefix', NULL, NULL, [], '{failed_prefix}'],
      'empty prefix leaves token' => ['{failed_prefix}', 'failed_prefix', NULL, NULL, ['failed_prefix' => ''], '{failed_prefix}'],
      'prefix set' => ['{failed_prefix}', 'failed_prefix', NULL, NULL, ['failed_prefix' => 'HelloFail_'], 'HelloFail_'],
    ];
  }

  #[DataProvider('dataProviderReplaceUrlTokenResolvesUrlPartsByQualifier')]
  public function testReplaceUrlTokenResolvesUrlPartsByQualifier(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void {
    $replacement = self::callProtectedMethod(Tokenizer::class, 'replaceUrlToken', [$token, $name, $qualifier, $format, $data]);
    $this->assertSame($expected, $replacement);
  }

  public static function dataProviderReplaceUrlTokenResolvesUrlPartsByQualifier(): array {
    return [
      'no url leaves token' => ['{url}', 'url', NULL, NULL, [], '{url}'],
      'full url sanitized' => ['{url}', 'url', NULL, NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'http_e_com_path_f1_f1-v1_frag'],

      'relative as format has no effect' => ['{url}', 'url', NULL, 'relative', ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'http_e_com_path_f1_f1-v1_frag'],
      'unparseable url leaves token' => ['{url}', 'url', NULL, NULL, ['url' => 'http:///e.com/path?f1=f1-v1#frag'], '{url}'],

      'relative qualifier' => ['{url_relative}', 'url', 'relative', NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], urlencode('path_f1_f1-v1_frag')],
      'relative token without qualifier' => ['{url_relative}', 'url', NULL, NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'http_e_com_path_f1_f1-v1_frag'],
      'relative token, no url' => ['{url_relative}', 'url', NULL, NULL, [], '{url_relative}'],

      'origin qualifier' => ['{url_origin}', 'url', 'origin', NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'http_e_com'],
      'origin token without qualifier' => ['{url_origin}', 'url', NULL, NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'http_e_com_path_f1_f1-v1_frag'],
      'origin token, no url' => ['{url_origin}', 'url', NULL, NULL, [], '{url_origin}'],

      'domain qualifier' => ['{url_domain}', 'url', 'domain', NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'e_com'],
      'domain token without qualifier' => ['{url_domain}', 'url', NULL, NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'http_e_com_path_f1_f1-v1_frag'],
      'domain token, no url' => ['{url_domain}', 'url', NULL, NULL, [], '{url_domain}'],

      'path qualifier' => ['{url_path}', 'url', 'path', NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'path'],
      'path token without qualifier' => ['{url_path}', 'url', NULL, NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'http_e_com_path_f1_f1-v1_frag'],
      'path token, no url' => ['{url_path}', 'url', NULL, NULL, [], '{url_path}'],

      'query qualifier' => ['{url_query}', 'url', 'query', NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'f1_f1-v1'],
      'query token without qualifier' => ['{url_query}', 'url', NULL, NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'http_e_com_path_f1_f1-v1_frag'],
      'query token, no url' => ['{url_query}', 'url', NULL, NULL, [], '{url_query}'],

      'fragment qualifier' => ['{url_fragment}', 'url', 'fragment', NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'frag'],
      'fragment token without qualifier' => ['{url_fragment}', 'url', NULL, NULL, ['url' => 'http://e.com/path?f1=f1-v1#frag'], 'http_e_com_path_f1_f1-v1_frag'],
      'fragment token, no url' => ['{url_fragment}', 'url', NULL, NULL, [], '{url_fragment}'],
    ];
  }

  #[DataProvider('dataProviderReplaceTokensResolvesKnownTokensAndKeepsUnknown')]
  public function testReplaceTokensResolvesKnownTokensAndKeepsUnknown(string $string_contains_tokens, array $data, string $expected): void {
    $replacement = Tokenizer::replaceTokens($string_contains_tokens, $data);
    $this->assertSame($expected, $replacement);
  }

  public static function dataProviderReplaceTokensResolvesKnownTokensAndKeepsUnknown(): array {
    $data = [
      'failed_prefix' => 'foo-failed_',
      'timestamp' => 1710219423,
      'ext' => 'png',
      'url' => 'http://example.com/foo?foo=foo-value#hello-fragment',
      'feature_file' => 'path/to/foo-file.feature',
      'step_line' => 6,
      'step_name' => 'Foo step name',
    ];

    return [
      'no tokens' => [
        'somestring',
        $data,
        'somestring',
      ],
      'tokens without step name' => [
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
        $data,
        '1710219423.foo-failed_foo-file.feature_6.png',
      ],
      'tokens with step name' => [
        '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}_{step_name}.{ext}',
        $data,
        '1710219423.foo-failed_foo-file.feature_6_Foo_step_name.png',
      ],
      'default datetime format' => [
        '{datetime}.{failed_prefix}{feature_file}.feature_{step_line}_{step_name}.{ext}',
        $data,
        '20240312_045703.foo-failed_foo-file.feature_6_Foo_step_name.png',
      ],
      'url token' => [
        '{url}.{ext}',
        $data,
        'http_example_com_foo_foo_foo-value_hello-fragment.png',
      ],
      'unknown token kept' => [
        '{nontoken}.{ext}',
        $data,
        '{nontoken}.png',
      ],
      // A step name carrying a token has that token resolved too.
      'token inside step name' => [
        '{step_name}.{ext}',
        ['step_name' => 'Visit {url} page', 'url' => 'http://example.com/foo', 'ext' => 'png'] + $data,
        'Visit_http_example_com_foo_page.png',
      ],
      // A token nested inside a step name that resolves to nothing known is
      // left alone rather than looped over.
      'unknown token inside step name' => [
        '{step_name}.{ext}',
        ['step_name' => 'Visit {nontoken} page'] + $data,
        'Visit_{nontoken}_page.png',
      ],
      // A step name that refers to itself expands once and then terminates.
      'self-referential step name' => [
        '{step_name}.{ext}',
        ['step_name' => 'Loop {step_name} end'] + $data,
        'Loop_{step_name}_end.png',
      ],
    ];
  }

}
