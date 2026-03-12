<?php

namespace Sendama\Engine\Util;

/**
 * Small UTF-8 helpers for glyph-aware string operations.
 */
final class Unicode
{
  private const string ENCODING = 'UTF-8';

  private function __construct()
  {
  }

  /**
   * Returns the number of visible glyphs in the given string.
   *
   * @param string $text
   * @return int
   */
  public static function length(string $text): int
  {
    $length = grapheme_strlen($text);

    if ($length !== false) {
      return $length;
    }

    return mb_strlen($text, self::ENCODING);
  }

  /**
   * Returns a glyph-aware substring.
   *
   * @param string $text
   * @param int $start
   * @param int|null $length
   * @return string
   */
  public static function substring(string $text, int $start, ?int $length = null): string
  {
    $result = $length === null
      ? grapheme_substr($text, $start)
      : grapheme_substr($text, $start, $length);

    if ($result !== false) {
      return $result;
    }

    return $length === null
      ? mb_substr($text, $start, null, self::ENCODING)
      : mb_substr($text, $start, $length, self::ENCODING);
  }

  /**
   * Splits a string into glyphs.
   *
   * @param string $text
   * @param int|null $limit
   * @return string[]
   */
  public static function characters(string $text, ?int $limit = null): array
  {
    if ($text === '') {
      return [];
    }

    $length = self::length($text);

    if ($limit !== null) {
      $length = min($length, $limit);
    }

    $characters = [];

    for ($index = 0; $index < $length; $index++) {
      $characters[] = self::substring($text, $index, 1);
    }

    return $characters;
  }
}
