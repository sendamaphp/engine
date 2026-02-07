<?php

namespace Sendama\Engine\Metadata\Interfaces;

/**
 * Interface SceneObjectMetadataInterface. Interface for scene object metadata.
 *
 * @package Sendama\Engine\Metadata\Interfaces
 */
interface SceneObjectMetadataInterface
{
    public static function fromArray(array $data): self;

    public function toArray(): array;
}