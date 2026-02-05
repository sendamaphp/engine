<?php

namespace Sendama\Engine\Core;

use Sendama\Engine\Core\Interfaces\CanEquate;
use Stringable;

class Vector2 implements CanEquate, Stringable
{
    /**
     * Vector2 constructor.
     *
     * @param int $x The x coordinate.
     * @param int $y The y coordinate.
     */
    public function __construct(protected int $x = 0, protected int $y = 0)
    {
    }

    /**
     * @param array{x: int, y: int} $vector
     * @return Vector2
     */
    public static function fromArray(array $vector): Vector2
    {
        if (array_is_list($vector)) {
            [$x, $y] = $vector;
        } else {
            $x = $vector['x'] ?? 0;
            $y = $vector['y'] ?? 0;
        }

        return new Vector2($x, $y);
    }

    /**
     * Shortcut for Vector2(0, 0).
     *
     * @return Vector2 Returns a new Vector2(0, 0).
     */
    public static function zero(): Vector2
    {
        return new Vector2(0, 0);
    }

    /**
     * Shortcut for Vector2(1, 1).
     *
     * @return Vector2 Returns a new Vector2(1, 1).
     */
    public static function one(): Vector2
    {
        return new Vector2(1, 1);
    }

    /**
     * Shortcut for Vector2(-1, 0).
     *
     * @return Vector2 Returns a new Vector2(-1, 0).
     */
    public static function left(): Vector2
    {
        return new Vector2(-1, 0);
    }

    /**
     * Shortcut for Vector2(1, 0).
     *
     * @return Vector2 Returns a new Vector2(1, 0).
     */
    public static function right(): Vector2
    {
        return new Vector2(1, 0);
    }

    /**
     * Shortcut for Vector2(0, 1).
     *
     * @return Vector2 Returns a new Vector2(0, 1).
     */
    public static function up(): Vector2
    {
        return new Vector2(0, 1);
    }

    /**
     * Shortcut for Vector2(0, -1).
     *
     * @return Vector2 Returns a new Vector2(0, -1).
     */
    public static function down(): Vector2
    {
        return new Vector2(0, -1);
    }

    /**
     * Gets a clone of the given vector.
     *
     * @param Vector2 $original The original vector.
     * @return self The clone.
     */
    public static function getClone(Vector2 $original): self
    {
        return new Vector2($original->getX(), $original->getY());
    }

    /* Getters and Setters */

    /**
     * Gets the x coordinate.
     *
     * @return int
     */
    public function getX(): int
    {
        return $this->x;
    }

    /**
     * Gets the y coordinate.
     *
     * @return int
     */
    public function getY(): int
    {
        return $this->y;
    }

    /**
     * Calculates the sum of the given vectors. The first vector is the augend, the rest are the addends.
     *
     * @param Vector2 ...$vectors
     * @return self This vector.
     */
    public static function sum(Vector2 ...$vectors): self
    {
        $result = new Vector2();

        foreach ($vectors as $vector) {
            $result->add($vector);
        }

        return $result;
    }

    /**
     * Adds the given vector to this vector.
     *
     * @param Vector2 $other The vector to add.
     * @return void
     */
    public function add(Vector2 $other): void
    {
        $this->setX($this->getX() + $other->getX());
        $this->setY($this->getY() + $other->getY());
    }

    /**
     * Sets the x coordinate.
     *
     * @param int $x The x coordinate.
     * @return Vector2
     */
    public function setX(int $x): self
    {
        $this->x = $x;

        return $this;
    }

    /**
     * Sets the y coordinate.
     *
     * @param int $y The y coordinate.
     * @return Vector2
     */
    public function setY(int $y): self
    {
        $this->y = $y;

        return $this;
    }

    /**
     * Calculates the product of the given vectors. The first vector is the multiplicand, the rest are the multipliers.
     *
     * @param Vector2 ...$vectors The vectors to multiply.
     * @return self The product.
     */
    public static function product(Vector2 ...$vectors): self
    {
        $result = new Vector2();

        foreach ($vectors as $index => $vector) {
            if ($index === 0) {
                $result = $vector;
                continue;
            }
            $result->setX($result->getX() * $vector->getX());
            $result->setY($result->getY() * $vector->getY());
        }

        return $result;
    }

    /**
     * Calculates the quotient of the given vectors. The first vector is the dividend, the rest are the divisors.
     *
     * @param Vector2 ...$vectors The vectors to divide.
     * @return self The quotient.
     */
    public static function quotient(Vector2 ...$vectors): self
    {
        $result = new Vector2();

        foreach ($vectors as $vector) {
            $result->setX($result->getX() / $vector->getX());
            $result->setY($result->getY() / $vector->getY());
        }

        return $result;
    }

