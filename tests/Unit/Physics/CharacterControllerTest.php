<?php

use Sendama\Engine\Core\Behaviours\Behaviour;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Grid;
use Sendama\Engine\Core\Sprite;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Physics\CharacterController;
use Sendama\Engine\Physics\Collider;
use Sendama\Engine\Physics\EnvironmentCollision;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Interfaces\CollisionInterface;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\Util\Path;

if (!class_exists(CharacterControllerCollisionProbe::class)) {
  class CharacterControllerCollisionProbe extends Behaviour
  {
    public array $collisionTypes = [];
    public array $collisionTargets = [];
    public array $triggerTargets = [];

    public function onCollisionEnter(CollisionInterface $hit): void
    {
      $this->collisionTypes[] = get_class($hit);
      $this->collisionTargets[] = [
        'self' => $this->getGameObject()->getTag(),
        'other' => $hit->getGameObject()->getTag(),
      ];
    }

    public function onTriggerEnter(ColliderInterface $other): void
    {
      $this->triggerTargets[] = [
        'self' => $this->getGameObject()->getTag(),
        'other' => $other->getGameObject()->getTag(),
      ];
    }
  }
}

beforeEach(function () {
  Physics::getInstance()->init();

  $testsDirectory = dirname(__DIR__, 2);
  $texturePath = Path::join($testsDirectory, 'Mocks', 'Textures', 'test.texture');

  $this->makeSprite = fn() => new Sprite(
    new Texture($texturePath),
    ['x' => 0, 'y' => 0, 'width' => 1, 'height' => 1]
  );

  $this->makeCollider = function (
    string $name,
    Vector2 $position,
    string $componentClass = Collider::class,
    bool $isTrigger = false,
    ?string $tag = null,
  ): array {
    $gameObject = new GameObject($name, $tag);
    $gameObject->setSprite(($this->makeSprite)());
    $gameObject->getTransform()->setPosition($position);

    $collider = $gameObject->addComponent($componentClass);
    assert($collider instanceof ColliderInterface);

    $collider->setTrigger($isTrigger);
    Physics::getInstance()->addCollider($collider);

    return [$gameObject, $collider];
  };
});

it('checks projected bounds against only the colliders that overlap the motion path', function () {
  [, $controller] = ($this->makeCollider)('Player', new Vector2(0, 0), CharacterController::class);
  [, $blocker] = ($this->makeCollider)('Wall', new Vector2(1, 0));
  [, $distant] = ($this->makeCollider)('Crate', new Vector2(10, 0));

  $collisions = Physics::getInstance()->checkCollisions($controller, new Vector2(1, 0));

  expect($collisions)
    ->toHaveCount(1)
    ->and($collisions[0]->getGameObject()->getName())->toBe('Wall')
    ->and($collisions[0]->getContact(0)?->getOtherCollider())->toBe($blocker)
    ->and($collisions[0]->getGameObject()->getName())->not()->toBe($distant->getGameObject()->getName());
});

it('ignores inactive colliders that remain registered for object pool reuse', function () {
  [, $controller] = ($this->makeCollider)('Player', new Vector2(0, 0), CharacterController::class);
  [$pooledBullet] = ($this->makeCollider)('Bullet', new Vector2(1, 0));

  $pooledBullet->deactivate();

  $collisions = Physics::getInstance()->checkCollisions($controller, new Vector2(1, 0));

  expect($collisions)->toBe([]);
});

it('emits mirrored trigger callbacks with the other collider identity for both participants', function () {
  [$player, $controller] = ($this->makeCollider)('Player', new Vector2(0, 0), CharacterController::class, false, 'Player');
  [$coin, $triggerCollider] = ($this->makeCollider)('Coin', new Vector2(1, 0), Collider::class, true, 'Pickup');

  $playerProbe = $player->addComponent(CharacterControllerCollisionProbe::class);
  $coinProbe = $coin->addComponent(CharacterControllerCollisionProbe::class);

  ob_start();
  $controller->move(new Vector2(1, 0));
  ob_end_clean();

  expect($playerProbe->collisionTargets)->toBe([[
    'self' => 'Player',
    'other' => 'Pickup',
  ]])
    ->and($coinProbe->collisionTargets)->toBe([[
      'self' => 'Pickup',
      'other' => 'Player',
    ]])
    ->and($playerProbe->triggerTargets)->toBe([[
      'self' => 'Player',
      'other' => 'Pickup',
    ]])
    ->and($coinProbe->triggerTargets)->toBe([[
      'self' => 'Pickup',
      'other' => 'Player',
    ]])
    ->and($player->getTransform()->getPosition()->getX())->toBe(1)
    ->and($triggerCollider->isTrigger())->toBeTrue();
});

it('stops a character controller before it moves into a solid collider', function () {
  [$player, $controller] = ($this->makeCollider)('Player', new Vector2(0, 0), CharacterController::class);
  ($this->makeCollider)('Wall', new Vector2(1, 0));

  ob_start();
  $controller->move(new Vector2(1, 0));
  ob_end_clean();

  expect($player->getTransform()->getPosition()->getX())->toBe(0)
    ->and($player->getTransform()->getPosition()->getY())->toBe(0);
});

it('allows movement through trigger colliders', function () {
  [$player, $controller] = ($this->makeCollider)('Player', new Vector2(0, 0), CharacterController::class);
  ($this->makeCollider)('Coin', new Vector2(1, 0), Collider::class, true);

  ob_start();
  $controller->move(new Vector2(1, 0));
  ob_end_clean();

  expect($player->getTransform()->getPosition()->getX())->toBe(1)
    ->and($player->getTransform()->getPosition()->getY())->toBe(0);
});

it('detects vertical collisions even when the sprite comes from an offset sprite-sheet frame', function () {
  [$player, $controller] = ($this->makeCollider)('Player', new Vector2(5, 0), CharacterController::class);
  [, $apple] = ($this->makeCollider)('Apple', new Vector2(5, 1));

  $player->setSprite(new Sprite(
    new Texture(getcwd() . '/tests/Mocks/Textures/test.texture'),
    ['x' => 2, 'y' => 0, 'width' => 1, 'height' => 1]
  ));

  $collisions = Physics::getInstance()->checkCollisions($controller, new Vector2(0, 1));

  expect($collisions)
    ->toHaveCount(1)
    ->and($collisions[0]->getContact(0)?->getOtherCollider())->toBe($apple);
});

it('ignores self while still detecting different colliders on objects with the same name', function () {
  [, $firstCollider] = ($this->makeCollider)('Enemy', new Vector2(3, 3));
  [, $secondCollider] = ($this->makeCollider)('Enemy', new Vector2(3, 3));

  expect($firstCollider->isTouching($firstCollider))->toBeFalse()
    ->and($firstCollider->isTouching($secondCollider))->toBeTrue();
});

it('creates environment collisions for static collision map cells', function () {
  $staticMap = new Grid(10, 10, 0);
  $staticMap->set(1, 0, 1);
  Physics::getInstance()->loadStaticCollisionMap($staticMap);

  [$player, $controller] = ($this->makeCollider)('Player', new Vector2(0, 0), CharacterController::class);
  $probe = $player->addComponent(CharacterControllerCollisionProbe::class);

  ob_start();
  $controller->move(new Vector2(1, 0));
  ob_end_clean();

  expect($player->getTransform()->getPosition()->getX())->toBe(0)
    ->and($probe->collisionTypes)->toBe([EnvironmentCollision::class]);
});
