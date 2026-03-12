<?php

namespace Sendama\Engine\Core\Behaviours;

use Sendama\Engine\Core\Component;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Interfaces\CollisionInterface;

/**
 * Behaviour class. This class is the base class for all behaviours in the engine.
 */
abstract class Behaviour extends Component
{
    public SceneInterface $activeScene {
        get {
            return $this->getGameObject()->activeScene;
        }
    }

    public SceneInterface $scene {
        get {
            return $this->getGameObject()->getScene();
        }
    }
    public final function __construct(GameObject $gameObject)
    {
        parent::__construct($gameObject);
    }

    /**
     * Called when the collider enters another collider.
     *
     * @param CollisionInterface $collision The collision.
     * @return void
     */
    public function onCollisionEnter(CollisionInterface $collision): void
    {
        // Override this method to handle collision enter events.
    }

    /**
     * Called when the collider exits another collider.
     *
     * @param CollisionInterface $collision The collision.
     * @return void
     */
    public function onCollisionExit(CollisionInterface $collision): void
    {
        // Override this method to handle collision exit events.
    }

    /**
     * Called when the collider stays in another collider.
     *
     * @param CollisionInterface $collision The collision.
     * @return void
     */
    public function onCollisionStay(CollisionInterface $collision): void
    {
        // Override this method to handle collision stay events.
    }

    /**
     * Called when the collider enters a trigger.
     *
     * @param ColliderInterface $collider The collider.
     * @return void
     */
    public function onTriggerEnter(ColliderInterface $collider): void
    {
        // Override this method to handle trigger enter events.
    }

    /**
     * Called when the collider exits a trigger.
     *
     * @param ColliderInterface $collider The collider.
     * @return void
     */
    public function onTriggerExit(ColliderInterface $collider): void
    {
        // Override this method to handle trigger exit events.
    }

    /**
     * Called when the collider stays in a trigger.
     *
     * @param ColliderInterface $collider The collider.
     * @return void
     */
    public function onTriggerStay(ColliderInterface $collider): void
    {
        // Override this method to handle trigger stay events.
    }
}