<?php

namespace Sendama\Engine\Util;

use Sendama\Engine\Exceptions\UtilityException;

/**
 * Path is a static class that provides path functionality.
 */
final class Path
{
  /**
   * @var string $workingDirectory The working directory.
   */
  private static string $workingDirectory = '';
  /**
   * @var string $gameFileDirectory The game file directory.
   */
  private static string $gameFileDirectory = '';

  /**
   * Path constructor.
   */
  private function __construct() { }

  /**
   * Joins the given paths.
   *
   * @param string ...$paths The paths to join.
   * @return string
   */
  public static function join(string ...$paths): string
  {
    $result = '';

    foreach ($paths as $path)
    {
      $result .= $path . DIRECTORY_SEPARATOR;
    }

    return self::normalize(rtrim($result, DIRECTORY_SEPARATOR));
  }

  /**
   * Returns the path to the resources' directory.
   *
   * @param string $path The path to the resource.
   * @return string The path to the resource.
   */
  public static function getResourcePath(string $path = ''): string
  {
    if ($path)
    {
      return self::join(self::getProjectRootPath(), 'res', $path);
    }

    return self::join(self::getProjectRootPath(), 'res');
  }

  /**
   * Returns the path to the project's root directory.
   *
   * @return string The path to the project's root directory.
   */
  public static function getProjectRootPath(): string
  {
    return dirname(__DIR__, 2);
  }

  /**
   * Sets the working directory and game file directory.
   *
   * @param false|string $currentWorkingDirectory The current working directory.
   * @param string $gameFileDirectory The game file directory.
   * @return void
   * @throws UtilityException
   */
  public static function init(false|string $currentWorkingDirectory, string $gameFileDirectory): void
  {
    if ($currentWorkingDirectory === false) {
      throw new UtilityException('Unable to get current working directory.');
    }

    self::$workingDirectory = $currentWorkingDirectory;
    self::$gameFileDirectory = $gameFileDirectory;
  }

  /**
   * Returns the working directory.
   *
   * @return string The working directory.
   */
  public static function getWorkingDirectory(): string
  {
    return self::$workingDirectory;
  }

  /**
   * Returns the game file directory.
   *
   * @return string The game file directory.
   */
  public static function getGameFileDirectory(): string
  {
    return self::$gameFileDirectory;
  }

  /**
   * Returns the path the assets' directory of the game engine.
   *
   * @return string The path to the assets' directory.
   */
  public static function getVendorAssetsDirectory(): string
  {
    return self::join(self::getProjectRootPath(), 'assets');
  }

  /**
   * Returns the path to the working directory's assets.
   *
   * @return string The path to the working directory's assets.
   */
  public static function getWorkingDirectoryAssetsPath(): string
  {
    return self::join(getcwd() ?: '', 'Assets');
  }

  /**
   * Normalizes the given path.
   *
   * @param string $path The path to normalize.
   * @return string The normalized path.
   */
  public static function normalize(string $path): string
  {
    // Replace backslashes with forward slashes
    $path = str_replace('\\', '/', $path);

    // Explode the path into segments
    $segments = explode('/', $path);

    // Initialize an array to hold normalized segments
    $normalizedSegments = [];

    foreach ($segments as $segment)
    {
      if ($segment === '..')
      {
        // If the segment is '..', pop the last segment from the array
        array_pop($normalizedSegments);
      }
      elseif ($segment !== '' && $segment !== '.')
      {
        // If the segment is not empty and not '.', add it to the array
        $normalizedSegments[] = $segment;
      }
    }

    // Recombine the normalized segments into a path string
    $normalizedPath = implode('/', $normalizedSegments);

    // Determine if the path was originally absolute or relative and prepend accordingly
    $isAbsolute = $path[0] === '/';
    if ($isAbsolute) {
      $normalizedPath = '/' . $normalizedPath;
    }

    return $normalizedPath;
  }

  /**
   * Returns the current working directory.
   *
   * @return string The current working directory.
   */
  public static function getCurrentWorkingDirectory(): string
  {
    return getcwd() ?: '';
  }
}