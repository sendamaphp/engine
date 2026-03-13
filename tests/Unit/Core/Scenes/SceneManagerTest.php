<?php

use Sendama\Engine\Core\Behaviours\Attributes\SerializeField;
use Sendama\Engine\Core\Behaviours\Behaviour;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\Util\Path;

if (!class_exists(SceneManagerDataProbe::class)) {
  class SceneManagerDataProbe extends Behaviour
  {
    public int $speed = 0;

    #[SerializeField]
    protected int $power = 0;

    public function getPower(): int
    {
      return $this->power;
    }
  }
}

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

  Physics::getInstance()->init();

  $this->scenePath = Path::join(dirname(__DIR__, 3), 'Mocks', 'Scenes', 'scene_with_dimensions');
  $this->sceneWithComponentDataPath = Path::join(dirname(__DIR__, 3), 'Mocks', 'Scenes', 'scene_with_component_data');
  $this->sceneWithEnvironmentCollisionPath = Path::join(dirname(__DIR__, 3), 'Mocks', 'Scenes', 'scene_with_environment_collision');
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

it('hydrates component data from editor scene files', function () {
  ob_start();
  $this->sceneManager->loadSceneFromFile($this->sceneWithComponentDataPath);
  $this->sceneManager->loadScene('Scene With Component Data');
  ob_end_clean();

  $scene = $this->sceneManager->getActiveScene();

  expect($scene)->not()->toBeNull();

  $probeGameObject = $scene->getRootGameObjects()[0] ?? null;
  $probeComponent = $probeGameObject?->getComponent(SceneManagerDataProbe::class);

  expect($probeComponent)->toBeInstanceOf(SceneManagerDataProbe::class)
    ->and($probeComponent->speed)->toBe(3)
    ->and($probeComponent->getPower())->toBe(7);
});

it('loads static collision maps from scene metadata without requiring a rendered tile map', function () {
  ob_start();
  $this->sceneManager->loadSceneFromFile($this->sceneWithEnvironmentCollisionPath);
  $this->sceneManager->loadScene('Scene With Environment Collision');
  ob_end_clean();

  $scene = $this->sceneManager->getActiveScene();

  expect($scene)->not()->toBeNull()
    ->and($scene->getCollisionWorldSpace()->get(2, 1))->toBe(1)
    ->and(Physics::getInstance()->isTouchingStaticObject(new Vector2(2, 1)))->toBeTrue()
    ->and(Physics::getInstance()->isTouchingStaticObject(new Vector2(0, 0)))->toBeFalse();
});

function resetSceneManagerStaticProperty(string $className, string $propertyName, mixed $value): void
{
  $reflection = new \ReflectionClass($className);
  $property = $reflection->getProperty($propertyName);
  $property->setValue(null, $value);
}
