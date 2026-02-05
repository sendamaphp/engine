<?php

namespace Sendama\Engine\States;

use Sendama\Engine\Core\Enumerations\SettingsKey;
use Sendama\Engine\IO\Input;

/**
 * Class SceneState. Represents a scene state of the game.
 *
 * @package Sendama\Engine\States
 */
class SceneState extends GameState
{
  /**
   * @inheritDoc
   */
  public function render(): void
  {
    $this->sceneManager->render();
  }

  /**
   * @inheritDoc
   */
  public function update(): void
  {
    if (Input::isKeyDown($this->game->getSettings(SettingsKey::PAUSE_KEY->value))) {
      $this->suspend();
    }

    $this->sceneManager->update();
    $this->notificationsManager->update();
  }

  /**
   * @inheritDoc
   */
  public function suspend(): void
  {
    if ($pauseState = $this->game->getState('paused')) {
      $this->game->setState($pauseState);
    }
  }

  /**
   * @inheritDoc
   */
  public function resume(): void
  {
    // Do nothing
  }
}