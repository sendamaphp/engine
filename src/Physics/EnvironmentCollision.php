<?php

namespace Sendama\Engine\Physics;

use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Transform;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;

/**
 * Represents a collision against the static environment collision map.
 *
 * Scripts can differentiate environment collisions from dynamic collider collisions via `instanceof`.
 *
 * @template T
 * @extends Collision<T>
 */
class EnvironmentCollision extends Collision
{
    private GameObject $environmentGameObject;

    public function __construct(ColliderInterface $collider, Vector2 $point)
    {
        parent::__construct(
            $collider,
            [
                new ContactPoint(
                    Vector2::getClone($point),
                    $collider,
                    null,
                ),
            ],
        );

        $this->environmentGameObject = new GameObject(
            name: 'Environment',
            tag: 'Environment',
            position: Vector2::getClone($point),
        );
    }

    public function getGameObject(): GameObject
    {
        return $this->environmentGameObject;
    }

    public function getTransform(): Transform
    {
        return $this->environmentGameObject->getTransform();
    }
}
