<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit\Tokenizer;

use DrevOps\BehatScreenshotExtension\Tokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test Tokenizer.
 */
#[CoversClass(Tokenizer::class)]
class TokenizerTest extends TestCase {

  /**
   * Test scan tokens.
   */
  #[DataProvider('dataProviderScanTokens')]
  public function testScanTokens(string $textContainsTokens, array $expectedTokens): void {
    $tokens = Tokenizer::scanTokens($textContainsTokens);
    $this->assertEquals($expectedTokens, $tokens);
  }

  /**
   * Data for test scan tokens.
   */
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

  /**
   * Test replace ext token.
   *
   * @param array<mixed> $data
   *   Data to replace tokens.
   * @param string $expected
   *   Expected string.
   */
  #[DataProvider('dataProviderReplaceExtToken')]
  public function testReplaceExtToken(array $data, string $expected): void {
    $replacement = Tokenizer::replaceExtToken('{ext}', 'ext', NULL, NULL, $data);
    $this->assertEquals($expected, $replacement);
  }

  /**
   * Provide data for test replace ext token.
   */
  public static function dataProviderReplaceExtToken(): array {
    return [
      [
        ['ext' => 'html'],
        'html',
      ],
      [
        ['ext' => 'png'],
        'png',
      ],
      [
        ['ext' => ''],
        'html',
      ],
      [
        [],
        'html',
      ],
    ];
  }

  /**
   * Test replace step token.
   */
  #[DataProvider('dataProviderReplaceStepToken')]
  public function testReplaceStepToken(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void {
    $replacement = Tokenizer::replaceStepToken($token, $name, $qualifier, $format, $data);
    $this->assertEquals($replacement, $expected);
  }

  /**
   * Data for test replace step token.
   *
   * @return array<mixed>
   *   Data provider.
   */
  public static function dataProviderReplaceStepToken(): array {
    return [
      ['{step}', 'step', NULL, NULL, ['step_name' => 'Hello step'], 'Hello_step'],
      ['{step_name}', 'step', 'name', NULL, ['step_name' => 'Hello step'], 'Hello_step'],
      ['{step_line}', 'step', 'line', NULL, ['step_line' => 6], '6'],
      ['{step_line}', 'step', 'line', NULL, ['step_line' => '6'], '6'],
      ['{step_line}', 'step', 'line', '%03d', ['step_line' => '6'], '006'],
    ];
  }

  /**
   * Test replace step token.
   */
  #[DataProvider('dataProviderReplaceDatetimeToken')]
  public function testReplaceDatetimeToken(array $data, ?string $format, string $expected): void {
    $replacement = Tokenizer::replaceDatetimeToken('{datetime}', 'datetime', NULL, $format, $data);
    $this->assertEquals($replacement, $expected);

    $data['time'] = 'foo';
    $this->expectExceptionMessage('Time must be an integer.');
    Tokenizer::replaceDatetimeToken('{datetime}', 'datetime', NULL, 'U', $data);
  }

  /**
   * Data provider for test replace datetime token.
   */
  public static function dataProviderReplaceDatetimeToken(): array {
    return [
      [['time' => strtotime('Tuesday, 12 March 2024 00:00:00')], 'U', '1710201600'],
      [['time' => strtotime('Tuesday, 12 March 2024 00:00:00')], 'Y-m-d', '2024-03-12'],
      [['time' => strtotime('Tuesday, 12 March 2024 00:00:00')], 'Y-m-d H:i:s', '2024-03-12 00:00:00'],
      [['time' => strtotime('Tuesday, 12 March 2024 00:00:00')], NULL, '20240312_000000'],
    ];
  }

  /**
   * Test replace feature token.
   */
  #[DataProvider('dataProviderReplaceFeatureToken')]
  public function testReplaceFeatureToken(array $data, string $expected): void {
    $replacement = Tokenizer::replaceFeatureToken('{feature}', 'feature', 'file', NULL, $data);
    $this->assertEquals($expected, $replacement);
  }

  /**
   * Data provider for test replace feature.
   */
  public static function dataProviderReplaceFeatureToken(): array {
    return [
      [['feature_file' => 'stub-file.feature'], 'stub-file'],
      [['feature_file' => 'path/example/stub-file.feature'], 'stub-file'],
      [['feature_file' => NULL], '{feature}'],
      [[], '{feature}'],
    ];
  }

  /**
   * Test replace fail token.
   */
  #[DataProvider('dataProviderReplaceFailToken')]
  public function testReplaceFailToken(array $data, string $expected): void {
    $replacement = Tokenizer::replaceFailToken('{fail}', 'fail', NULL, NULL, $data);
    $this->assertEquals($expected, $replacement);
  }

  /**
   * Data provider for test replace fail token.
   */
  public static function dataProviderReplaceFailToken(): array {
    return [
      [['fail_prefix' => 'HelloFail_'], 'HelloFail_'],
      [[], '{fail}'],
    ];
  }

  /**
   * Test replace url token.
   */
  #[DataProvider('dataProviderReplaceUrlToken')]
  public function testReplaceUrlToken(string $token, ?string $qualifier, ?string $format, array $data, ?string $expected, bool $expectedException = FALSE): void {
    if ($expectedException) {
      $this->expectException(\Exception::class);
      Tokenizer::replaceUrlToken($token, 'url', $qualifier, $format, $data);
    }
    else {
      $replacement = Tokenizer::replaceUrlToken($token, 'url', $qualifier, $format, $data);
      $this->assertEquals($expected, $replacement);
    }
  }

  /**
   * Data provider for test replace url token.
   */
  public static function dataProviderReplaceUrlToken(): array {
    $url = 'http://example.com/foo?foo=foo-value#hello-fragment';

    return [
      [
        '{url}',
        NULL,
        NULL,
        ['url' => $url],
        urlencode($url),
      ],
      [
        '{url_relative}',
        'relative',
        NULL,
        ['url' => $url],
        urlencode('foo?foo=foo-value#hello-fragment'),
      ],
      [
        '{url_origin}',
        'origin',
        NULL,
        ['url' => $url],
        urlencode('http://example.com'),
      ],
      [
        '{url_domain}',
        'domain',
        NULL,
        ['url' => $url],
        urlencode('example.com'),
      ],
      [
        '{url_path}',
        'path',
        NULL,
        ['url' => 'http://example.com/foo?foo=foo-value#hello-fragment'],
        'foo',
      ],
      [
        '{url_query}',
        'query',
        NULL,
        ['url' => $url],
        urlencode('foo=foo-value'),
      ],
      [
        '{url_fragment}',
        'fragment',
        NULL,
        ['url' => $url],
        'hello-fragment',
      ],
      [
        '{url}',
        NULL,
        NULL,
        ['url' => 'http:///example.com'],
        NULL,
        TRUE,
      ],
    ];
  }

  /**
   * Test replace tokens.
   */
  #[DataProvider('dataProviderReplaceTokens')]
  public function testReplaceTokens(string $stringContainsTokens, array $data, string $expected): void {
    $replacement = Tokenizer::replaceTokens($stringContainsTokens, $data);
    $this->assertEquals($expected, $replacement);
  }

  /**
   * Data provider for replace tokens.
   */
  public static function dataProviderReplaceTokens(): array {
    $data = [
      'fail_prefix' => 'foo-fail_',
      'time' => 1710219423,
      'ext' => 'png',
      'url' => 'http://example.com/foo?foo=foo-value#hello-fragment',
      'feature_file' => 'path/to/foo-file.feature',
      'step_line' => 6,
      'step_name' => 'Foo step name',
    ];

    return [
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
    ];
  }

}
