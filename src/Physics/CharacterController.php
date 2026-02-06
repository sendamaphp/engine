<?php

namespace Sendama\Engine\Physics;

use Assegai\Collections\ItemList;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Debug\Debug;
use Sendama\Engine\Events\Interfaces\EventInterface;
use Sendama\Engine\Events\Interfaces\ObservableInterface;
use Sendama\Engine\Events\Interfaces\ObserverInterface;
use Sendama\Engine\Events\Interfaces\StaticObserverInterface;
use Sendama\Engine\Physics\Interfaces\CollisionInterface;
use Sendama\Engine\Physics\Strategies\SimpleCollisionDetectionStrategy;

/**
 * The class CharacterController. It allows you to do movement constrained by collisions without having to deal with a
 * rigidbody. It is not affected by forces and will only move when call the move method. It then carries out the
 * movement and will be constrained by collisions.
 *
 * @package Sendama\Engine\Physics
 *
 * @template T
 * @extends Collider<mixed>
 */
class CharacterController extends Collider implements ObservableInterface
{
    /**
     * The observers.
     *
     * @var ItemList<ObserverInterface>
     */
    protected ItemList $observers;

    /**
     * @var ItemList<StaticObserverInterface>
     */
    protected ItemList $staticObservers;

    /**
     * @var array<CollisionInterface<T>> The previous collisions.
     */
    private array $previousCollisions = [];

    public function onStart(): void
    {
        $this->collisionDetectionStrategy = new SimpleCollisionDetectionStrategy($this);

        /** @var ItemList<ObserverInterface> $observers */
        $observers = new ItemList(ObserverInterface::class);
        $this->observers = $observers;

        /** @var ItemList<StaticObserverInterface> $staticObservers */
        $staticObservers = new ItemList(StaticObserverInterface::class);
        $this->staticObservers = $staticObservers;
    }

    /**
     * Moves the character.
     *
     * @param Vector2 $motion The motion.
     * @return void
     */
    public function move(Vector2 $motion): void
    {
        $collisions = $this->physics?->checkCollisions($this, $motion);
        $canMove = true;

        // If there are collisions, resolve them.
        foreach ($collisions ?? [] as $collision) {
            if ($collision->getContact(0)?->getSeparation()) {
                $this->resolveCollision($collision);
            }
        }

        // If there are no collisions, move the character.
        $this->getTransform()->translate($motion);
    }

    /**
     * Resolves the collision.
     *
     * @param CollisionInterface<T> $collision The collision.
     * @return void
     */
    private function resolveCollision(CollisionInterface $collision): void
    {
        // TODO: Implement collision resolution.
        $methodName = match (true) {
            $this->previousCollisionsIncludes($collision) => "onCollisionStay",
            default => "onCollisionEnter"
        };

        $collision->getContact(0)?->getThisCollider()->getGameObject()->broadcast($methodName, ['collision' => $collision]);
        $collision->getContact(0)?->getOtherCollider()->getGameObject()->broadcast($methodName, ['collision' => $collision]);

        Debug::log("Collision for {$collision->getGameObject()->getName()} at " . $collision->getContact(0)?->getPoint());
    }

    /**
     * Checks if the previous collisions includes the collision.
     *
     * @param CollisionInterface<T> $collision The collision.
     * @return bool
     */
    private function previousCollisionsIncludes(CollisionInterface $collision): bool
    {
        foreach ($this->previousCollisions as $previousCollision) {
            if ($previousCollision->getContact(0)?->getOtherCollider() === $collision->getContact(0)?->getOtherCollider()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function addObservers(string|StaticObserverInterface|ObserverInterface ...$observers): void
    {
        foreach ($observers as $observer) {
            if (is_object($observer)) {
                if (get_class($observer) === ObserverInterface::class) {
                    $this->observers->add($observer);
                }

                if (get_class($observer) === StaticObserverInterface::class) {
                    $this->staticObservers->add($observer);
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function removeObservers(string|StaticObserverInterface|ObserverInterface|null ...$observers): void
    {
        foreach ($observers as $observer) {
            if (is_object($observer)) {
                if (get_class($observer) === ObserverInterface::class) {
                    $this->observers->remove($observer);
                }

                if (get_class($observer) === StaticObserverInterface::class) {
                    $this->staticObservers->remove($observer);
                }
            }

        }
    }

    /**
     * @inheritDoc
     */
    public function notify(EventInterface $event): void
    {
        /** @var ObserverInterface $observer */
        foreach ($this->observers as $observer) {
            $observer->onNotify($this, $event);
        }

        /** @var StaticObserverInterface $staticObserver */
        foreach ($this->staticObservers as $staticObserver) {
            $staticObserver::onNotify($this, $event);
        }
    }
}