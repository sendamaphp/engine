<?php

namespace Sendama\Engine\Core;

use InvalidArgumentException;
use ReflectionObject;
use Sendama\Engine\Core\Behaviours\Attributes\SerializeField;
use Sendama\Engine\Core\Interfaces\CanCompare;
use Sendama\Engine\Core\Interfaces\CanEquate;
use Sendama\Engine\Core\Interfaces\ComponentInterface;
use Sendama\Engine\Core\Interfaces\GameObjectInterface;
use Sendama\Engine\Core\Rendering\Renderer;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Scenes\Scene;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
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
    protected bool $started = false;
    protected bool $starting = false;

    public SceneInterface $activeScene {
        get {
            return SceneManager::getInstance()->getActiveScene();
        }
    }

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
     * Serializes the game object into an array.
     *
     * @return array<string, mixed> The serialized game object.
     */
    public function __serialize(): array
    {
        $sprite = $this->renderer->getSprite();

        return [
            "hash" => $this->hash,
            "name" => $this->name,
            "tag" => $this->tag,
            "position" => $this->position,
            "rotation" => $this->rotation,
            "scale" => $this->scale,
            "transform" => $this->transform,
            "render" => $this->renderer,
            "sprite" => $sprite,
        ];
    }

    /**
     * Unserializes the game object from an array.
     *
     * @param array<string, mixed> $data The data to unserialize the game object from.
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->name = $data["name"];
        $this->tag = $data["tag"];
        $this->position = $data["position"];
        $this->rotation = $data["rotation"];
        $this->scale = $data["scale"];
        $this->transform = $data["transform"];
        $this->renderer = $data["render"];
        $this->sprite = $data["sprite"];
        $this->hash = $data["hash"] ?? md5(__CLASS__) . '-' . uniqid($data["name"] ?? 'GameObject', true);
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
        $this->started = false;
        $this->starting = false;

        $originalComponents = $this->components;
        $position = clone $this->transform->getPosition();
        $rotation = clone $this->transform->getRotation();
        $scale = clone $this->transform->getScale();
        $parent = $this->transform->getParent();
        $currentSprite = $this->renderer->getSprite();
        $sprite = $currentSprite ? clone $currentSprite : null;

        $this->position = clone $position;
        $this->rotation = clone $rotation;
        $this->scale = clone $scale;
        $this->sprite = $sprite;
        $this->transform = new Transform($this, $position, $scale, $rotation, $parent);
        $this->renderer = new Renderer($this, $sprite);
        $this->components = [$this->transform, $this->renderer];

        foreach ($originalComponents as $component) {
            if ($component instanceof Transform || $component instanceof Renderer) {
                continue;
            }

            $this->components[] = $this->cloneComponentForInstance($component);
        }
    }

    /**
     * Rebuild a component for a cloned game object and copy its serializable state.
     *
     * @param ComponentInterface $component
     * @return ComponentInterface
     */
    private function cloneComponentForInstance(ComponentInterface $component): ComponentInterface
    {
        $componentClass = $component::class;
        /** @var ComponentInterface $componentClone */
        $componentClone = new $componentClass($this);
        $reflection = new ReflectionObject($componentClone);

        foreach ($this->extractSerializableComponentData($component) as $propertyName => $value) {
            if (!$reflection->hasProperty($propertyName)) {
                continue;
            }

            $property = $reflection->getProperty($propertyName);
            $property->setValue($componentClone, self::duplicateComponentValue($value));
        }

        if (!$component->isEnabled()) {
            $enabledProperty = new \ReflectionProperty(Component::class, 'enabled');
            $enabledProperty->setValue($componentClone, false);
        }

        return $componentClone;
    }

    /**
     * Read serializable component data while skipping virtual accessors like activeScene/scene.
     *
     * @param ComponentInterface $component
     * @return array<string, mixed>
     */
    private function extractSerializableComponentData(ComponentInterface $component): array
    {
        $data = [];
        $reflection = new ReflectionObject($component);

        foreach ($reflection->getProperties() as $property) {
            $isSerializable = $property->isPublic() || $property->getAttributes(SerializeField::class);

            if (!$isSerializable) {
                continue;
            }

            if (method_exists($property, 'isVirtual') && $property->isVirtual()) {
                continue;
            }

            $data[$property->getName()] = $property->getValue($component);
        }

        return $data;
    }

    /**
     * Duplicate nested values so prefab instances do not share mutable component state.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function duplicateComponentValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $duplicate = [];

            foreach ($value as $key => $item) {
                $duplicate[$key] = self::duplicateComponentValue($item);
            }

            return $duplicate;
        }

        if (is_object($value)) {
            return clone $value;
        }

        return $value;
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
        if (!$this->isActive() || $this->started) {
            return;
        }

        $this->starting = true;

        for ($index = 0; $index < count($this->components); $index++) {
            $component = $this->components[$index];

            if ($component->isEnabled()) {
                $component->start();

                if ($component instanceof ColliderInterface) {
                    Physics::getInstance()->addCollider($component);
                }
            }
        }

        $this->starting = false;
        $this->started = true;
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
        $wasActive = $this->active;
        $this->active = true;

        if (!$this->started) {
            $this->start();
        } elseif (!$wasActive) {
            $this->resume();
        }

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
        if ($this->active) {
            $this->suspend();
        }

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

        if ($this->started && $this->belongsToActiveScene()) {
            $component->start();
        }

        if ($component instanceof ColliderInterface && $this->belongsToActiveScene()) {
            Physics::getInstance()->addCollider($component);
        }

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
     * Determines whether a newly-added runtime component should be initialized immediately.
     *
     * @return bool
     */
    private function belongsToActiveScene(): bool
    {
        $activeScene = SceneManager::getInstance()->getActiveScene();

        if ($activeScene === null) {
            return false;
        }

        return in_array($this, $activeScene->getRootGameObjects(), true);
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
        $this->sprite = $sprite;
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
