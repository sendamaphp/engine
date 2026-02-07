<?php

namespace Sendama\Engine\Metadata;

use InvalidArgumentException;
use Sendama\Engine\Metadata\Interfaces\SceneObjectMetadataInterface;

class UIElementMetadata implements SceneObjectMetadataInterface
{
    public string $name;
    public Vector2Metadata $position;
    public Vector2Metadata $size;

    /**
     * Creates a new instance of UIElementMetadata from an array.
     *
     * @param array $data The data to create the metadata from.
     * @return self The created UIElementMetadata instance.
     * @throws InvalidArgumentException If required fields are missing or invalid.
     */
    public static function fromArray(array $data): self
    {
        $metadata = new self();
        $metadata->name = $data['name'] ?? throw new InvalidArgumentException('Name is required for UIElementMetadata');
        $metadata->position = Vector2Metadata::fromArray($data['position'] ?? []);
        $metadata->size = Vector2Metadata::fromArray($data['size'] ?? []);
        return $metadata;
    }

    /**
     * Converts the UIElementMetadata instance to an array.
     *
     * @return array{name: string, position: object, size: object} The array representation of the UIElementMetadata instance.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'position' => $this->position->toArray(),
            'size' => $this->size->toArray(),
        ];
    }
}