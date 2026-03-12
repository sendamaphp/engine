<?php

namespace Sendama\Engine\Core;

use InvalidArgumentException;
use ReflectionObject;
use Sendama\Engine\Core\Behaviours\Attributes\SerializeField;
use Sendama\Engine\Core\Interfaces\CanCompare;
use Sendama\Engine\Core\Interfaces\CanEquate;
use Sendama\Engine\Core\Interfaces\ComponentInterface;
use Sendama\Engine\Core\Rendering\Renderer;

/**
 * Represents a component. This class is the base class for all components in the engine.
 */
abstract class Component implements ComponentInterface
{
    /**
     * @var bool $enabled Whether the component is enabled or not.
     */
    protected bool $enabled = true;

    /**
     * @var string
     */
    protected string $hash;

    /**
     * Component constructor.
     *
     * @param GameObject $gameObject The game object.
     */
    public function __construct(private readonly GameObject $gameObject)
    {
        $this->hash = md5(__CLASS__) . '-' . uniqid($this->gameObject->getName(), true);

        $this->awake();
    }

    /**
     * @inheritDoc
     */
    public function awake(): void
    {
        // Do nothing
    }

    /**
     * @inheritDoc
     */
    public final function getTransform(): Transform
    {
        return $this->gameObject->getTransform();
    }

    /**
     * @inheritDoc
     */
    public final function getRenderer(): Renderer
    {
        return $this->getGameObject()->getRenderer();
    }

    /**
     * @inheritDoc
     */
    public final function getGameObject(): GameObject
    {
        return $this->gameObject;
    }

    /**
     * Enables the component.
     *
     * @inheritDoc
     */
    public final function enable(): void
    {
        $this->enabled = true;
        $this->start();
    }

    /**
     * @inheritDoc
     */
    public final function start(): void
    {
        $this->onStart();
    }

    /**
     * Called when the component is started.
     *
     * @return void
     */
    public function onStart(): void
    {
        // Do nothing
    }

    /**
     * @inheritDoc
     */
    public final function disable(): void
    {
        $this->enabled = false;
        $this->stop();
    }

    /**
     * @inheritDoc
     */
    public final function stop(): void
    {
        $this->onStop();
    }

    /**
     * Called when the component is stopped.
     *
     * @return void
     */
    public function onStop(): void
    {
        // Do nothing
    }

    /**
     * Called when the component is disabled.
     *
     * @return void
     */
    public function onDisable(): void
    {
        // Do nothing
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
        if (!$other instanceof Component) {
            throw new InvalidArgumentException('Cannot compare a component with a non-component.');
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
    public function equals(CanEquate $equatable): bool
    {
        return $this->getHash() === $equatable->getHash();
    }

    /**
     * @inheritDoc
     */
    public function notEquals(CanEquate $equatable): bool
    {
        return $this->getHash() !== $equatable->getHash();
    }

    /**
     * @inheritDoc
     */
    public final function resume(): void
    {
        $this->onResume();
    }

    /**
     * Called when the component is resumed.
     *
     * @return void
     */
    public function onResume(): void
    {
        // Do nothing
    }

    /**
     * @inheritDoc
     */
    public final function suspend(): void
    {
        $this->onSuspend();
    }

    /**
     * Called when the component is suspended.
     *
     * @return void
     */
    public function onSuspend(): void
    {
        // Do nothing
    }

    /**
     * @inheritDoc
     */
    public final function fixedUpdate(): void
    {
        if ($this->isEnabled()) {
            $this->onFixedUpdate();
        }
    }

    /**
     * @inheritDoc
     */
    public final function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Called once every physics step.
     *
     * @return void
     */
    public function onFixedUpdate(): void
    {
        // Do nothing
    }

    /**
     * @inheritDoc
     */
    public final function update(): void
    {
        if ($this->isEnabled()) {
            $this->onUpdate();
        }
    }

    /**
     * Called when the component is updated.
     *
     * @return void
     */
    public function onUpdate(): void
    {
        // Do nothing
    }

    /**
     * @inheritDoc
     */
    public function broadcast(string $methodName, array $args = []): void
    {
        $this->gameObject->broadcast($methodName, $args);
    }

    /**
     * @inheritDoc
     */
    public function hasTag(string $tag): bool
    {
        return $this->gameObject->getTag() === $tag;
    }

    /**
     * Serializes the component.
     */
    public function __serialize(): array
    {
        $data = [];
        $reflection = new ReflectionObject($this);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            if ($property->isPublic() || $property->getAttributes(SerializeField::class)) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }

    /**
     * Deserializes the component.
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * @inheritDoc
     */
    public function getComponent(string $componentClass): ?ComponentInterface
    {
        return $this->getGameObject()->getComponent($componentClass);
    }

    /**
     * @inheritDoc
     */
    public function getComponents(string $componentClass): array
    {
        return $this->getGameObject()->getComponents($componentClass);
    }
}