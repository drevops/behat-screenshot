<?php

/**
 * @file
 * Tokenizer support replace tokens.
 */

declare(strict_types = 1);

namespace DrevOps\BehatScreenshotExtension;

use Behat\Behat\Hook\Scope\StepScope;
use Behat\Mink\Session;

/**
 * Handler token replacements.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Tokenizer
{
    /**
     * Replace tokens from the text.
     *
     * @param string       $text Text may contain tokens.
     * @param array<mixed> $data Extra data to provide context to replace token.
     *
     * @return string
     *   String after replace tokens.
     *
     * @throws \Exception
     */
    public function replaceToken(string $text, array $data = []): string
    {
        $replacement = $text;
        $tokens = $this->scanTokens($text);
        $tokenReplacements = $this->buildTokenReplacements($tokens, $data);

        if (!empty($tokenReplacements)) {
            // If token replacements have {step_name} token.
            // We need move {step_name} token to the last position,
            // because {step_name} token may contain other tokens.
            foreach ($tokenReplacements as $token => $replacement) {
                if ('{step_name}' === $token) {
                    $element = [$token => $replacement];
                    unset($tokenReplacements[$token]);
                    break;
                }
            }
            if (isset($element)) {
                $tokenReplacements = array_merge($tokenReplacements, $element);
            }

            $replacement = str_replace(array_keys($tokenReplacements), array_values($tokenReplacements), $text);
        }

        return $replacement;
    }

    /**
     * Scan tokens of specific text.
     *
     * @param string $text
     *   The text to scan tokens.
     * @return string[]
     *   The tokens.
     */
    public function scanTokens(string $text): array
    {
        $pattern = '/\{(.*?)\}/';
        preg_match_all($pattern, $text, $matches);
        $result = [];
        foreach ($matches[0] as $key => $match) {
            $result[$match] = $matches[1][$key];
        }

        return $result;
    }

    /**
     * Replace {feature} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function replaceFeatureToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $replacement = $token;
        if (isset($data['step_scope']) && $data['step_scope'] instanceof StepScope) {
            $stepScope = $data['step_scope'];
            $featureFile = $stepScope->getFeature()->getFile();
            if ($featureFile) {
                $replacement = basename($featureFile, '.feature');
            }
        }

        return $replacement;
    }

    /**
     * Replace {ext} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function replaceExtToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $ext = 'html';
        if (isset($data['ext']) && is_string($data['ext']) && $data['ext'] !== '') {
            $ext = $data['ext'];
        }

        return $ext;
    }

    /**
     * Replace {step} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function replaceStepToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $replacement = $token;
        if (isset($data['step_scope']) && $data['step_scope'] instanceof StepScope) {
            $stepScope = $data['step_scope'];
            switch ($qualifier) {
                case 'line':
                    $replacement = (string) $stepScope->getStep()->getLine();
                    if ($format) {
                        $replacement = sprintf($format, $replacement);
                    }
                    break;
                case 'name':
                default:
                    $stepText = $stepScope->getStep()->getText();
                    $replacement = str_replace([' ', '"'], ['_', ''], $stepText);
                    break;
            }
        }

        return $replacement;
    }

    /**
     * Replace {datetime} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws \Exception
     */
    public function replaceDatetimeToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $timestamp = null;
        if ($data['time']) {
            if (!is_int($data['time'])) {
                throw new \Exception('Time must be an integer.');
            }
            $timestamp = $data['time'];
        }

        if ($format) {
            return date($format, $timestamp);
        }

        return date('Ymd_His', $timestamp);
    }

    /**
     * Replace {url} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function replaceUrlToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $replacement = $token;
        if (isset($data['session']) && $data['session'] instanceof Session) {
            $session = $data['session'];

            $currentUrl = $session->getDriver()->getCurrentUrl();
            $currentUrlParts = parse_url($currentUrl);
            if (!$currentUrlParts) {
                throw new \Exception('Could not parse url.');
            }
            switch ($qualifier) {
                case 'origin':
                    $replacement = sprintf('%s://%s', $currentUrlParts['scheme'], $currentUrlParts['host']);
                    break;
                case 'relative':
                    $replacement = trim($currentUrlParts['path'], '/');
                    $replacement = (isset($currentUrlParts['query'])) ? $replacement.'?'.$currentUrlParts['query'] : $replacement;
                    $replacement = (isset($currentUrlParts['fragment'])) ? $replacement.'#'.$currentUrlParts['fragment'] : $replacement;
                    break;
                case 'domain':
                    $replacement = $currentUrlParts['host'];
                    break;
                case 'path':
                    $replacement = trim($currentUrlParts['path'], '/');
                    break;
                case 'query':
                    $replacement = (isset($currentUrlParts['query'])) ? $currentUrlParts['query'] : '';
                    break;
                case 'fragment':
                    $replacement = (isset($currentUrlParts['fragment'])) ? $currentUrlParts['fragment'] : '';
                    break;
                default:
                    $replacement = $currentUrl;
                    break;
            }
            $replacement = urlencode($replacement);
        }

        return $replacement;
    }

    /**
     * Replace {fail} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function replaceFailToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $replacement = $token;
        if (!empty($data['fail_prefix']) && is_string($data['fail_prefix'])) {
            $replacement = $data['fail_prefix'];
        }

        return $replacement;
    }


    /**
     * Build replacements tokens.
     *
     * @param string[]     $tokens Token.
     * @param array<mixed> $data   Extra data to provide context to replace token.
     *
     * @return array<string, string>
     *   Replacements has key as token and value as token replacement.
     *
     * @throws \Exception
     */
    protected function buildTokenReplacements(array $tokens, array $data): array
    {
        $replacements = [];
        foreach ($tokens as $originalToken => $token) {
            $tokenParts = explode(':', $token);
            $qualifier = null;
            $format = null;
            $nameQualifier = $tokenParts[0];
            if (isset($tokenParts[1])) {
                $format = $tokenParts[1];
            }
            $nameQualifierParts = explode('_', $nameQualifier);
            $name = array_shift($nameQualifierParts);
            if (!empty($nameQualifierParts)) {
                $qualifier = implode('_', $nameQualifierParts);
            }
            $replacements[$originalToken] = $this->buildTokenReplacement($originalToken, $name, $qualifier, $format, $data);
        }

        return $replacements;
    }

    /**
     * Build replacement for a token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @throws \Exception
     */
    protected function buildTokenReplacement(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $replacement = $token;
        switch ($name) {
            case 'feature':
                $replacement = $this->replaceFeatureToken($token, $name, $qualifier, $format, $data);
                break;
            case 'url':
                $replacement = $this->replaceUrlToken($token, $name, $qualifier, $format, $data);
                break;
            case 'datetime':
                $replacement = $this->replaceDatetimeToken($token, $name, $qualifier, $format, $data);
                break;
            case 'fail':
                $replacement = $this->replaceFailToken($token, $name, $qualifier, $format, $data);
                break;
            case 'ext':
                $replacement = $this->replaceExtToken($token, $name, $qualifier, $format, $data);
                break;
            case 'step':
                $replacement = $this->replaceStepToken($token, $name, $qualifier, $format, $data);
                break;
            default:
                break;
        }

        return $replacement;
    }
}
