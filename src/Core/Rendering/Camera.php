<?php

namespace Sendama\Engine\Core\Rendering;

use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Rendering\Interfaces\CameraInterface;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Transform;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Console\Cursor;

/**
 * Represents a camera.
 *
 * @package Sendama\Engine\Core\Rendering
 */
class Camera implements CameraInterface
{
  /**
   * @var Rect The viewport.
   */
  protected Rect $viewport;

  /**
   * @var Transform|null The target.
   */
  protected ?Transform $target = null;

  /**
   * @var Cursor The cursor.
   */
  protected Cursor $cursor;

  /**
   * Camera constructor.
   *
   * @param Vector2 $offset The offset.
   * @param float $zoom The zoom.
   * @param float $rotation The rotation.
   */
  public function __construct(protected SceneInterface $scene, protected Vector2 $offset = new Vector2(0, 0), protected float $zoom = 1.0, protected float $rotation = 0.0, protected int $width = DEFAULT_SCREEN_WIDTH, protected int $height = DEFAULT_SCREEN_HEIGHT)
  {
    $this->viewport = new Rect($this->offset, new Vector2($width, $height));
    $this->cursor = Console::cursor();
  }

  /**
   * @inheritDoc
   */
  public function getOffset(): Vector2
  {
    return $this->offset;
  }

  /**
   * @inheritDoc
   */
  public function setOffset(Vector2 $offset): void
  {
    $this->offset = $offset;
  }

  /**
   * @inheritDoc
   */
  public function getZoom(): float
  {
    return $this->zoom;
  }

  /**
   * @inheritDoc
   */
  public function setZoom(float $zoom): void
  {
    $this->zoom = $zoom;
  }

  /**
   * @inheritDoc
   */
  public function getRotation(): float
  {
    return $this->rotation;
  }

  /**
   * @inheritDoc
   */
  public function setRotation(float $rotation): void
  {
    $this->rotation = $rotation;
  }

  /**
   * @inheritDoc
   */
  public function getViewport(): Rect
  {
    return $this->viewport;
  }

  /**
   * @inheritDoc
   */
  public function setViewport(Rect $viewport): void
  {
    $this->viewport = $viewport;
  }

  /**
   * @inheritDoc
   */
  public function render(): void
  {
    /** @var GameObject $gameObject */
    foreach ($this->scene->getRootGameObjects() as $gameObject) {
      if ($gameObject->isActive()) {
        $gameObject->render();
      }
    }

    foreach ($this->scene->getUIElements() as $uiElement) {
      if ($uiElement->isActive()) {
        $uiElement->render();
      }
    }

  }

  /**
   * @inheritDoc
   */
  public function renderAt(?int $x = null, ?int $y = null): void
  {
    foreach ($this->scene->getRootGameObjects() as $gameObject) {
      if ($gameObject->isActive()) {
        $gameObject->renderAt($x, $y);
      }
    }

    foreach ($this->scene->getUIElements() as $uiElement) {
      if ($uiElement->isActive()) {
        $uiElement->renderAt($x, $y);
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function erase(): void
  {
    foreach ($this->scene->getRootGameObjects() as $gameObject) {
      if ($gameObject->isActive()) {
        $gameObject->erase();
      }
    }

    foreach ($this->scene->getUIElements() as $uiElement) {
      if ($uiElement->isActive()) {
        $uiElement->erase();
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function eraseAt(?int $x = null, ?int $y = null): void
  {
    foreach ($this->scene->getRootGameObjects() as $gameObject) {
      if ($gameObject->isActive()) {
        $gameObject->eraseAt($x, $y);
      }
    }

    foreach ($this->scene->getUIElements() as $uiElement) {
      if ($uiElement->isActive()) {
        $uiElement->eraseAt($x, $y);
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function clearScreen(): void
  {
    Console::clear();
  }

  /**
   * @inheritDoc
   */
  public function update(): void
  {
    // TODO: Implement update() method.
  }

  /**
   * @inheritDoc
   */
  public function renderWorldSpace(): void
  {
    Console::clear();
    Console::writeLines(
      explode("\n", (string)$this->scene->getWorldSpace()),
      1,
      1
    );
  }
}
