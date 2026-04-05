<?php

namespace Sendama\Engine\Physics;

use Sendama\Engine\Metadata\PhysicsMaterialMetadata;
use Sendama\Engine\Util\Path;
use Throwable;

/**
 * A physics material defines the physical properties of a collider, such as its friction and bounciness.
 * It can be used to create different types of surfaces, such as slippery ice or sticky mud.
 */
final class PhysicsMaterial
{
    /**
     * PhysicsMaterial constructor.
     *
     * @param float $friction The friction of the material. A value of 0 means no friction, while a value of 1 means maximum friction.
     * @param float $bounciness The bounciness of the material. A value of 0 means no bounciness, while a value of 1 means maximum bounciness.
     */
    public function __construct(
        public float $friction = 0.5,
        public float $bounciness = 0.5,
        public ?string $name = null,
        private ?string $assetPath = null,
    )
    {
        $this->friction = self::clamp01($this->friction);
        $this->bounciness = self::clamp01($this->bounciness);
        $this->assetPath = self::normalizeAssetPath($this->assetPath);
    }

    /**
     * Creates a material from scene metadata, a plain array, or an existing material.
     *
     * @param mixed $source
     * @return self
     */
    public static function fromMetadata(mixed $source): self
    {
        if ($source instanceof self) {
            return new self($source->friction, $source->bounciness, $source->name, $source->getAssetPath());
        }

        if ($source instanceof PhysicsMaterialMetadata) {
            return new self($source->friction, $source->bounciness, $source->name);
        }

        if (is_string($source)) {
            $normalizedSource = trim($source);

            if ($normalizedSource === '') {
                return new self();
            }

            $decodedSource = json_decode($normalizedSource, true);

            if (is_array($decodedSource)) {
                return self::fromMetadata($decodedSource);
            }

            $materialFromPath = self::loadFromPath($normalizedSource);

            return $materialFromPath ?? new self();
        }

        if (is_array($source)) {
            if (is_string($source['path'] ?? null) && trim($source['path']) !== '') {
                $materialFromPath = self::loadFromPath((string)$source['path']);

                if ($materialFromPath instanceof self) {
                    return $materialFromPath;
                }
            }

            return new self(
                (float)($source['friction'] ?? PhysicsMaterialMetadata::DEFAULT_FRICTION),
                (float)($source['bounciness'] ?? PhysicsMaterialMetadata::DEFAULT_BOUNCINESS),
                is_string($source['name'] ?? null) ? trim((string)$source['name']) : null,
                is_string($source['path'] ?? null) ? (string)$source['path'] : null,
            );
        }

        if (is_object($source)) {
            if (is_string($source->path ?? null) && trim($source->path) !== '') {
                $materialFromPath = self::loadFromPath((string)$source->path);

                if ($materialFromPath instanceof self) {
                    return $materialFromPath;
                }
            }

            return new self(
                (float)($source->friction ?? PhysicsMaterialMetadata::DEFAULT_FRICTION),
                (float)($source->bounciness ?? PhysicsMaterialMetadata::DEFAULT_BOUNCINESS),
                is_string($source->name ?? null) ? trim((string)$source->name) : null,
                is_string($source->path ?? null) ? (string)$source->path : null,
            );
        }

        return new self();
    }

    /**
     * Returns the assets-relative source path when this material comes from a material asset.
     *
     * @return string|null
     */
    public function getAssetPath(): ?string
    {
        return $this->assetPath;
    }

    /**
     * Returns the serialized metadata shape for this material.
     *
     * Asset-backed materials serialize as their asset path; inline materials remain arrays.
     *
     * @return array{name?: string, friction: float, bounciness: float}|string
     */
    public function toMetadata(): array|string
    {
        if (is_string($this->assetPath) && $this->assetPath !== '') {
            return $this->assetPath;
        }

        $metadata = [
            'friction' => $this->friction,
            'bounciness' => $this->bounciness,
        ];

        if (is_string($this->name) && trim($this->name) !== '') {
            $metadata['name'] = trim($this->name);
        }

        return $metadata;
    }

    /**
     * Returns a combined material for two colliders touching one another.
     *
     * @param self|null $other
     * @return self
     */
    public function combine(?self $other = null): self
    {
        $other ??= new self();

        return new self(
            ($this->friction + $other->friction) / 2,
            ($this->bounciness + $other->bounciness) / 2
        );
    }

