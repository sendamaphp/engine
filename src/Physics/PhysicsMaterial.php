<?php

namespace Sendama\Engine\Physics;

use Sendama\Engine\Metadata\PhysicsMaterialMetadata;

/**
 * A physics material defines the physical properties of a collider, such as its friction and bounciness.
 * It can be used to create different types of surfaces, such as slippery ice or sticky mud.
 */
final class PhysicsMaterial
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
        $this->friction = self::clamp01($this->friction);
        $this->bounciness = self::clamp01($this->bounciness);
    }

    /**
     * Creates a material from scene metadata, a plain array, or an existing material.
     *
     * @param mixed $source
     * @return self
     */
    public static function fromMetadata(mixed $source): self
    {
        if ($source instanceof self) {
            return $source;
        }

        if ($source instanceof PhysicsMaterialMetadata) {
            return new self($source->friction, $source->bounciness);
        }

        if (is_array($source)) {
            return new self(
                (float)($source['friction'] ?? PhysicsMaterialMetadata::DEFAULT_FRICTION),
                (float)($source['bounciness'] ?? PhysicsMaterialMetadata::DEFAULT_BOUNCINESS)
            );
        }

        if (is_object($source)) {
            return new self(
                (float)($source->friction ?? PhysicsMaterialMetadata::DEFAULT_FRICTION),
                (float)($source->bounciness ?? PhysicsMaterialMetadata::DEFAULT_BOUNCINESS)
            );
        }

        return new self();
    }

    /**
     * Returns a combined material for two colliders touching one another.
     *
     * @param self|null $other
     * @return self
     */
    public function combine(?self $other = null): self
    {
        $other ??= new self();

        return new self(
            ($this->friction + $other->friction) / 2,
            ($this->bounciness + $other->bounciness) / 2
        );
    }

    /**
     * Applies friction to a tangential velocity component.
     *
     * @param float $velocity
     * @return float
     */
    public function applyFriction(float $velocity): float
    {
        return $velocity * max(0.0, 1.0 - $this->friction);
    }

    /**
     * Applies restitution to a normal velocity component.
     *
     * @param float $velocity
     * @return float
     */
    public function applyBounce(float $velocity): float
    {
        return -$velocity * $this->bounciness;
    }

    /**
     * Clamp a normalized physics coefficient to the valid range.
     *
     * @param float $value
     * @return float
     */
    private static function clamp01(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
