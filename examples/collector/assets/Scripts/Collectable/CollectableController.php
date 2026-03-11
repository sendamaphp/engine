<?php

namespace Sendama\Examples\Collector\Scripts\Collectable;

use Override;
use Sendama\Engine\Core\Behaviours\Attributes\SerializeField;
use Sendama\Engine\Core\Behaviours\Behaviour;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Physics\Collider;
use Sendama\Engine\Physics\Interfaces\CollisionInterface;
use Sendama\Examples\Collector\Scripts\Enumerations\Name;
use Sendama\Examples\Collector\Scripts\Game\LevelManager;

/**
 * Class CollectableController is responsible for controlling a collectable.
 *
 * @package Sendama\Examples\Collector\Scripts\Collectable
 */
class CollectableController extends Behaviour
{
  public ?LevelManager $levelManager = null;

  #[SerializeField]
  protected int $value = 1;

  private const int MIN_X_POSITION = 2;
  private const int MIN_Y_POSITION = 2;
  private const int MAX_X_POSITION = 78;
  private const int MAX_Y_POSITION = 24;

  #[Override]
  public function onStart(): void
  {
    $collider = $this->getComponent(Collider::class);

    if (!$collider) {
      $collider = $this->getGameObject()->addComponent(Collider::class);
    }

    if ($collider instanceof Collider) {
      $collider->setTrigger(true);
    }

    $this->randomizePosition();

    if ($levelManagerGO = GameObject::find(Name::LEVEL_MANAGER->value))
    {
      $this->levelManager = $levelManagerGO->getComponent(LevelManager::class);
    }
  }

  /**
   * @inheritDoc
   */
  public function onCollisionEnter(CollisionInterface $collision): void
  {
    if ($collision->getGameObject()->getName() === Name::PLAYER->value)
    {
      // Increase the score
      $this->levelManager->incrementScore();
    }

    $this->randomizePosition();
  }

  /**
   * Randomizes the position of the collectable.
   */
  protected function randomizePosition(): void
  {
    $this->getGameObject()->erase();
    $this
      ->getTransform()
      ->setPosition(
        new Vector2(
          rand(self::MIN_X_POSITION, self::MAX_X_POSITION),
          rand(self::MIN_Y_POSITION, self::MAX_Y_POSITION)
        )
      );
  }
}
