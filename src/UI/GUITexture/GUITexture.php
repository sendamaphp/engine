<?php

namespace Sendama\Engine\UI\GUITexture;

use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Enumerations\Color;
use Sendama\Engine\UI\UIElement;
use Throwable;

class GUITexture extends UIElement
{
    protected string $texturePath = '';
    protected Color $color = Color::WHITE;
    protected ?Texture $texture = null;

    public function __construct(
        SceneInterface $scene,
        string $name,
        Vector2 $position = new Vector2(0, 0),
        Vector2 $size = new Vector2(1, 1),
        string $tag = '',
        string $texturePath = '',
        Color $color = Color::WHITE,
    ) {
        $this->texturePath = $texturePath;
        $this->color = $color;

        parent::__construct($scene, $name, $position, self::normalizeSize($size), $tag);
    }

    public function awake(): void
    {
        $this->reloadTexture();
    }

    public function start(): void
    {
        // Do nothing.
    }

    public function update(): void
    {
        // Do nothing.
    }

    public function getTexturePath(): string
    {
        return $this->texturePath;
    }

    public function setTexturePath(string $texturePath): void
    {
        $shouldRerender = $this->shouldRenderWithinScene();

        if ($shouldRerender) {
            $this->erase();
        }

        $this->texturePath = trim($texturePath);
        $this->reloadTexture();

        if ($shouldRerender) {
            $this->render();
        }
    }

    public function getColor(): Color
    {
        return $this->color;
    }

    public function setColor(Color $color): void
    {
        $shouldRerender = $this->shouldRenderWithinScene();

        if ($shouldRerender) {
            $this->erase();
        }

        $this->color = $color;

        if ($shouldRerender) {
            $this->render();
        }
    }

    public function setSize(Vector2 $size): void
    {
        $shouldRerender = $this->shouldRenderWithinScene();

        if ($shouldRerender) {
            $this->erase();
        }

        parent::setSize(self::normalizeSize($size));
        $this->reloadTexture();

        if ($shouldRerender) {
            $this->render();
        }
    }

    public function render(): void
    {
        if ($this->isActive()) {
            $this->renderAt($this->position->getX(), $this->position->getY());
        }
    }

    public function renderAt(?int $x = null, ?int $y = null): void
    {
        $renderedLines = $this->buildRenderedLines();

        if ($renderedLines === []) {
            return;
        }

        Console::writeLines($renderedLines, $x ?? 0, $y ?? 0);
    }

    public function erase(): void
    {
        $this->eraseAt($this->position->getX(), $this->position->getY());
    }

    public function eraseAt(?int $x = null, ?int $y = null): void
    {
        $width = $this->texture?->getWidth() ?? max(0, $this->size->getX());
        $height = $this->texture?->getHeight() ?? max(0, $this->size->getY());

        if ($width <= 0 || $height <= 0) {
            return;
        }

        Console::writeLines(
            array_fill(0, $height, str_repeat(' ', $width)),
            $x ?? 0,
            $y ?? 0,
        );
    }

    private function reloadTexture(): void
    {
        if ($this->texturePath === '' || strcasecmp($this->texturePath, 'None') === 0) {
            $this->texture = null;
            return;
        }

        $width = max(1, $this->size->getX());
        $height = max(1, $this->size->getY());

        try {
            $this->texture = new Texture($this->texturePath, $width, $height);
        } catch (Throwable) {
            $this->texture = null;
        }
    }

    private static function normalizeSize(Vector2 $size): Vector2
    {
        return new Vector2(
            max(1, $size->getX()),
            max(1, $size->getY()),
        );
    }

    private function buildRenderedLines(): array
    {
        if (!$this->texture instanceof Texture) {
            return [];
        }

        $lines = array_map(
            static fn (array $row): string => implode('', $row),
            $this->texture->getPixels(),
        );

        return array_map(
            fn (string $line): string => Color::apply($this->color, $line),
            $lines,
        );
    }
}
