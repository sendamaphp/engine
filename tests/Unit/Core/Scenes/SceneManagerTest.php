<?php

use Sendama\Engine\Core\Behaviours\Attributes\SerializeField;
use Sendama\Engine\Core\Behaviours\Behaviour;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Sprite;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Enumerations\Color as EngineColor;
use Sendama\Engine\Mocks\MockBehavior;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\UI\GUITexture\GUITexture;
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

if (!class_exists(SceneManagerVectorProbe::class)) {
  class SceneManagerVectorProbe extends Behaviour
  {
    public ?Vector2 $minBound = null;
    public ?Vector2 $maxBound = null;
  }
}

if (!class_exists(SceneManagerUIElementProbe::class)) {
  class SceneManagerUIElementProbe extends Behaviour
  {
    public ?UIElement $statusUi = null;
    public ?GUITexture $heart = null;
  }
}

if (!class_exists(SceneManagerNativeTypeProbe::class)) {
  class SceneManagerNativeTypeProbe extends Behaviour
  {
    public ?Texture $bulletTexture = null;
    public ?Rect $clipRect = null;
    public ?Sprite $aimSprite = null;
    public ?EngineColor $tint = null;
  }
}

if (!class_exists(SceneManagerCompoundSettings::class)) {
  class SceneManagerCompoundSettings
  {
    public int $waves = 0;
    public ?Vector2 $origin = null;
  }
}

