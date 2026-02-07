<?php

namespace Sendama\Engine\Core;

use InvalidArgumentException;
use Sendama\Engine\Core\Interfaces\CanCompare;
use Sendama\Engine\Core\Interfaces\CanEquate;
use Sendama\Engine\Core\Interfaces\ComponentInterface;
use Sendama\Engine\Core\Interfaces\GameObjectInterface;
use Sendama\Engine\Core\Rendering\Renderer;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\UI\Interfaces\UIElementInterface;

/**
 * Class GameObject. This class represents a game object in the engine.
 *
 * @package Sendama\Engine\Core
 */
class GameObject implements GameObjectInterface
{
    /**
     * @var bool $active Whether the game object is active or not.
     */
    protected bool $active = true;
    /**
     * @var string $hash The hash of the game object.
     */
    protected string $hash = '';
    /**
     * @var ComponentInterface[] $components The components attached to the game object.
     */
    protected array $components = [];
    /**
     * @var UIElementInterface[] $uiElements The UI elements attached to the game object.
     */
    protected array $uiElements = [];
    /**
     * @var Transform $transform The transform of the game object.
     */
    protected Transform $transform;
    /**
     * @var Renderer $renderer The renderer for the game object.
     */
    protected Renderer $renderer;

    /**
     * GameObject constructor.
     *
     * @param string $name The name of the game object.
     * @param string|null $tag The tag of the game object.
     * @param Vector2 $position The position of the game object.
     * @param Vector2 $rotation The rotation of the game object.
     * @param Vector2 $scale The scale of the game object.
     * @param Sprite|null $sprite The sprite of the game object.
     */
    public function __construct(protected string $name, protected ?string $tag = null, protected Vector2 $position = new Vector2(), protected Vector2 $rotation = new Vector2(), protected Vector2 $scale = new Vector2(), protected ?Sprite $sprite = null)
    {
        $this->hash = md5(__CLASS__) . '-' . uniqid($this->name, true);
        $this->transform = new Transform($this, $position, $scale, $rotation);
        $this->renderer = new Renderer($this, $sprite);

        $this->components[] = $this->transform;
        $this->components[] = $this->renderer;
    }

    /**
     * Clones the original game object and returns the clone.
     *
     * @param GameObject $original The original game object to clone.
     * @param Vector2|null $position The position of the clone.
     * @param Vector2|null $rotation The rotation of the clone.
     * @param Vector2|null $scale The scale of the clone.
     * @param Transform|null $parent The parent of the clone.
     * @return GameObject The clone of the original game object.
     */
    public static function instantiate(GameObject $original, ?Vector2 $position = null, ?Vector2 $rotation = null, ?Vector2 $scale = null, ?Transform $parent = null): GameObject
    {
        $clone = clone $original;

        if ($position) {
            $clone->transform->setPosition($position);
        }

        if ($rotation) {
            $clone->transform->setRotation($rotation);
        }

        if ($scale) {
            $clone->transform->setScale($scale);
        }

        if ($parent) {
            $clone->transform->setParent($parent);
        }

        SceneManager::getInstance()->getActiveScene()->add($clone);

        return $clone;
    }

    /**
     * Destroys the game object after the specified delay. This removes the game object from the scene.
     *
     * @param GameObject $gameObject The game object to destroy.
     * @param float $delay The delay before destroying the game object.
     * @return void
     */
    public static function destroy(GameObject $gameObject, float $delay = 0.0): void
    {
        if ($activeScene = SceneManager::getInstance()->getActiveScene()) {
            // Wait for the delay before destroying the game object.

            $activeScene->remove($gameObject);
            unset($gameObject);
        }
    }

    /**
     * @inheritDoc
     */
    public static function pool(GameObjectInterface $gameObject, int $size): array
    {
        $pool = [];

        for ($i = 0; $i < $size; ++$i) {
            $pool[] = clone $gameObject;
        }

        return $pool;
    }

    /**
     * @inheritDoc
     */
    public static function find(string $gameObjectName): ?GameObjectInterface
    {
        if ($activeScene = SceneManager::getInstance()->getActiveScene()) {
            foreach ($activeScene->getRootGameObjects() as $gameObject) {
                if ($gameObject->getName() === $gameObjectName) {
                    return $gameObject;
                }
            }
        }

        return null;
    }

