<?php

use Sendama\Engine\Core\Scenes\AbstractScene;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Scenes\TitleScene;
use Sendama\Engine\UI\Menus\Menu;
use Sendama\Engine\UI\Text\Text;

beforeEach(function () {
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

  expect($scene->getSettings('screen_width'))->toBeNull()
    ->and($menu)->toBeInstanceOf(Menu::class)
    ->and($menu->getPosition()->getX())->toBe(60)
    ->and($titleText)->toBeInstanceOf(Text::class)
    ->and($titleText->getPosition()->getX())->toBe((int)round((140 / 2) - ($titleText->getWidth() / 2)))
    ->and($titleText->getPosition()->getY())->toBe(TitleScene::TOP_MARGIN_OFFSET);
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

function getProtectedProperty(object $object, string $property): mixed
{
  $reflection = new ReflectionClass($object);
  return $reflection->getProperty($property)->getValue($object);
}
