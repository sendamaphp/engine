<?php

namespace Sendama\Engine\States;

use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Input;
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
    $promptText = 'PAUSED';
    $leftMargin = intval(($this->game->getSettings('screen_width') / 2) - (strlen($promptText) / 2));
    $topMargin = intval(($this->game->getSettings('screen_height') / 2) - 1);
    Console::cursor()->moveTo($leftMargin, $topMargin);
    echo $promptText;
  }
}