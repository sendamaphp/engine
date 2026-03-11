<?php

namespace Sendama\Engine\Physics\Strategies;

use Sendama\Engine\Debug\Debug;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Strategies\AbstractCollisionDetectionStrategy;

/**
 * The class BasicCollisionDetectionStrategy.
 *
 * @package Sendama\Engine\Physics\Strategies
 * @template T
 */
class BasicCollisionDetectionStrategy extends AbstractCollisionDetectionStrategy
{

  /**
   * @inheritDoc
   *
   * @param ColliderInterface<T> $collider The collider to check if it is touching.
   */
  public function isTouching(ColliderInterface $collider): bool
  {
    if ($this->collider === $collider || $this->collider->getGameObject() === $collider->getGameObject()) {
      return false;
    }

    if ($this->collider->getTransform()->getPosition()->getX() !== $collider->getTransform()->getPosition()->getX()) {
      return false;
    }

    if ($this->collider->getTransform()->getPosition()->getY() !== $collider->getTransform()->getPosition()->getY()) {
      return false;
    }

    return true;
  }
}
