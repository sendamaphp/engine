<?php

namespace Sendama\Engine\Metadata;

use InvalidArgumentException;
use Sendama\Engine\Metadata\Interfaces\SceneObjectMetadataInterface;

/**
 * Represents metadata for a sprite object in a scene.
 */
class SpriteMetadata implements SceneObjectMetadataInterface
{
    public TextureMetadata $texture;

    /**
     * Creates a SpriteMetadata instance from an array.
     *
     * @param array{texture: array<string, mixed>} $data The sprite metadata as an array.
     * @return self The created SpriteMetadata instance.
     * @throws InvalidArgumentException If required keys are missing or invalid.
     */
    public static function fromArray(array $data): self
    {
        $metadata = new self();

        // Validate that the required 'texture' key exists in the data array
        if (!isset($data['texture']) || !is_array($data['texture'])) {
            throw new InvalidArgumentException("The 'texture' key is required and must be an array.");
        }

        $metadata->texture = TextureMetadata::fromArray($data['texture']);
        return $metadata;
    }

    /**
     * @return array{texture: array<string, mixed>} The sprite metadata as an array.
     */
    public function toArray(): array
    {
        return [
            'texture' => $this->texture->toArray(),
        ];
    }
}