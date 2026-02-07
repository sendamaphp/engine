<?php

namespace Sendama\Engine\Core\Interfaces;

use Sendama\Engine\Core\Component;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Sprite;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\UI\Interfaces\UIElementInterface;

/**
 * The GameObjectInterface
 *
 * @package Sendama\Engine\Core\Interfaces
 */
interface GameObjectInterface extends CanCompare, CanResume, CanUpdate, CanStart, CanRender, CanActivate, FixedUpdateInterface
{
  /**
   * Returns the scene of the game object.
   *
   * @return SceneInterface The scene of the game object.
   */
  public function getScene(): SceneInterface;

  /**
   * Sets the sprite of the game object from a texture.
   *
   * @param Texture|array{path: string, width: ?int, height: ?int}|string $texture The path to the sprite texture.
   * @param Vector2 $position The position of the sprite
   * @param Vector2 $size
   * @return void
   */
  public function setSpriteFromTexture(Texture|array|string $texture, Vector2 $position, Vector2 $size): void;

  /**
   * Sets the sprite of the game object
   *
   * @param Sprite $sprite The sprite to set.
   * @return void
   */
  public function setSprite(Sprite $sprite): void;

  /**
   * Return the sprite set in the object's renderer.
   *
   * @return Sprite The sprite set in the object's renderer.
   */
  public function getSprite(): Sprite;

  /**
   * Gets a reference to a component of type T on the specified GameObject.
   *
   * @param class-string<ComponentInterface> $componentClass
   * @return ComponentInterface|null The component of type T that is attached to the GameObject or null if it is not attached.
   */
  public function getComponent(string $componentClass): ?ComponentInterface;

  /**
   * Gets references to all components of type T on the specified GameObject. If the type is not specified then all
   * the components are returned.
   *
   * @param class-string<ComponentInterface>|null $componentClass The class of the component to get. If null, all
   * components are returned.
   * @return array<ComponentInterface> The components of type T that are attached to the GameObject.
   */
  public function getComponents(?string $componentClass = null): array;

  /**
   * Gets a reference to the UI element of the specified class.
   *
   * @param class-string<UIElementInterface> $uiElementClass The class of the UI element to get.
   * @return UIElementInterface|null The UI element of the specified class or null if it is not attached.
   */
  public function getUIElement(string $uiElementClass): ?UIElementInterface;

  /**
   * Gets references to all UI elements of the specified class. If the class is not specified then all the UI elements
   * are returned.
   *
   * @param class-string<UIElementInterface>|null $uiElementClass The class of the UI element to get. If null, all UI
   * elements are returned.
   * @return array<UIElementInterface> The UI elements of the specified class that are attached to the GameObject.
   */
  public function getUIElements(?string $uiElementClass = null): array;

  /**
   * Adds a component class of type $componentType to the game object.
   *
   * @template T
   * @param class-string<T> $componentType
   *
   * @return T The component that was added.
   */
  public function addComponent(string $componentType): Component;

  /**
   * Creates a pool of game objects of the specified size. This is useful for creating a pool of bullets, enemies, etc.
   *
   * @param GameObjectInterface $gameObject The game object to pool.
   * @param int $size The size of the pool.
   * @return array<GameObjectInterface> The pool of game objects.
   */
  public static function pool(GameObjectInterface $gameObject, int $size): array;

  /**
   * Finds the game object with the specified name.
   *
   * @param string $gameObjectName The name of the game object to find.
   * @return GameObjectInterface|null The game object with the specified name.
   */
  public static function find(string $gameObjectName): ?GameObjectInterface;

  /**
   * Finds the game object with the specified tag.
   *
   * @param string $gameObjectTag The tag of the game object to find.
   * @return GameObjectInterface|null The game object with the specified tag.
   */
  public static function findWithTag(string $gameObjectTag): ?GameObjectInterface;

  /**
   * Finds all game objects with the specified name.
   *
   * @param string $gameObjectName The name of the game object to find.
   * @return array<GameObjectInterface> The game objects with the specified name.
   */
  public static function findAll(string $gameObjectName): array;

  /**
   * Finds all game objects with the specified tag.
   *
   * @param string $gameObjectTag The tag of the game object to find.
   * @return array<GameObjectInterface> The game objects with the specified tag.
   */
  public static function findAllWithTag(string $gameObjectTag): array;
}