    /**
     * Returns the name of the game object.
     *
     * @return string The name of the game object.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public static function findWithTag(string $gameObjectTag): ?GameObjectInterface
    {
        if ($activeScene = SceneManager::getInstance()->getActiveScene()) {
            foreach ($activeScene->getRootGameObjects() as $gameObject) {
                if ($gameObject->getTag() === $gameObjectTag) {
                    return $gameObject;
                }
            }
        }

        return null;
    }

    /**
     * Returns the tag of the game object.
     *
     * @return string The tag of the game object.
     */
    public function getTag(): string
    {
        return $this->tag ?? '';
    }

    /**
     * @inheritDoc
     */
    public static function findAll(string $gameObjectName): array
    {
        $gameObjects = [];

        if ($activeScene = SceneManager::getInstance()->getActiveScene()) {
            foreach ($activeScene->getRootGameObjects() as $gameObject) {
                if ($gameObject->getName() === $gameObjectName) {
                    $gameObjects[] = $gameObject;
                }
            }
        }

        return $gameObjects;
    }

    /**
     * @inheritDoc
     */
    public static function findAllWithTag(string $gameObjectTag): array
    {
        $gameObjects = [];

        if ($activeScene = SceneManager::getInstance()->getActiveScene()) {
            foreach ($activeScene->getRootGameObjects() as $gameObject) {
                if ($gameObject->getTag() === $gameObjectTag) {
                    $gameObjects[] = $gameObject;
                }
            }
        }

        return $gameObjects;
    }

    /**
     * @inheritDoc
     */
    public function getScene(): SceneInterface
    {
        return SceneManager::getInstance()->getActiveScene();
    }

    /**
     * @return void
     */
    public function __clone(): void
    {
        $this->hash = md5(__CLASS__) . '-' . uniqid($this->name, true);

        $this->transform = clone $this->transform;
        $this->renderer = clone $this->renderer;

        if ($this->sprite) {
            $this->sprite = clone $this->sprite;
        }
    }

    /**
     * Returns the transform of the game object.
     *
     * @return Transform The transform of the game object.
     */
    public function getTransform(): Transform
    {
        return $this->transform;
    }

