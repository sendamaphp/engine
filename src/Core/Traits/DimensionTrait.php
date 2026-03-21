<?php

namespace Sendama\Engine\Core\Traits;

/**
 * A trait that provides dimension properties and methods.
 */
trait DimensionTrait
{
    /**
     * The width of the object.
     *
     * @var int
     */
    protected int $width = 0;
    /**
     * The height of the object.
     *
     * @var int
     */
    protected int $height = 0;

    /**
     * Get the width of the object.
     *
     * @return int The width of the object.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Set the width of the object.
     *
     * @param int $width The width of the object.
     * @return void
     */
    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    /**
     * Get the height of the object.
     *
     * @return int The height of the object.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Set the height of the object.
     *
     * @param int $height The height of the object.
     * @return void
     */
    public function setHeight(int $height): void
    {
        $this->height = $height;
    }
}