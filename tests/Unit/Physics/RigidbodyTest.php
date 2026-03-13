<?php

use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Behaviours\Behaviour;
use Sendama\Engine\Core\Grid;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\Physics\CharacterController;
use Sendama\Engine\Physics\Collider;
use Sendama\Engine\Physics\EnvironmentCollision;
use Sendama\Engine\Physics\Interfaces\CollisionInterface;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\Physics\PhysicsMaterial;
use Sendama\Engine\Physics\Rigidbody;

if (!class_exists(RigidbodyCollisionProbe::class)) {
  class RigidbodyCollisionProbe extends Behaviour
  {
    public array $events = [];
    public array $collisionTypes = [];

    public function onCollisionEnter(CollisionInterface $collision): void
    {
      $this->events[] = 'enter:' . $collision->getGameObject()->getName();
      $this->collisionTypes[] = get_class($collision);
    }

    public function onCollisionExit(CollisionInterface $collision): void
    {
      $this->events[] = 'exit:' . $collision->getGameObject()->getName();
    }

    public function onCollisionStay(CollisionInterface $collision): void
    {
      $this->events[] = 'stay:' . $collision->getGameObject()->getName();
    }
  }
}

beforeEach(function () {
  resetPhysicsSingleton(Physics::class, 'instance');
  $this->physics = Physics::getInstance();
  $this->physics->init();
  $this->texturePath = getcwd() . '/tests/Mocks/Textures/test.texture';
  Console::refreshLayout(
    DEFAULT_SCREEN_WIDTH,
    DEFAULT_SCREEN_HEIGHT,
    new Rect(new Vector2(1, 1), new Vector2(DEFAULT_SCREEN_WIDTH, DEFAULT_SCREEN_HEIGHT)),
    clearWhenChanged: false
  );
});

it('constrains movePosition against solid colliders', function () {
  [$mover, $rigidbody] = createPhysicsObject('Mover', $this->texturePath, new Vector2(1, 1), Rigidbody::class);
  [, $wallCollider] = createPhysicsObject('Wall', $this->texturePath, new Vector2(3, 1), Collider::class);

  $this->physics->addCollider($rigidbody);
  $this->physics->addCollider($wallCollider);

  $rigidbody->movePosition(new Vector2(5, 1));

  ob_start();
  $rigidbody->simulate();
  ob_end_clean();

  expect($mover->getTransform()->getPosition()->getX())->toBe(2)
    ->and($mover->getTransform()->getPosition()->getY())->toBe(1);
});

it('moves position and rotation together on the next simulation step', function () {
  [$mover, $rigidbody] = createPhysicsObject('Mover', $this->texturePath, new Vector2(0, 0), Rigidbody::class);

  $rigidbody->movePositionAndRotation(new Vector2(3, 4), new Vector2(90, 0));

  ob_start();
  $rigidbody->simulate();
  ob_end_clean();

  expect($mover->getTransform()->getPosition()->getX())->toBe(3)
    ->and($mover->getTransform()->getPosition()->getY())->toBe(4)
    ->and($mover->getTransform()->getRotation()->getX())->toBe(90)
    ->and($mover->getTransform()->getRotation()->getY())->toBe(0);
});

it('applies accumulated force as constrained movement', function () {
  [$mover, $rigidbody] = createPhysicsObject('Mover', $this->texturePath, new Vector2(0, 0), Rigidbody::class);

  $rigidbody->addForce(new Vector2(3600, 0));

  ob_start();
  $rigidbody->simulate();
  ob_end_clean();

  expect($mover->getTransform()->getPosition()->getX())->toBe(1)
    ->and($mover->getTransform()->getPosition()->getY())->toBe(0);
});