if (!class_exists(SceneManagerCompoundProbe::class)) {
  class SceneManagerCompoundProbe extends Behaviour
  {
    /** @var Vector2[] */
    public array $waypoints = [];

    public ?SceneManagerCompoundSettings $settings = null;
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
  $this->sceneWithNestedObjectsPath = Path::join(dirname(__DIR__, 3), 'Mocks', 'Scenes', 'scene_with_nested_objects');
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

it('hydrates gui textures from file scene metadata', function () {
  $workspace = sys_get_temp_dir() . '/sendama-gui-texture-' . uniqid('', true);
  $texturesDirectory = $workspace . '/assets/Textures';
  $scenesDirectory = $workspace . '/assets/Scenes';

  mkdir($texturesDirectory, 0777, true);
  mkdir($scenesDirectory, 0777, true);

  file_put_contents($texturesDirectory . '/hud.texture', "><\n[]\n");
  file_put_contents($scenesDirectory . '/gui_texture.scene.php', <<<'PHP'
<?php

return [
    'name' => 'GUI Texture Scene',
    'width' => 20,
    'height' => 10,
    'hierarchy' => [
        [
            'type' => \Sendama\Engine\UI\GUITexture\GUITexture::class,
            'name' => 'HUD Logo',
            'tag' => 'HUD',
            'position' => ['x' => 2, 'y' => 1],
            'size' => ['x' => 2, 'y' => 2],
            'texture' => 'Textures/hud',
            'color' => 'Yellow',
        ],
    ],
];
PHP);

  chdir($workspace);

  ob_start();
  $this->sceneManager->loadSceneFromFile($scenesDirectory . '/gui_texture');
  $this->sceneManager->loadScene('GUI Texture Scene');
  ob_end_clean();

  $uiElement = UIElement::find('HUD Logo');

  expect($uiElement)->toBeInstanceOf(GUITexture::class)
    ->and($uiElement?->getTag())->toBe('HUD')
    ->and($uiElement?->getTexturePath())->toBe('Textures/hud')
    ->and($uiElement?->getColor())->toBe(EngineColor::YELLOW);
});

it('hydrates component ui element references from scene metadata after the scene ui is built', function () {
  $workspace = sys_get_temp_dir() . '/sendama-ui-reference-' . uniqid('', true);
  $texturesDirectory = $workspace . '/assets/Textures';
  $scenesDirectory = $workspace . '/assets/Scenes';

  mkdir($texturesDirectory, 0777, true);
  mkdir($scenesDirectory, 0777, true);

  file_put_contents($texturesDirectory . '/heart.texture', "[]\n");
  file_put_contents($scenesDirectory . '/ui_reference.scene.php', <<<'PHP'
<?php

return [
    'name' => 'UI Reference Scene',
    'width' => 20,
    'height' => 10,
    'hierarchy' => [
        [
            'type' => \Sendama\Engine\Core\GameObject::class,
            'name' => 'Level Manager',
            'tag' => 'Manager',
            'position' => ['x' => 0, 'y' => 0],
            'rotation' => ['x' => 0, 'y' => 0],
            'scale' => ['x' => 1, 'y' => 1],
            'components' => [
                [
                    'class' => \SceneManagerUIElementProbe::class,
                    'data' => [
                        'statusUi' => 'Score',
                        'heart' => 'Heart #1',
                    ],
                ],
            ],
        ],
        [
            'type' => \Sendama\Engine\UI\Label\Label::class,
            'name' => 'Score',
            'tag' => 'UI',
            'position' => ['x' => 1, 'y' => 1],
            'size' => ['x' => 8, 'y' => 1],
            'text' => 'Score: 0',
        ],
        [
            'type' => \Sendama\Engine\UI\GUITexture\GUITexture::class,
            'name' => 'Heart #1',
            'tag' => 'UI',
            'position' => ['x' => 1, 'y' => 2],
            'size' => ['x' => 1, 'y' => 1],
            'texture' => 'Textures/heart',
            'color' => 'White',
        ],
    ],
];
PHP);

  chdir($workspace);

  ob_start();
  $this->sceneManager->loadSceneFromFile($scenesDirectory . '/ui_reference');
  $this->sceneManager->loadScene('UI Reference Scene');
  ob_end_clean();

  $scene = $this->sceneManager->getActiveScene();
  $controller = $scene?->getRootGameObjects()[0]?->getComponent(SceneManagerUIElementProbe::class);

  expect($controller)->toBeInstanceOf(SceneManagerUIElementProbe::class)
    ->and($controller?->statusUi)->toBeInstanceOf(Label::class)
    ->and($controller?->statusUi?->getName())->toBe('Score')
    ->and($controller?->heart)->toBeInstanceOf(GUITexture::class)
    ->and($controller?->heart?->getName())->toBe('Heart #1');
});

it('hydrates nested hierarchy children as runtime game objects', function () {
  ob_start();
  $this->sceneManager->loadSceneFromFile($this->sceneWithNestedObjectsPath);
  $this->sceneManager->loadScene('Scene With Nested Objects');
  ob_end_clean();

  $scene = $this->sceneManager->getActiveScene();
  $player = GameObject::find('Player');
  $weapon = GameObject::find('Weapon');

  expect($scene)->not()->toBeNull()
    ->and($scene->getRootGameObjects())->toHaveCount(1)
    ->and($player)->toBeInstanceOf(GameObject::class)
    ->and($weapon)->toBeInstanceOf(GameObject::class)
    ->and($weapon->getTransform()->getParent())->toBe($player->getTransform())
    ->and($weapon->getTransform()->getWorldPosition()->getX())->toBe(3)
    ->and($weapon->getTransform()->getWorldPosition()->getY())->toBe(2)
    ->and(GameObject::findWithTag('Equipment'))->toBe($weapon)
    ->and(GameObject::findAllWithTag('Equipment'))->toHaveCount(1);
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

it('hydrates vector component fields from legacy string metadata', function () {
  $probe = new SceneManagerVectorProbe(new GameObject('Bullet'));

  SceneManager::applySceneComponentMetadata(
    $probe,
    SceneManagerVectorProbe::class,
    (object) [
      'data' => (object) [
        'minBound' => '[1,1]',
        'maxBound' => '[120,25]',
      ],
    ]
  );

  expect($probe->minBound)->toBeInstanceOf(Vector2::class)
    ->and($probe->minBound?->getX())->toBe(1)
    ->and($probe->minBound?->getY())->toBe(1)
    ->and($probe->maxBound)->toBeInstanceOf(Vector2::class)
    ->and($probe->maxBound?->getX())->toBe(120)
    ->and($probe->maxBound?->getY())->toBe(25);
});

it('hydrates native engine component fields from scene metadata', function () {
  $workspace = sys_get_temp_dir() . '/sendama-native-type-hydration-' . uniqid('', true);
  $texturesDirectory = $workspace . '/assets/Textures';
  mkdir($texturesDirectory, 0777, true);
  file_put_contents($texturesDirectory . '/bullet.texture', "<>\n[]\n");

  chdir($workspace);

  $probe = new SceneManagerNativeTypeProbe(new GameObject('Weapon'));

  SceneManager::applySceneComponentMetadata(
    $probe,
    SceneManagerNativeTypeProbe::class,
    (object) [
      'data' => (object) [
        'bulletTexture' => 'Textures/bullet',
        'clipRect' => [
          'x' => 1,
          'y' => 2,
          'width' => 3,
          'height' => 4,
        ],
        'aimSprite' => [
          'texture' => 'Textures/bullet',
          'rect' => [
            'x' => 0,
            'y' => 1,
            'width' => 1,
            'height' => 1,
          ],
          'pivot' => [
            'x' => 1,
            'y' => 0,
          ],
        ],
        'tint' => 'Light Red',
      ],
    ],
  );

  expect($probe->bulletTexture)->toBeInstanceOf(Texture::class)
    ->and($probe->bulletTexture?->getPath())->toBe('Textures/bullet.texture')
    ->and($probe->clipRect)->toBeInstanceOf(Rect::class)
    ->and($probe->clipRect?->getX())->toBe(1)
    ->and($probe->clipRect?->getY())->toBe(2)
    ->and($probe->clipRect?->getWidth())->toBe(3)
    ->and($probe->clipRect?->getHeight())->toBe(4)
    ->and($probe->aimSprite)->toBeInstanceOf(Sprite::class)
    ->and($probe->aimSprite?->getTexture()->getPath())->toBe('Textures/bullet.texture')
    ->and($probe->aimSprite?->getRect()->getY())->toBe(1)
    ->and($probe->aimSprite?->getPivot()->getX())->toBe(1)
    ->and($probe->tint)->toBe(EngineColor::LIGHT_RED);
});

it('hydrates compound component structures and typed vector lists from scene metadata', function () {
  $probe = new SceneManagerCompoundProbe(new GameObject('Spawner'));

  SceneManager::applySceneComponentMetadata(
    $probe,
    SceneManagerCompoundProbe::class,
    (object) [
      'data' => (object) [
        'waypoints' => [
          ['x' => 1, 'y' => 2],
          ['x' => 3, 'y' => 4],
        ],
        'settings' => [
          'waves' => 3,
          'origin' => ['x' => 8, 'y' => 9],
        ],
      ],
    ],
  );

  expect($probe->waypoints)->toHaveCount(2)
    ->and($probe->waypoints[0])->toBeInstanceOf(Vector2::class)
    ->and($probe->waypoints[0]->getX())->toBe(1)
    ->and($probe->waypoints[1]->getY())->toBe(4)
    ->and($probe->settings)->toBeInstanceOf(SceneManagerCompoundSettings::class)
    ->and($probe->settings?->waves)->toBe(3)
    ->and($probe->settings?->origin)->toBeInstanceOf(Vector2::class)
    ->and($probe->settings?->origin?->getX())->toBe(8)
    ->and($probe->settings?->origin?->getY())->toBe(9);
});

function resetSceneManagerStaticProperty(string $className, string $propertyName, mixed $value): void
{
  $reflection = new \ReflectionClass($className);
  $property = $reflection->getProperty($propertyName);
  $property->setValue(null, $value);
}
