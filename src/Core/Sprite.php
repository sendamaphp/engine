<?php

namespace Sendama\Engine\Core;

use Sendama\Engine\Debug\Debug;
use Serializable;

/**
 * Class Sprite
 *
 * @package Sendama\Engine\Core
 */
class Sprite implements Serializable
{
  protected Rect $rect;

  /**
   * Sprite constructor. Creates a new sprite.
   *
   * @param Texture $texture The texture of the sprite.
   * @param Rect|array{x: int, y: int, width: int, height: int} $rect The rectangle of the sprite.
   * @param Vector2 $pivot The pivot of the sprite.
   */
  public function __construct(protected Texture $texture, Rect|array $rect, protected Vector2 $pivot = new Vector2(0, 0))
  {
    $this->rect = is_array($rect) ? Rect::fromArray($rect) : $rect;
  }

  /**
   * Returns the texture of the sprite.
   *
   * @return Texture The texture of the sprite.
   */
  public function getTexture(): Texture
  {
    return $this->texture;
  }

  /**
   * Sets the texture of the sprite.
   *
   * @param Texture $texture The texture to set.
   * @return void
   */
  public function setTexture(Texture $texture): void
  {
    $this->texture = $texture;
  }

  /**
   * Returns the rectangle of the sprite.
   *
   * @return Rect The rectangle of the sprite.
   */
  public function getRect(): Rect
  {
    return $this->rect;
  }

  /**
   * Sets the rectangle of the sprite.
   *
   * @param Rect $rect The rectangle to set.
   * @return void
   */
  public function setRect(Rect $rect): void
  {
    $this->rect = $rect;
  }

  /**
   * Returns the pivot of the sprite.
   *
   * @return Vector2 The pivot of the sprite.
   */
  public function getPivot(): Vector2
  {
    return $this->pivot;
  }

  /**
   * Sets the pivot of the sprite.
   *
   * @param Vector2 $pivot The pivot to set.
   * @return void
   */
  public function setPivot(Vector2 $pivot): void
  {
    $this->pivot = $pivot;
  }

  /**
   * Returns the buffered image of the sprite.
   *
   * @return string[] The buffered image of the sprite.
   */
  public function getBufferedImage(): array
  {
    $buffer = [];
    $rectX = $this->rect->getX();
    $rectY = $this->rect->getY();
    $width = $this->rect->getWidth();
    $height = $this->rect->getHeight();
    $pixels = $this->texture->getPixels();

    for ($y = 0; $y < $height; $y++) {
      $buffer[$y] = [];
      for ($x = 0; $x < $width; $x++) {
        $row = $rectY + $y;
        $column = $rectX + $x;

        if (!isset($pixels[$row])) {
          $pixels[$row] = [];
          Debug::warn("Pixel row $row does not exist.");
        }

        if (!isset($pixels[$row][$column])) {
          $pixels[$row][$column] = ' ';
          Debug::warn("Pixel column $column does not exist - "  . json_encode($pixels[$row], JSON_PRETTY_PRINT));
        }

        if (!isset($buffer[$y])) {
          $buffer[$y] = [];
          Debug::warn("Buffered image row $row does not exist.");
        }

        $buffer[$y][$x] = $pixels[$row][$column];
      }
    }

    return $buffer;
  }

  /**
   * @inheritDoc
   */
  public function serialize(): string
  {
    return json_encode(['texture' => $this->texture, 'rect' => $this->rect, 'pivot' => $this->pivot,]);
  }

  /**
   * @inheritDoc
   */
  public function unserialize(string $data): void
  {
    $data = json_decode($data, true);
    $this->texture = $data['texture'];
    $this->rect = $data['rect'];
    $this->pivot = $data['pivot'];
  }

  /**
   * @return array{texture: Texture, rect: Rect, pivot: Vector2}
   */
  public function __serialize(): array
  {
    return ['texture' => $this->texture, 'rect' => $this->rect, 'pivot' => $this->pivot,];
  }

  /**
   * @param array{texture: Texture, rect: Rect, pivot: Vector2} $data
   */
  public function __unserialize(array $data): void
  {
    $this->texture = $data['texture'];
    $this->rect = $data['rect'];
    $this->pivot = $data['pivot'];
  }
}