    /**
     * @inheritDoc
     */
    public function greaterThan(CanCompare $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * @inheritDoc
     */
    public function compareTo(CanCompare $other): int
    {
        if (!$other instanceof GameObject) {
            throw new InvalidArgumentException('Cannot compare a game object with a non-game object.');
        }

        return strcmp($this->getHash(), $other->getHash());
    }

    /**
     * @inheritDoc
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @inheritDoc
     */
    public function greaterThanOrEqual(CanCompare $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    /**
     * @inheritDoc
     */
    public function lessThan(CanCompare $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    /**
     * @inheritDoc
     */
    public function lessThanOrEqual(CanCompare $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    /**
     * @inheritDoc
     */
    public function notEquals(CanEquate $equatable): bool
    {
        return !$this->equals($equatable);
    }

    /**
     * @inheritDoc
     */
    public function equals(CanEquate $equatable): bool
    {
        return $this->getHash() === $equatable->getHash();
    }

    /**
     * @inheritDoc
     */
    public function renderAt(?int $x = null, ?int $y = null): void
    {
        if ($this->isActive() && $this->renderer->isEnabled()) {
            $this->renderer->renderAt($x, $y);
        }
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @inheritDoc
     */
    public function eraseAt(?int $x = null, ?int $y = null): void
    {
        if ($this->isActive() && $this->renderer->isEnabled()) {
            $this->renderer->eraseAt($x, $y);
        }
    }

    /**
     * @inheritDoc
     */
    public function resume(): void
    {
        if ($this->isActive()) {
            foreach ($this->components as $component) {
                if ($component->isEnabled()) {
                    $component->resume();
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function suspend(): void
    {
        if ($this->isActive()) {
            foreach ($this->components as $component) {
                if ($component->isEnabled()) {
                    $component->suspend();
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function start(): void
    {
        if ($this->isActive()) {
            foreach ($this->components as $component) {
                if ($component->isEnabled()) {
                    $component->start();
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function stop(): void
    {
        if ($this->isActive()) {
            foreach ($this->components as $component) {
                if ($component->isEnabled()) {
                    $component->stop();
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function fixedUpdate(): void
    {
        if ($this->isActive()) {
            foreach ($this->components as $component) {
                if ($component->isEnabled()) {
                    $component->fixedUpdate();
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function update(): void
    {
        if ($this->isActive()) {
            foreach ($this->components as $component) {
                if ($component->isEnabled()) {
                    $component->update();
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function activate(): void
    {
        $this->active = true;
        $this->getRenderer()->render();
    }

    /**
     * @inheritDoc
     */
    public function render(): void
    {
        if ($this->isActive() && $this->renderer->isEnabled()) {
            $this->renderer->render();
        }
    }

    /**
     * Returns the renderer for the game object.
     *
     * @return Renderer The renderer for the game object.
     */
    public function getRenderer(): Renderer
    {
        return $this->renderer;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(): void
    {
        $this->active = false;
        $this->getRenderer()->erase();
    }

    /**
     * @inheritDoc
     */
    public function erase(): void
    {
        if ($this->isActive() && $this->renderer->isEnabled()) {
            $this->renderer->erase();
        }
    }

    /**
     * Calls the method named $methodName on every component in this game object and its children.
     *
     * @param string $methodName The name of the method to call.
     * @param array<string, mixed> $args The arguments to pass to the method.
     * @return void
     */
    public function broadcast(string $methodName, array $args = []): void
    {
        foreach ($this->components as $component) {
            if (method_exists($component, $methodName)) {
                $component->$methodName(...$args);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function addComponent(string $componentType): Component
    {
        if (!class_exists($componentType)) {
            throw new InvalidArgumentException('The component type ' . $componentType . ' does not exist.');
        }

        if (!is_subclass_of($componentType, Component::class)) {
            throw new InvalidArgumentException('The component type ' . $componentType . ' is not a subclass of ' . Component::class);
        }

        $component = new $componentType($this);
        $this->components[] = $component;
        return $component;
    }

    /**
     * Returns the number of components attached to the game object.
     *
     * @return int The number of components attached to the game object.
     */
    public function getComponentCount(): int
    {
        return count($this->components);
    }

    /**
     * Gets the index of the component specified on the specified GameObject.
     *
     * @param Component $component The component to find.
     * @return int The index of the component, or -1 if the component is not found.
     */
    public function getComponentIndex(Component $component): int
    {
        foreach ($this->components as $index => $gameObjectComponent) {
            if ($component->equals($gameObjectComponent)) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * @inheritDoc
     */
    public function getComponent(string $componentClass): ?ComponentInterface
    {
        if (!class_exists($componentClass) && !interface_exists($componentClass)) {
            throw new InvalidArgumentException('The component type ' . $componentClass . ' does not exist.');
        }

        foreach ($this->components as $component) {
            if ($component instanceof $componentClass) {
                return $component;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getComponents(?string $componentClass = null): array
    {
        if ($componentClass) {
            return array_filter($this->components, fn(ComponentInterface $component) => $component instanceof $componentClass);
        }

        return $this->components;
    }

    /**
     * @inheritDoc
     */
    public function getUIElement(string $uiElementClass): ?UIElementInterface
    {
        if (!class_exists($uiElementClass) && !interface_exists($uiElementClass)) {
            throw new InvalidArgumentException('The ui element type ' . $uiElementClass . ' does not exist.');
        }

        foreach ($this->uiElements as $uiElement) {
            if ($uiElement instanceof $uiElementClass) {
                return $uiElement;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getUIElements(?string $uiElementClass = null): array
    {
        if ($uiElementClass) {
            return array_filter($this->uiElements, fn(UIElementInterface $uiElement) => $uiElement instanceof $uiElementClass);
        }

        return $this->uiElements;
    }

    /**
     * @inheritDoc
     */
    public function setSpriteFromTexture(Texture|array|string $texture, Vector2 $position, Vector2 $size): void
    {
        if (is_array($texture)) {
            $texture = new Texture($texture['path'], $texture['width'] ?? -1, $texture['height'] ?? -1);
        }

        if (is_string($texture)) {
            $texture = new Texture($texture);
        }

        $this->setSprite(new Sprite($texture, new Rect($position, $size)));
    }

    /**
     * @inheritDoc
     */
    public function setSprite(Sprite $sprite): void
    {
        $this->getRenderer()->setSprite($sprite);
    }

    /**
     * @inheritDoc
     */
    public function getSprite(): Sprite
    {
        return $this->getRenderer()->getSprite();
    }
}