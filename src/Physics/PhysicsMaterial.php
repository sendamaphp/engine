<?php

namespace Sendama\Engine\Physics;

/**
 * A physics material defines the physical properties of a collider, such as its friction and bounciness.
 * It can be used to create different types of surfaces, such as slippery ice or sticky mud.
 */
final readonly class PhysicsMaterial
{
    /**
     * PhysicsMaterial constructor.
     *
     * @param float $friction The friction of the material. A value of 0 means no friction, while a value of 1 means maximum friction.
     * @param float $bounciness The bounciness of the material. A value of 0 means no bounciness, while a value of 1 means maximum bounciness.
     */
    public function __construct(
        public float $friction = 0.5,
        public float $bounciness = 0.5
    )
    {
    }
}