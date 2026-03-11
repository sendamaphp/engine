<?php

use Sendama\Engine\Game;
use Sendama\Engine\Util\Config\AppConfig;
use Sendama\Engine\Util\Config\ConfigStore;
use Sendama\Engine\Util\Interfaces\ConfigInterface;

beforeEach(function () {
  resetStaticProperty(ConfigStore::class, 'store', []);
  unset($_ENV['DEBUG_MODE'], $_ENV['SHOW_DEBUG_INFO']);
});

afterEach(function () {
  unset($_ENV['DEBUG_MODE'], $_ENV['SHOW_DEBUG_INFO']);
});

it('reads debug flags from sendama config when env overrides are absent', function () {
  ConfigStore::put(AppConfig::class, new ArrayConfig([
    'debug' => true,
    'showDebugInfo' => true,
  ]));

  expect(invokePrivateStaticMethod(Game::class, 'resolveConfiguredSetting', 'DEBUG_MODE', ['debug', 'debugMode'], false))->toBeTrue()
    ->and(invokePrivateStaticMethod(Game::class, 'resolveConfiguredSetting', 'SHOW_DEBUG_INFO', ['showDebugInfo', 'debug_info'], false))->toBeTrue()
    ->and(invokePrivateStaticMethod(Game::class, 'isTruthySetting', true))->toBeTrue();
});

it('prefers env debug flags over sendama config values', function () {
  $_ENV['DEBUG_MODE'] = 'false';
  $_ENV['SHOW_DEBUG_INFO'] = '0';

  ConfigStore::put(AppConfig::class, new ArrayConfig([
    'debug' => true,
    'showDebugInfo' => true,
  ]));

  expect(invokePrivateStaticMethod(Game::class, 'resolveConfiguredSetting', 'DEBUG_MODE', ['debug', 'debugMode'], false))->toBe('false')
    ->and(invokePrivateStaticMethod(Game::class, 'resolveConfiguredSetting', 'SHOW_DEBUG_INFO', ['showDebugInfo', 'debug_info'], false))->toBe('0')
    ->and(invokePrivateStaticMethod(Game::class, 'isTruthySetting', 'false'))->toBeFalse()
    ->and(invokePrivateStaticMethod(Game::class, 'isTruthySetting', '0'))->toBeFalse();
});

function invokePrivateStaticMethod(string $className, string $methodName, mixed ...$args): mixed
{
  $reflection = new \ReflectionClass($className);
  $method = $reflection->getMethod($methodName);

  return $method->invoke(null, ...$args);
}

function resetStaticProperty(string $className, string $propertyName, mixed $value): void
{
  $reflection = new \ReflectionClass($className);
  $property = $reflection->getProperty($propertyName);
  $property->setValue(null, $value);
}

final class ArrayConfig implements ConfigInterface
{
  public function __construct(private array $config)
  {
  }

  public function get(string $path, mixed $default = null): mixed
  {
    $config = $this->config;

    foreach (explode('.', $path) as $segment) {
      if (!is_array($config) || !array_key_exists($segment, $config)) {
        return $default;
      }

      $config = $config[$segment];
    }

    return $config;
  }

  public function set(string $path, mixed $value): void
  {
    $config = &$this->config;

    foreach (explode('.', $path) as $segment) {
      if (!isset($config[$segment]) || !is_array($config[$segment])) {
        $config[$segment] = [];
      }

      $config = &$config[$segment];
    }

    $config = $value;
  }

  public function has(string $path): bool
  {
    $config = $this->config;

    foreach (explode('.', $path) as $segment) {
      if (!is_array($config) || !array_key_exists($segment, $config)) {
        return false;
      }

      $config = $config[$segment];
    }

    return true;
  }

  public function persist(): void
  {
    // No-op for tests.
  }
}
