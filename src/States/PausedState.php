<?php

namespace Sendama\Engine\States;

use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Input;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\UI\Menus\Menu;

/**
 * Represents the paused state.
 *
 * @package Sendama\Engine\States
 */
class PausedState extends GameState
{
  /**
   * @var Menu|null $menu The pause menu
   */
  protected ?Menu $menu = null;

  /**
   * @inheritDoc
   */
  public function update(): void
  {
    if (Input::isKeyDown($this->game->getSettings('pause_key'))) {
      $this->resume();
    }

    $this->menu?->update();
  }

  /**
   * @inheritDoc
   */
  public function render(): void
  {
    if ($this->menu) {
      // Display the pause menu
      $this->menu->render();
    } else {
      $this->renderDefaultPauseText();
    }
  }

  /**
   * @inheritDoc
   */
  public function resume(): void
  {
    Console::clear();
    $this->sceneManager->getActiveScene()?->resume();
    if ($sceneState = $this->game->getState('scene')) {
      $this->game->setState($sceneState);
    }
  }

  /**
   * @inheritDoc
   */
  public function suspend(): void
  {
    // Do nothing
  }

  /**
   * Renders the default pause text.
   *
   * @return void
   */
  private function renderDefaultPauseText(): void
  {
    $activeScene = $this->sceneManager->getActiveScene();
    $promptText = 'PAUSED';
    [$left, $top, $width, $height] = $this->getPauseBounds($activeScene);
    $leftMargin = $left + (int)floor(($width - strlen($promptText)) / 2);
    $topMargin = $top + (int)floor(($height - 1) / 2);
    Console::write($promptText, $leftMargin, $topMargin);
  }

  /**
   * Returns the bounds that should be used for pause overlay placement.
   *
   * @param SceneInterface|null $activeScene The active scene.
   * @return array{0: int, 1: int, 2: int, 3: int}
   */
  private function getPauseBounds(?SceneInterface $activeScene): array
  {
    $defaultWidth = (int)($activeScene?->getSettings('screen_width') ?? $this->game->getSettings('screen_width') ?? DEFAULT_SCREEN_WIDTH);
    $defaultHeight = (int)($activeScene?->getSettings('screen_height') ?? $this->game->getSettings('screen_height') ?? DEFAULT_SCREEN_HEIGHT);

    if (!$activeScene) {
      return [1, 1, $defaultWidth, $defaultHeight];
    }

    $minX = null;
    $minY = null;
    $maxX = null;
    $maxY = null;

    foreach ($activeScene->getWorldSpace()->toArray() as $rowIndex => $row) {
      foreach ($row as $columnIndex => $cell) {
        if ($cell === ' ' || $cell === '' || $cell === 0) {
          continue;
        }

        $logicalX = $columnIndex + 1;
        $logicalY = $rowIndex + 1;
        $minX = $minX === null ? $logicalX : min($minX, $logicalX);
        $minY = $minY === null ? $logicalY : min($minY, $logicalY);
        $maxX = $maxX === null ? $logicalX : max($maxX, $logicalX);
        $maxY = $maxY === null ? $logicalY : max($maxY, $logicalY);
      }
    }

    foreach ($activeScene->getUIElements() as $uiElement) {
      if (!$uiElement->isActive()) {
        continue;
      }

      $position = $uiElement->getPosition();
      $size = $uiElement->getSize();
      $logicalX = max(1, $position->getX());
      $logicalY = max(1, $position->getY());
      $logicalMaxX = $logicalX + max(0, $size->getX() - 1);
      $logicalMaxY = $logicalY + max(0, $size->getY() - 1);

      $minX = $minX === null ? $logicalX : min($minX, $logicalX);
      $minY = $minY === null ? $logicalY : min($minY, $logicalY);
      $maxX = $maxX === null ? $logicalMaxX : max($maxX, $logicalMaxX);
      $maxY = $maxY === null ? $logicalMaxY : max($maxY, $logicalMaxY);
    }

    if ($minX === null || $minY === null || $maxX === null || $maxY === null) {
      return [1, 1, $defaultWidth, $defaultHeight];
    }

    return [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
  }
}
