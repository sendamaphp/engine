<?php

namespace Sendama\Engine\Metadata;

use Sendama\Engine\Metadata\Interfaces\SceneObjectMetadataInterface;

/**
 * Metadata for a component attached to a scene object.
 *
 * @package Sendama\Engine\Metadata
 */
class ComponentMetadata implements SceneObjectMetadataInterface
{
    public string $class;
    public ?object $properties = null;

    /**
     * Creates an instance of ComponentMetadata from an array.
     *
     * @param array $data The data to create the instance from.
     * @return self The created instance.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->class = $data['class'] ?? '';
        $instance->properties = $data['properties'] ?? new \stdClass();
        return $instance;
    }

    /**
     * Converts the instance to an array.
     *
     * @return array{class: string, properties: null|object} The array representation of the instance.
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'properties' => $this->properties,
        ];
    }
}