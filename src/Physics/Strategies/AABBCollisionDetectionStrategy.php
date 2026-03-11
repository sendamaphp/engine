<?php

namespace Sendama\Engine\Physics\Strategies;

use Sendama\Engine\Debug\Debug;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;

/**
 * Class AABBCollisionDetectionStrategy implements the AABB collision detection strategy.
 *
 * @package Sendama\Engine\Physics\Strategies
 */
class AABBCollisionDetectionStrategy extends AbstractCollisionDetectionStrategy
{

  /**
   * @inheritDoc
   *
   * @template T
   * @param ColliderInterface<T> $collider The collider to check if it is touching.
   */
  public function isTouching(ColliderInterface $collider): bool
  {
    if ($this->collider === $collider || $this->collider->getGameObject() === $collider->getGameObject())
    {
      return false;
    }

    if ($this->collider->getBoundingBox()->overlaps($collider->getBoundingBox()))
    {
      Debug::log(__CLASS__ . ' detected a collision between ' . $this->collider->getGameObject()->getName() . ' and ' . $collider->getGameObject()->getName() . '.');
      Debug::log($this->collider->getGameObject()->getName() . ' is at ' . $this->collider->getTransform()->getPosition());
      Debug::log($collider->getGameObject()->getName() . ' is at ' . $collider->getTransform()->getPosition());
      return true;
    }

    return false;
  }
}
