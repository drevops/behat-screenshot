<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension;

/**
 * Handler token replacements.
 */
class Tokenizer {

  /**
   * Replace tokens from the text.
   *
   * @param string $text
   *   Text may contain tokens.
   * @param array<mixed> $data
   *   Extra data to provide context to replace token.
   *
   * @return string
   *   String after replace tokens.
   *
   * @throws \Exception
   */
  public static function replaceTokens(string $text, array $data = []): string {
    $replacement = $text;
    $tokens = self::scanTokens($text);
    $tokenReplacements = self::extractTokens($tokens, $data);

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

      $replacement = strtr($text, $tokenReplacements);
    }

    return $replacement;
  }

  /**
   * Scan tokens of specific text.
   *
   * @param string $text
   *   The text to scan tokens.
   *
   * @return string[]
   *   The tokens keyed by the token name.
   */
  public static function scanTokens(string $text): array {
    $pattern = '/\{(.*?)\}/';
    preg_match_all($pattern, $text, $matches);
    $tokens = [];
    foreach ($matches[0] as $key => $match) {
      $tokens[$match] = $matches[1][$key];
    }

    return $tokens;
  }

  /**
   * Replace {feature} token.
   *
   * @param string $token
   *   Original token.
   * @param string $name
   *   Token name.
   * @param string|null $qualifier
   *   Token qualifier.
   * @param string|null $format
   *   Token format.
   * @param array<mixed> $data
   *   Extra data to provide context to replace token.
   *
   * @return string
   *   Token replacement.
   */
  public static function replaceFeatureToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    $replacement = $token;
    if (isset($data['feature_file']) && is_string($data['feature_file'])) {
      $featureFile = $data['feature_file'];
      if (!empty($featureFile)) {
        $replacement = basename($featureFile, '.feature');
      }
    }

    return $replacement;
  }

  /**
   * Replace {ext} token.
   *
   * @param string $token
   *   Original token.
   * @param string $name
   *   Token name.
   * @param string|null $qualifier
   *   Token qualifier.
   * @param string|null $format
   *   Token format.
   * @param array<mixed> $data
   *   Extra data to provide context to replace token.
   *
   * @return string
   *   Token replacement.
   */
  public static function replaceExtToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    $ext = 'html';
    if (isset($data['ext']) && is_string($data['ext']) && $data['ext'] !== '') {
      $ext = $data['ext'];
    }

    return $ext;
  }

  /**
   * Replace {step} token.
   *
   * @param string $token
   *   Original token.
   * @param string $name
   *   Token name.
   * @param string|null $qualifier
   *   Token qualifier.
   * @param string|null $format
   *   Token format.
   * @param array<mixed> $data
   *   Extra data to provide context to replace token.
   *
   * @return string
   *   Token replacement.
   */
  public static function replaceStepToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    $replacement = $token;
    switch ($qualifier) {
      case 'line':
        if (isset($data['step_line']) && (is_string($data['step_line']) || is_int($data['step_line']))) {
          $replacement = (string) $data['step_line'];
          if ($format) {
            $replacement = sprintf($format, $replacement);
          }
        }
        break;

      case 'name':
      default:
        if (isset($data['step_name']) && is_string($data['step_name'])) {
          $stepName = $data['step_name'];
          $replacement = str_replace([' ', '"'], ['_', ''], $stepName);
        }
        break;
    }

    return $replacement;
  }

  /**
   * Replace {datetime} token.
   *
   * @param string $token
   *   Original token.
   * @param string $name
   *   Token name.
   * @param string|null $qualifier
   *   Token qualifier.
   * @param string|null $format
   *   Token format.
   * @param array<mixed> $data
   *   Extra data to provide context to replace token.
   *
   * @return string
   *   Token replacement.
   *
   * @throws \Exception
   */
  public static function replaceDatetimeToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    $timestamp = NULL;
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
   * @param string $token
   *   Original token.
   * @param string $name
   *   Token name.
   * @param string|null $qualifier
   *   Token qualifier.
   * @param string|null $format
   *   Token format.
   * @param array<mixed> $data
   *   Extra data to provide context to replace token.
   *
   * @return string
   *   Token replacement.
   *
   * @throws \Exception
   */
  public static function replaceUrlToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    $replacement = $token;
    if (isset($data['url']) && is_string($data['url'])) {
      $url = $data['url'];
      $urlParts = parse_url($url);
      if (!$urlParts) {
        throw new \Exception('Could not parse url.');
      }
      switch ($qualifier) {
        case 'origin':
          $replacement = sprintf('%s://%s', $urlParts['scheme'], $urlParts['host']);
          break;

        case 'relative':
          $replacement = trim($urlParts['path'], '/');
          $replacement = (isset($urlParts['query'])) ? $replacement . '?' . $urlParts['query'] : $replacement;
          $replacement = (isset($urlParts['fragment'])) ? $replacement . '#' . $urlParts['fragment'] : $replacement;
          break;

        case 'domain':
          $replacement = $urlParts['host'];
          break;

        case 'path':
          $replacement = trim($urlParts['path'], '/');
          break;

        case 'query':
          $replacement = (isset($urlParts['query'])) ? $urlParts['query'] : '';
          break;

        case 'fragment':
          $replacement = (isset($urlParts['fragment'])) ? $urlParts['fragment'] : '';
          break;

        default:
          $replacement = $url;
          break;
      }
      $replacement = urlencode($replacement);
    }

    return $replacement;
  }

  /**
   * Replace {fail} token.
   *
   * @param string $token
   *   Original token.
   * @param string $name
   *   Token name.
   * @param string|null $qualifier
   *   Token qualifier.
   * @param string|null $format
   *   Token format.
   * @param array<mixed> $data
   *   Extra data to provide context to replace token.
   *
   * @return string
   *   Token replacement.
   */
  public static function replaceFailToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    $replacement = $token;
    if (!empty($data['fail_prefix']) && is_string($data['fail_prefix'])) {
      $replacement = $data['fail_prefix'];
    }

    return $replacement;
  }

  /**
   * Build replacements tokens.
   *
   * @param string[] $tokens
   *   Token.
   * @param array<mixed> $data
   *   Extra data to provide context to replace token.
   *
   * @return array<string, string>
   *   Replacements has key as token and value as token replacement.
   *
   * @throws \Exception
   */
  public static function extractTokens(array $tokens, array $data): array {
    $replacements = [];
    foreach ($tokens as $originalToken => $token) {
      $tokenParts = explode(':', $token);
      $qualifier = NULL;
      $format = NULL;
      $nameQualifier = $tokenParts[0];
      if (isset($tokenParts[1])) {
        $format = $tokenParts[1];
      }
      $nameQualifierParts = explode('_', $nameQualifier);
      $name = array_shift($nameQualifierParts);
      if (!empty($nameQualifierParts)) {
        $qualifier = implode('_', $nameQualifierParts);
      }
      $replacements[$originalToken] = self::buildTokenReplacement($originalToken, $name, $qualifier, $format, $data);
    }

    return $replacements;
  }

  /**
   * Build replacement for a token.
   *
   * @param string $token
   *   Original token.
   * @param string $name
   *   Token name.
   * @param string|null $qualifier
   *   Token qualifier.
   * @param string|null $format
   *   Token format.
   * @param array<mixed> $data
   *   Extra data to provide context to replace token.
   *
   * @return string
   *   Token replacement.
   *
   * @throws \Exception
   */
  public static function buildTokenReplacement(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    $replacement = $token;
    switch ($name) {
      case 'feature':
        $replacement = self::replaceFeatureToken($token, $name, $qualifier, $format, $data);
        break;

      case 'url':
        $replacement = self::replaceUrlToken($token, $name, $qualifier, $format, $data);
        break;

      case 'datetime':
        $replacement = self::replaceDatetimeToken($token, $name, $qualifier, $format, $data);
        break;

      case 'fail':
        $replacement = self::replaceFailToken($token, $name, $qualifier, $format, $data);
        break;

      case 'ext':
        $replacement = self::replaceExtToken($token, $name, $qualifier, $format, $data);
        break;

      case 'step':
        $replacement = self::replaceStepToken($token, $name, $qualifier, $format, $data);
        break;

      default:
        break;
    }

    return $replacement;
  }

}
