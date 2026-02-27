<?php

namespace Sendama\Examples\Collector\Scenes;

use Sendama\Engine\Core\Behaviours\CharacterMovement;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Scenes\AbstractScene;
use Sendama\Engine\Core\Sprite;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Physics\CharacterController;
use Sendama\Engine\Physics\Collider;
use Sendama\Engine\UI\Label\Label;
use Sendama\Examples\Collector\Scripts\Collectable\CollectableController;
use Sendama\Examples\Collector\Scripts\Enumerations\Name;
use Sendama\Examples\Collector\Scripts\Game\LevelManager;

/**
 * Class Level01. This is the first level of the game.
 *
 * @package Sendama\Examples\Collector\Scenes
 */
class Level01 extends AbstractScene
{

  /**
   * @inheritDoc
   */
  public function awake(): void
  {
    $this->environmentTileMapPath = 'Maps/level01';

    // Create the actors in the scene (i.e. game objects and ui elements)
    $levelManager = new GameObject(Name::LEVEL_MANAGER->value);
    $player = new GameObject(Name::PLAYER->value);
    $apple = new GameObject(Name::APPLE->value);

    // GUI Elements
    $collectedLabel = new Label($this, 'Collected Label', new Vector2(0, 27), new Vector2(15, 1));
    $collected = 0;
    $collectedLabel->setText(sprintf("%-12s %03d", 'Collected: ',$collected));

    $stepsLabel = new Label($this, 'Steps Label', new Vector2(65, 27), new Vector2(15, 1));
    $stepsLabel->setText(sprintf("%-9s %06d", 'Steps: ', 0));

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
    $player->getTransform()->setPosition(new Vector2($playerStartingX, $playerStartingY));
    /**
     * @var CharacterMovement $playerMovementController
     */
    $playerMovementController = $player->addComponent(CharacterMovement::class);
    $player->addComponent(CharacterController::class);
    $playerMovementController->setSprites(
      new Sprite($playerTexture, ['x' => 0, 'y' => 0, 'width' => 1, 'height' => 1]),
      new Sprite($playerTexture, ['x' => 1, 'y' => 0, 'width' => 1, 'height' => 1]),
      new Sprite($playerTexture, ['x' => 2, 'y' => 0, 'width' => 1, 'height' => 1]),
      new Sprite($playerTexture, ['x' => 3, 'y' => 0, 'width' => 1, 'height' => 1]),
    );
    $playerMovementController->setSpeed(1);
    $player->setSpriteFromTexture($playerTexture, Vector2::zero(), Vector2::one());

    // Set up the apple
    $appleTexture = new Texture('Textures/apple.texture');
    $apple->addComponent(CollectableController::class);
    $apple->addComponent(Collider::class);
    $apple->setSpriteFromTexture($appleTexture, Vector2::zero(), Vector2::one());

    // Add the game objects to the scene
    $this->add($levelManager);
    $this->add($player);
    $this->add($apple);
    $this->add($collectedLabel);
    $this->add($stepsLabel);
  }
}