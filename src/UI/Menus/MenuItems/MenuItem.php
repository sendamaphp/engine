<?php

namespace Sendama\Engine\UI\Menus\MenuItems;

use Closure;
use Sendama\Engine\Core\Interfaces\ExecutionContextInterface;
use Sendama\Engine\UI\Menus\Interfaces;

/**
 * The class MenuItem.
 *
 * @package Sendama\Engine\UI\Menus
 */
class MenuItem implements Interfaces\MenuItemInterface
{
  /**
   * Constructs a MenuItem.
   *
   * @param string $label The label of the menu item.
   * @param string $description The description of the menu item.
   * @param string $icon The icon of the menu item.
   * @param Closure|null $callback The callback to execute when the menu item is selected.
   */
  public function __construct(
    protected string   $label,
    protected string   $description = '',
    protected string   $icon = '',
    protected ?Closure $callback = null,
    protected bool $enabled = true
  )
  {
  }

  /**
   * @inheritDoc
   */
  public function isEnabled(): bool
  {
    return $this->enabled;
  }

  /**
   * @inheritDoc
   */
  public function setEnabled(bool $enabled): void
  {
    $this->enabled = $enabled;
  }

  /**
   * @inheritDoc
   */
  public function execute(?ExecutionContextInterface $context = null): void
  {
    if (!$this->enabled) {
      return;
    }

    $this->callback?->call($context ?? $this);
  }

  /**
   * @inheritDoc
   */
  public function getLabel(): string
  {
    return $this->label;
  }

  /**
   * @inheritDoc
   */
  public function setLabel(string $label): void
  {
    $this->label = $label;
  }

  /**
   * @inheritDoc
   */
  public function getIcon(): string
  {
    return $this->icon;
  }

  /**
   * @inheritDoc
   */
  public function setIcon(string $icon)
  {
    $this->icon = $icon;
  }

  /**
   * @inheritDoc
   */
  public function getDescription(): string
  {
    return $this->description;
  }

  /**
   * @inheritDoc
   */
  public function setDescription(string $description): void
  {
    $this->description = $description;
  }

  /**
   * Sets the callback to execute when the menu item is selected.
   *
   * @param Closure|null $callback The callback to execute when the menu item is selected.
   * @return void
   */
  public function setCallback(?Closure $callback = null): void
  {
    $this->callback = $callback;
  }

  /**
   * @inheritDoc
   */
  public function __toString(): string
  {
    return sprintf("%s %s", $this->icon, $this->label);
  }
}