    /* Static methods */

    /**
     * Calculates the distance between two vectors. It is the same as ($a - $b).getMagnitude().
     *
     * @param Vector2 $a The first vector.
     * @param Vector2 $b The second vector.
     * @return float The distance between the two vectors.
     */
    public static function distance(Vector2 $a, Vector2 $b): float
    {
        return Vector2::difference($a, $b)->getMagnitude();
    }

    /**
     * Returns the length of this vector.
     *
     * @return float
     */
    public function getMagnitude(): float
    {
        return sqrt($this->getSquareMagnitude());
    }

    /**
     * Gets the square magnitude of this vector.
     *
     * @return float The square magnitude of this vector.
     */
    public function getSquareMagnitude(): float
    {
        return $this->x * $this->x + $this->y * $this->y;
    }

    /**
     * Calculates the difference of the given vectors. The first vector is the minuend, the rest are the subtrahends.
     *
     * @param Vector2 ...$vectors The vectors to subtract.
     * @return self The difference.
     */
    public static function difference(Vector2 ...$vectors): self
    {
        $result = new Vector2();

        foreach ($vectors as $index => $vector) {
            if ($index === 0) {
                $result = $vector;
                continue;
            }
            $result->subtract($vector);
        }

        return $result;
    }

    /**
     * Subtracts the given vector from this vector.
     *
     * @param Vector2 $other The vector to subtract.
     * @return void
     */
    public function subtract(Vector2 $other): void
    {
        $this->setX($this->getX() - $other->getX());
        $this->setY($this->getY() - $other->getY());
    }

    /**
     * Linearly interpolates between two vectors.
     *
     * @param Vector2 $a The first vector.
     * @param Vector2 $b The second vector.
     * @param float $t The interpolation value. Should be between 0 and 1.
     *
     * @return Vector2 The interpolated vector.
     */
    public static function lerp(Vector2 $a, Vector2 $b, float $t): Vector2
    {
        $t = clamp($t, 0, 1);

        $x = lerp($a->getX(), $b->getX(), $t);
        $y = lerp($a->getY(), $b->getY(), $t);

        return new Vector2($x, $y);
    }

    /* Operator methods */

    /**
     * Returns the angle between two vectors.
     *
     * @param Vector2 $from The first vector.
     * @param Vector2 $to The second vector.
     * @return float The angle between the two vectors.
     */
    public static function angle(Vector2 $from, Vector2 $to): float
    {
        $dotProduct = self::dot($from, $to);
        $magnitudes = $from->getMagnitude() * $to->getMagnitude();

        // Prevent division by zero
        if ($magnitudes == 0) {
            return 0.0;
        }

        $angleInRadians = acos($dotProduct / $magnitudes);

        // Convert radians to degrees
        return rad2deg($angleInRadians);
    }

    /**
     * Returns the dot product of two vectors. For normalized vectors dot returns 1 if they point in exactly the same
     * direction, -1 if they point in completely opposite directions and 0 if the vectors are perpendicular.
     *
     * For vectors of arbitrary length the Dot return values are similar: they get larger when the angle between
     * vectors decreases.
     *
     * @param Vector2 $lhs The left-hand side vector.
     * @param Vector2 $rhs The right-hand side vector.
     * @return float The dot product of the two vectors.
     */
    public static function dot(Vector2 $lhs, Vector2 $rhs): float
    {
        return $lhs->getX() * $rhs->getX() + $lhs->getY() * $rhs->getY();
    }

    /**
     * Returns a vector that is made up of the largest components of two vectors.
     *
     * @param Vector2 $lhs The first vector.
     * @param Vector2 $rhs The second vector.
     * @return Vector2
     */
    public static function max(Vector2 $lhs, Vector2 $rhs): Vector2
    {
        return new Vector2(max($lhs->getX(), $rhs->getX()), max($lhs->getY(), $rhs->getY()));
    }

    /**
     * Returns a vector that is made up of the largest components of two vectors.
     *
     * @param Vector2 $lhs The first vector.
     * @param Vector2 $rhs The second vector.
     * @return Vector2
     */
    public static function min(Vector2 $lhs, Vector2 $rhs): Vector2
    {
        return new Vector2(min($lhs->getX(), $rhs->getX()), min($lhs->getY(), $rhs->getY()));
    }

