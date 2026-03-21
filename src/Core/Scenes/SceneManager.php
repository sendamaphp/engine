<?php

namespace Sendama\Engine\Core\Scenes;

use Assegai\Collections\ItemList;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionProperty;
use ReflectionUnionType;
use Sendama\Engine\Core\Behaviours\Attributes\SerializeField;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Interfaces\CanLoad;
use Sendama\Engine\Core\Interfaces\CanRender;
use Sendama\Engine\Core\Interfaces\CanResume;
use Sendama\Engine\Core\Interfaces\CanStart;
use Sendama\Engine\Core\Interfaces\CanUpdate;
use Sendama\Engine\Core\Interfaces\SingletonInterface;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Scenes\Interfaces\SceneNodeInterface;
use Sendama\Engine\Core\Sprite;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Debug\Debug;
use Sendama\Engine\Events\Enumerations\SceneEventType;
use Sendama\Engine\Events\EventManager;
use Sendama\Engine\Events\SceneEvent;
use Sendama\Engine\Exceptions\IncorrectComponentTypeException;
use Sendama\Engine\Exceptions\Scenes\SceneManagementException;
use Sendama\Engine\Exceptions\Scenes\SceneNotFoundException;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Enumerations\Color as EngineColor;
use Sendama\Engine\Physics\Collider;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\Physics\PhysicsMaterial;
use Sendama\Engine\Physics\Rigidbody;
use Sendama\Engine\UI\GUITexture\GUITexture;
use Sendama\Engine\UI\Label\Label;
use Sendama\Engine\UI\Text\Text;
use Sendama\Engine\UI\UIElement;
use Sendama\Engine\Util\Path;
use Throwable;
use function dispatchEvent;

/**
 * Class SceneManager. Manages the scenes of the game.
 *
 * @package Sendama\Engine\Core\Scenes
 */
final class SceneManager implements SingletonInterface, CanStart, CanResume, CanUpdate, CanRender, CanLoad
{
    public const string SCENE_FILE_EXTENSION = '.scene.php';

    /**
     * @var SceneManager|null $instance The instance of the SceneManager.
     */
    protected static ?SceneManager $instance = null;
    protected static ?string $metadataAssetsRoot = null;
    protected static array $classImportAliasCache = [];
    /**
     * @var ItemList<SceneInterface> $scenes The list of scenes.
     */
    protected ItemList $scenes;
    /**
     * @var array<string, mixed> $settings The settings for the SceneManager.
     */
    protected array $settings = [];
    /**
     * @var SceneNodeInterface|null $activeSceneNode The currently active scene node.
     */
    protected ?SceneNodeInterface $activeSceneNode = null;
    /**
     * @var EventManager $eventManager The event manager.
     */
    protected EventManager $eventManager;
    protected Physics $physics;

    /**
     * Constructs a SceneManager
     */
    private final function __construct()
    {
        $this->eventManager = EventManager::getInstance();
        $this->scenes = new ItemList(SceneInterface::class);
        $this->physics = Physics::getInstance();
    }

