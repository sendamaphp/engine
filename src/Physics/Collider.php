<?php

namespace Sendama\Engine\Physics;

use Override;
use Sendama\Engine\Core\Behaviours\Attributes\SerializeField;
use Sendama\Engine\Core\Component;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Interfaces\CollisionDetectionStrategyInterface;
use Sendama\Engine\Physics\Strategies\AABBCollisionDetectionStrategy;
use Sendama\Engine\Physics\Traits\BoundTrait;

/**
 * The class Collider.
 *
 * @package Sendama\Engine\Physics
 *
 * @template T
 * @implements ColliderInterface<T>
 */
class Collider extends Component implements ColliderInterface
{
    use BoundTrait;

    /** @var Physics<T>|null $physics The physics. */
    protected ?Physics $physics = null;

    #[SerializeField]
    /** @var bool $isTrigger Whether the collider is a trigger. */
    protected bool $isTrigger = false;
    #[SerializeField]
    /** @var PhysicsMaterial $material The physics material used when resolving friction and bounce. */
    protected PhysicsMaterial $material;
    /** @var CollisionDetectionStrategyInterface $collisionDetectionStrategy The collision detection strategy. */
    protected CollisionDetectionStrategyInterface $collisionDetectionStrategy;

    /**
     * @inheritDoc
     */
    #[Override]
    public final function awake(): void
    {
        $this->physics = Physics::getInstance();
        $this->collisionDetectionStrategy = new AABBCollisionDetectionStrategy($this);
        $this->material = new PhysicsMaterial();
    }

    /**
     * @inheritDoc
     */
    public function isTouching(ColliderInterface $collider): bool
    {
        return $this->collisionDetectionStrategy->isTouching($collider);
    }

    /**
     * @inheritDoc
     */
    public function isTrigger(): bool
    {
        return $this->isTrigger;
    }

    /**
     * @inheritDoc
     */
    public function setCollisionDetectionStrategy(CollisionDetectionStrategyInterface $collisionDetectionStrategy): void
    {
        $this->collisionDetectionStrategy = $collisionDetectionStrategy;
    }

    /**
     * @inheritDoc
     */
    public function configure(array $options = []): void
    {
        if (array_key_exists('isTrigger', $options)) {
            $this->setTrigger((bool)$options['isTrigger']);
        }

        if (array_key_exists('material', $options)) {
            $this->setMaterial($options['material']);
        }
    }

    /**
     * @inheritDoc
     */
    public function setTrigger(bool $isTrigger): void
    {
        $this->isTrigger = $isTrigger;
    }

    /**
     * Returns the collider's physics material.
     *
     * @return PhysicsMaterial
     */
    public function getMaterial(): PhysicsMaterial
    {
        return $this->material;
    }

    /**
     * Sets the collider's physics material.
     *
     * @param mixed $material
     * @return void
     */
    public function setMaterial(mixed $material): void
    {
        $this->material = PhysicsMaterial::fromMetadata($material);
    }

    /**
     * @inheritDoc
     */
    public function simulate(): void
    {
        if (method_exists($this->getGameObject(), 'fixedUpdate')) {
            $this->getGameObject()->fixedUpdate();
        }
    }
}
