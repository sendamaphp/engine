<?php

use Sendama\Engine\Core\Behaviours\Attributes\SerializeField;
use Sendama\Engine\Core\Behaviours\Behaviour;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\Mocks\MockBehavior;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\UI\Label\Label;
use Sendama\Engine\UI\UIElement;
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

if (!class_exists(SceneManagerPrefabProbe::class)) {
  class SceneManagerPrefabProbe extends Behaviour
  {
    public ?GameObject $enemyPrefab = null;
  }
}

beforeEach(function () {
  resetSceneManagerStaticProperty(SceneManager::class, 'instance', null);
  $this->originalCwd = getcwd();

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
  $this->sceneWithNamedObjectsPath = Path::join(dirname(__DIR__, 3), 'Mocks', 'Scenes', 'scene_with_named_objects');
});

afterEach(function () {
  if (is_string($this->originalCwd) && $this->originalCwd !== '') {
    chdir($this->originalCwd);
  }
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

it('preserves authored scene object names for find lookups', function () {
  ob_start();
  $this->sceneManager->loadSceneFromFile($this->sceneWithNamedObjectsPath);
  $this->sceneManager->loadScene('Scene With Named Objects');
  ob_end_clean();

  $gameObject = GameObject::find('Player');
  $uiElement = UIElement::find('Score');

  expect($gameObject)->not()->toBeNull()
    ->and($gameObject->getName())->toBe('Player')
    ->and($uiElement)->toBeInstanceOf(Label::class)
    ->and($uiElement->getName())->toBe('Score');
});

it('inflates prefab reference fields into concrete game object templates', function () {
  $workspace = sys_get_temp_dir() . '/sendama-prefab-' . uniqid('', true);
  $prefabsDirectory = $workspace . '/assets/Prefabs';
  $scenesDirectory = $workspace . '/assets/Scenes';

  mkdir($prefabsDirectory, 0777, true);
  mkdir($scenesDirectory, 0777, true);

  file_put_contents($prefabsDirectory . '/enemy.prefab.php', <<<'PHP'
<?php

return [
    'type' => \Sendama\Engine\Core\GameObject::class,
    'name' => 'Enemy Prefab',
    'tag' => 'Enemy',
    'position' => ['x' => 0, 'y' => 0],
    'rotation' => ['x' => 0, 'y' => 0],
    'scale' => ['x' => 1, 'y' => 1],
    'components' => [
        [
            'class' => \Sendama\Engine\Mocks\MockBehavior::class,
            'data' => [],
        ],
    ],
];
PHP);

  file_put_contents($scenesDirectory . '/prefab_field.scene.php', <<<'PHP'
<?php

return [
    'name' => 'Prefab Field Scene',
    'width' => 20,
    'height' => 10,
    'hierarchy' => [
        [
            'type' => \Sendama\Engine\Core\GameObject::class,
            'name' => 'Controller',
            'tag' => 'Manager',
            'position' => ['x' => 0, 'y' => 0],
            'rotation' => ['x' => 0, 'y' => 0],
            'scale' => ['x' => 1, 'y' => 1],
            'components' => [
                [
                    'class' => \SceneManagerPrefabProbe::class,
                    'data' => [
                        'enemyPrefab' => 'Prefabs/enemy.prefab.php',
                    ],
                ],
            ],
        ],
    ],
];
PHP);

  chdir($workspace);

  ob_start();
  $this->sceneManager->loadSceneFromFile($scenesDirectory . '/prefab_field');
  $this->sceneManager->loadScene('Prefab Field Scene');
  ob_end_clean();

  $scene = $this->sceneManager->getActiveScene();
  $controller = $scene?->getRootGameObjects()[0] ?? null;
  $probe = $controller?->getComponent(SceneManagerPrefabProbe::class);

  expect($probe)->toBeInstanceOf(SceneManagerPrefabProbe::class)
    ->and($probe->enemyPrefab)->toBeInstanceOf(GameObject::class)
    ->and($probe->enemyPrefab->getName())->toBe('Enemy Prefab')
    ->and($probe->enemyPrefab->getComponent(MockBehavior::class))->toBeInstanceOf(MockBehavior::class);
});

function resetSceneManagerStaticProperty(string $className, string $propertyName, mixed $value): void
{
  $reflection = new \ReflectionClass($className);
  $property = $reflection->getProperty($propertyName);
  $property->setValue(null, $value);
}
