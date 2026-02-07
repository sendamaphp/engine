<?php

namespace Sendama\Engine\Core\Scenes;

use Assegai\Collections\ItemList;
use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Interfaces\CanLoad;
use Sendama\Engine\Core\Interfaces\CanRender;
use Sendama\Engine\Core\Interfaces\CanResume;
use Sendama\Engine\Core\Interfaces\CanStart;
use Sendama\Engine\Core\Interfaces\CanUpdate;
use Sendama\Engine\Core\Interfaces\SingletonInterface;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Scenes\Interfaces\SceneNodeInterface;
use Sendama\Engine\Core\Texture;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Debug\Debug;
use Sendama\Engine\Events\Enumerations\SceneEventType;
use Sendama\Engine\Events\EventManager;
use Sendama\Engine\Events\SceneEvent;
use Sendama\Engine\Exceptions\IncorrectComponentTypeException;
use Sendama\Engine\Exceptions\Scenes\SceneManagementException;
use Sendama\Engine\Exceptions\Scenes\SceneNotFoundException;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\UI\Label\Label;
use Sendama\Engine\UI\Text\Text;
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
     * Returns the currently active scene.
     *
     * @return SceneInterface|null
     */
    public function getActiveScene(): ?SceneInterface
    {
        return $this->activeSceneNode?->getScene();
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
        $this->activeSceneNode = new SceneNode($sceneToBeLoaded->loadSceneSettings($this->settings), $this->activeSceneNode);
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
     * Returns the settings for the SceneManager.
     *
     * @param string|null $key
     * @return mixed
     */
    public function getSettings(?string $key = null): mixed
    {
        return $this->settings[$key] ?? $this->settings;
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
    public function unload(): void
    {
        $this->activeSceneNode?->getScene()->unload();
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
            throw new SceneNotFoundException($path);
        }

        $sceneMetadata = require($filename);
        $sceneMetadata = json_decode(json_encode($sceneMetadata, JSON_UNESCAPED_SLASHES), false);

        $sceneName = $sceneMetadata->name ?? basename($path);

        $scene = new class($sceneName, $sceneMetadata) extends AbstractScene {
            public function awake(): void
            {
                $sceneMetadata = $this->sceneMetadata;

                if (isset($sceneMetadata->environmentTileMapPath)) {
                    $this->environmentTileMapPath = $sceneMetadata->environmentTileMapPath;
                }

                // Build hierarchy
                if (isset($sceneMetadata->hierarchy)) {
                    foreach ($sceneMetadata->hierarchy as $index => $item) {
                        if (!isset($item->type)) {
                            Debug::warn("The 'type' property is not supported in scene hierarchy items. Item: " . ($item->name ?? "Unnamed GameObject") . " - $index");
                            continue;
                        }

                        $itemName = $item?->name . " - $index" ?? throw new SceneManagementException("Invalid game object name");

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
                                $rotation = new Vector2();
                                if (isset($item->rotation)) {
                                    $rotation = Vector2::fromArray((array)$item->rotation);
                                }

                                $scale = new Vector2();
                                if (isset($item->scale)) {
                                    $scale = Vector2::fromArray((array)$item->scale);
                                }

                                $gameObject = new GameObject(
                                    $itemName,
                                    $item?->tag,
                                    $position,
                                    $rotation,
                                    $scale
                                );

                                if (isset($item->sprite)) {
                                    if (!isset($item->sprite->texture)) {
                                        throw new SceneManagementException("Sprite texture not defined for game object: " . $gameObject->getName());
                                    }

                                    $spriteTextureMetadata = $item->sprite->texture;
                                    $spriteTexture = new Texture($spriteTextureMetadata->path ?? throw new SceneManagementException("Invalid sprite texture path"));
                                    $spritePosition = new Vector2();
                                    if (isset($spriteTextureMetadata->position)) {
                                        $spritePosition = Vector2::fromArray((array)$spriteTextureMetadata->position);
                                    }
                                    $spriteSize = new Vector2();
                                    if (isset($spriteTextureMetadata->size)) {
                                        $spriteSize = Vector2::fromArray((array)$spriteTextureMetadata->size);
                                    }

                                    $gameObject->setSpriteFromTexture($spriteTexture, $spritePosition, $spriteSize);
                                }

                                if (isset($item->components)) {
                                    foreach ($item->components as $componentMetadata) {
                                        if (!isset($componentMetadata->class)) {
                                            throw new SceneManagementException("Component class not defined for game object: " . $gameObject->getName());
                                        }

                                        $componentClass = $componentMetadata->class;
                                        $component = $gameObject->addComponent($componentClass);

                                        if (isset($componentMetadata->proerties)) {
                                            foreach ($componentMetadata->proerties as $key => $value) {
                                                if (!property_exists($component, $key)) {
                                                    Debug::warn("Property '$key' does not exist on component of type: " . $componentClass);
                                                    continue;
                                                }

                                                $component->$key = $value;
                                            }
                                        }
                                    }
                                }

                                break;

                            default:
                                $gameObject = match($item->type) {
                                    Label::class => new Label($this, $itemName, $position, $size),
                                    Text::class => new Text($this, $itemName, $position, $size)
                                };

                                if (isset($item->text)) {
                                    if (!method_exists($gameObject, 'setText')) {
                                        throw new SceneManagementException("The 'text' property is not supported for game object of type: " . $item->type);
                                    }

                                    $gameObject->setText($item->text);
                                }
                        }

                        $this->add($gameObject);
                    }
                }
            }
        };

        $this->addScene($scene);
    }
}