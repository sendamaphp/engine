<?php

namespace Sendama\Examples\Blasters\Scenes;

use Sendama\Engine\Core\Behaviours\CharacterMovement;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Scenes\AbstractScene;
use Sendama\Engine\Core\Sprite;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;
use Sendama\Examples\Blasters\Scripts\Game\LevelManager;
use Sendama\Examples\Blasters\Scripts\Player\WeaponManager;
use Sendama\Examples\Blasters\Scripts\PlayerController;

class Level01 extends AbstractScene
{
  /**
   * @inheritDoc
   */
  public function awake(): void
  {
    $this->environmentTileMapPath = 'Maps/level01';

    // Create the actors in the scene (i.e. game objects and ui elements)
    $levelManager = new GameObject('Level Manager');
    $player = new GameObject('Player Ship');

    // Set up the level manager
    $levelManager->addComponent(LevelManager::class);

    // Set up the player
    $screenHeight = $this->getSettings('screen_height');
    if (! is_int($screenHeight) )
    {
      $screenHeight = DEFAULT_SCREEN_HEIGHT;
    }

    $playerStartingX = 4;
    $playerStartingY = $screenHeight / 2;
    $playerTexture = new Texture('Textures/player.texture');
    $player->setSpriteFromTexture($playerTexture, new Vector2(0, 1), new Vector2(5, 3));
    $player->getTransform()->setPosition(new Vector2($playerStartingX, $playerStartingY));
    /**
     * @var CharacterMovement $playerMovementController
     */
    $playerMovementController = $player->addComponent(CharacterMovement::class);
    $playerMovementController->setSpeed(1);
    $playerMovementController->setSprites(
      new Sprite($playerTexture, ['x' => 0, 'y' => 1, 'width' => 5, 'height' => 3]),
      new Sprite($playerTexture, ['x' => 5, 'y' => 1, 'width' => 5, 'height' => 3]),
      new Sprite($playerTexture, ['x' => 10, 'y' => 1, 'width' => 5, 'height' => 3]),
      new Sprite($playerTexture, ['x' => 15, 'y' => 1, 'width' => 5, 'height' => 3]),
    );
    $player->addComponent(WeaponManager::class);
    $player->addComponent(PlayerController::class);

    // Add the game objects to the scene
    $this->add($levelManager);
    $this->add($player);
  }
}