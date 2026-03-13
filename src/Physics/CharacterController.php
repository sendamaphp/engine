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
use Sendama\Engine\Physics\Strategies\AABBCollisionDetectionStrategy;

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
     * @var array<string, CollisionInterface<T>> The previous collisions.
     */
    private array $previousCollisions = [];

    public function onStart(): void
    {
        $this->collisionDetectionStrategy = new AABBCollisionDetectionStrategy($this);

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
        $collisions = $this->physics?->checkCollisions($this, $motion) ?? [];
        $blockingCollisionCount = 0;
        $currentCollisions = [];

        foreach ($collisions as $collision) {
            $collisionKey = $this->getCollisionKey($collision);

            if ($collisionKey !== null && !isset($currentCollisions[$collisionKey])) {
                $currentCollisions[$collisionKey] = $collision;
                $this->dispatchCollision(
                    isset($this->previousCollisions[$collisionKey]) ? 'onCollisionStay' : 'onCollisionEnter',
                    $collision
                );
            }

            if (!($collision->getContact(0)?->getOtherCollider()?->isTrigger() ?? false)) {
                $blockingCollisionCount++;
            }
        }

        foreach ($this->previousCollisions as $collisionKey => $collision) {
            if (!isset($currentCollisions[$collisionKey])) {
                $this->dispatchCollision('onCollisionExit', $collision);
            }
        }

        $this->previousCollisions = $currentCollisions;

        if ($blockingCollisionCount === 0) {
            $this->getTransform()->translate($motion);
        }
    }

    /**
     * Resolves the collision.
     *
     * @param CollisionInterface<T> $collision The collision.
     * @return void
     */
    private function dispatchCollision(string $methodName, CollisionInterface $collision): void
    {
        $contact = $collision->getContact(0);
        $otherCollider = $contact?->getOtherCollider();

        $this->getGameObject()->broadcast($methodName, ['collision' => $collision]);

        if ($otherCollider !== null) {
            $mirroredCollision = new Collision(
                $this,
                [
                    new ContactPoint(
                        Vector2::getClone($contact->getPoint()),
                        $otherCollider,
                        $this,
                    ),
                ],
            );

            $otherCollider->getGameObject()->broadcast($methodName, ['collision' => $mirroredCollision]);
        }

        Debug::log("Collision for {$collision->getGameObject()->getName()} at " . $collision->getContact(0)?->getPoint());
    }

    /**
     * Returns a stable key for the collision target.
     *
     * @param CollisionInterface<T> $collision The collision.
     * @return string|null
     */
    private function getCollisionKey(CollisionInterface $collision): ?string
    {
        $contact = $collision->getContact(0);
        $otherCollider = $contact?->getOtherCollider();

        if ($otherCollider !== null) {
            return $otherCollider->getHash();
        }

        if ($collision instanceof EnvironmentCollision) {
            $point = $contact?->getPoint();

            if ($point !== null) {
                return 'environment:' . $point->getX() . ':' . $point->getY();
            }
        }

        return null;
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
