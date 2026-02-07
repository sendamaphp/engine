<?php

namespace Sendama\Engine\Metadata;

use Sendama\Engine\Metadata\Interfaces\SceneObjectMetadataInterface;

class SceneMetadata
{
    public int $width = DEFAULT_SCREEN_WIDTH;
    public int $height = DEFAULT_SCREEN_HEIGHT;
    public string $environmentTileMapPath = '';
    /** @var SceneObjectMetadataInterface[] */
    public array $hierarchy = [];
}