<?php

namespace Sendama\Engine\Metadata;

use Sendama\Engine\Metadata\Interfaces\SceneObjectMetadataInterface;

/**
 * Metadata class representing a 2D vector with x and y coordinates.
 */
class Vector2Metadata implements SceneObjectMetadataInterface
{
    public int $x = 0;
    public int $y = 0;

    /**
     * Creates an instance of Vector2Metadata from an associative array.
     *
     * @param array $data Associative array with keys 'x' and 'y'.
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->x = $data['x'] ?? 0;
        $instance->y = $data['y'] ?? 0;

        return $instance;
    }

    /**
     * Converts the Vector2Metadata instance to an associative array.
     *
     * @return array{x: int, y: int} Associative array with keys 'x' and 'y'.
     */
    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
        ];
    }
}