it('rotates relative force by the current rigidbody rotation', function () {
  [$mover, $rigidbody] = createPhysicsObject('Mover', $this->texturePath, new Vector2(0, 0), Rigidbody::class);
  $mover->getTransform()->setRotation(new Vector2(90, 0));

  $rigidbody->addRelativeForceX(3600);

  ob_start();
  $rigidbody->simulate();
  ob_end_clean();

  expect($mover->getTransform()->getPosition()->getX())->toBe(0)
    ->and($mover->getTransform()->getPosition()->getY())->toBe(1);
});

it('adds torque when force is applied away from the rigidbody center', function () {
  [$mover, $rigidbody] = createPhysicsObject('Mover', $this->texturePath, new Vector2(0, 0), Rigidbody::class);

  $rigidbody->addForceAtPosition(new Vector2(0, 3600), new Vector2(1, 0));

  ob_start();
  $rigidbody->simulate();
  ob_end_clean();

  expect($mover->getTransform()->getRotation()->getX())->toBe(1)
    ->and($mover->getTransform()->getRotation()->getY())->toBe(0);
});

it('applies physics materials when bouncing off a solid collider', function () {
  [$mover, $rigidbody] = createPhysicsObject('Mover', $this->texturePath, new Vector2(0, 0), Rigidbody::class);
  [, $wallCollider] = createPhysicsObject('Wall', $this->texturePath, new Vector2(1, 0), Collider::class);

  $rigidbody->setMaterial(new PhysicsMaterial(1.0, 1.0));
  $wallCollider->setMaterial(new PhysicsMaterial(1.0, 1.0));
  $rigidbody->setVelocity(new Vector2(60, 60));

  $this->physics->addCollider($rigidbody);
  $this->physics->addCollider($wallCollider);

  ob_start();
  $rigidbody->simulate();
  ob_end_clean();

  expect($mover->getTransform()->getPosition()->getX())->toBe(0)
    ->and($mover->getTransform()->getPosition()->getY())->toBe(0)
    ->and($rigidbody->getVelocity()->getX())->toBe(-60)
    ->and($rigidbody->getVelocity()->getY())->toBe(0);
});

it('dispatches mirrored collision enter events for rigidbody movement', function () {
  [$bullet, $rigidbody] = createPhysicsObject('Bullet', $this->texturePath, new Vector2(0, 0), Rigidbody::class);
  [$enemy, $enemyController] = createPhysicsObject('Enemy', $this->texturePath, new Vector2(1, 0), CharacterController::class);

  $bulletProbe = $bullet->addComponent(RigidbodyCollisionProbe::class);
  $enemyProbe = $enemy->addComponent(RigidbodyCollisionProbe::class);

  expect($bulletProbe)->toBeInstanceOf(RigidbodyCollisionProbe::class);
  expect($enemyProbe)->toBeInstanceOf(RigidbodyCollisionProbe::class);

  $this->physics->addCollider($rigidbody);
  $this->physics->addCollider($enemyController);

  $rigidbody->movePosition(new Vector2(1, 0));

  ob_start();
  $rigidbody->simulate();
  ob_end_clean();

  expect($bulletProbe->events)->toBe(['enter:Enemy'])
    ->and($enemyProbe->events)->toBe(['enter:Bullet']);
});

it('restores buffered console content when movePosition advances a rigidbody', function () {
  Console::write('-----', 2, 2);

  [$mover, $rigidbody] = createPhysicsObject('Mover', $this->texturePath, new Vector2(2, 2), Rigidbody::class);

  ob_start();
  $mover->render();
  ob_end_clean();

  ob_start();
  $mover->render();
  ob_end_clean();

  $rigidbody->movePosition(new Vector2(4, 2));

  ob_start();
  $rigidbody->simulate();
  $output = ob_get_clean();

  $offset = Console::getRenderOffset();
  $row = $offset->getY() + 1;
  $column = $offset->getX() + 1;

  expect($output)->toContain("\033[{$row};{$column}H-")
    ->and($mover->getTransform()->getPosition()->getX())->toBe(4)
    ->and($mover->getTransform()->getPosition()->getY())->toBe(2);
});

