<?php

namespace Sendama\Engine\Physics;

use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;

/**
 * Represents a contact point between two colliders.
 *
 * @template T
 */
readonly class ContactPoint
{
  /**
   * ContactPoint constructor.
   *
   * @param Vector2 $point The point of contact.
   * @param ColliderInterface<T> $thisCollider The collider of the game object that this contact point belongs to.
   * @param ColliderInterface<T>|null $otherCollider The collider of the other game object that this contact point belongs to.
   */
  public function __construct(
    protected Vector2 $point,
    protected ColliderInterface $thisCollider,
    protected ?ColliderInterface $otherCollider,
  )
  {
  }

  /**
   * Get the point of contact.
   *
   * @return Vector2 The point of contact.
   */
  public function getPoint(): Vector2
  {
    return $this->point;
  }

  /**
   * Get the collider of the game object that this contact point belongs to.
   *
   * @return ColliderInterface<T> The collider of the game object that this contact point belongs to.
   */
  public function getThisCollider(): ColliderInterface
  {
    return $this->thisCollider;
  }

  /**
   * Get the collider of the other game object that this contact point belongs to.
   *
   * @return ColliderInterface<T>|null The collider of the other game object that this contact point belongs to.
   */
  public function getOtherCollider(): ?ColliderInterface
  {
    return $this->otherCollider;
  }

  /**
   * Get the normal of the contact point.
   *
   * @return Vector2 The normal of the contact point.
   */
  public function getNormal(): Vector2
  {
    if ($this->otherCollider === null) {
      return Vector2::difference($this->point, $this->thisCollider->getTransform()->getPosition())->getNormalized();
    }

    $otherPosition = $this->otherCollider->getTransform()->getPosition();
    $thisPosition = $this->thisCollider->getTransform()->getPosition();

    return Vector2::difference($otherPosition, $thisPosition)->getNormalized();
  }

  /**
   * Get the separation of the contact point.
   *
   * @return float The separation of the contact point.
   */
  public function getSeparation(): float
  {
    if ($this->otherCollider === null) {
      return Vector2::distance($this->thisCollider->getTransform()->getPosition(), $this->point);
    }

    return Vector2::distance(
      $this->thisCollider->getTransform()->getPosition(),
      $this->otherCollider->getTransform()->getPosition()
    );
  }
}
