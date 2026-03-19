<?php

use Sendama\Engine\IO\InputManager;
use Sendama\Engine\UI\Menus\Menu;
use Sendama\Engine\UI\Menus\MenuItems\MenuItem;

beforeEach(function () {
  setMenuInputState('previousKeyPress', '');
  setMenuInputState('keyPress', '');
});

it('selects the first enabled menu item', function () {
  $menu = new Menu('Main Menu');
  $disabledItem = new MenuItem(label: 'New Game', enabled: false);
  $quitItem = new MenuItem(label: 'Quit');

  $menu->addItem($disabledItem);
  $menu->addItem($quitItem);

  expect($menu->getActiveItem())->toBe($quitItem)
    ->and($disabledItem->isEnabled())->toBeFalse();
});

it('skips disabled items while navigating', function () {
  $menu = new Menu('Main Menu');
  $firstItem = new MenuItem(label: 'Start');
  $disabledItem = new MenuItem(label: 'Continue', enabled: false);
  $thirdItem = new MenuItem(label: 'Quit');

  $menu->addItem($firstItem);
  $menu->addItem($disabledItem);
  $menu->addItem($thirdItem);

  setMenuInputState('previousKeyPress', '');
  setMenuInputState('keyPress', "\033[B");

  $menu->update();

  expect($menu->getActiveItem())->toBe($thirdItem);
});

it('does not keep a disabled item active when no selectable items exist', function () {
  $menu = new Menu('Main Menu');
  $disabledItem = new MenuItem(label: 'New Game', enabled: false);

  $menu->addItem($disabledItem);
  $menu->setActiveItem($disabledItem);

  setMenuInputState('previousKeyPress', '');
  setMenuInputState('keyPress', "\n");

  $menu->update();

  expect($menu->getActiveItem())->toBeNull();
});

function setMenuInputState(string $property, string $value): void
{
  $reflection = new ReflectionClass(InputManager::class);
  $reflection->getProperty($property)->setValue(null, $value);
}
