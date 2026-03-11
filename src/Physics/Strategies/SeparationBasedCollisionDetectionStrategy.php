<?php

namespace Sendama\Engine\Physics\Strategies;

use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Strategies\AbstractCollisionDetectionStrategy;

/**
 * The class SeparationBasedCollisionDetectionStrategy.
 *
 * @package Sendama\Engine\Physics\Strategies
 *
 * @template T
 */
class SeparationBasedCollisionDetectionStrategy extends AbstractCollisionDetectionStrategy
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

    return Vector2::distance(
      $this->collider->getTransform()->getPosition(),
      $collider->getTransform()->getPosition()
    ) < 1;
  }
}
