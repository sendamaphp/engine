<?php

use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\Util\Path;

beforeEach(function () {
  resetSceneManagerStaticProperty(SceneManager::class, 'instance', null);

  Console::refreshLayout(
    160,
    40,
    new Rect(new Vector2(1, 1), new Vector2(160, 40)),
    clearWhenChanged: false
  );

  $this->sceneManager = SceneManager::getInstance();
  $this->sceneManager->loadSettings([
    'screen_width' => 160,
    'screen_height' => 40,
  ]);

  $this->scenePath = Path::join(dirname(__DIR__, 3), 'Mocks', 'Scenes', 'scene_with_dimensions');
});

it('applies file scene dimensions to the active viewport and centered layout', function () {
  ob_start();
  $this->sceneManager->loadSceneFromFile($this->scenePath);
  $this->sceneManager->loadScene('Scene With Dimensions');
  ob_end_clean();

  $scene = $this->sceneManager->getActiveScene();
  $terminalSize = Console::getSize(force: true);
  $offset = Console::getRenderOffset();
  $expectedOffsetX = (int)floor(($terminalSize->getWidth() - 80) / 2) + 1;
  $expectedOffsetY = (int)floor(($terminalSize->getHeight() - 25) / 2) + 1;

  expect($scene)->not()->toBeNull()
    ->and($scene->getSettings('screen_width'))->toBe(80)
    ->and($scene->getSettings('screen_height'))->toBe(25)
    ->and($scene->getCamera()->getViewport()->getWidth())->toBe(80)
    ->and($scene->getCamera()->getViewport()->getHeight())->toBe(25)
    ->and($offset->getX())->toBe($expectedOffsetX)
    ->and($offset->getY())->toBe($expectedOffsetY);
});

function resetSceneManagerStaticProperty(string $className, string $propertyName, mixed $value): void
{
  $reflection = new \ReflectionClass($className);
  $property = $reflection->getProperty($propertyName);
  $property->setValue(null, $value);
}