    /**
     * Returns the 2D vector perpendicular to this 2D vector. The result is always rotated 90-degrees
     * in a counter-clockwise direction for a 2D coordinate system where the positive Y axis goes up.
     *
     * @param Vector2 $inDirection The input direction.
     * @return Vector2
     */
    public static function perpendicular(Vector2 $inDirection): Vector2
    {
        return new Vector2(-$inDirection->getY(), $inDirection->getX());
    }

    /**
     * Reflects a vector off the surface defined by a normal.
     *
     * This method calculates a reflected vector using the following formula:
     * `v = inDirection - 2 * inNormal * dot(inDirection inNormal).`
     * The inNormal vector defines a surface. A surface's normal is the vector that is perpendicular to its surface.
     * The inDirection vector is treated as a directional arrow coming into the surface. The returned value is a
     * vector of equal magnitude to inDirection but with its direction reflected.
     *
     * @param Vector2 $inDirection The direction towards the surface.
     * @param Vector2 $inNormal The normal vector that defines the surface.
     * @return Vector2
     */
    public static function reflect(Vector2 $inDirection, Vector2 $inNormal): Vector2
    {
        // Normalize the normal vector, ensuring integer rounding
        $normalizedNormal = $inNormal->getNormalized();

        // Compute dot product
        $dotProduct = self::dot($inDirection, $normalizedNormal);

        // Scale the normal vector by (2 * dot product), ensuring integer rounding
        $scaledNormal = new Vector2(
            (int)round($normalizedNormal->getX() * (2 * $dotProduct)),
            (int)round($normalizedNormal->getY() * (2 * $dotProduct))
        );

        // Compute the reflected vector, ensuring integer rounding
        return new Vector2(
            (int)round($inDirection->x - $scaledNormal->x),
            (int)round($inDirection->y - $scaledNormal->y)
        );
    }

    /**
     * Returns a new vector that is the normalized version of this vector.
     *
     * @return Vector2 The normalized vector.
     */
    public function getNormalized(): Vector2
    {
        $magnitude = $this->getMagnitude();

        if ($magnitude > PHP_FLOAT_MIN) {
            return new Vector2(
                (int)round($this->x / $magnitude),
                (int)round($this->y / $magnitude)
            );
        }

        return new Vector2(0, 0);
    }

    /**
     * Multiplies a vector by a number. Multiplies each component of the vector by the scalar.
     *
     * @param int|float $scalar The scalar to multiply by.
     * @return void
     */
    public function multiply(int|float $scalar): void
    {
        $this->setX($this->getX() * $scalar);
        $this->setY($this->getY() * $scalar);
    }

    /**
     * Divides a vector by a number. Divides each component of the vector by the scalar.
     *
     * @param int|float $scalar The scalar to divide by.
     * @return void
     */
    public function divide(int|float $scalar): void
    {
        $this->setX(intval($this->getX() / $scalar));
        $this->setY(intval($this->getY() / $scalar));
    }

    /**
     * Gets the string representation of this vector.
     *
     * @return string The string representation of this vector.
     */
    public function __toString(): string
    {
        return "($this->x, $this->y)";
    }

    public function normalize(): void
    {
        $length = $this->getMagnitude();

        if (abs($length) > PHP_FLOAT_MIN) {
            $this->setX((int)($this->getX() / $length));
            $this->setY((int)($this->getY() / $length));
        }
    }

    /**
     * Returns true if the given equatable is not equal to this equatable.
     *
     * @param CanEquate $equatable The equatable to compare.
     * @return bool True if the given equatable is not equal to this equatable.
     */
    public function notEquals(CanEquate $equatable): bool
    {
        return !$this->equals($equatable);
    }

    /**
     * Returns true if the given equatable is equal to this equatable.
     *
     * @param CanEquate $equatable The equatable to compare.
     * @return bool True if the given equatable is equal to this equatable.
     */
    public function equals(CanEquate $equatable): bool
    {
        return $this->getHash() === $equatable->getHash();
    }

    /**
     * Gets the hash of this equatable.
     *
     * @return string The hash of this equatable.
     */
    public function getHash(): string
    {
        return uniqid(md5(__CLASS__) . '.' . md5($this->x . '.' . $this->y));
    }

    /**
     * Scales this vector by the given scalar.
     *
     * @param int $scalar The scalar to scale by.
     * @return void
     */
    public function scale(int $scalar): void
    {
        $this->setX($this->getX() * $scalar);
        $this->setY($this->getY() * $scalar);
    }
}