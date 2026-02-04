<?php

namespace Sendama\Engine\IO;

use Sendama\Engine\IO\Enumerations\AxisName;
use Sendama\Engine\IO\Enumerations\KeyCode;

/**
 * Class Input. This class is a facade for the InputManager class.
 *
 * @package Sendama\Engine\IO
 */
class Input
{
  /**
   * Returns the value of the virtual axis identified by axisName.
   *
   * @param AxisName $axisName
   * @return float
   */
  public static function getAxis(AxisName $axisName): float
  {
    return InputManager::getAxis($axisName);
  }

  /**
   * Checks if a key is pressed.
   *
   * @param KeyCode $keyCode The key code to check.
   * @return bool Returns true if the key is pressed, false otherwise.
   */
  public static function isKeyPressed(KeyCode $keyCode): bool
  {
    return InputManager::isKeyPressed($keyCode);
  }

  /**
   * Checks if all the given keys are pressed.
   *
   * @param array<KeyCode> $keyCodes The key codes to check.
   * @return bool Returns true if any key is pressed, false otherwise.
   */
  public static function areAllKeysPressed(array $keyCodes): bool
  {
    return InputManager::areAllKeysPressed($keyCodes);
  }

  /**
   * Checks if any of the given keys are pressed.
   *
   * @param array<KeyCode> $keyCodes
   * @return bool Returns true if any key is pressed, false otherwise.
   */
  public static function isAnyKeyPressed(array $keyCodes, bool $ignoreCase = true): bool
  {
    return InputManager::isAnyKeyPressed($keyCodes, $ignoreCase);
  }

  /**
   * Checks if any of the given keys are released.
   *
   * @param array<KeyCode> $keyCodes The key codes to check.
   * @return bool Returns true if any key is released, false otherwise.
   */
  public static function isAnyKeyReleased(array $keyCodes): bool
  {
    return InputManager::isAnyKeyReleased($keyCodes);
  }

  /**
   * Checks if the given key is pressed.
   *
   * @param KeyCode $keyCode The key code to check.
   * @return bool Returns true if the key is pressed, false otherwise.
   */
  public static function isKeyDown(KeyCode $keyCode): bool
  {
    return InputManager::isKeyDown($keyCode);
  }

  /**
   * Checks if the given key was released.
   *
   * @param KeyCode $keyCode The key code to check.
   * @return bool Returns true if the key was released, false otherwise.
   */
  public static function isKeyUp(KeyCode $keyCode): bool
  {
    return InputManager::isKeyUp($keyCode);
  }

  /**
   * Checks if the given button is pressed.
   *
   * @param string $buttonName The name of the button to check.
   * @return bool Returns true if the button is pressed, false otherwise.
   */
  public static function isButtonDown(string $buttonName): bool
  {
    return InputManager::isButtonDown($buttonName);
  }
}
