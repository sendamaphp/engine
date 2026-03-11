<?php

use Sendama\Engine\Debug\Debug;
use Sendama\Engine\Debug\Enumerations\LogLevel;

beforeEach(function () {
  $this->logDirectory = sys_get_temp_dir() . '/sendama-debug-' . uniqid('', true);

  Debug::setLogDirectory($this->logDirectory);
  Debug::setLogLevel(LogLevel::DEBUG);
});

afterEach(function () {
  deleteDirectory($this->logDirectory);
});

it('suppresses messages below the configured log level threshold', function () {
  Debug::setLogLevel(LogLevel::INFO);

  Debug::log('debug message');
  Debug::info('info message');
  Debug::warn('warn message');
  Debug::error('error message');

  $debugLog = readLogFile($this->logDirectory . '/debug.log');
  $errorLog = readLogFile($this->logDirectory . '/error.log');

  expect($debugLog)->not()->toContain('debug message')
    ->and($debugLog)->toContain('info message')
    ->and($debugLog)->toContain('warn message')
    ->and($errorLog)->toContain('error message');
});

it('suppresses error logs when the threshold is fatal', function () {
  Debug::setLogLevel(LogLevel::FATAL);

  Debug::error('error message');

  expect(readLogFile($this->logDirectory . '/error.log'))->toBe('');
});

function readLogFile(string $path): string
{
  if (!file_exists($path)) {
    return '';
  }

  return file_get_contents($path) ?: '';
}

function deleteDirectory(string $path): void
{
  if (!is_dir($path)) {
    return;
  }

  $files = scandir($path);

  if ($files === false) {
    return;
  }

  foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
      continue;
    }

    $childPath = $path . '/' . $file;

    if (is_dir($childPath)) {
      deleteDirectory($childPath);
      continue;
    }

    unlink($childPath);
  }

  rmdir($path);
}
