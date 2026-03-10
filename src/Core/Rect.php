<?php

namespace Sendama\Engine\Core;

use InvalidArgumentException;
use Sendama\Engine\Core\Traits\DimensionTrait;
use Sendama\Engine\Core\Traits\PositionTrait;

/**
 * The rect class.
 */
class Rect
{
  use PositionTrait;
  use DimensionTrait;

  /**
   * Create a new instance of the rect.
   *
   * @param Vector2 $position The position.
   * @param Vector2 $size The size.
   */
  public function __construct(Vector2 $position = new Vector2(0, 0), Vector2 $size = new Vector2(1, 1))
  {
    $this->setX($position->getX());
    $this->setY($position->getY());
    $this->setWidth($size->getX());
    $this->setHeight($size->getY());
  }

  /**
   * Create a new instance of the rect from the specified array.
   *
   * @param array{x: int, y: int, width: int, height: int} $array The array.
   * @return self
   */
  public static function fromArray(array $array): self
  {
    if (count($array) !== 4) {
      throw new InvalidArgumentException("The array must contain exactly 4 elements.");
    }

    if (array_is_list($array)) {
      [$x, $y, $width, $height] = $array;
    } else {
      extract($array);
    }

    return new Rect(new Vector2($x, $y), new Vector2($width, $height));
  }

  /**
   * Get the position of the rect.
   *
   * @return Vector2 The position of the rect.
   */
  public function getPosition(): Vector2
  {
    return new Vector2($this->getX(), $this->getY());
  }

  /**
   * Get the size of the rect.
   *
   * @return Vector2 The size of the rect.
   */
  public function getSize(): Vector2
  {
    return new Vector2($this->getWidth(), $this->getHeight());
  }

  /**
   * Set the values of the rect.
   *
   * @param Vector2|null $position The position.
   * @param Vector2|null $size The size.
   * @return void
   */
  public function set(?Vector2 $position = null, ?Vector2 $size = null): void
  {
    $this->setX($position->getX() ?? $this->x);
    $this->setY($position->getY() ?? $this->y);
    $this->setWidth($size->getX() ?? $this->width);
    $this->setHeight($size->getY() ?? $this->height);
  }

  /**
   * Check if the rect overlaps with the specified rect.
   *
   * @param Rect $other The other rect.
   * @return bool True if the rect overlaps with the other rect, false otherwise.
   */
  public function overlaps(Rect $other): bool
  {
    return
      $this->getX() < $other->getX() + $other->getWidth() &&
      $this->getX() + $this->getWidth() > $other->getX() &&
      $this->getY() < $other->getY() + $other->getHeight() &&
      $this->getY() + $this->getHeight() > $other->getY();
  }

  /**
   * Returns a translated copy of the rect.
   *
   * @param Vector2 $translation The translation to apply.
   * @return self
   */
  public function translated(Vector2 $translation): self
  {
    return new self(
      new Vector2(
        $this->getX() + $translation->getX(),
        $this->getY() + $translation->getY()
      ),
      $this->getSize(),
    );
  }

  /**
   * Check if the rect contains the specified point.
   *
   * @param Vector2 $point The point to check.
   * @return bool True if the rect contains the point, false otherwise.
   */
  public function contains(Vector2 $point): bool
  {
    return $point->getX() >= $this->getX() && $point->getX() <= $this->getX() + $this->getWidth() && $point->getY() >= $this->getY() && $point->getY() <= $this->getY() + $this->getHeight();
  }
}
