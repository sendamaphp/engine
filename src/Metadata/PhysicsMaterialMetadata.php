<?php

namespace Sendama\Engine\Metadata;

use Sendama\Engine\Metadata\Interfaces\SceneObjectMetadataInterface;

/**
 * Class PhysicsMaterialMetadata
 *
 * This class represents the metadata for a physics material in the game engine.
 * It includes properties such as friction and bounciness, which can be used to define
 * how objects with this material interact with each other in terms of physics.
 */
class PhysicsMaterialMetadata implements SceneObjectMetadataInterface
{
    const float DEFAULT_FRICTION = 0.5;
    const float DEFAULT_BOUNCINESS = 0.5;

    public string $name;
    public float $friction = self::DEFAULT_FRICTION;
    public float $bounciness = self::DEFAULT_BOUNCINESS;

    public static function fromArray(array $data): self
    {
        $instance = new self();

        $instance->name = $data['name'];
        $instance->friction = $data['friction'] ?? self::DEFAULT_FRICTION;
        $instance->bounciness = $data['bounciness'] ?? self::DEFAULT_BOUNCINESS;

        return $instance;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'friction' => $this->friction,
            'bounciness' => $this->bounciness,
        ];
    }
}