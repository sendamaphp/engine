<?php

namespace Sendama\Engine\UI\Menus\Interfaces;

use Closure;
use Sendama\Engine\Core\Interfaces\ExecutableInterface;
use Stringable;

/**
 * Represents a menu item. A menu item is an element in a menu.
 *
 * @package Sendama\Engine\UI\Menus\Interfaces
 */
interface MenuItemInterface extends Stringable, ExecutableInterface
{
  /**
   * Checks whether the menu item can currently be selected.
   *
   * @return bool
   */
  public function isEnabled(): bool;

  /**
   * Enables or disables the menu item.
   *
   * @param bool $enabled
   * @return void
   */
  public function setEnabled(bool $enabled): void;

  /**
   * Returns the label of the menu item.
   *
   * @return string The label of the menu item.
   */
  public function getLabel(): string;

  /**
   * Sets the label of the menu item.
   *
   * @param string $label The label of the menu item.
   * @return void
   */
  public function setLabel(string $label): void;

  /**
   * Returns the icon of the menu item.
   *
   * @return string The icon of the menu item.
   */
  public function getIcon(): string;

  /**
   * Sets the icon of the menu item.
   *
   * @param string $icon The icon of the menu item.
   * @return void
   */
  public function setIcon(string $icon);

  /**
   * Returns the description of the menu item.
   *
   * @return string The description of the menu item.
   */
  public function getDescription(): string;

  /**
   * Sets the description of the menu item.
   *
   * @param string $description The description of the menu item.
   * @return void
   */
  public function setDescription(string $description): void;

  /**
   * Sets the callback to execute when the menu item is selected.
   *
   * @param Closure|null $callback The callback to execute when the menu item is selected.
   * @return void
   */
  public function setCallback(?Closure $callback = null): void;
}
