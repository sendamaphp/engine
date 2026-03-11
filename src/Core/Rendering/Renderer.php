<?php

namespace Sendama\Engine\Core\Rendering;

use Sendama\Engine\Core\Component;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Interfaces\CanRender;
use Sendama\Engine\Core\Sprite;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Console\Cursor;

class Renderer extends Component implements CanRender
{
  /**
   * @var array{x: int, y: int, width: int, height: int}|null
   */
  protected ?array $lastRenderedBounds = null;
  /**
   * The console cursor.
   *
   * @var Cursor
   */
  protected Cursor $consoleCursor;

  /**
   * Renderer constructor.
   *
   * @param GameObject $gameObject
   * @param Sprite|null $sprite
   */
  public function __construct(GameObject $gameObject, protected ?Sprite $sprite = null)
  {
    parent::__construct($gameObject);

    $this->consoleCursor = Console::cursor();
  }

  /**
   * Returns the sprite of the renderer.
   *
   * @return Sprite|null The sprite of the renderer.
   */
  public final function getSprite(): ?Sprite
  {
    return $this->sprite;
  }

  public final function setSprite(?Sprite $sprite): void
  {
    $this->sprite = $sprite;
  }

  /**
   * @inheritDoc
   */
  public final function onUpdate(): void
  {
    // Do nothing.
  }

  /**
   * @inheritDoc
   */
  public final function render(): void
  {
    $this->renderAt();
  }

  /**
   * @inheritDoc
   */
  public final function renderAt(?int $x = null, ?int $y = null): void
  {
    if (!$this->sprite) {
      return;
    }

    $xOffset = $this->getGameObject()->getTransform()->getPosition()->getX() + ($x ?? 0);
    $yOffset = $this->getGameObject()->getTransform()->getPosition()->getY() + ($y ?? 0);
    $spriteBufferedImage = $this->sprite->getBufferedImage();

    for ($row = 0; $row < $this->sprite->getRect()->getHeight(); $row++) {
      Console::write(
        implode($spriteBufferedImage[$row] ?? []),
        $xOffset,
        $yOffset + $row
      );
    }

    $this->lastRenderedBounds = [
      'x' => $xOffset,
      'y' => $yOffset,
      'width' => $this->sprite->getRect()->getWidth(),
      'height' => $this->sprite->getRect()->getHeight(),
    ];
  }

  /**
   * @inheritDoc
   */
  public final function erase(): void
  {
    $this->eraseAt();
  }

  /**
   * @inheritDoc
   */
  public final function eraseAt(?int $x = null, ?int $y = null): void
  {
    if (!$this->sprite || !$this->lastRenderedBounds) {
      return;
    }

    $xOffset = $this->lastRenderedBounds['x'];
    $yOffset = $this->lastRenderedBounds['y'];
    $width = $this->lastRenderedBounds['width'];
    $height = $this->lastRenderedBounds['height'];

    for ($row = 0; $row < $height; $row++) {
      Console::write(
        $this->getBackgroundRowSegment($xOffset, $yOffset + $row, $width),
        $xOffset,
        $yOffset + $row
      );
    }

    $this->lastRenderedBounds = null;
  }

  /**
   * Returns the static world-space row segment underneath the sprite.
   *
   * @param int $xOffset
   * @param int $yOffset
   * @param int $width
   * @return string
   */
  private function getBackgroundRowSegment(int $xOffset, int $yOffset, int $width): string
  {
    $worldRows = SceneManager::getInstance()->getActiveScene()?->getWorldSpace()->toArray() ?? [];
    $worldY = max(0, $yOffset - 1);
    $startX = max(0, $xOffset - 1);
    $buffer = '';

    for ($column = 0; $column < $width; $column++) {
      $worldX = $startX + $column;
      $buffer .= $worldRows[$worldY][$worldX] ?? ' ';
    }

    return $buffer;
  }
}
