<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension;

/**
 * Handles token replacements.
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
    $tokens = self::scanTokens($text);

    if (empty($tokens)) {
      return $text;
    }

    $replacements = self::extractTokens($tokens, $data);

    // Move {step_name} token to the last position as it may contain other
    // tokens.
    if (isset($replacements['{step_name}'])) {
      $step_name_value = $replacements['{step_name}'];
      unset($replacements['{step_name}']);
      $replacements['{step_name}'] = $step_name_value;
    }

    return strtr($text, $replacements);
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
    $pattern = '/\{(.*?)}/';

    preg_match_all($pattern, $text, $matches);

    $tokens = [];
    foreach ($matches[0] as $key => $name) {
      $tokens[$name] = $matches[1][$key];
    }

    return $tokens;
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
   */
  protected static function extractTokens(array $tokens, array $data): array {
    $replacements = [];

    foreach ($tokens as $original_token => $token) {
      $parts = explode(':', $token);

      $qualifier = NULL;
      $format = $parts[1] ?? NULL;

      $name_qualifier = $parts[0];
      $name_qualifier_parts = explode('_', $name_qualifier);
      $name = array_shift($name_qualifier_parts);
      if (!empty($name_qualifier_parts)) {
        $qualifier = implode('_', $name_qualifier_parts);
      }

      $replacements[$original_token] = self::buildTokenReplacement($original_token, $name, $qualifier, $format, $data);
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
   */
  protected static function buildTokenReplacement(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    $method = 'replace' . str_replace('_', '', ucwords($name, '_')) . 'Token';
    if (is_callable([self::class, $method])) {
      return self::$method($token, $name, $qualifier, $format, $data);
    }

    $method = 'replace' . str_replace('_', '', ucwords($name . '_' . $qualifier, '_')) . 'Token';
    if (is_callable([self::class, $method])) {
      return self::$method($token, $name, $qualifier, $format, $data);
    }

    return $token;
  }

  /**
   * Replace {feature} token.
   */
  protected static function replaceFeatureToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    return !empty($data['feature_file']) && is_string($data['feature_file']) ? basename($data['feature_file'], '.feature') : $token;
  }

  /**
   * Replace {ext} token.
   */
  protected static function replaceExtToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    return isset($data['ext']) && is_string($data['ext']) && $data['ext'] !== '' ? $data['ext'] : 'html';
  }

  /**
   * Replace {step} token.
   */
  protected static function replaceStepToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    if ($qualifier == 'line' && isset($data['step_line']) && (is_string($data['step_line']) || is_int($data['step_line']))) {
      return $format ? sprintf($format, intval($data['step_line'])) : strval($data['step_line']);
    }

    if (isset($data['step_name']) && is_string($data['step_name'])) {
      return str_replace([' ', '"'], ['_', ''], $data['step_name']);
    }

    return $token;
  }

  /**
   * Replace {datetime} token.
   */
  protected static function replaceDatetimeToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    $timestamp = NULL;

    if (isset($data['timestamp'])) {
      if (!is_scalar($data['timestamp'])) {
        throw new \InvalidArgumentException('Timestamp must be numeric.');
      }

      $timestamp = intval($data['timestamp']);

      if ($timestamp < 1) {
        throw new \InvalidArgumentException('Timestamp must be greater than 0.');
      }
    }

    return $timestamp ? date($format ?: 'Ymd_His', $timestamp) : $token;
  }

  /**
   * Replace {url} token.
   */
  protected static function replaceUrlToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    $replacement = $token;

    if (isset($data['url']) && is_string($data['url'])) {
      $url = $data['url'];
      $url_parts = parse_url($url);

      if (!$url_parts) {
        return $token;
      }

      switch ($qualifier) {
        case 'origin':
          $replacement = sprintf('%s://%s', $url_parts['scheme'], $url_parts['host']);
          break;

        case 'relative':
          $replacement = trim($url_parts['path'], '/');
          $replacement = isset($url_parts['query']) ? $replacement . '?' . $url_parts['query'] : $replacement;
          $replacement = isset($url_parts['fragment']) ? $replacement . '#' . $url_parts['fragment'] : $replacement;
          break;

        case 'domain':
          $replacement = $url_parts['host'];
          break;

        case 'path':
          $replacement = trim($url_parts['path'], '/');
          break;

        case 'query':
          $replacement = $url_parts['query'] ?: '';
          break;

        case 'fragment':
          $replacement = $url_parts['fragment'] ?: '';
          break;

        default:
          $replacement = $url;
          break;
      }

      $replacement = preg_replace('/[^\w\-]+/', '_', $replacement) ?: $replacement;
    }

    return $replacement;
  }

  /**
   * Replace {failed_prefix} token.
   */
  protected static function replaceFailedPrefixToken(string $token, string $name, ?string $qualifier = NULL, ?string $format = NULL, array $data = []): string {
    return !empty($data['failed_prefix']) && is_string($data['failed_prefix']) ? $data['failed_prefix'] : $token;
  }

}
