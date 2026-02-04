<?php

namespace Sendama\Engine\Debug;

use RuntimeException;
use Sendama\Engine\Debug\Enumerations\LogLevel;
use Sendama\Engine\Exceptions\DebuggingException;
use Sendama\Engine\Util\Path;

/**
 * Class Debug. A class for logging debug messages.
 *
 * @package Sendama\Engine\Debug
 */
final class Debug
{
  /**
   * @var string|null $logDirectory The directory to write the log files to.
   */
  private static ?string $logDirectory = null;
  /**
   * @var LogLevel $logLevel The log level.
   */
  private static LogLevel $logLevel = LogLevel::DEBUG;
  /**
   * The Debug constructor.
   */
  private function __construct()
  {
  }

  /**
   * Sets the log directory.
   *
   * @param string $logDirectory The directory to write the log files to.
   * @return void
   */
  public static function setLogDirectory(string $logDirectory): void
  {
    self::$logDirectory = $logDirectory;
  }

  /**
   * Returns the log directory.
   *
   * @return string The log directory.
   */
  public static function getLogDirectory(): string
  {
    if (self::$logDirectory === null) {
      self::$logDirectory = Path::join(getcwd(), DEFAULT_LOGS_DIR);
    }

    return self::$logDirectory;
  }

  /**
   * Sets the log level.
   *
   * @param LogLevel $level The log level to set.
   * @return void
   */
  public static function setLogLevel(LogLevel $level): void
  {
    self::$logLevel = $level;
  }

  /**
   * Logs a message to the debug log.
   *
   * @param string $message The message to log.
   * @param string $prefix The prefix to add to the message.
   * @throws RuntimeException Thrown if the debug log file cannot be written to.
   */
  public static function log(
    string $message,
    string $prefix = '[DEBUG]',
    LogLevel $logLevel = LogLevel::DEBUG
  ): void
  {
    $filename = Path::join(self::getLogDirectory(),  'debug.log');

    if (self::$logLevel->getPriority() > $logLevel->getPriority()) {
      return;
    }


    if (!file_exists($filename)) {
      if (!is_writeable(self::getLogDirectory())) {
        throw new DebuggingException("The directory, " . self::getLogDirectory() . ", is not writable.");
      }

      if (false === $file = fopen($filename, 'w')) {
        throw new DebuggingException("Failed to create the debug log file.");
      }
      fclose($file);
    }

    $message = sprintf("[%s] %s - %s", date('Y-m-d H:i:s'), $prefix, $message) . PHP_EOL;
    if (false === error_log($message, 3, $filename)) {
      throw new DebuggingException("Failed to write to the debug log.");
    }
  }

  /**
   * Logs an error message to the error log.
   *
   * @param string $message The message to log.
   * @param string $prefix The prefix to add to the message.
   * @throws RuntimeException Thrown if the error log file cannot be written to.
   */
  public static function error(string $message, string $prefix = '[ERROR]'): void
  {
    if (self::$logLevel->getPriority() > LogLevel::ERROR->getPriority()) {
      return;
    }

    $filename = Path::join(self::getLogDirectory(),  'error.log');

    if (!file_exists($filename)) {
      if (!is_writeable(self::getLogDirectory())) {
        throw new DebuggingException("The directory, " . self::getLogDirectory() . ", is not writable.");
      }

      if (false === $file = fopen($filename, 'w')) {
        throw new DebuggingException("Failed to create the error log file.");
      }

      fclose($file);
    }

    $message = sprintf("[%s] %s - %s", date('Y-m-d H:i:s'), $prefix, $message) . PHP_EOL;
    if (false === error_log($message, 3, $filename)) {
      throw new DebuggingException("Failed to write to the error log.");
    }
  }

  /**
   * Logs a warning message to the warning log.
   *
   * @param string $message The message to log.
   * @param string|null $prefix The prefix to add to the message.
   */
  public static function warn(string $message, ?string $prefix = null): void
  {
    self::log($message, $prefix ?? '[WARN]', LogLevel::WARN);
  }

  /**
   * Logs an info message to the info log.
   *
   * @param string $message The message to log.
   * @param string|null $prefix The prefix to add to the message.
   */
  public static function info(string $message, ?string $prefix = null): void
  {
    self::log($message, $prefix ?? '[INFO]', LogLevel::INFO);
  }
}
