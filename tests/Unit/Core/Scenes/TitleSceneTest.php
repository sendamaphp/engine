<?php

use Sendama\Engine\Core\Scenes\AbstractScene;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Scenes\TitleScene;
use Sendama\Engine\UI\Menus\Menu;
use Sendama\Engine\UI\Menus\MenuItems\MenuItem;
use Sendama\Engine\UI\Text\Text;
use Amasiye\Figlet\FontName;

beforeEach(function () {
  resetTitleSceneSingleton(SceneManager::class, 'instance');

  SceneManager::getInstance()->loadSettings([
    'game_name' => 'Blasters',
    'screen_width' => 140,
    'screen_height' => 30,
  ]);
});

it('uses scene manager dimensions while title scenes are still waking up', function () {
  $scene = new TitleScene('Blasters');

  $menu = getProtectedProperty($scene, 'menu');
  $titleText = getProtectedProperty($scene, 'titleText');
  $window = getProtectedProperty($menu, 'window');
  $expectedTitleTopMargin = (int) intdiv(
    max(0, 30 - ($titleText->getHeight() + 1 + $menu->getSize()->getY())),
    2,
  );

  expect($scene->getSettings('screen_width'))->toBeNull()
    ->and($menu)->toBeInstanceOf(Menu::class)
    ->and($menu->getPosition()->getX())->toBe(60)
    ->and($menu->getPosition()->getY())->toBe($expectedTitleTopMargin + $titleText->getHeight() + 1)
    ->and($window->getPosition()->getX())->toBe(60)
    ->and($window->getPosition()->getY())->toBe($expectedTitleTopMargin + $titleText->getHeight() + 1)
    ->and($titleText)->toBeInstanceOf(Text::class)
    ->and($titleText->getPosition()->getX())->toBe((int) intdiv(max(0, 140 - $titleText->getWidth()), 2))
    ->and($titleText->getPosition()->getY())->toBe($expectedTitleTopMargin);
});

it('returns null for missing settings keys instead of the full settings payload', function () {
  $scene = new class('Test Scene') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  };

  expect($scene->getSettings('missing'))->toBeNull()
    ->and($scene->getSettings(null))->toBe([])
    ->and(SceneManager::getInstance()->getSettings('missing'))->toBeNull()
    ->and(SceneManager::getInstance()->getSettings('screen_width'))->toBe(140);
});

it('disables new game when the default target scene is unavailable', function () {
  $scene = new TitleScene('Blasters');
  /** @var Menu $menu */
  $menu = getProtectedProperty($scene, 'menu');
  /** @var MenuItem $newGameItem */
  $newGameItem = $menu->getItemByIndex(0);
  $quitItem = $menu->getItemByIndex(1);

  expect($newGameItem->isEnabled())->toBeFalse()
    ->and($menu->getActiveItem())->toBe($quitItem);
});

it('keeps new game enabled when its target scene exists', function () {
  SceneManager::getInstance()->addScene(new class('Title Placeholder') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  });

  SceneManager::getInstance()->addScene(new class('Game Scene') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  });

  $scene = new TitleScene('Blasters');
  /** @var Menu $menu */
  $menu = getProtectedProperty($scene, 'menu');
  /** @var MenuItem $newGameItem */
  $newGameItem = $menu->getItemByIndex(0);

  expect($newGameItem->isEnabled())->toBeTrue()
    ->and($menu->getActiveItemIndex())->toBe(0);
});

it('enables new game when the target scene is added after the title scene is registered', function () {
  $titleScene = new TitleScene('Blasters');
  SceneManager::getInstance()->addScene($titleScene);

  /** @var Menu $menu */
  $menu = getProtectedProperty($titleScene, 'menu');
  /** @var MenuItem $newGameItem */
  $newGameItem = $menu->getItemByIndex(0);

  expect($newGameItem->isEnabled())->toBeFalse();

  SceneManager::getInstance()->addScene(new class('Level 1') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  });

  expect($newGameItem->isEnabled())->toBeTrue()
    ->and($menu->getActiveItemIndex())->toBe(0);
});

it('recenters the title presentation after screen settings load later', function () {
  resetTitleSceneSingleton(SceneManager::class, 'instance');
  $sceneManager = SceneManager::getInstance();
  $titleScene = new TitleScene('Blasters');
  $sceneManager->addScene($titleScene);

  $menu = getProtectedProperty($titleScene, 'menu');
  $titleText = getProtectedProperty($titleScene, 'titleText');
  $window = getProtectedProperty($menu, 'window');

  $sceneManager->loadSettings([
    'game_name' => 'Blasters',
    'screen_width' => 120,
    'screen_height' => 25,
  ]);

  $expectedTitleTopMargin = (int) intdiv(
    max(0, 25 - ($titleText->getHeight() + 1 + $menu->getSize()->getY())),
    2,
  );

  expect($titleText->getPosition()->getX())->toBe((int) intdiv(max(0, 120 - $titleText->getWidth()), 2))
    ->and($titleText->getPosition()->getY())->toBe($expectedTitleTopMargin)
    ->and($menu->getPosition()->getX())->toBe((int) intdiv(max(0, 120 - $menu->getSize()->getX()), 2))
    ->and($menu->getPosition()->getY())->toBe($expectedTitleTopMargin + $titleText->getHeight() + 1)
    ->and($window->getPosition()->getX())->toBe((int) intdiv(max(0, 120 - $menu->getSize()->getX()), 2))
    ->and($window->getPosition()->getY())->toBe($expectedTitleTopMargin + $titleText->getHeight() + 1);
});

it('recenters the title after its font changes', function () {
  $scene = new TitleScene('Blasters');
  $scene->setTitleFont(FontName::BANNER3_D);

  /** @var Menu $menu */
  $menu = getProtectedProperty($scene, 'menu');
  /** @var Text $titleText */
  $titleText = getProtectedProperty($scene, 'titleText');
  $window = getProtectedProperty($menu, 'window');
  $expectedTitleTopMargin = (int) intdiv(
    max(0, 30 - ($titleText->getHeight() + 1 + $menu->getSize()->getY())),
    2,
  );

  expect($titleText->getPosition()->getX())->toBe((int) intdiv(max(0, 140 - $titleText->getWidth()), 2))
    ->and($titleText->getPosition()->getY())->toBe($expectedTitleTopMargin)
    ->and($menu->getPosition()->getX())->toBe((int) intdiv(max(0, 140 - $menu->getSize()->getX()), 2))
    ->and($menu->getPosition()->getY())->toBe($expectedTitleTopMargin + $titleText->getHeight() + 1)
    ->and($window->getPosition()->getX())->toBe((int) intdiv(max(0, 140 - $menu->getSize()->getX()), 2))
    ->and($window->getPosition()->getY())->toBe($expectedTitleTopMargin + $titleText->getHeight() + 1);
});

function getProtectedProperty(object $object, string $property): mixed
{
  $reflection = new ReflectionClass($object);
  return $reflection->getProperty($property)->getValue($object);
}

function resetTitleSceneSingleton(string $className, string $propertyName): void
{
  $reflection = new ReflectionClass($className);
  $property = $reflection->getProperty($propertyName);
  $property->setValue(null, null);
}
