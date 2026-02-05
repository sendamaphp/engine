<?php

namespace Sendama\Engine\Core;

use InvalidArgumentException;
use Sendama\Engine\Core\Traits\DimensionTrait;
use Sendama\Engine\IO\Enumerations\Color;
use Sendama\Engine\Util\Path;
use Stringable;

/**
 * Represents a 2D texture.
 *
 * @package Sendama\Engine\Core
 */
class Texture2D implements Stringable
{
    use DimensionTrait;

    /**
     * The texture extension.
     */
    const string TEXTURE_EXTENSION = '.texture';

    /**
     * The pixels of the texture.
     *
     * @var string[][] The pixels of the texture.
     */
    protected array $pixels = [];
    private string $path;

    /**
     * Creates a new instance of the Texture2D class.
     *
     * @param string $path The path to the image file.
     * @param int $width The width of the texture.
     * @param int $height The height of the texture.
     */
    public function __construct(string $path, int $width = -1, int $height = -1, private ?Color $color = null, protected array $options = [])
    {
        $this->path = $path;

        if (!str_ends_with($this->getAbsolutePath(), self::TEXTURE_EXTENSION)) {
            $this->path .= self::TEXTURE_EXTENSION;
        }

        if (!file_exists($this->getAbsolutePath())) {
            throw new InvalidArgumentException("The file '" . $this->getAbsolutePath() . "' does not exist.");
        }

        $this->setWidth($width);
        $this->setHeight($height);

        $this->loadImage();
    }

    /**
     * Returns the absolute path to the texture.
     *
     * @return string The absolute path to the texture.
     */
    private function getAbsolutePath(): string
    {
        if (str_starts_with($this->path, '/')) {
            return $this->path;
        }

        return Path::join(Path::getWorkingDirectoryAssetsPath(), $this->path);
    }

    /**
     * Loads the image from the specified path.
     */
    protected function loadImage(): void
    {
        // Load the image.
        $image = file_get_contents($this->getAbsolutePath());

        if ($this->color) {
            $image = Color::apply($this->color, to: $image);
        }

        // Convert the image to an array of pixels.
        $imageMatrix = explode("\n", $image);
        $height = 0;
        $longestRow = 0;

        foreach ($imageMatrix as $row) {
            $width = $this->width < 1 ? strlen($row) : $this->width;
            $chunks = str_split(substr($row, 0, $width));
            $this->pixels[] = $chunks;
            $longestRow = max($longestRow, $width);
            $height++;
        }

        if ($this->width < 1) {
            $this->setWidth($longestRow);
        }

        if ($this->height < 1) {
            $this->setHeight($height);
        }
    }

    /**
     * Sets the color of the texture.
     *
     * @param Color|null $color The color to set.
     * @return void
     */
    public function setColor(?Color $color): void
    {
        $this->color = $color;
    }

    /**
     * Returns the color of the texture.
     *
     * @return Color|null The color of the texture.
     */
    public function getColor(): ?Color
    {
        return $this->color;
    }

    /**
     * Returns the pixel at the specified coordinates.
     *
     * @param int $x The x coordinate.
     * @param int $y The y coordinate.
     * @return string The pixel at the specified coordinates.
     */
    public function getPixel(int $x, int $y): string
    {
        if (!isset($this->pixels[$y][$x])) {
            throw new InvalidArgumentException("The pixel at ($x, $y) does not exist.");
        }

        return $this->pixels[$y][$x];
    }

    /**
     * Sets the pixel at the specified coordinates.
     *
     * @param int $x The x coordinate.
     * @param int $y The y coordinate.
     * @param string $pixel The pixel to set.
     */
    public function setPixel(int $x, int $y, string $pixel): void
    {
        if ($x < 0 || $x >= $this->width || $y < 0 || $y >= $this->height) {
            throw new InvalidArgumentException("The pixel at ($x, $y) does not fall within range (0, 0) to ($this->width, $this->height).");
        }

        $output = $pixel;

        if ($this->color) {
            $output = Color::apply($this->color, to: $output);
        }

        $this->pixels[$y][$x] = substr($output, 0, 1);
    }

    public function __toString(): string
    {
        $output = '';

        foreach ($this->getPixels() as $pixel) {
            if (is_array($pixel)) {
                $output .= implode($pixel);
            }
        }

        return $output;
    }

    /**
     * Returns the pixels of the texture.
     *
     * @return string[][] The pixels of the texture.
     */
    public function getPixels(): array
    {
        return $this->pixels;
    }
}