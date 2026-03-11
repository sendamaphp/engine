<?php

namespace Sendama\Engine\Debug\Enumerations;

/**
 * LogLevel enumeration
 */
enum LogLevel: string
{
  case DEBUG = 'debug';
  case INFO = 'info';
  case WARN = 'warn';
  case ERROR = 'error';
  case FATAL = 'fatal';

  /**
   * Returns the priority of the log level.
   *
   * Higher numbers represent more severe log levels.
   *
   * @return int The priority of the log level.
   */
  public function getPriority(): int
  {
    return match ($this) {
      LogLevel::DEBUG => 0,
      LogLevel::INFO  => 1,
      LogLevel::WARN  => 2,
      LogLevel::ERROR => 3,
      LogLevel::FATAL => 4,
    };
  }
}
