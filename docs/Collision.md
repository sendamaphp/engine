# Collision Detection Guide

This guide shows the basic collision workflow in Sendama and how to swap collision strategies when you need different behavior.

## Quick Setup

1. Add a collider component to the game object before you add it to a scene.
2. Give the object a sprite, because collider bounds are derived from the sprite rectangle.
3. Move the object with `CharacterController` if you want movement to stop on solid collisions.
4. Handle collision callbacks in a behaviour.

```php
<?php

use Sendama\Engine\Core\Behaviours\Behaviour;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Physics\CharacterController;
use Sendama\Engine\Physics\Interfaces\CollisionInterface;

final class PlayerCollisionHandler extends Behaviour
{
  public function onCollisionEnter(CollisionInterface $collision): void
  {
    echo 'Hit ' . $collision->getGameObject()->getName();
  }
}

$player = new GameObject('Player');
$player->setSpriteFromTexture('Textures/player.texture', Vector2::zero(), Vector2::one());
$player->addComponent(CharacterController::class);
$player->addComponent(PlayerCollisionHandler::class);
```

## Built-in Strategies

- `AABBCollisionDetectionStrategy`: compares bounding boxes. This is the default for `Collider`, `CharacterController`, and `Rigidbody`.
- `SimpleCollisionDetectionStrategy`: currently uses the same bounding-box overlap check, but without the extra debug logging.
- `BasicCollisionDetectionStrategy`: only reports a hit when two colliders share the exact same position.
- `SeparationBasedCollisionDetectionStrategy`: reports a hit when the distance between colliders is less than `1`.

## Changing a Strategy

Use `setCollisionDetectionStrategy()` on the collider component instance itself. In practice that means a `Collider`, `CharacterController`, or `Rigidbody`, because those are the components that implement `ColliderInterface`.

```php
<?php

use Sendama\Engine\Physics\CharacterController;
use Sendama\Engine\Physics\Strategies\BasicCollisionDetectionStrategy;

/** @var CharacterController $controller */
$controller = $player->addComponent(CharacterController::class);
$controller->setCollisionDetectionStrategy(
  new BasicCollisionDetectionStrategy($controller)
);
```

## Solid vs Trigger Colliders

- Solid colliders block `CharacterController::move()`.
- Trigger colliders do not block movement.
- To make a pickup or checkpoint a trigger, call `setTrigger(true)`.

```php
$coinCollider->setTrigger(true);
```

At the moment, the `CharacterController` path dispatches `onCollisionEnter()` and `onCollisionStay()` for both solid and trigger contacts, so those are the safest callbacks to implement for gameplay reactions.

## Manual Collision Checks

If you want to query collisions yourself:

```php
<?php

use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Physics\Physics;

$collisions = Physics::getInstance()->checkCollisions($playerCollider, new Vector2(1, 0));

if ($collisions !== []) {
  // The move would overlap another collider.
}
```

`checkCollisions()` tests the collider's projected bounds after applying the motion vector, which is useful for predicting whether a move will be blocked before translating the object.

## Notes

- Colliders are registered with physics when the game object is added to the scene.
- If you add a collider after the object is already running, register it with `Physics::getInstance()->addCollider(...)` yourself.
- Collision bounds come from the game object's sprite rect, so a missing sprite means unreliable collision bounds.