    /**
     * @inheritDoc
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Checks whether a scene exists at the given index or name.
     *
     * @param int|string $index
     * @return bool
     */
    public function hasScene(int|string $index): bool
    {
        $sceneList = $this->scenes->toArray();

        foreach ($sceneList as $i => $scene) {
            if (is_int($index) && $i === $index) {
                return true;
            }

            if (is_string($index) && $scene->getName() === $index) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes a scene from the SceneManager.
     *
     * @param SceneInterface $scene The scene to remove.
     * @return $this The SceneManager instance.
     */
    public function removeScene(SceneInterface $scene): self
    {
        $this->scenes->remove($scene);

        return $this;
    }

    /**
     * Loads the previous scene.
     *
     * @return $this The SceneManager instance.
     * @throws SceneNotFoundException If the previous scene is not found.
     */
    public function loadPreviousScene(): self
    {
        Debug::info("Loading previous scene");

        if ($this->getPreviousSceneNode()) {
            return $this->loadScene($this->getPreviousSceneNode()->getScene()->getName());
        }

        return $this;
    }

    /**
     * Returns the previous scene.
     *
     * @return SceneNodeInterface|null The previous scene.
     */
    public function getPreviousSceneNode(): ?SceneNodeInterface
    {
        return $this->activeSceneNode?->getPreviousNode();
    }

    /**
     * Loads the scene with the given index.
     *
     * @param int|string $index The index of the scene to load. If a string is provided, the scene with the name will be
     * loaded. If an integer is provided, the scene at the index will be loaded.
     * @return $this The SceneManager instance.
     *
     * @throws SceneNotFoundException
     */
    public function loadScene(int|string $index): self
    {
        Debug::info("Loading scene: $index");
        dispatchEvent(new SceneEvent(SceneEventType::LOAD_START));

        $sceneToBeLoaded = null;

        $scenes = $this->scenes->toArray();
        /**
         * @var SceneInterface $scene
         */
        foreach ($scenes as $i => $scene) {
            if (is_int($index) && $i === $index) {
                $sceneToBeLoaded = $scene;
                break;
            }

            if (is_string($index) && $scene->getName() === $index) {
                $sceneToBeLoaded = $scene;
                break;
            }
        }

        if (!$sceneToBeLoaded) {
            throw new SceneNotFoundException($index);
        }

        $this->stop();
        $this->unload();

        $sceneSettings = $this->settings;
        $localSceneSettings = $sceneToBeLoaded->getSettings(null);

        if (is_array($localSceneSettings)) {
            $sceneSettings = array_replace($sceneSettings, $localSceneSettings);
        }

        $loadedScene = $sceneToBeLoaded->loadSceneSettings($sceneSettings);
        $viewport = $loadedScene->getCamera()->getViewport();

        Console::refreshLayout(
            $viewport->getWidth(),
            $viewport->getHeight(),
            Console::getSize(force: true)
        );

        $this->activeSceneNode = new SceneNode($loadedScene, $this->activeSceneNode);
        $this->load();

        $this->start();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function stop(): void
    {
        $this->activeSceneNode?->getScene()->stop();
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        $this->activeSceneNode?->getScene()->unload();
    }

    /**
     * Returns the settings for the SceneManager.
     *
     * @param string|null $key
     * @return mixed
     */
    public function getSettings(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->settings;
        }

        return array_key_exists($key, $this->settings) ? $this->settings[$key] : null;
    }

    /**
     * @inheritDoc
     */
    public function load(): void
    {
        $this->physics->init();
        foreach ($this->activeSceneNode->getScene()->getRootGameObjects() as $gameObject) {
            if ($collider = $gameObject->getComponent(ColliderInterface::class)) {
                assert($collider instanceof ColliderInterface, new IncorrectComponentTypeException(
                    ColliderInterface::class,
                    get_class($collider)
                ));
                $this->physics->addCollider($collider);;
            }
        }
        $this->activeSceneNode->getScene()->load();
        dispatchEvent(new SceneEvent(SceneEventType::LOAD_END));
    }

    /**
     * @inheritDoc
     */
    public function start(): void
    {
        $this->activeSceneNode?->getScene()->start();
    }

    /**
     * @inheritDoc
     */
    public function render(): void
    {
        $this->activeSceneNode?->getScene()->render();
    }

    /**
     * @inheritDoc
     */
    public function renderAt(?int $x = null, ?int $y = null): void
    {
        $this->activeSceneNode?->getScene()->renderAt($x, $y);
    }

    /**
     * @inheritDoc
     */
    public function erase(): void
    {
        $this->activeSceneNode?->getScene()->erase();
    }

    /**
     * @inheritDoc
     */
    public function eraseAt(?int $x = null, ?int $y = null): void
    {
        $this->activeSceneNode?->getScene()->eraseAt($x, $y);
    }

    /**
     * @inheritDoc
     */
    public function resume(): void
    {
        $this->activeSceneNode?->getScene()->resume();
    }

    /**
     * @inheritDoc
     */
    public function suspend(): void
    {
        $this->activeSceneNode?->getScene()->suspend();
    }

    /**
     * @inheritDoc
     */
    public function update(): void
    {
        $this->updatePhysics();

        if ($this->activeSceneNode) {
            $this->activeSceneNode->getScene()->update();
            dispatchEvent(new SceneEvent(SceneEventType::UPDATE, $this->activeSceneNode->getScene()));
        }
    }

    /**
     * Updates the physics of the active scene.
     */
    public function updatePhysics(): void
    {
        if ($this->activeSceneNode) {
            $this->activeSceneNode->getScene()->updatePhysics();
            dispatchEvent(new SceneEvent(SceneEventType::UPDATE_PHYSICS, $this->activeSceneNode->getScene()));
        }
    }

    /**
     * Loads the settings for the SceneManager.
     *
     * @param array<string, mixed> $settings
     */
    public function loadSettings(?array $settings = null): void
    {
        if ($settings) {
            $this->settings = $settings;
        }
    }

    /**
     * Loads a scene from a file.
     *
     * @param string $path The path to the scene file without the extension.
     * @return void
     * @throws SceneNotFoundException
     */
    public function loadSceneFromFile(string $path): void
    {
        $filename = $path . self::SCENE_FILE_EXTENSION;

        if (!file_exists($filename)) {
            // Try assets rather than Assets
            $filename = str_replace('Assets', 'assets', $filename);

            if (!file_exists($filename)) {
                throw new SceneNotFoundException($path);
            }
        }

        $sceneMetadata = require($filename);
        $sceneMetadata = json_decode(json_encode($sceneMetadata, JSON_UNESCAPED_SLASHES), false);
        self::$metadataAssetsRoot = Path::normalize(dirname($filename, 2));

        $sceneName = $sceneMetadata->name ?? basename($path);

        $scene = new class($sceneName, $sceneMetadata) extends AbstractScene {
            /**
             * @return void
             * @throws SceneManagementException
             */
            public function awake(): void
            {
                $sceneMetadata = $this->sceneMetadata;

                $sceneWidth = $sceneMetadata->screen_width ?? $sceneMetadata->screenWidth ?? $sceneMetadata->width ?? null;
                if (is_numeric($sceneWidth)) {
                    $this->settings['screen_width'] = (int)$sceneWidth;
                }

                $sceneHeight = $sceneMetadata->screen_height ?? $sceneMetadata->screenHeight ?? $sceneMetadata->height ?? null;
                if (is_numeric($sceneHeight)) {
                    $this->settings['screen_height'] = (int)$sceneHeight;
                }

                if (isset($sceneMetadata->environmentTileMapPath)) {
                    $this->environmentTileMapPath = $sceneMetadata->environmentTileMapPath;
                }

                if (isset($sceneMetadata->environmentCollisionMapPath)) {
                    $this->environmentCollisionMapPath = $sceneMetadata->environmentCollisionMapPath;
                }

                // Build hierarchy
                if (isset($sceneMetadata->hierarchy)) {
                    $pendingComponentPropertyAssignments = [];

                    foreach ($sceneMetadata->hierarchy as $index => $item) {
                        if (!isset($item->type)) {
                            Debug::warn("The 'type' property is not supported in scene hierarchy items. Item: " . ($item->name ?? "Unnamed GameObject") . " - $index");
                            continue;
                        }

                        $itemName = $item->name ?? throw new SceneManagementException("Invalid game object name");

                        $position = new Vector2();
                        if (isset($item->position)) {
                            $position = Vector2::fromArray((array)$item->position);
                        }

                        $size = new Vector2();
                        if (isset($item->size)) {
                            $size = Vector2::fromArray((array)$item->size);
                        }

                        $gameObject = null;

                        switch ($item->type) {
                            case GameObject::class:
                                $gameObject = SceneManager::inflateGameObjectMetadata(
                                    $item,
                                    $this,
                                    $pendingComponentPropertyAssignments,
                                );
                                break;

                            default:
                                $gameObject = match ($item->type) {
                                    Label::class => new Label($this, $itemName, $position, $size),
                                    Text::class => new Text($this, $itemName, $position, $size),
                                    GUITexture::class => new GUITexture($this, $itemName, $position, $size),
                                    default => throw new SceneManagementException(
                                        "Unsupported scene hierarchy item type: {$item->type}"
                                    ),
                                };

                                if (isset($item->tag) && method_exists($gameObject, 'setTag')) {
                                    $gameObject->setTag((string)$item->tag);
                                }

                                if (isset($item->text)) {
                                    if (!method_exists($gameObject, 'setText')) {
                                        throw new SceneManagementException("The 'text' property is not supported for game object of type: " . $item->type);
                                    }

                                    $gameObject->setText($item->text);
                                }

                                if ($gameObject instanceof GUITexture) {
                                    $gameObject->setTexturePath(
                                        SceneManager::extractTexturePathFromMetadata($item->texture ?? null) ?? ''
                                    );
                                    $gameObject->setColor(
                                        SceneManager::resolveColorMetadataValue($item->color ?? null) ?? EngineColor::WHITE
                                    );
                                }
                        }

                        $this->add($gameObject);
                    }

                    SceneManager::resolvePendingSceneComponentPropertyAssignments(
                        $pendingComponentPropertyAssignments,
                        $this,
                    );
                }
            }
        };

        $this->addScene($scene);
    }

    /**
     * Inflates a game object from scene/prefab metadata without attaching it to a scene.
     *
     * @param object|array<string, mixed> $itemMetadata
     * @param SceneInterface|null $sceneContext
     * @param array<int, array{component: object, property: ReflectionProperty, referenceName: string}>|null $pendingComponentPropertyAssignments
     * @return GameObject
     * @throws SceneManagementException
     */
    public static function inflateGameObjectMetadata(
        object|array    $itemMetadata,
        ?SceneInterface $sceneContext = null,
        ?array          &$pendingComponentPropertyAssignments = null,
    ): GameObject
    {
        $item = self::normalizeMetadata($itemMetadata);

        if (($item->type ?? null) !== GameObject::class) {
            throw new SceneManagementException('Prefab metadata must describe a ' . GameObject::class . '.');
        }

        $itemName = $item->name ?? throw new SceneManagementException('Invalid game object name');
        $position = isset($item->position)
            ? Vector2::fromArray((array)$item->position)
            : new Vector2();
        $rotation = isset($item->rotation)
            ? Vector2::fromArray((array)$item->rotation)
            : new Vector2();
        $scale = isset($item->scale)
            ? Vector2::fromArray((array)$item->scale)
            : new Vector2();

        $gameObject = new GameObject(
            $itemName,
            $item->tag ?? null,
            $position,
            $rotation,
            $scale
        );

        if (isset($item->sprite)) {
            if (!isset($item->sprite->texture)) {
                throw new SceneManagementException('Sprite texture not defined for game object: ' . $gameObject->getName());
            }

            $spriteTextureMetadata = $item->sprite->texture;
            $spriteTexture = new Texture($spriteTextureMetadata->path ?? throw new SceneManagementException('Invalid sprite texture path'));
            $spritePosition = isset($spriteTextureMetadata->position)
                ? Vector2::fromArray((array)$spriteTextureMetadata->position)
                : new Vector2();
            $spriteSize = isset($spriteTextureMetadata->size)
                ? Vector2::fromArray((array)$spriteTextureMetadata->size)
                : new Vector2();

            $gameObject->setSpriteFromTexture($spriteTexture, $spritePosition, $spriteSize);
        }

        if (isset($item->components)) {
            foreach ($item->components as $componentMetadata) {
                $componentMetadataObject = self::normalizeMetadata($componentMetadata);

                if (!isset($componentMetadataObject->class)) {
                    throw new SceneManagementException('Component class not defined for game object: ' . $gameObject->getName());
                }

                $componentClass = $componentMetadataObject->class;
                $component = $gameObject->addComponent($componentClass);
                self::applySceneComponentMetadata(
                    $component,
                    $componentClass,
                    $componentMetadataObject,
                    $sceneContext,
                    $pendingComponentPropertyAssignments,
                );
            }
        }

        if (isset($item->children) && is_iterable($item->children)) {
            foreach ($item->children as $childMetadata) {
                $child = self::inflateGameObjectMetadata(
                    $childMetadata,
                    $sceneContext,
                    $pendingComponentPropertyAssignments,
                );
                $child->getTransform()->setParent($gameObject->getTransform());
            }
        }

        return $gameObject;
    }

    /**
     * Normalizes scene/prefab metadata to an object for consistent property access.
     *
     * @param object|array<string, mixed> $metadata
     * @return object
     */
    private static function normalizeMetadata(object|array $metadata): object
    {
        if (is_object($metadata)) {
            return $metadata;
        }

        return json_decode(json_encode($metadata, JSON_UNESCAPED_SLASHES), false);
    }

    /**
     * Applies editor/file-scene component metadata onto the instantiated component.
     *
     * Supports legacy `proerties`, current `properties`, and editor-authored `data` payloads.
     *
     * @param object $component
     * @param string $componentClass
     * @param object $componentMetadata
     * @param SceneInterface|null $sceneContext
     * @param array<int, array{component: object, property: ReflectionProperty, referenceName: string}>|null $pendingComponentPropertyAssignments
     * @return void
     */
    public static function applySceneComponentMetadata(
        object          $component,
        string          $componentClass,
        object          $componentMetadata,
        ?SceneInterface $sceneContext = null,
        ?array          &$pendingComponentPropertyAssignments = null,
    ): void
    {
        $componentProperties = $componentMetadata->properties
            ?? $componentMetadata->proerties
            ?? $componentMetadata->data
            ?? null;

        if (!$componentProperties) {
            return;
        }

        $componentOptions = (array)$componentProperties;

        if (method_exists($component, 'configure')) {
            $component->configure($componentOptions);
        }

        $reflection = new ReflectionObject($component);

        foreach ($componentOptions as $key => $value) {
            if ($key === 'material' && ($component instanceof Collider || $component instanceof Rigidbody)) {
                $component->setMaterial(PhysicsMaterial::fromMetadata((array)$value));
                continue;
            }

            if (!$reflection->hasProperty($key)) {
                Debug::warn("Property '$key' does not exist on component of type: " . $componentClass);
                continue;
            }

            $property = $reflection->getProperty($key);

            if (!$property->isPublic() && !$property->getAttributes(SerializeField::class)) {
                continue;
            }

            $assignment = self::resolveSceneComponentPropertyAssignment($property, $value, $sceneContext);

            if (($assignment['shouldAssign'] ?? true) !== true) {
                $referenceName = $assignment['referenceName'] ?? null;

                if (
                    is_array($pendingComponentPropertyAssignments)
                    && is_string($referenceName)
                    && $referenceName !== ''
                ) {
                    $pendingComponentPropertyAssignments[] = [
                        'component' => $component,
                        'property' => $property,
                        'referenceName' => $referenceName,
                    ];
                }

                continue;
            }

            $property->setValue($component, $assignment['value'] ?? null);
        }
    }

    /**
     * Converts serialized scene values into runtime objects when a typed property requires it.
     *
     * @param ReflectionProperty $property
     * @param mixed $value
     * @param SceneInterface|null $sceneContext
     * @return array{shouldAssign: bool, value?: mixed, referenceName?: string}
     * @throws SceneManagementException
     */
    private static function resolveSceneComponentPropertyAssignment(
        ReflectionProperty $property,
        mixed              $value,
        ?SceneInterface    $sceneContext = null,
    ): array
    {
        if (is_string($value) && self::propertyAcceptsClass($property, GameObject::class)) {
            return [
                'shouldAssign' => true,
                'value' => self::loadPrefabFromPath($value),
            ];
        }

        if (self::propertyReferencesClassHierarchy($property, UIElement::class)) {
            return self::resolveUIElementComponentPropertyAssignment($property, $value, $sceneContext);
        }

        if (self::propertyAcceptsClass($property, Vector2::class)) {
            return [
                'shouldAssign' => true,
                'value' => self::hydrateVector2PropertyValue($property, $value),
            ];
        }

        if (self::propertyAcceptsClass($property, Rect::class)) {
            return [
                'shouldAssign' => true,
                'value' => self::hydrateRectPropertyValue($property, $value),
            ];
        }

        if (self::propertyAcceptsClass($property, Texture::class)) {
            return [
                'shouldAssign' => true,
                'value' => self::hydrateTexturePropertyValue($property, $value),
            ];
        }

        if (self::propertyAcceptsClass($property, Sprite::class)) {
            return [
                'shouldAssign' => true,
                'value' => self::hydrateSpritePropertyValue($property, $value),
            ];
        }

        $enumClass = self::resolvePropertyEnumClass($property);

        if ($enumClass !== null) {
            return [
                'shouldAssign' => true,
                'value' => self::hydrateEnumPropertyValue($property, $enumClass, $value),
            ];
        }

        if (self::propertyAcceptsBuiltinType($property, 'array')) {
            return [
                'shouldAssign' => true,
                'value' => self::hydrateCollectionPropertyValue($property, $value, $sceneContext),
            ];
        }

        $compoundClass = self::resolvePropertyCompoundClass($property);

        if ($compoundClass !== null) {
            return [
                'shouldAssign' => true,
                'value' => self::hydrateCompoundPropertyValue($property, $compoundClass, $value, $sceneContext),
            ];
        }

        return [
            'shouldAssign' => true,
            'value' => $value,
        ];
    }

    /**
     * Determines whether the property type can accept the given class.
     *
     * @param ReflectionProperty $property
     * @param class-string $className
     * @return bool
     */
    private static function propertyAcceptsClass(ReflectionProperty $property, string $className): bool
    {
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            return !$type->isBuiltin() && is_a($className, $type->getName(), true);
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $namedType) {
                if ($namedType instanceof ReflectionNamedType && !$namedType->isBuiltin() && is_a($className, $namedType->getName(), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Loads and inflates a prefab reference into a concrete game object template.
     *
     * @param string $path
     * @return GameObject
     * @throws SceneManagementException
     */
    public static function loadPrefabFromPath(string $path): GameObject
    {
        $resolvedPath = self::resolvePrefabPath($path);

        if (!$resolvedPath) {
            throw new SceneManagementException("Prefab not found: {$path}");
        }

        try {
            $prefabMetadata = require $resolvedPath;
        } catch (Throwable $throwable) {
            throw new SceneManagementException(
                "Failed to load prefab at {$resolvedPath}: {$throwable->getMessage()}",
                previous: $throwable
            );
        }

        if (!is_array($prefabMetadata) && !is_object($prefabMetadata)) {
            throw new SceneManagementException("Prefab metadata at {$resolvedPath} did not return a valid game object description.");
        }

        return self::inflateGameObjectMetadata($prefabMetadata);
    }

    /**
     * Resolves a prefab reference from either an absolute filesystem path or an assets-relative path.
     *
     * @param string $path
     * @return string|null
     */
    private static function resolvePrefabPath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        $candidates = [$path];
        if (is_string(self::$metadataAssetsRoot) && self::$metadataAssetsRoot !== '') {
            $candidates[] = Path::join(self::$metadataAssetsRoot, $path);
        }
        $assetsRelativePath = Path::join(Path::getWorkingDirectoryAssetsPath(), $path);
        $candidates[] = $assetsRelativePath;

        if (!str_ends_with(strtolower($path), '.prefab.php')) {
            $candidates[] = $path . '.prefab.php';
            if (is_string(self::$metadataAssetsRoot) && self::$metadataAssetsRoot !== '') {
                $candidates[] = Path::join(self::$metadataAssetsRoot, $path . '.prefab.php');
            }
            $candidates[] = $assetsRelativePath . '.prefab.php';
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return Path::normalize($candidate);
            }
        }

        return null;
    }

    /**
     * Determines whether the property's declared type belongs to the given class hierarchy.
     *
     * @param ReflectionProperty $property
     * @param class-string $className
     * @return bool
     */
    private static function propertyReferencesClassHierarchy(ReflectionProperty $property, string $className): bool
    {
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                return false;
            }

            $typeName = $type->getName();

            return is_a($typeName, $className, true) || is_a($className, $typeName, true);
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $namedType) {
                if (!$namedType instanceof ReflectionNamedType || $namedType->isBuiltin()) {
                    continue;
                }

                $typeName = $namedType->getName();

                if (is_a($typeName, $className, true) || is_a($className, $typeName, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolves serialized scene UI element references into runtime UI elements.
     *
     * @param ReflectionProperty $property
     * @param mixed $value
     * @param SceneInterface|null $sceneContext
     * @return array{shouldAssign: bool, value?: mixed, referenceName?: string}
     */
    private static function resolveUIElementComponentPropertyAssignment(
        ReflectionProperty $property,
        mixed              $value,
        ?SceneInterface    $sceneContext = null,
    ): array
    {
        if ($value === null) {
            return [
                'shouldAssign' => true,
                'value' => null,
            ];
        }

        if (is_object($value) && self::propertyAcceptsClass($property, $value::class)) {
            return [
                'shouldAssign' => true,
                'value' => $value,
            ];
        }

        if (!is_string($value)) {
            return [
                'shouldAssign' => true,
                'value' => $value,
            ];
        }

        $referenceName = trim($value);

        if ($referenceName === '') {
            return [
                'shouldAssign' => true,
                'value' => null,
            ];
        }

        $referenceScene = $sceneContext ?? self::getInstance()->getActiveScene();
        $resolvedReference = $referenceScene
            ? self::resolveSceneUIElementReferenceByName($referenceScene, $property, $referenceName)
            : null;

        if ($resolvedReference instanceof UIElement) {
            return [
                'shouldAssign' => true,
                'value' => $resolvedReference,
            ];
        }

        return [
            'shouldAssign' => false,
            'referenceName' => $referenceName,
        ];
    }

    /**
     * Returns the currently active scene.
     *
     * @return SceneInterface|null
     */
    public function getActiveScene(): ?SceneInterface
    {
        return $this->activeSceneNode?->getScene();
    }

    /**
     * Finds a scene UI element by name while respecting the receiving property type.
     *
     * @param SceneInterface $sceneContext
     * @param ReflectionProperty $property
     * @param string $referenceName
     * @return UIElement|null
     */
    private static function resolveSceneUIElementReferenceByName(
        SceneInterface     $sceneContext,
        ReflectionProperty $property,
        string             $referenceName,
    ): ?UIElement
    {
        foreach ($sceneContext->getUIElements() as $uiElement) {
            if (
                !$uiElement instanceof UIElement
                || $uiElement->getName() !== $referenceName
                || !self::propertyAcceptsClass($property, $uiElement::class)
            ) {
                continue;
            }

            return $uiElement;
        }

        return null;
    }

    /**
     * Hydrates scene metadata into a Vector2-compatible runtime value.
     *
     * @param ReflectionProperty $property
     * @param mixed $value
     * @return Vector2|null
     */
    private static function hydrateVector2PropertyValue(ReflectionProperty $property, mixed $value): ?Vector2
    {
        if ($value instanceof Vector2) {
            return Vector2::getClone($value);
        }

        if ($value === null) {
            return null;
        }

        $vectorPayload = self::extractVector2MetadataPayload($value);

        if (is_array($vectorPayload)) {
            return Vector2::fromArray($vectorPayload);
        }

        Debug::warn(sprintf(
            "Unable to hydrate Vector2 property '%s::%s' from scene metadata; falling back to %s.",
            $property->getDeclaringClass()->getName(),
            $property->getName(),
            self::propertyAllowsNull($property) ? 'null' : 'Vector2::zero()'
        ));

        return self::propertyAllowsNull($property) ? null : Vector2::zero();
    }

    /**
     * Attempts to normalize serialized vector metadata from arrays, objects, or legacy strings.
     *
     * @param mixed $value
     * @return array{x: int, y: int}|null
     */
    private static function extractVector2MetadataPayload(mixed $value): ?array
    {
        if ($value instanceof Vector2) {
            return [
                'x' => $value->getX(),
                'y' => $value->getY(),
            ];
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'x' => (int)($value[0] ?? 0),
                    'y' => (int)($value[1] ?? 0),
                ];
            }

            if (array_key_exists('x', $value) || array_key_exists('y', $value)) {
                return [
                    'x' => (int)($value['x'] ?? 0),
                    'y' => (int)($value['y'] ?? 0),
                ];
            }

            return null;
        }

        if (is_object($value)) {
            return self::extractVector2MetadataPayload((array)$value);
        }

        if (!is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        if ($normalizedValue === '') {
            return null;
        }

        $decodedValue = json_decode($normalizedValue, true);

        if (is_array($decodedValue)) {
            return self::extractVector2MetadataPayload($decodedValue);
        }

        if (
            preg_match('/^\[\s*(-?\d+)\s*,\s*(-?\d+)\s*\]$/', $normalizedValue, $matches) === 1
            || preg_match('/^\s*(-?\d+)\s*,\s*(-?\d+)\s*$/', $normalizedValue, $matches) === 1
        ) {
            return [
                'x' => (int)$matches[1],
                'y' => (int)$matches[2],
            ];
        }

        return null;
    }

    /**
     * Determines whether a typed property explicitly allows null values.
     *
     * @param ReflectionProperty $property
     * @return bool
     */
    private static function propertyAllowsNull(ReflectionProperty $property): bool
    {
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            return $type->allowsNull();
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $namedType) {
                if ($namedType instanceof ReflectionNamedType && $namedType->getName() === 'null') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Hydrates scene metadata into a Rect-compatible runtime value.
     *
     * @param ReflectionProperty $property
     * @param mixed $value
     * @return Rect|null
     */
    private static function hydrateRectPropertyValue(ReflectionProperty $property, mixed $value): ?Rect
    {
        if ($value instanceof Rect) {
            return new Rect($value->getPosition(), $value->getSize());
        }

        if ($value === null) {
            return null;
        }

        $rectPayload = self::extractRectMetadataPayload($value);

        if (is_array($rectPayload)) {
            return Rect::fromArray($rectPayload);
        }

        Debug::warn(sprintf(
            "Unable to hydrate Rect property '%s::%s' from scene metadata; falling back to %s.",
            $property->getDeclaringClass()->getName(),
            $property->getName(),
            self::propertyAllowsNull($property) ? 'null' : 'Rect(0,0,1,1)'
        ));

        return self::propertyAllowsNull($property)
            ? null
            : new Rect(new Vector2(0, 0), new Vector2(1, 1));
    }

    /**
     * Attempts to normalize serialized rect metadata from arrays, objects, or legacy strings.
     *
     * @param mixed $value
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private static function extractRectMetadataPayload(mixed $value): ?array
    {
        if ($value instanceof Rect) {
            return [
                'x' => $value->getX(),
                'y' => $value->getY(),
                'width' => $value->getWidth(),
                'height' => $value->getHeight(),
            ];
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'x' => (int)($value[0] ?? 0),
                    'y' => (int)($value[1] ?? 0),
                    'width' => max(1, (int)($value[2] ?? 1)),
                    'height' => max(1, (int)($value[3] ?? 1)),
                ];
            }

            if (array_key_exists('position', $value) || array_key_exists('size', $value)) {
                $position = self::extractVector2MetadataPayload($value['position'] ?? null) ?? ['x' => 0, 'y' => 0];
                $size = self::extractVector2MetadataPayload($value['size'] ?? null) ?? ['x' => 1, 'y' => 1];

                return [
                    'x' => (int)($position['x'] ?? 0),
                    'y' => (int)($position['y'] ?? 0),
                    'width' => max(1, (int)($size['x'] ?? 1)),
                    'height' => max(1, (int)($size['y'] ?? 1)),
                ];
            }

            if (
                array_key_exists('x', $value)
                || array_key_exists('y', $value)
                || array_key_exists('width', $value)
                || array_key_exists('height', $value)
            ) {
                return [
                    'x' => (int)($value['x'] ?? 0),
                    'y' => (int)($value['y'] ?? 0),
                    'width' => max(1, (int)($value['width'] ?? 1)),
                    'height' => max(1, (int)($value['height'] ?? 1)),
                ];
            }

            return null;
        }

        if (is_object($value)) {
            return self::extractRectMetadataPayload((array)$value);
        }

        if (!is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        if ($normalizedValue === '') {
            return null;
        }

        $decodedValue = json_decode($normalizedValue, true);

        if (is_array($decodedValue)) {
            return self::extractRectMetadataPayload($decodedValue);
        }

        if (preg_match('/^\[\s*(-?\d+)\s*,\s*(-?\d+)\s*,\s*(-?\d+)\s*,\s*(-?\d+)\s*\]$/', $normalizedValue, $matches) === 1) {
            return [
                'x' => (int)$matches[1],
                'y' => (int)$matches[2],
                'width' => max(1, (int)$matches[3]),
                'height' => max(1, (int)$matches[4]),
            ];
        }

        return null;
    }

    /**
     * Hydrates scene metadata into a Texture-compatible runtime value.
     *
     * @param ReflectionProperty $property
     * @param mixed $value
     * @return Texture|null
     */
    private static function hydrateTexturePropertyValue(ReflectionProperty $property, mixed $value): ?Texture
    {
        if ($value instanceof Texture) {
            return new Texture(
                $value->getPath(),
                $value->getRequestedWidth(),
                $value->getRequestedHeight(),
                $value->getColor(),
            );
        }

        if ($value === null) {
            return null;
        }

        $texturePayload = self::extractTextureMetadataPayload($value);

        if (!is_array($texturePayload) || !is_string($texturePayload['path'] ?? null) || trim($texturePayload['path']) === '') {
            Debug::warn(sprintf(
                "Unable to hydrate Texture property '%s::%s' from scene metadata; falling back to null.",
                $property->getDeclaringClass()->getName(),
                $property->getName(),
            ));

            return null;
        }

        try {
            return new Texture(
                $texturePayload['path'],
                (int)($texturePayload['width'] ?? -1),
                (int)($texturePayload['height'] ?? -1),
                self::resolveColorMetadataValue($texturePayload['color'] ?? null),
            );
        } catch (Throwable $throwable) {
            Debug::warn(sprintf(
                "Unable to hydrate Texture property '%s::%s': %s",
                $property->getDeclaringClass()->getName(),
                $property->getName(),
                $throwable->getMessage(),
            ));
        }

        return null;
    }

    /**
     * Normalizes serialized texture metadata from arrays, objects, or legacy strings.
     *
     * @param mixed $value
     * @return array{path: string, width?: int, height?: int, color?: mixed}|null
     */
    private static function extractTextureMetadataPayload(mixed $value): ?array
    {
        if ($value instanceof Texture) {
            $payload = ['path' => $value->getPath()];

            if ($value->getRequestedWidth() > 0) {
                $payload['width'] = $value->getRequestedWidth();
            }

            if ($value->getRequestedHeight() > 0) {
                $payload['height'] = $value->getRequestedHeight();
            }

            if ($value->getColor() !== null) {
                $payload['color'] = $value->getColor();
            }

            return $payload;
        }

        if (is_string($value)) {
            $normalizedValue = trim($value);

            if ($normalizedValue === '') {
                return null;
            }

            $decodedValue = json_decode($normalizedValue, true);

            if (is_array($decodedValue)) {
                return self::extractTextureMetadataPayload($decodedValue);
            }

            return ['path' => $normalizedValue];
        }

        $texturePath = self::extractTexturePathFromMetadata($value);

        if (!is_string($texturePath) || trim($texturePath) === '') {
            return null;
        }

        $payload = ['path' => trim($texturePath)];

        if (is_array($value)) {
            if (is_numeric($value['width'] ?? null)) {
                $payload['width'] = (int)$value['width'];
            }

            if (is_numeric($value['height'] ?? null)) {
                $payload['height'] = (int)$value['height'];
            }

            if (array_key_exists('color', $value)) {
                $payload['color'] = $value['color'];
            }

            return $payload;
        }

        if (is_object($value)) {
            if (is_numeric($value->width ?? null)) {
                $payload['width'] = (int)$value->width;
            }

            if (is_numeric($value->height ?? null)) {
                $payload['height'] = (int)$value->height;
            }

            if (property_exists($value, 'color')) {
                $payload['color'] = $value->color;
            }
        }

        return $payload;
    }

    /**
     * Extracts a UI texture path from scene metadata.
     *
     * @param mixed $textureMetadata
     * @return string|null
     */
    public static function extractTexturePathFromMetadata(mixed $textureMetadata): ?string
    {
        if (is_string($textureMetadata)) {
            $texturePath = trim($textureMetadata);

            return $texturePath !== '' ? $texturePath : null;
        }

        if (is_array($textureMetadata)) {
            $texturePath = $textureMetadata['path'] ?? null;

            return is_string($texturePath) && trim($texturePath) !== ''
                ? trim($texturePath)
                : null;
        }

        if (is_object($textureMetadata)) {
            $texturePath = $textureMetadata->path ?? null;

            return is_string($texturePath) && trim($texturePath) !== ''
                ? trim($texturePath)
                : null;
        }

        return null;
    }

    /**
     * Resolves scene metadata into a runtime console color.
     *
     * @param mixed $colorMetadata
     * @return EngineColor|null
     */
    public static function resolveColorMetadataValue(mixed $colorMetadata): ?EngineColor
    {
        if ($colorMetadata instanceof EngineColor) {
            return $colorMetadata;
        }

        if (!is_string($colorMetadata) || trim($colorMetadata) === '') {
            return null;
        }

        $normalizedColor = strtoupper(str_replace([' ', '-'], '_', trim($colorMetadata)));

        foreach (EngineColor::cases() as $color) {
            $normalizedCaseName = strtoupper($color->name);
            $normalizedPhoneticName = strtoupper(str_replace([' ', '-'], '_', $color->getPhoneticName()));
            $normalizedEscapeValue = strtoupper(trim($color->value));

            if (
                $normalizedColor === $normalizedCaseName
                || $normalizedColor === $normalizedPhoneticName
                || $normalizedColor === $normalizedEscapeValue
            ) {
                return $color;
            }
        }

        return null;
    }

    /**
     * Hydrates scene metadata into a Sprite-compatible runtime value.
     *
     * @param ReflectionProperty $property
     * @param mixed $value
     * @return Sprite|null
     */
    private static function hydrateSpritePropertyValue(ReflectionProperty $property, mixed $value): ?Sprite
    {
        if ($value instanceof Sprite) {
            return new Sprite(
                self::hydrateTexturePropertyValue($property, $value->getTexture()) ?? $value->getTexture(),
                $value->getRect(),
                Vector2::getClone($value->getPivot()),
            );
        }

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decodedValue = json_decode(trim($value), true);

            if (is_array($decodedValue)) {
                $value = $decodedValue;
            }
        }

        if (!is_array($value) && !is_object($value)) {
            return null;
        }

        $spriteMetadata = self::normalizeMetadata($value);
        $texture = self::hydrateTexturePropertyValue($property, $spriteMetadata->texture ?? null);
        $rect = self::hydrateRectPropertyValue($property, $spriteMetadata->rect ?? null);
        $pivot = self::hydrateVector2PropertyValue($property, $spriteMetadata->pivot ?? ['x' => 0, 'y' => 0]);

        if (!$texture instanceof Texture || !$rect instanceof Rect || !$pivot instanceof Vector2) {
            Debug::warn(sprintf(
                "Unable to hydrate Sprite property '%s::%s' from scene metadata; falling back to null.",
                $property->getDeclaringClass()->getName(),
                $property->getName(),
            ));

            return null;
        }

        return new Sprite($texture, $rect, $pivot);
    }

    /**
     * Resolves the enum type declared on a property, if any.
     *
     * @param ReflectionProperty $property
     * @return class-string<\UnitEnum>|null
     */
    private static function resolvePropertyEnumClass(ReflectionProperty $property): ?string
    {
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            return !$type->isBuiltin() && enum_exists($typeName)
                ? $typeName
                : null;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $namedType) {
                if (!$namedType instanceof ReflectionNamedType || $namedType->isBuiltin()) {
                    continue;
                }

                $typeName = $namedType->getName();

                if (enum_exists($typeName)) {
                    return $typeName;
                }
            }
        }

        return null;
    }

    /**
     * Hydrates scalar metadata into an enum case.
     *
     * @param ReflectionProperty $property
     * @param class-string<\UnitEnum> $enumClass
     * @param mixed $value
     * @return \UnitEnum|null
     */
    private static function hydrateEnumPropertyValue(
        ReflectionProperty $property,
        string             $enumClass,
        mixed              $value,
    ): ?\UnitEnum
    {
        if ($value instanceof $enumClass) {
            return $value;
        }

        if ($value === null) {
            return self::propertyAllowsNull($property) ? null : (($enumClass::cases()[0] ?? null) ?: null);
        }

        if (
            is_subclass_of($enumClass, \BackedEnum::class)
            && (is_string($value) || is_int($value))
        ) {
            $resolvedCase = $enumClass::tryFrom($value);

            if ($resolvedCase instanceof \UnitEnum) {
                return $resolvedCase;
            }
        }

        if (is_string($value)) {
            $normalizedValue = strtoupper(str_replace([' ', '-'], '_', trim($value)));

            foreach ($enumClass::cases() as $case) {
                $caseName = strtoupper($case->name);

                if ($normalizedValue === $caseName) {
                    return $case;
                }

                if (method_exists($case, 'getPhoneticName')) {
                    $phoneticName = strtoupper(str_replace([' ', '-'], '_', $case->getPhoneticName()));

                    if ($normalizedValue === $phoneticName) {
                        return $case;
                    }
                }
            }
        }

        Debug::warn(sprintf(
            "Unable to hydrate enum property '%s::%s' as %s from scene metadata.",
            $property->getDeclaringClass()->getName(),
            $property->getName(),
            $enumClass,
        ));

        return self::propertyAllowsNull($property) ? null : (($enumClass::cases()[0] ?? null) ?: null);
    }

    /**
     * Determines whether the property can accept the specified builtin type.
     *
     * @param ReflectionProperty $property
     * @param string $typeName
     * @return bool
     */
    private static function propertyAcceptsBuiltinType(ReflectionProperty $property, string $typeName): bool
    {
        $type = $property->getType();
        $normalizedTypeName = strtolower(trim($typeName));

        if ($type instanceof ReflectionNamedType) {
            return $type->isBuiltin() && strtolower($type->getName()) === $normalizedTypeName;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $namedType) {
                if (
                    $namedType instanceof ReflectionNamedType
                    && $namedType->isBuiltin()
                    && strtolower($namedType->getName()) === $normalizedTypeName
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Hydrates list/array metadata using an optional @param ReflectionProperty $property
     * @param mixed $value
     * @param SceneInterface|null $sceneContext
     * @return array<mixed>
     * @var item type.
     *
     */
    private static function hydrateCollectionPropertyValue(
        ReflectionProperty $property,
        mixed              $value,
        ?SceneInterface    $sceneContext = null,
    ): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $decodedValue = json_decode(trim($value), true);

            if (is_array($decodedValue)) {
                $value = $decodedValue;
            }
        }

        if (is_object($value)) {
            $value = (array)$value;
        }

        if (!is_array($value)) {
            return [];
        }

        $itemType = self::resolveCollectionItemType($property);

        if (!is_string($itemType) || $itemType === '') {
            return $value;
        }

        $hydrated = [];

        foreach ($value as $key => $item) {
            $hydrated[$key] = self::hydrateValueForDeclaredType($itemType, $item, $sceneContext);
        }

        return $hydrated;
    }

    /**
     * Resolves @param ReflectionProperty $property
     * @return class-string|string|null
     * @var item types declared on array properties.
     *
     */
    private static function resolveCollectionItemType(ReflectionProperty $property): ?string
    {
        $docComment = $property->getDocComment();

        if (!is_string($docComment) || $docComment === '') {
            return null;
        }

        if (preg_match('/@var\s+([^\s]+)/', $docComment, $matches) !== 1) {
            return null;
        }

        $itemType = self::extractCollectionItemTypeExpression(trim($matches[1]));

        if ($itemType === null) {
            return null;
        }

        return self::resolveDocblockTypeReference($property->getDeclaringClass(), $itemType);
    }

    /**
     * Extracts a collection item type from a @param string $typeExpression
     * @return string|null
     * @var expression.
     *
     */
    private static function extractCollectionItemTypeExpression(string $typeExpression): ?string
    {
        $unionMembers = array_values(array_filter(array_map('trim', explode('|', $typeExpression))));

        foreach ($unionMembers as $unionMember) {
            if (strtolower($unionMember) === 'null') {
                continue;
            }

            if (preg_match('/^(.+)\[\]$/', $unionMember, $matches) === 1) {
                return trim($matches[1]);
            }

            if (preg_match('/^(?:array|list)<(.+)>$/', $unionMember, $matches) === 1) {
                $innerType = trim($matches[1]);
                $segments = array_values(array_filter(array_map('trim', explode(',', $innerType))));

                return $segments === [] ? null : end($segments);
            }
        }

        return null;
    }

    /**
     * Resolves a short docblock type against the declaring class imports.
     *
     * @param ReflectionClass $scope
     * @param string $typeReference
     * @return string|null
     */
    private static function resolveDocblockTypeReference(ReflectionClass $scope, string $typeReference): ?string
    {
        $normalizedTypeReference = trim($typeReference);

        if ($normalizedTypeReference === '') {
            return null;
        }

        if ($normalizedTypeReference[0] === '\\') {
            return ltrim($normalizedTypeReference, '\\');
        }

        if (in_array(strtolower($normalizedTypeReference), ['int', 'float', 'string', 'bool', 'array', 'mixed'], true)) {
            return strtolower($normalizedTypeReference);
        }

        if (str_contains($normalizedTypeReference, '\\')) {
            return ltrim($normalizedTypeReference, '\\');
        }

        $aliases = self::resolveClassImportAliases($scope);
        $normalizedAlias = strtolower($normalizedTypeReference);

        if (isset($aliases[$normalizedAlias])) {
            return $aliases[$normalizedAlias];
        }

        $namespace = $scope->getNamespaceName();

        return $namespace !== ''
            ? $namespace . '\\' . $normalizedTypeReference
            : $normalizedTypeReference;
    }

    /**
     * Parses simple use aliases for a reflected class file.
     *
     * @param ReflectionClass $scope
     * @return array<string, string>
     */
    private static function resolveClassImportAliases(ReflectionClass $scope): array
    {
        $scopeName = $scope->getName();

        if (array_key_exists($scopeName, self::$classImportAliasCache)) {
            return self::$classImportAliasCache[$scopeName];
        }

        $fileName = $scope->getFileName();

        if (!is_string($fileName) || !is_file($fileName)) {
            return self::$classImportAliasCache[$scopeName] = [];
        }

        $source = file_get_contents($fileName);

        if (!is_string($source) || $source === '') {
            return self::$classImportAliasCache[$scopeName] = [];
        }

        $aliases = [];

        if (preg_match_all('/^\s*use\s+([^;]+);/mi', $source, $matches) > 0) {
            foreach ($matches[1] as $importClause) {
                if (!is_string($importClause) || str_contains($importClause, '{')) {
                    continue;
                }

                $normalizedClause = trim($importClause);
                $alias = basename(str_replace('\\', '/', $normalizedClause));
                $typeReference = $normalizedClause;

                if (preg_match('/^(.+)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $normalizedClause, $aliasMatches) === 1) {
                    $typeReference = trim($aliasMatches[1]);
                    $alias = trim($aliasMatches[2]);
                }

                $aliases[strtolower($alias)] = ltrim($typeReference, '\\');
            }
        }

        return self::$classImportAliasCache[$scopeName] = $aliases;
    }

    /**
     * Hydrates a value according to a declared class or builtin type.
     *
     * @param string $declaredType
     * @param mixed $value
     * @param SceneInterface|null $sceneContext
     * @return mixed
     */
    private static function hydrateValueForDeclaredType(
        string          $declaredType,
        mixed           $value,
        ?SceneInterface $sceneContext = null,
    ): mixed
    {
        $normalizedType = ltrim(trim($declaredType), '\\');
        $builtinType = strtolower($normalizedType);

        if ($value === null) {
            return null;
        }

        return match ($builtinType) {
            'int' => (int)$value,
            'float' => (float)$value,
            'string' => is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_SLASHES),
            'bool' => (bool)$value,
            'array' => is_array($value) ? $value : (is_object($value) ? (array)$value : [$value]),
            default => self::hydrateDeclaredObjectType($normalizedType, $value, $sceneContext),
        };
    }

    /**
     * Hydrates non-builtin declared types.
     *
     * @param class-string|string $declaredType
     * @param mixed $value
     * @param SceneInterface|null $sceneContext
     * @return mixed
     */
    private static function hydrateDeclaredObjectType(
        string          $declaredType,
        mixed           $value,
        ?SceneInterface $sceneContext = null,
    ): mixed
    {
        if (enum_exists($declaredType)) {
            return self::hydrateEnumValueByClass($declaredType, $value);
        }

        if ($value instanceof $declaredType) {
            return $value;
        }

        if (is_a($declaredType, GameObject::class, true) && is_string($value)) {
            return self::loadPrefabFromPath($value);
        }

        if (is_a($declaredType, UIElement::class, true)) {
            if (!is_string($value) || !$sceneContext instanceof SceneInterface) {
                return null;
            }

            return self::resolveSceneUIElementReferenceByTypeName($sceneContext, $declaredType, $value);
        }

        if (is_a($declaredType, Vector2::class, true)) {
            $vectorPayload = self::extractVector2MetadataPayload($value);

            return is_array($vectorPayload)
                ? Vector2::fromArray($vectorPayload)
                : null;
        }

        if (is_a($declaredType, Rect::class, true)) {
            $rectPayload = self::extractRectMetadataPayload($value);

            return is_array($rectPayload)
                ? Rect::fromArray($rectPayload)
                : null;
        }

        if (is_a($declaredType, Texture::class, true)) {
            $texturePayload = self::extractTextureMetadataPayload($value);

            if (!is_array($texturePayload) || !is_string($texturePayload['path'] ?? null) || trim($texturePayload['path']) === '') {
                return null;
            }

            try {
                return new Texture(
                    $texturePayload['path'],
                    (int)($texturePayload['width'] ?? -1),
                    (int)($texturePayload['height'] ?? -1),
                    self::resolveColorMetadataValue($texturePayload['color'] ?? null),
                );
            } catch (Throwable) {
                return null;
            }
        }

        if (is_a($declaredType, Sprite::class, true)) {
            if (is_string($value)) {
                $decodedValue = json_decode(trim($value), true);

                if (is_array($decodedValue)) {
                    $value = $decodedValue;
                }
            }

            if (!is_array($value) && !is_object($value)) {
                return null;
            }

            $spriteMetadata = self::normalizeMetadata($value);
            $texture = self::hydrateDeclaredObjectType(Texture::class, $spriteMetadata->texture ?? null, $sceneContext);
            $rect = self::hydrateDeclaredObjectType(Rect::class, $spriteMetadata->rect ?? null, $sceneContext);
            $pivot = self::hydrateDeclaredObjectType(Vector2::class, $spriteMetadata->pivot ?? ['x' => 0, 'y' => 0], $sceneContext);

            return $texture instanceof Texture && $rect instanceof Rect && $pivot instanceof Vector2
                ? new Sprite($texture, $rect, $pivot)
                : null;
        }

        if (self::isCompoundStructureType($declaredType)) {
            return self::hydrateCompoundValueByClass($declaredType, $value, $sceneContext);
        }

        return $value;
    }

    /**
     * Hydrates enum values outside the context of a reflected property.
     *
     * @param class-string<\UnitEnum> $enumClass
     * @param mixed $value
     * @return \UnitEnum|null
     */
    private static function hydrateEnumValueByClass(string $enumClass, mixed $value): ?\UnitEnum
    {
        if ($value instanceof $enumClass) {
            return $value;
        }

        if (is_subclass_of($enumClass, \BackedEnum::class) && (is_string($value) || is_int($value))) {
            $resolvedCase = $enumClass::tryFrom($value);

            if ($resolvedCase instanceof \UnitEnum) {
                return $resolvedCase;
            }
        }

        if (is_string($value)) {
            $normalizedValue = strtoupper(str_replace([' ', '-'], '_', trim($value)));

            foreach ($enumClass::cases() as $case) {
                if ($normalizedValue === strtoupper($case->name)) {
                    return $case;
                }

                if (method_exists($case, 'getPhoneticName')) {
                    $phoneticName = strtoupper(str_replace([' ', '-'], '_', $case->getPhoneticName()));

                    if ($normalizedValue === $phoneticName) {
                        return $case;
                    }
                }
            }
        }

        return $enumClass::cases()[0] ?? null;
    }

    /**
     * Resolves a UI element by name and declared type.
     *
     * @param SceneInterface $sceneContext
     * @param class-string|string $declaredType
     * @param string $referenceName
     * @return UIElement|null
     */
    private static function resolveSceneUIElementReferenceByTypeName(
        SceneInterface $sceneContext,
        string         $declaredType,
        string         $referenceName,
    ): ?UIElement
    {
        foreach ($sceneContext->getUIElements() as $uiElement) {
            if (
                !$uiElement instanceof UIElement
                || $uiElement->getName() !== $referenceName
                || !is_a($uiElement::class, $declaredType, true)
            ) {
                continue;
            }

            return $uiElement;
        }

        return null;
    }

    /**
     * Determines whether a class should be treated as a compound structure.
     *
     * @param class-string|string $typeName
     * @return bool
     */
    private static function isCompoundStructureType(string $typeName): bool
    {
        $normalizedType = ltrim(trim($typeName), '\\');

        if (
            $normalizedType === ''
            || !class_exists($normalizedType)
            || interface_exists($normalizedType)
            || enum_exists($normalizedType)
            || is_a($normalizedType, GameObject::class, true)
            || is_a($normalizedType, UIElement::class, true)
            || is_a($normalizedType, 'Sendama\\Engine\\Core\\Component', true)
        ) {
            return false;
        }

        if (in_array($normalizedType, [
            Vector2::class,
            Rect::class,
            Texture::class,
            Sprite::class,
        ], true)) {
            return false;
        }

        return true;
    }

    /**
     * Instantiates and hydrates a compound object by class.
     *
     * @param class-string $compoundClass
     * @param mixed $value
     * @param SceneInterface|null $sceneContext
     * @return object|null
     */
    private static function hydrateCompoundValueByClass(
        string          $compoundClass,
        mixed           $value,
        ?SceneInterface $sceneContext = null,
    ): ?object
    {
        if ($value instanceof $compoundClass) {
            return $value;
        }

        if (is_string($value)) {
            $decodedValue = json_decode(trim($value), true);

            if (is_array($decodedValue)) {
                $value = $decodedValue;
            }
        }

        if (is_object($value)) {
            $value = (array)$value;
        }

        if (!is_array($value)) {
            return null;
        }

        $instance = self::instantiateCompoundStructure($compoundClass);

        if (!is_object($instance)) {
            return null;
        }

        $reflection = new ReflectionObject($instance);

        foreach ($reflection->getProperties() as $property) {
            if (
                $property->isStatic()
                || (!$property->isPublic() && !$property->getAttributes(SerializeField::class))
                || (method_exists($property, 'isVirtual') && $property->isVirtual())
                || !array_key_exists($property->getName(), $value)
            ) {
                continue;
            }

            $assignment = self::resolveSceneComponentPropertyAssignment(
                $property,
                $value[$property->getName()],
                $sceneContext,
            );

            if (($assignment['shouldAssign'] ?? true) !== true || !array_key_exists('value', $assignment)) {
                continue;
            }

            try {
                $property->setValue($instance, $assignment['value']);
            } catch (Throwable) {
                continue;
            }
        }

        return $instance;
    }

    /**
     * Creates an empty instance of a compound structure.
     *
     * @param class-string $compoundClass
     * @return object|null
     */
    private static function instantiateCompoundStructure(string $compoundClass): ?object
    {
        try {
            $reflection = new ReflectionClass($compoundClass);

            if (!$reflection->isInstantiable()) {
                return null;
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
                return $reflection->newInstance();
            }

            return $reflection->newInstanceWithoutConstructor();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolves a compound class declared on a property, if that class can be hydrated from metadata.
     *
     * @param ReflectionProperty $property
     * @return class-string|null
     */
    private static function resolvePropertyCompoundClass(ReflectionProperty $property): ?string
    {
        $candidateTypes = [];
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $candidateTypes[] = $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $namedType) {
                if ($namedType instanceof ReflectionNamedType && !$namedType->isBuiltin()) {
                    $candidateTypes[] = $namedType->getName();
                }
            }
        }

        foreach (array_values(array_unique(array_filter($candidateTypes))) as $candidateType) {
            if (self::isCompoundStructureType($candidateType)) {
                return $candidateType;
            }
        }

        return null;
    }

    /**
     * Hydrates metadata into a custom compound structure.
     *
     * @param ReflectionProperty $property
     * @param class-string $compoundClass
     * @param mixed $value
     * @param SceneInterface|null $sceneContext
     * @return object|null
     */
    private static function hydrateCompoundPropertyValue(
        ReflectionProperty $property,
        string             $compoundClass,
        mixed              $value,
        ?SceneInterface    $sceneContext = null,
    ): ?object
    {
        if ($value === null) {
            return self::propertyAllowsNull($property)
                ? null
                : self::instantiateCompoundStructure($compoundClass);
        }

        $hydrated = self::hydrateCompoundValueByClass($compoundClass, $value, $sceneContext);

        if (is_object($hydrated)) {
            return $hydrated;
        }

        Debug::warn(sprintf(
            "Unable to hydrate compound property '%s::%s' as %s from scene metadata.",
            $property->getDeclaringClass()->getName(),
            $property->getName(),
            $compoundClass,
        ));

        return self::propertyAllowsNull($property)
            ? null
            : self::instantiateCompoundStructure($compoundClass);
    }

    /**
     * Resolves deferred scene component property references once the scene hierarchy has been added.
     *
     * @param array<int, array{component: object, property: ReflectionProperty, referenceName: string}> $pendingComponentPropertyAssignments
     * @param SceneInterface $sceneContext
     * @return void
     */
    public static function resolvePendingSceneComponentPropertyAssignments(
        array          $pendingComponentPropertyAssignments,
        SceneInterface $sceneContext,
    ): void
    {
        foreach ($pendingComponentPropertyAssignments as $assignment) {
            $component = $assignment['component'] ?? null;
            $property = $assignment['property'] ?? null;
            $referenceName = $assignment['referenceName'] ?? null;

            if (
                !is_object($component)
                || !$property instanceof ReflectionProperty
                || !is_string($referenceName)
                || $referenceName === ''
            ) {
                continue;
            }

            $resolvedReference = self::resolveSceneUIElementReferenceByName(
                $sceneContext,
                $property,
                $referenceName,
            );

            if (!$resolvedReference instanceof UIElement) {
                Debug::warn(sprintf(
                    "Unable to resolve UI element reference '%s' for %s::%s during scene hydration.",
                    $referenceName,
                    $property->getDeclaringClass()->getName(),
                    $property->getName(),
                ));
                continue;
            }

            $property->setValue($component, $resolvedReference);
        }
    }

    /**
     * Adds a scene to the SceneManager.
     *
     * @param SceneInterface $scene The scene to add.
     * @param mixed|null $data The data to associate with the scene.
     * @return $this The SceneManager instance.
     */
    public function addScene(SceneInterface $scene, mixed $data = null): self
    {
        $this->scenes->add($scene);

        return $this;
    }
}
