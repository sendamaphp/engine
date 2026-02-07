<?php

namespace Sendama\Engine\Metadata;

use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Metadata\Interfaces\SceneObjectMetadataInterface;

/**
 * Metadata representation of a GameObject.
 *
 * @package Sendama\Engine\Metadata
 */
class GameObjectMetadata implements SceneObjectMetadataInterface
{
    public string $type = GameObject::class;
    public string $name = '';
    public string $tag = '';
    public Vector2Metadata $position;
    public Vector2Metadata $rotation;
    public Vector2Metadata $scale;
    public SpriteMetadata $sprite;
    /** @var ComponentMetadata[] */
    public array $components = [];

    public static function fromArray(array $data): self
    {
        $instance = new self();

        // Validate and assign properties
        $instance->name = $data['name'] ?? '';
        $instance->tag = $data['tag'] ?? '';
        $instance->position = Vector2Metadata::fromArray($data['position'] ?? ['x' => 0, 'y' => 0]);
        $instance->rotation = Vector2Metadata::fromArray($data['rotation'] ?? ['x' => 0, 'y' => 0]);
        $instance->scale = Vector2Metadata::fromArray($data['scale'] ?? ['x' => 1, 'y' => 1]);
        $instance->sprite = SpriteMetadata::fromArray($data['sprite'] ?? []);
        $instance->components = array_map(
            fn($componentData) => ComponentMetadata::fromArray($componentData),
            $data['components'] ?? []
        );

        return $instance;
    }

    public function toArray(): array
    {
        return [
            "type" => GameObject::class,
            "name" => $this->name,
            "tag" => $this->tag,
            "position" => $this->position->toArray(),
            "rotation" => $this->rotation->toArray(),
            "scale" => $this->scale->toArray(),
            "sprite" => [
                "texture" => [
                    "path" => "Textures/player",
                    "position" => ["x" => 0, "y" => 0],
                    "size" => ["x" => 1, "y" => 5]
                ]
            ],
            "components" => array_map(fn($component) => $component->toArray(), $this->components)
        ];
    }
}