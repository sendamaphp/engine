<?php

namespace Sendama\Engine\Core;

use InvalidArgumentException;

class Transform extends Component
{
  /**
   * @var array<Transform> $children
   */
  protected array $children = [];

  /**
   * Transform constructor.
   *
   * @param GameObject $gameObject The game object.
   * @param Vector2 $position The position of the transform.
   * @param Vector2 $scale The scale of the transform.
   * @param Vector2 $rotation The rotation of the transform.
   * @param Transform|null $parent The parent of the transform.
   */
  public function __construct(
    GameObject $gameObject,
    protected Vector2 $position = new Vector2(0, 0),
    protected Vector2 $scale    = new Vector2(0, 0),
    protected Vector2 $rotation = new Vector2(0, 0),
    protected ?Transform $parent = null
  )
  {
    parent::__construct($gameObject);
  }

  /**
   * Returns the position of the transform.
   *
   * @return Vector2 The position of the transform.
   */
  public function getPosition(): Vector2
  {
    return $this->position;
  }

  /**
   * Returns the scale of the transform.
   *
   * @return Vector2 The scale of the transform.
   */
  public function getScale(): Vector2
  {
    return $this->scale;
  }

  /**
   * Returns the rotation of the transform.
   *
   * @return Vector2 The rotation of the transform.
   */
  public function getRotation(): Vector2
  {
    return $this->rotation;
  }

  /**
   * Moves the transform in direction and distance specified by translation.
   *
   * @param Vector2 $translation The translation to apply to the transform.
   * @return void
   */
  public function translate(Vector2 $translation): void
  {
    $this->getRenderer()->erase();
    $this->position->add($translation);
  }

  /**
   * Sets the position of the transform.
   *
   * @param Vector2 $position The position of the transform.
   * @return void
   */
  public function setPosition(Vector2 $position): void
  {
    $this->position = $position;
  }

  /**
   * Returns the world-space position of the transform.
   *
   * @return Vector2
   */
  public function getWorldPosition(): Vector2
  {
    if ($this->parent === null) {
      return Vector2::getClone($this->position);
    }

    $parentPosition = $this->parent->getWorldPosition();

    return new Vector2(
      $parentPosition->getX() + $this->position->getX(),
      $parentPosition->getY() + $this->position->getY()
    );
  }

  /**
   * Sets the world-space position while keeping the current parent relationship.
   *
   * @param Vector2 $position
   * @return void
   */
  public function setWorldPosition(Vector2 $position): void
  {
    if ($this->parent === null) {
      $this->setPosition(Vector2::getClone($position));
      return;
    }

    $parentPosition = $this->parent->getWorldPosition();
    $this->position = new Vector2(
      $position->getX() - $parentPosition->getX(),
      $position->getY() - $parentPosition->getY()
    );
  }

  /**
   * Sets the rotation of the transform.
   *
   * @param Vector2 $rotation The rotation of the transform.
   * @return void
   */
  public function setRotation(Vector2 $rotation): void
  {
    $this->rotation = $rotation;
  }

  /**
   * Returns the world-space rotation of the transform.
   *
   * @return Vector2
   */
  public function getWorldRotation(): Vector2
  {
    if ($this->parent === null) {
      return Vector2::getClone($this->rotation);
    }

    $parentRotation = $this->parent->getWorldRotation();

    return new Vector2(
      $parentRotation->getX() + $this->rotation->getX(),
      $parentRotation->getY() + $this->rotation->getY()
    );
  }

  /**
   * Sets the world-space rotation while keeping the current parent relationship.
   *
   * @param Vector2 $rotation
   * @return void
   */
  public function setWorldRotation(Vector2 $rotation): void
  {
    if ($this->parent === null) {
      $this->setRotation(Vector2::getClone($rotation));
      return;
    }

    $parentRotation = $this->parent->getWorldRotation();
    $this->rotation = new Vector2(
      $rotation->getX() - $parentRotation->getX(),
      $rotation->getY() - $parentRotation->getY()
    );
  }

  /**
   * Sets the scale of the transform.
   *
   * @param Vector2 $scale The scale of the transform.
   * @return void
   */
  public function setScale(Vector2 $scale): void
  {
    $this->scale = $scale;
  }

  /**
   * Sets the parent of the transform.
   *
   * @param Transform|null $parent The parent of the transform.
   * @return void
   */
  public function setParent(?Transform $parent, bool $preserveWorldTransform = false): void
  {
    if ($parent === $this->parent) {
      return;
    }

    if ($parent === $this) {
      throw new InvalidArgumentException('A transform cannot be parented to itself.');
    }

    if ($parent !== null && $parent->isDescendantOf($this)) {
      throw new InvalidArgumentException('A transform cannot be parented to one of its descendants.');
    }

    $worldPosition = $preserveWorldTransform ? $this->getWorldPosition() : null;
    $worldRotation = $preserveWorldTransform ? $this->getWorldRotation() : null;

    if ($this->parent !== null) {
      $this->parent->removeChild($this);
    }

    $this->parent = $parent;

    if ($this->parent !== null) {
      $this->parent->addChild($this);
    }

    if ($preserveWorldTransform) {
      if ($worldPosition !== null) {
        $this->setWorldPosition($worldPosition);
      }

      if ($worldRotation !== null) {
        $this->setWorldRotation($worldRotation);
      }
    }
  }

  /**
   * Returns the parent of the transform.
   *
   * @return Transform|null The parent of the transform.
   */
  public function getParent(): ?Transform
  {
    return $this->parent;
  }

  /**
   * Returns whether this transform currently has a parent.
   *
   * @return bool
   */
  public function hasParent(): bool
  {
    return $this->parent !== null;
  }

  /**
   * Returns the direct child transforms.
   *
   * @return array<Transform>
   */
  public function getChildren(): array
  {
    return $this->children;
  }

  /**
   * Registers a child transform.
   *
   * @param Transform $child
   * @return void
   */
  private function addChild(Transform $child): void
  {
    foreach ($this->children as $existingChild) {
      if ($existingChild === $child) {
        return;
      }
    }

    $this->children[] = $child;
  }

  /**
   * Removes a child transform.
   *
   * @param Transform $child
   * @return void
   */
  private function removeChild(Transform $child): void
  {
    $this->children = array_values(
      array_filter(
        $this->children,
        static fn (Transform $existingChild): bool => $existingChild !== $child
      )
    );
  }

  /**
   * Returns whether this transform descends from the given ancestor.
   *
   * @param Transform $ancestor
   * @return bool
   */
  private function isDescendantOf(Transform $ancestor): bool
  {
    $currentParent = $this->parent;

    while ($currentParent !== null) {
      if ($currentParent === $ancestor) {
        return true;
      }

      $currentParent = $currentParent->getParent();
    }

    return false;
  }
}