it('cleans up stale render bounds when movePosition is given a mutated live transform position', function () {
  Console::write('-----', 2, 2);

  [$mover, $rigidbody] = createPhysicsObject('Mover', $this->texturePath, new Vector2(2, 2), Rigidbody::class);

  ob_start();
  $mover->render();
  ob_end_clean();

  $position = $mover->getTransform()->getPosition();
  $position->add(new Vector2(2, 0));
  $rigidbody->movePosition($position);

  ob_start();
  $rigidbody->simulate();
  $mover->render();
  $output = ob_get_clean();

  $offset = Console::getRenderOffset();
  $row = $offset->getY() + 1;
  $oldColumn = $offset->getX() + 1;
  $newColumn = $offset->getX() + 3;

  expect($output)
    ->toContain("\033[{$row};{$oldColumn}H-")
    ->toContain("\033[{$row};{$newColumn}H>")
    ->and($mover->getTransform()->getPosition()->getX())->toBe(4)
    ->and($mover->getTransform()->getPosition()->getY())->toBe(2);
});

it('still resolves collisions when movePosition is given a mutated live transform position', function () {
  [$bullet, $rigidbody] = createPhysicsObject('Bullet', $this->texturePath, new Vector2(0, 0), Rigidbody::class);
  [$enemy, $enemyController] = createPhysicsObject('Enemy', $this->texturePath, new Vector2(1, 0), CharacterController::class);

  $bulletProbe = $bullet->addComponent(RigidbodyCollisionProbe::class);
  $enemyProbe = $enemy->addComponent(RigidbodyCollisionProbe::class);

  $this->physics->addCollider($rigidbody);
  $this->physics->addCollider($enemyController);

  $rigidbody->start();

  $position = $bullet->getTransform()->getPosition();
  $position->add(new Vector2(1, 0));
  $rigidbody->movePosition($position);

  ob_start();
  $rigidbody->simulate();
  ob_end_clean();

  expect($bullet->getTransform()->getPosition()->getX())->toBe(0)
    ->and($bulletProbe->events)->toBe(['enter:Enemy'])
    ->and($enemyProbe->events)->toBe(['enter:Bullet']);
});

it('dispatches environment collisions for static collision maps', function () {
  $staticMap = new Grid(10, 10, 0);
  $staticMap->set(1, 0, 1);
  $this->physics->loadStaticCollisionMap($staticMap);

  [$bullet, $rigidbody] = createPhysicsObject('Bullet', $this->texturePath, new Vector2(0, 0), Rigidbody::class);
  $bulletProbe = $bullet->addComponent(RigidbodyCollisionProbe::class);

  $rigidbody->movePosition(new Vector2(1, 0));

  ob_start();
  $rigidbody->simulate();
  ob_end_clean();

  expect($bullet->getTransform()->getPosition()->getX())->toBe(0)
    ->and($bulletProbe->events)->toBe(['enter:Environment'])
    ->and($bulletProbe->collisionTypes)->toBe([EnvironmentCollision::class]);
});

/**
 * @param class-string<Collider|Rigidbody> $componentClass
 * @return array{0: GameObject, 1: Collider|Rigidbody}
 */
function createPhysicsObject(string $name, string $texturePath, Vector2 $position, string $componentClass): array
{
  $gameObject = new GameObject($name, position: $position);
  $gameObject->setSpriteFromTexture(
    new \Sendama\Engine\Core\Texture($texturePath),
    new Vector2(0, 0),
    new Vector2(1, 1)
  );

  $component = $gameObject->addComponent($componentClass);

  expect($component)->toBeInstanceOf($componentClass);

  return [$gameObject, $component];
}

function resetPhysicsSingleton(string $className, string $propertyName): void
{
  $reflection = new ReflectionClass($className);
  $reflection->getProperty($propertyName)->setValue(null, null);
}
