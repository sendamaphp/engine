<?php

use Sendama\Engine\IO\Input;
use Sendama\Engine\IO\InputManager;

beforeEach(function () {
  setInputManagerState('previousKeyPress', '');
  setInputManagerState('keyPress', '');
});

it('accepts serialized string key codes in key arrays', function () {
  setInputManagerState('previousKeyPress', '');
  setInputManagerState('keyPress', 'R');

  expect(InputManager::isAnyKeyPressed(['R']))->toBeTrue()
    ->and(InputManager::isAnyKeyPressed(['<R>']))->toBeTrue()
    ->and(InputManager::isAnyKeyPressed(['r']))->toBeTrue();
});

it('accepts string key codes through the input facade', function () {
  setInputManagerState('previousKeyPress', '');
  setInputManagerState('keyPress', "\033");

  expect(Input::isKeyDown('escape'))->toBeTrue()
    ->and(Input::isKeyDown('<ESCAPE>'))->toBeTrue();
});

it('returns false for unknown serialized key codes', function () {
  setInputManagerState('previousKeyPress', '');
  setInputManagerState('keyPress', 'q');

  expect(InputManager::isAnyKeyPressed(['<NOT_A_KEY>']))->toBeFalse()
    ->and(Input::isKeyDown('not_a_key'))->toBeFalse();
});

function setInputManagerState(string $property, string $value): void
{
  $reflection = new ReflectionClass(InputManager::class);
  $reflection->getProperty($property)->setValue(null, $value);
}
