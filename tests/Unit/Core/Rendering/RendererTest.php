<?php

use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Scenes\AbstractScene;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\Physics\Physics;

beforeEach(function () {
  resetSingleton(SceneManager::class, 'instance');
  resetSingleton(Physics::class, 'instance');

  Console::refreshLayout(
    DEFAULT_SCREEN_WIDTH,
    DEFAULT_SCREEN_HEIGHT,
    new Rect(new Vector2(1, 1), new Vector2(DEFAULT_SCREEN_WIDTH, DEFAULT_SCREEN_HEIGHT)),
    clearWhenChanged: false
  );
});

it('renders sprite rows at the exact world coordinate', function () {
  $gameObject = new GameObject('Player', position: new Vector2(1, 1));
  $gameObject->setSpriteFromTexture(
    new Texture(getcwd() . '/tests/Mocks/Textures/test.texture'),
    new Vector2(0, 0),
    new Vector2(1, 1)
  );

  ob_start();
  $gameObject->render();
  $output = ob_get_clean();

  expect($output)->toContain("\033[1;1H>");
});

it('does not erase anything before a sprite has rendered for the first time', function () {
  $sceneManager = SceneManager::getInstance();
  $sceneManager->loadSettings([
    'screen_width' => 10,
    'screen_height' => 10,
  ]);

  $scene = new class('Test Scene') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  };

  $sceneManager->addScene($scene);
  $sceneManager->loadScene('Test Scene');

  $gameObject = new GameObject('Player', position: new Vector2(0, 0));
  $gameObject->setSpriteFromTexture(
    new Texture(getcwd() . '/tests/Mocks/Textures/test.texture'),
    new Vector2(0, 0),
    new Vector2(1, 1)
  );
  $scene->add($gameObject);

  ob_start();
  $gameObject->erase();
  $output = ob_get_clean();

  expect($output)->toBe('');
});

it('restores the environment tile map under a sprite when it is erased', function () {
  $sceneManager = SceneManager::getInstance();
  $sceneManager->loadSettings([
    'screen_width' => 10,
    'screen_height' => 10,
  ]);

  $scene = new class('Test Scene') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  };

  $sceneManager->addScene($scene);
  $sceneManager->loadScene('Test Scene');
  $scene->getWorldSpace()->set(0, 0, '#');

  $gameObject = new GameObject('Player', position: new Vector2(1, 1));
  $gameObject->setSpriteFromTexture(
    new Texture(getcwd() . '/tests/Mocks/Textures/test.texture'),
    new Vector2(0, 0),
    new Vector2(1, 1)
  );
  $scene->add($gameObject);

  ob_start();
  $gameObject->render();
  ob_end_clean();

  ob_start();
  $gameObject->erase();
  $output = ob_get_clean();

  $offset = Console::getRenderOffset();
  expect($output)->toContain("\033[{$offset->getY()};{$offset->getX()}H#");
});

it('restores buffered console content after repeated renders at the same position', function () {
  Console::write('-----', 2, 2);

  $gameObject = new GameObject('Player', position: new Vector2(2, 2));
  $gameObject->setSpriteFromTexture(
    new Texture(getcwd() . '/tests/Mocks/Textures/test.texture'),
    new Vector2(0, 0),
    new Vector2(1, 1)
  );

  ob_start();
  $gameObject->render();
  ob_end_clean();

  ob_start();
  $gameObject->render();
  ob_end_clean();

  ob_start();
  $gameObject->erase();
  $output = ob_get_clean();

  $offset = Console::getRenderOffset();
  $row = $offset->getY() + 1;
  $column = $offset->getX() + 1;

  expect($output)->toContain("\033[{$row};{$column}H-");
});

it('restores the previous bounds when a transform position is mutated directly before render', function () {
  Console::write('-----', 2, 2);

  $gameObject = new GameObject('Player', position: new Vector2(2, 2));
  $gameObject->setSpriteFromTexture(
    new Texture(getcwd() . '/tests/Mocks/Textures/test.texture'),
    new Vector2(0, 0),
    new Vector2(1, 1)
  );

  ob_start();
  $gameObject->render();
  ob_end_clean();

  $gameObject->getTransform()->getPosition()->add(new Vector2(2, 0));

  ob_start();
  $gameObject->render();
  $output = ob_get_clean();

  $offset = Console::getRenderOffset();
  $oldRow = $offset->getY() + 1;
  $oldColumn = $offset->getX() + 1;
  $newColumn = $offset->getX() + 3;

  expect($output)
    ->toContain("\033[{$oldRow};{$oldColumn}H-")
    ->toContain("\033[{$oldRow};{$newColumn}H>");
});

function resetSingleton(string $className, string $property): void
{
  $reflection = new ReflectionClass($className);
  $reflection->getProperty($property)->setValue(null, null);
}
