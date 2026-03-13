<?php

use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Scenes\Scene;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Scenes\SceneNode;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Transform;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Core\Behaviours\Behaviour;
use Sendama\Engine\Mocks\MockBehavior;
use Sendama\Engine\Physics\Collider;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\Physics\Rigidbody;

if (!class_exists(StartAddsColliderBehavior::class)) {
  class StartAddsColliderBehavior extends Behaviour
  {
    public function onStart(): void
    {
      $this->getGameObject()->addComponent(Collider::class);
    }
  }
}


describe('GameObject', function () {
  beforeEach(function () {
    $this->gameObjectName = 'Test Game Object';
    $this->gameObjectTag = 'Test Tag';

    $this->gameObject = new GameObject($this->gameObjectName);
  });

  it('can be created', function () {
    $gameObject = new GameObject($this->gameObjectName);
    expect(get_class($gameObject))->toEqual(GameObject::class);
  });

  it('can have a name', function () {
    expect($this->gameObject->getName())->toEqual($this->gameObjectName);
  });

  it('can have a tag', function () {
    $gameObject = new GameObject($this->gameObjectName, $this->gameObjectTag);
    expect($gameObject->getTag())->toEqual($this->gameObjectTag);
  });

  it('can have a parent', function () {
    $parent = new GameObject('Parent');
    $this->gameObject->getTransform()->setParent($parent->getTransform());
    expect($this->gameObject->getTransform()->getParent())->toEqual($parent->getTransform());
  });

  it('can have a transform', function () {
    expect($this->gameObject->getTransform())->toBeInstanceOf(Transform::class);
  });

  it('can set and get a component', function () {
    $mockBehaviour = $this->gameObject->addComponent(MockBehavior::class);

    expect($mockBehaviour)
      ->toBeInstanceOf(MockBehavior::class)
      ->and($this->gameObject->getComponent(MockBehavior::class))
      ->toEqual($mockBehaviour);
  });

  it('can set and get multiple components', function () {
    $mockBehaviour1 = $this->gameObject->addComponent(MockBehavior::class);
    $mockBehaviour2 = $this->gameObject->addComponent(MockBehavior::class);

    $gameObject = new GameObject($this->gameObjectName, $this->gameObjectTag);

    $receivedComponent = $gameObject->getComponent(MockBehavior::class);

    expect($mockBehaviour1)
      ->toBeInstanceOf(MockBehavior::class)
      ->and($mockBehaviour2)
      ->toBeInstanceOf(MockBehavior::class)
      ->and($receivedComponent)
      ->toEqual(null)
      ->and($gameObject->getComponentCount())
      ->toBeInt()
      ->toEqual(2)
      ->and($gameObject->getComponentIndex($mockBehaviour2))
      ->toBeInt()
      ->toEqual(-1)
      ->and($this->gameObject->getComponentCount())
      ->toBeInt()
      ->toEqual(4)
      ->and($this->gameObject->getComponentIndex($mockBehaviour2))
      ->toBeInt()
      ->toEqual(3);
  });

  it('can creat a pool of game objects', function () {
    $pool = GameObject::pool($this->gameObject, 10);
    expect($pool)
      ->toBeArray()
      ->toHaveLength(10)
      ->and($pool[0]->getName())
      ->toEqual($this->gameObjectName);
  });

  it('can update all components', function () {
    $mockBehaviour1 = $this->gameObject->addComponent(MockBehavior::class);
    $mockBehaviour2 = $this->gameObject->addComponent(MockBehavior::class);

    $this->gameObject->update();

    expect($mockBehaviour1->updateCount)->toEqual(1);
    expect($mockBehaviour2->updateCount)->toEqual(1);
  });

  it('can broadcast a message to all components', function () {
    $mockBehaviour1 = $this->gameObject->addComponent(MockBehavior::class);
    $mockBehaviour2 = $this->gameObject->addComponent(MockBehavior::class);

    $this->gameObject->broadcast('onUpdate');

    expect($mockBehaviour1->updateCount)->toEqual(1);
    expect($mockBehaviour2->updateCount)->toEqual(1);
  });

  it('starts components the first time an inactive game object is activated', function () {
    $this->gameObject->deactivate();
    $mockBehaviour = $this->gameObject->addComponent(MockBehavior::class);

    expect($mockBehaviour->startCount)->toEqual(0);

    $this->gameObject->activate();

    expect($mockBehaviour->startCount)->toEqual(1);
  });

  it('registers runtime-added collider components for objects already in the active scene', function () {
    resetGameObjectSingleton(SceneManager::class, 'instance');
    resetGameObjectSingleton(Physics::class, 'instance');

    $sceneManager = SceneManager::getInstance();
    $physics = Physics::getInstance();
    $scene = new class('Runtime Scene') extends Scene
    {
      public function awake(): void
      {
      }
    };
    $scene->loadSceneSettings([
      'screen_width' => 10,
      'screen_height' => 10,
    ]);

    $activeSceneNode = new ReflectionProperty(SceneManager::class, 'activeSceneNode');
    $activeSceneNode->setValue($sceneManager, new SceneNode($scene));

    $texturePath = getcwd() . '/tests/Mocks/Textures/test.texture';
    $mover = new GameObject('Mover', position: new Vector2(0, 0));
    $mover->setSpriteFromTexture(new Texture($texturePath), new Vector2(0, 0), new Vector2(1, 1));
    $wall = new GameObject('Wall', position: new Vector2(1, 0));
    $wall->setSpriteFromTexture(new Texture($texturePath), new Vector2(0, 0), new Vector2(1, 1));

    $scene->add($mover);
    $scene->add($wall);
    $scene->start();

    $rigidbody = $mover->addComponent(Rigidbody::class);
    $wallCollider = $wall->addComponent(Collider::class);

    expect($rigidbody)->toBeInstanceOf(Rigidbody::class)
      ->and($wallCollider)->toBeInstanceOf(Collider::class);

    $collisions = $physics->checkCollisions($rigidbody, new Vector2(1, 0));

    expect($collisions)
      ->toHaveCount(1)
      ->and($collisions[0]->getGameObject()->getName())->toEqual('Wall');
  });

  it('registers collider components that are added during another component start callback', function () {
    resetGameObjectSingleton(SceneManager::class, 'instance');
    resetGameObjectSingleton(Physics::class, 'instance');

    $sceneManager = SceneManager::getInstance();
    $physics = Physics::getInstance();
    $scene = new class('Runtime Scene') extends Scene
    {
      public function awake(): void
      {
      }
    };
    $scene->loadSceneSettings([
      'screen_width' => 10,
      'screen_height' => 10,
    ]);

    $activeSceneNode = new ReflectionProperty(SceneManager::class, 'activeSceneNode');
    $activeSceneNode->setValue($sceneManager, new SceneNode($scene));

    $texturePath = getcwd() . '/tests/Mocks/Textures/test.texture';
    $mover = new GameObject('Mover', position: new Vector2(0, 0));
    $mover->setSpriteFromTexture(new Texture($texturePath), new Vector2(0, 0), new Vector2(1, 1));
    $mover->addComponent(Rigidbody::class);

    $wall = new GameObject('Wall', position: new Vector2(1, 0));
    $wall->setSpriteFromTexture(new Texture($texturePath), new Vector2(0, 0), new Vector2(1, 1));
    $wall->addComponent(StartAddsColliderBehavior::class);

    $scene->add($mover);
    $scene->add($wall);
    $scene->start();

    $rigidbody = $mover->getComponent(Rigidbody::class);

    expect($rigidbody)->toBeInstanceOf(Rigidbody::class);

    $collisions = $physics->checkCollisions($rigidbody, new Vector2(1, 0));

    expect($collisions)
      ->toHaveCount(1)
      ->and($collisions[0]->getGameObject()->getName())->toEqual('Wall');
  });
});

function resetGameObjectSingleton(string $className, string $propertyName): void
{
  $reflection = new ReflectionClass($className);
  $property = $reflection->getProperty($propertyName);
  $property->setValue(null, null);
}
