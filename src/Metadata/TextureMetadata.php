<?php

namespace Sendama\Engine\Metadata;

use InvalidArgumentException;
use Sendama\Engine\Metadata\Interfaces\SceneObjectMetadataInterface;

/**
 * Metadata for a texture object in a scene.
 *
 * @package Sendama\Engine\Metadata
 */
class TextureMetadata implements SceneObjectMetadataInterface
{
    public string $path;
    public Vector2Metadata $position;
    public Vector2Metadata $size;

    /**
     * Create TextureMetadata from an associative array.
     *
     * @param array $data
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $metadata = new self();

        // Validate required fields
        if (!isset($data['path'], $data['position'], $data['size'])) {
            throw new InvalidArgumentException('Missing required fields for TextureMetadata: path, position, size');
        }

        $metadata->path = $data['path'];
        $metadata->position = Vector2Metadata::fromArray($data['position']);
        $metadata->size = Vector2Metadata::fromArray($data['size']);
        return $metadata;
    }

    /**
     * Convert TextureMetadata to an associative array.
     *
     * @return array{path: string, position: array, size: array}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'position' => $this->position->toArray(),
            'size' => $this->size->toArray(),
        ];
    }
}