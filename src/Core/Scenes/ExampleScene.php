<?php

namespace Sendama\Engine\Core\Scenes;

use Sendama\Engine\Core\Behaviours\CharacterMovement;
use Sendama\Engine\Core\Behaviours\SimpleQuitListener;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Sprite;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;

/**
 * Class ExampleScene is an example scene.
 *
 * @package Sendama\Engine\Core\Scenes
 */
class ExampleScene extends AbstractScene
{
  /**
   * @inheritDoc
   */
  public function awake(): void
  {
    $this->environmentTileMapPath = 'Maps/example';

    # Create the actors in the scene (i.e. game objects and ui elements)
    $levelManager = new GameObject('LevelManager');
    $player = new GameObject('Player', position: new Vector2(4, 10));

    # Set up the level manager
    $levelManager->addComponent(SimpleQuitListener::class);

    # Set up the player
    $playerTexture = new Texture('Textures/player.texture');
    $player->setSpriteFromTexture($playerTexture, Vector2::zero(), Vector2::one());
    /**
     * @var CharacterMovement $characterMovement
     */
    $characterMovement = $player->addComponent(CharacterMovement::class);
    $characterMovement->setSpeed(1);
    $characterMovement->setSprites(
      new Sprite($playerTexture, ['x' => 0, 'y' => 0, 'width' => 1, 'height' => 1]),
      new Sprite($playerTexture, ['x' => 1, 'y' => 0, 'width' => 1, 'height' => 1]),
      new Sprite($playerTexture, ['x' => 2, 'y' => 0, 'width' => 1, 'height' => 1]),
      new Sprite($playerTexture, ['x' => 3, 'y' => 0, 'width' => 1, 'height' => 1]),
    );

    # Add the player to the scene
    $this->add($levelManager);
    $this->add($player);
  }
}