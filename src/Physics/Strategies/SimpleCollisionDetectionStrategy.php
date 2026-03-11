<?php

namespace Sendama\Engine\Physics\Strategies;

use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Strategies\AbstractCollisionDetectionStrategy;

/**
 * Class SimpleCollisionDetectionStrategy. A simple collision detection strategy.
 *
 * @package Sendama\Engine\Physics\Strategies
 */
class SimpleCollisionDetectionStrategy extends AbstractCollisionDetectionStrategy
{
  /**
   * @inheritDoc
   */
  public function isTouching(ColliderInterface $collider): bool
  {
    if ($this->collider === $collider || $this->collider->getGameObject() === $collider->getGameObject()) {
      return false;
    }

    return $this->collider->getBoundingBox()->overlaps($collider->getBoundingBox());
  }
}
