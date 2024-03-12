<?php

/**
 * @file
 * Unit test Tokenizer.
 */

declare(strict_types = 1);

namespace DrevOps\BehatScreenshot\Tests\Unit\Tokenizer;

use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Behat\Hook\Scope\StepScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Session;
use DrevOps\BehatScreenshotExtension\Tokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test Tokenizer
 */
#[CoversClass(Tokenizer::class)]
class TokenizerTestCase extends TestCase
{
    protected Tokenizer $tokenizer;

    /**
     * Setup test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenizer = new Tokenizer();
    }

    /**
     * Test scan tokens.
     *
     * @param string       $textContainsTokens
     * @param array<mixed> $expectedTokens
     */
    #[DataProvider('dataProviderScanTokens')]
    public function testScanTokens(string $textContainsTokens, array $expectedTokens): void
    {
        $tokens = $this->tokenizer->scanTokens($textContainsTokens);
        $this->assertEquals($expectedTokens, $tokens);
    }

    /**
     * Test replace ext token.
     *
     * @param array<mixed> $data
     *   Context data.
     * @param string       $expected
     *   Expected string.
     */
    #[DataProvider('dataProviderReplaceExtToken')]
    public function testReplaceExtToken(array $data, string $expected): void
    {
        $replacement = $this->tokenizer->replaceExtToken('{ext}', 'ext', null, null, $data);
        $this->assertEquals($expected, $replacement);
    }

    /**
     * Test replace step token.
     *
     * @param string      $token     Token.
     * @param string      $name      Name.
     * @param string|null $qualifier qualifier.
     * @param string|null $format    Format.
     * @param string      $expected  Expected.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    #[DataProvider('dataProviderReplaceStepToken')]
    public function testReplaceStepToken(string $token, string $name, ?string $qualifier, ?string $format, string $expected): void
    {
        $stepScope = $this->mockStepScope([
            'step_line' => 6,
            'step_text' => 'Hello step',
        ]);
        $data['step_scope'] = $stepScope;
        $replacement = $this->tokenizer->replaceStepToken($token, $name, $qualifier, $format, $data);
        $this->assertEquals($replacement, $expected);
    }

    /**
     * Test replace step token.
     *
     * @param string      $time     Time string.
     * @param string|null $format   Format.
     * @param string      $expected Expected.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Exception
     */
    #[DataProvider('dataProviderReplaceDatetimeToken')]
    public function testReplaceDatetimeToken(string $time, ?string $format, string $expected): void
    {
        $timestamp = strtotime($time);
        $data['time'] = $timestamp;
        $replacement = $this->tokenizer->replaceDatetimeToken('{datetime}', 'datetime', null, $format, $data);
        $this->assertEquals($replacement, $expected);

        $data['time'] = 'foo';
        $this->expectExceptionMessage('Time must be an integer.');
        $this->tokenizer->replaceDatetimeToken('{datetime}', 'datetime', null, 'U', $data);
    }

    /**
     * Test replace feature token.
     *
     * @throws Exception
     */
    public function testReplaceFeatureToken(): void
    {
        $stepScope = $this->mockStepScope(['feature_file' => 'stub-file.feature']);
        $data['step_scope'] = $stepScope;
        $replacement = $this->tokenizer->replaceFeatureToken('{feature}', 'feature', 'file', null, $data);
        $this->assertEquals('stub-file', $replacement);

        $data['step_scope'] = 'foo';
        $replacement = $this->tokenizer->replaceFeatureToken('{feature}', 'feature', 'file', null, $data);
        $this->assertEquals('{feature}', $replacement);
    }

    /**
     * Test replace fail token.
     */
    public function testReplaceFailToken(): void
    {
        $failPrefix = 'FailHello_';
        $data['fail_prefix'] = $failPrefix;
        $replacement = $this->tokenizer->replaceFailToken('{fail}', 'fail', null, null, $data);
        $this->assertEquals($failPrefix, $replacement);

        unset($data['fail_prefix']);
        $replacement = $this->tokenizer->replaceFailToken('{fail}', 'fail', null, null, $data);
        $this->assertEquals('{fail}', $replacement);
    }

