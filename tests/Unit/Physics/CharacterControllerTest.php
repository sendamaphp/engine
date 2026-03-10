<?php

use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Sprite;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Physics\CharacterController;
use Sendama\Engine\Physics\Collider;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\Util\Path;

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
  ): array {
    $gameObject = new GameObject($name);
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

it('ignores self while still detecting different colliders on objects with the same name', function () {
  [, $firstCollider] = ($this->makeCollider)('Enemy', new Vector2(3, 3));
  [, $secondCollider] = ($this->makeCollider)('Enemy', new Vector2(3, 3));

  expect($firstCollider->isTouching($firstCollider))->toBeFalse()
    ->and($firstCollider->isTouching($secondCollider))->toBeTrue();
});
