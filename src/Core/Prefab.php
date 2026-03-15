<?php

namespace Sendama\Engine\Core;

use Sendama\Engine\Core\Interfaces\GameObjectInterface;
use Sendama\Engine\Core\Interfaces\PrefabCallbackInterface;
use Sendama\Engine\Core\Interfaces\PrefabInterface;
use Sendama\Engine\Core\Scenes\SceneManager;

/**
 * Class Prefab is a class that represents a prefab in the engine.
 *
 * @package Sendama\Engine\Core
 */
class Prefab implements PrefabInterface
{
  /**
   * Prefab constructor.
   *
   * @param string $name The name of the prefab.
   */
  protected ?GameObjectInterface $gameObject = null;

  /**
   * Prefab constructor.
   *
   * @param string $name The name of the prefab.
   */
  public function __construct(protected string $name)
  {
  }

  /**
   * @inheritDoc
   */
  public function getName(): string
  {
    return $this->name;
  }

  /**
   * @inheritDoc
   */
  public function setName(string $name): void
  {
    $this->name = $name;
  }

  /**
   * @inheritDoc
   */
  public function serialize(): string
  {
    return json_encode(['game_object' => $this->gameObject]);
  }

  /**
   * @inheritDoc
   */
  public function unserialize(string $data): void
  {
    $data = json_decode($data, true);
    $this->gameObject = $data['game_object'] ?? null;
  }

  /**
   * @inheritDoc
   */
  public function fabricate(PrefabCallbackInterface $callback): void
  {
    $this->gameObject = $callback();
  }

  /**
   * @inheritDoc
   */
  public function instantiate(): GameObject
  {
    return clone $this->gameObject;
  }

  /**
   * @inheritDoc
   */
  public function load(string $path): void
  {
    $this->gameObject = SceneManager::loadPrefabFromPath($path);
  }

  public function __serialize(): array
  {
    return [
      'game_object' => $this->gameObject
    ];
  }

  public function __unserialize(array $data): void
  {
    $this->gameObject = $data['game_object'];
  }

  /**
   * @inheritDoc
   */
  public function pool(int $size): array
  {
    return GameObject::pool($this->gameObject, $size);
  }
}
