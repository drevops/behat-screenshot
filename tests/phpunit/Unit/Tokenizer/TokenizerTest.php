<?php

/**
 * @file
 * Unit test Tokenizer.
 */

declare(strict_types = 1);

namespace DrevOps\BehatScreenshot\Tests\Unit\Tokenizer;

use DrevOps\BehatScreenshotExtension\Tokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test Tokenizer
 */
#[CoversClass(Tokenizer::class)]
class TokenizerTest extends TestCase
{

    /**
     * Test scan tokens.
     *
     * @param string       $textContainsTokens
     * @param array<mixed> $expectedTokens
     */
    #[DataProvider('dataProviderScanTokens')]
    public function testScanTokens(string $textContainsTokens, array $expectedTokens): void
    {
        $tokens = Tokenizer::scanTokens($textContainsTokens);
        $this->assertEquals($expectedTokens, $tokens);
    }

    /**
     * Data for test scan tokens.
     *
     * @return array<mixed>
     *    Data for test.
     */
    public static function dataProviderScanTokens(): array
    {
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
     * @param string       $expected
     *   Expected string.
     */
    #[DataProvider('dataProviderReplaceExtToken')]
    public function testReplaceExtToken(array $data, string $expected): void
    {
        $replacement = Tokenizer::replaceExtToken('{ext}', 'ext', null, null, $data);
        $this->assertEquals($expected, $replacement);
    }

    /**
     * Provide data for test replace ext token.
     *
     * @return array<mixed>
     *   Data provider.
     */
    public static function dataProviderReplaceExtToken(): array
    {
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
     *
     * @param string       $token     Token.
     * @param string       $name      Name.
     * @param string|null  $qualifier qualifier.
     * @param string|null  $format    Format.
     * @param array<mixed> $data      Data to replace tokens.
     * @param string       $expected  Expected.
     *
     */
    #[DataProvider('dataProviderReplaceStepToken')]
    public function testReplaceStepToken(string $token, string $name, ?string $qualifier, ?string $format, array $data, string $expected): void
    {
        $replacement = Tokenizer::replaceStepToken($token, $name, $qualifier, $format, $data);
        $this->assertEquals($replacement, $expected);
    }

    /**
     * Data for test replace step token.
     *
     * @return array<mixed>
     *     Data provider.
     */
    public static function dataProviderReplaceStepToken(): array
    {
        return [
            ['{step}', 'step', null, null, ['step_name' => 'Hello step'], 'Hello_step'],
            ['{step_name}', 'step', 'name', null, ['step_name' => 'Hello step'], 'Hello_step'],
            ['{step_line}', 'step', 'line', null, ['step_line' => 6], '6'],
            ['{step_line}', 'step', 'line', null, ['step_line' => '6'], '6'],
            ['{step_line}', 'step', 'line', '%03d', ['step_line' => '6'], '006'],
        ];
    }

    /**
     * Test replace step token.
     *
     * @param array<mixed> $data     Data to replace tokens.
     * @param string|null  $format   Format.
     * @param string       $expected Expected.
     *
     * @throws \Exception
     */
    #[DataProvider('dataProviderReplaceDatetimeToken')]
    public function testReplaceDatetimeToken(array $data, ?string $format, string $expected): void
    {
        $replacement = Tokenizer::replaceDatetimeToken('{datetime}', 'datetime', null, $format, $data);
        $this->assertEquals($replacement, $expected);

        $data['time'] = 'foo';
        $this->expectExceptionMessage('Time must be an integer.');
        Tokenizer::replaceDatetimeToken('{datetime}', 'datetime', null, 'U', $data);
    }

    /**
     * Data provider for test replace datetime token.
     *
     * @return array<mixed>
     *     Data provider.
     */
    public static function dataProviderReplaceDatetimeToken(): array
    {
        return [
            [['time' => strtotime('Tuesday, 12 March 2024 00:00:00')], 'U', '1710201600'],
            [['time' => strtotime('Tuesday, 12 March 2024 00:00:00')], 'Y-m-d', '2024-03-12'],
            [['time' => strtotime('Tuesday, 12 March 2024 00:00:00')], 'Y-m-d H:i:s', '2024-03-12 00:00:00'],
            [['time' => strtotime('Tuesday, 12 March 2024 00:00:00')], null, '20240312_000000'],
        ];
    }

    /**
     * Test replace feature token.
     *
     * @param array<mixed> $data
     * @param string       $expected
     */
    #[DataProvider('dataProviderReplaceFeatureToken')]
    public function testReplaceFeatureToken(array $data, string $expected): void
    {
        $replacement = Tokenizer::replaceFeatureToken('{feature}', 'feature', 'file', null, $data);
        $this->assertEquals($expected, $replacement);
    }

    /**
     * Data provider for test replace feature
     *
     * @return array<mixed>
     *   Data provider.
     */
    public static function dataProviderReplaceFeatureToken(): array
    {
        return [
            [['feature_file' => 'stub-file.feature'], 'stub-file'],
            [['feature_file' => 'path/example/stub-file.feature'], 'stub-file'],
            [['feature_file' => null], '{feature}'],
            [[], '{feature}'],
        ];
    }

    /**
     * Test replace fail token.
     * @param array<mixed> $data
     *   Data to replace tokens.
     * @param string       $expected
     *   Expected.
     */
    #[DataProvider('dataProviderReplaceFailToken')]
    public function testReplaceFailToken(array $data, string $expected): void
    {
        $replacement = Tokenizer::replaceFailToken('{fail}', 'fail', null, null, $data);
        $this->assertEquals($expected, $replacement);
    }

    /**
     * Data provider for test replace fail token.
     *
     * @return array<mixed>
     *   Data provider.
     */
    public static function dataProviderReplaceFailToken(): array
    {
        return [
            [['fail_prefix' => 'HelloFail_'], 'HelloFail_'],
            [[], '{fail}'],
        ];
    }

    /**
     * Test replace url token.
     * @param string       $token
     *   Token.
     * @param string|null  $qualifier
     *   Qualifier.
     * @param string|null  $format
     *   Format.
     * @param array<mixed> $data
     *   Data to replace tokens.
     * @param string|null  $expected
     *   Expected.
     * @param bool         $expectedException
     *   Expected exception.
     *
     * @throws \Exception
     */
    #[DataProvider('dataProviderReplaceUrlToken')]
    public function testReplaceUrlToken(string $token, ?string $qualifier, ?string $format, array $data, ?string $expected, $expectedException = false): void
    {
        if ($expectedException) {
            $this->expectException(\Exception::class);
            Tokenizer::replaceUrlToken($token, 'url', $qualifier, $format, $data);
        } else {
            $replacement = Tokenizer::replaceUrlToken($token, 'url', $qualifier, $format, $data);
            $this->assertEquals($expected, $replacement);
        }
    }

    /**
     * Data provider for test replace url token.
     *
     * @return array<mixed>
     *   Data provider.
     */
    public static function dataProviderReplaceUrlToken(): array
    {
        $url = 'http://example.com/foo?foo=foo-value#hello-fragment';

        return [
            [
                '{url}',
                null,
                null,
                ['url' => $url],
                urlencode($url),
            ],
            [
                '{url_relative}',
                'relative',
                null,
                ['url' => $url],
                urlencode('foo?foo=foo-value#hello-fragment'),
            ],
            [
                '{url_origin}',
                'origin',
                null,
                ['url' => $url],
                urlencode('http://example.com'),
            ],
            [
                '{url_domain}',
                'domain',
                null,
                ['url' => $url],
                urlencode('example.com'),
            ],
            [
                '{url_path}',
                'path',
                null,
                ['url' => 'http://example.com/foo?foo=foo-value#hello-fragment'],
                'foo',
            ],
            [
                '{url_query}',
                'query',
                null,
                ['url' => $url],
                urlencode('foo=foo-value'),
            ],
            [
                '{url_fragment}',
                'fragment',
                null,
                ['url' => $url],
                'hello-fragment',
            ],
            [
                '{url}',
                null,
                null,
                ['url' => 'http:///example.com'],
                null,
                true,
            ],
        ];
    }

    /**
     * Test replace tokens.
     *
     * @param string       $stringContainsTokens
     *   Text contains tokens.
     * @param array<mixed> $data
     *   Data to replace tokens.
     * @param string       $expected
     *   Expected.
     *
     * @throws \Exception
     */
    #[DataProvider('dataProviderReplaceTokens')]
    public function testReplaceTokens(string $stringContainsTokens, array $data, string $expected): void
    {
        $replacement = Tokenizer::replaceTokens($stringContainsTokens, $data);
        $this->assertEquals($expected, $replacement);
    }

    /**
     * Data provider for replace tokens.
     *
     * @return array<mixed>
     *   Data provider.
     */
    public static function dataProviderReplaceTokens(): array
    {
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