    /**
     * Test replace url token.
     *
     * @throws Exception
     * @throws \Exception
     */
    public function testReplaceUrlToken(): void
    {
        $url = 'http://example.com/foo?foo=foo-value#hello-fragment';
        $session = $this->mockSession(['current_url' => $url]);

        $data['session'] = $session;
        $replacement = $this->tokenizer->replaceUrlToken('{url}', 'url', null, null, $data);
        $this->assertEquals(urlencode($url), $replacement);
        $replacement = $this->tokenizer->replaceUrlToken('{url_relative}', 'url', 'relative', null, $data);
        $this->assertEquals(urlencode('foo?foo=foo-value#hello-fragment'), $replacement);
        $replacement = $this->tokenizer->replaceUrlToken('{url_origin}', 'url', 'origin', null, $data);
        $this->assertEquals(urlencode('http://example.com'), $replacement);
        $replacement = $this->tokenizer->replaceUrlToken('{url_domain}', 'url', 'domain', null, $data);
        $this->assertEquals(urlencode('example.com'), $replacement);
        $replacement = $this->tokenizer->replaceUrlToken('{url_path}', 'url', 'path', null, $data);
        $this->assertEquals('foo', $replacement);
        $replacement = $this->tokenizer->replaceUrlToken('{url_query}', 'url', 'query', null, $data);
        $this->assertEquals(urlencode('foo=foo-value'), $replacement);
        $replacement = $this->tokenizer->replaceUrlToken('{url_fragment}', 'url', 'fragment', null, $data);
        $this->assertEquals('hello-fragment', $replacement);

        $url = 'http:///example.com';
        $session = $this->mockSession(['current_url' => $url]);
        $data['session'] = $session;
        $this->expectExceptionMessage('Could not parse url.');
        $this->tokenizer->replaceUrlToken('{url}', 'url', null, null, $data);
    }

    /**
     * Test replace token.
     *
     * @param string       $token
     *   Token.
     * @param string       $expected
     *   Expected.
     * @param array<mixed> $tokenData
     *   Token data.
     * @param callable     $mockExtraData
     *   Callble to mock session and step scope.
     * @throws \Exception
     */
    #[DataProvider('dataProviderReplaceToken')]
    public function testReplaceToken(string $token, string $expected, array $tokenData, callable $mockExtraData): void
    {
        $extraTokenData = $mockExtraData($this, $tokenData);
        $tokenData = array_merge($tokenData, $extraTokenData);
        $replacement = $this->tokenizer->replaceToken($token, $tokenData);
        $this->assertEquals($expected, $replacement);
    }

    /**
     * Data provider for replace token.
     *
     * @return array<mixed>
     *   Data provider.
     */
    public static function dataProviderReplaceToken(): array
    {
        $dataContext = [
            'fail_prefix' => 'foo-fail_',
            'time' => 1710219423,
            'ext' => 'png',
            'url' => 'http://example.com/foo?foo=foo-value#hello-fragment',
            'feature_file' => 'foo-file.feature',
            'step_line' => 6,
            'step_text' => 'Foo step name',
        ];
        $mockExtraDataFunction = function (TokenizerTestCase $tokenizerTestCase, array $dataContext) {
            $session = $tokenizerTestCase->mockSession($dataContext);
            $stepScope = $tokenizerTestCase->mockStepScope($dataContext);

            return [
                'session' => $session,
                'step_scope' => $stepScope,
            ];
        };

        return [
            [
                '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}.{ext}',
                '1710219423.foo-fail_foo-file.feature_6.png',
                $dataContext,
                $mockExtraDataFunction,
            ],
            [
                '{datetime:U}.{fail_prefix}{feature_file}.feature_{step_line}_{step_name}.{ext}',
                '1710219423.foo-fail_foo-file.feature_6_Foo_step_name.png',
                $dataContext,
                $mockExtraDataFunction,
            ],
        ];
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
            ['Tuesday, 12 March 2024 00:00:00', 'U', '1710201600'],
            ['Tuesday, 12 March 2024 00:00:00', 'Y-m-d', '2024-03-12'],
            ['Tuesday, 12 March 2024 00:00:00', 'Y-m-d H:i:s', '2024-03-12 00:00:00'],
            ['Tuesday, 12 March 2024 00:00:00', null, '20240312_000000'],
        ];
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
            ['{step}', 'step', null, null, 'Hello_step'],
            ['{step_name}', 'step', 'name', null, 'Hello_step'],
            ['{step_line}', 'step', 'line', null, '6'],
        ];
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
     * Data for testScanTokens.
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
     * Mock before step scope.
     *
     * @return StepScope
     *   Mock object.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    protected function mockStepScope(array $options = []): StepScope
    {
        $stepLine = $options['step_line'] ?? null;
        $stepText = $options['step_text'] ?? null;
        $featureFile = $options['feature_file'] ?? null;
        $stepScope = $this->createMock(StepScope::class);
        $stepNode = $this->createMock(StepNode::class);
        $stepNode->method('getLine')->willReturn($stepLine);
        $stepNode->method('getText')->willReturn($stepText);
        $stepScope->method('getStep')->willReturn($stepNode);

        $featureNode = $this->createMock(FeatureNode::class);
        $featureNode->method('getFile')->willReturn($featureFile);
        $stepScope->method('getFeature')->willReturn($featureNode);

        return $stepScope;
    }

    /**
     * Mock behat mink session.
     *
     * @param array<mixed> $options
     *
     * @return MockObject|Session
     *
     * @throws Exception
     */
    protected function mockSession(array $options = []): MockObject|Session
    {
        $currentUrl = $options['current_url'] ?? null;
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getCurrentUrl')->willReturn($currentUrl);
        $session = $this->createMock(Session::class);
        $session->method('getDriver')->willReturn($driver);

        return $session;
    }
}