    /**
     * Applies friction to a tangential velocity component.
     *
     * @param float $velocity
     * @return float
     */
    public function applyFriction(float $velocity): float
    {
        return $velocity * max(0.0, 1.0 - $this->friction);
    }

    /**
     * Applies restitution to a normal velocity component.
     *
     * @param float $velocity
     * @return float
     */
    public function applyBounce(float $velocity): float
    {
        return -$velocity * $this->bounciness;
    }

    /**
     * Clamp a normalized physics coefficient to the valid range.
     *
     * @param float $value
     * @return float
     */
    private static function clamp01(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /**
     * Attempts to load a physics material from an asset metadata file.
     *
     * @param string $path
     * @return self|null
     */
    private static function loadFromPath(string $path): ?self
    {
        $resolvedPath = self::resolveAssetFilePath($path);

        if (!is_string($resolvedPath) || $resolvedPath === '' || !is_file($resolvedPath)) {
            return null;
        }

        try {
            $metadata = require $resolvedPath;
        } catch (Throwable) {
            return null;
        }

        $assetReference = self::normalizeAssetReference($path, $resolvedPath);

        if ($metadata instanceof self) {
            return new self(
                $metadata->friction,
                $metadata->bounciness,
                $metadata->name,
                $assetReference,
            );
        }

        if ($metadata instanceof PhysicsMaterialMetadata) {
            return new self(
                $metadata->friction,
                $metadata->bounciness,
                $metadata->name,
                $assetReference,
            );
        }

        if (is_array($metadata) || is_object($metadata)) {
            $normalizedMetadata = is_array($metadata) ? $metadata : (array)$metadata;

            return new self(
                (float)($normalizedMetadata['friction'] ?? PhysicsMaterialMetadata::DEFAULT_FRICTION),
                (float)($normalizedMetadata['bounciness'] ?? PhysicsMaterialMetadata::DEFAULT_BOUNCINESS),
                is_string($normalizedMetadata['name'] ?? null) ? trim((string)$normalizedMetadata['name']) : null,
                $assetReference,
            );
        }

        return null;
    }

    /**
     * Resolves a material asset path from either an absolute path or an assets-relative reference.
     *
     * @param string $path
     * @return string|null
     */
    private static function resolveAssetFilePath(string $path): ?string
    {
        $normalizedPath = trim(str_replace('\\', '/', $path));

        if ($normalizedPath === '' || $normalizedPath === self::class) {
            return null;
        }

        $candidates = [$normalizedPath];
        $assetsDirectory = rtrim(str_replace('\\', '/', Path::getWorkingDirectoryAssetsPath()), '/');

        if ($assetsDirectory !== '') {
            $candidates[] = $assetsDirectory . '/' . ltrim($normalizedPath, '/');
        }

        if (!str_ends_with(strtolower($normalizedPath), '.material.php')) {
            $candidates[] = $normalizedPath . '.material.php';

            if ($assetsDirectory !== '') {
                $candidates[] = $assetsDirectory . '/' . ltrim($normalizedPath . '.material.php', '/');
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return Path::normalize($candidate);
            }
        }

        return null;
    }

    /**
     * Returns a stable assets-relative path when possible.
     *
     * @param string $providedPath
     * @param string $resolvedPath
     * @return string
     */
    private static function normalizeAssetReference(string $providedPath, string $resolvedPath): string
    {
        $normalizedProvidedPath = self::normalizeAssetPath($providedPath);

        if (is_string($normalizedProvidedPath) && $normalizedProvidedPath !== '') {
            return $normalizedProvidedPath;
        }

        $assetsDirectory = rtrim(str_replace('\\', '/', Path::getWorkingDirectoryAssetsPath()), '/');
        $normalizedResolvedPath = str_replace('\\', '/', $resolvedPath);

        if ($assetsDirectory !== '' && str_starts_with($normalizedResolvedPath, $assetsDirectory . '/')) {
            return substr($normalizedResolvedPath, strlen($assetsDirectory) + 1) ?: $normalizedResolvedPath;
        }

        return $normalizedResolvedPath;
    }

    /**
     * Normalizes empty and legacy asset-path values.
     *
     * @param string|null $path
     * @return string|null
     */
    private static function normalizeAssetPath(?string $path): ?string
    {
        if (!is_string($path)) {
            return null;
        }

        $normalizedPath = trim(str_replace('\\', '/', $path));

        if ($normalizedPath === '' || $normalizedPath === self::class) {
            return null;
        }

        return $normalizedPath;
    }
}
