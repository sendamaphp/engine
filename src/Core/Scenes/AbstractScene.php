<?php

namespace Sendama\Engine\Core\Scenes;

use Sendama\Engine\Core\Grid;
use Sendama\Engine\Core\Interfaces\GameObjectInterface;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Rendering\Camera;
use Sendama\Engine\Core\Rendering\Interfaces\CameraInterface;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Debug\Debug;
use Sendama\Engine\Exceptions\FileNotFoundException;
use Sendama\Engine\Physics\Interfaces\ColliderInterface;
use Sendama\Engine\Physics\Physics;
use Sendama\Engine\UI\Interfaces\UIElementInterface;
use Sendama\Engine\Util\Path;

/**
 * The abstract scene class.
 *
 * @package Sendama\Engine\Core\Scenes
 */
abstract class AbstractScene implements SceneInterface
{
    /**
     * @const string MAP_FILE_EXTENSION
     */
    const string MAP_FILE_EXTENSION = '.tmap';
    /**
     * @var array<GameObjectInterface> $rootGameObjects
     */
    public array $rootGameObjects = [];
    /**
     * @var array<UIElementInterface> $uiElements
     */
    public array $uiElements = [];
    /**
     * @var array<string, mixed> $settings
     */
    protected array $settings = [];
    /**
     * @var Physics
     */
    protected Physics $physics;

    /**
     * @var Grid $worldsSpace
     */
    protected Grid $worldsSpace;

    /**
     * @var Grid $collisionWorldSpace
     */
    protected Grid $collisionWorldSpace;

    /**
     * @var CameraInterface $camera
     */
    protected CameraInterface $camera;
    /**
     * @var string $environmentTileMapPath
     */
    protected string $environmentTileMapPath = '';
    /**
     * @var string $environmentTileMapPath
     */
    protected string $environmentTileMapData = '';

    /**
     * @var bool $started
     */
    protected bool $started = false;

    /**
     * Constructs a scene.
     *
     * @param string $name The name of the scene.
     * @throws FileNotFoundException
     */
    public final function __construct(protected string $name, protected ?object $sceneMetadata = null)
    {
        $this->worldsSpace = new Grid();
        $this->collisionWorldSpace = new Grid();
        $this->physics = Physics::getInstance();
        $this->camera = new Camera($this);

        $this->awake();

        if ($this->environmentTileMapPath) {
            $this->loadEnvironmentTileMapData();
        }
    }

    /**
     * Called when the scene is awake.
     */
    public abstract function awake(): void;

    /**
     * Loads the environment tile map data from a file on disk.
     *
     * @param string|null $path The path to the environment tile map file.
     * @return void
     * @throws FileNotFoundException If the file does not exist.
     */
    private function loadEnvironmentTileMapData(?string $path = null): void
    {
        Debug::info("Loading environment tile map data: $path");
        // Check if the file exists
        if (!file_exists($this->getAbsoluteEnvironmentTileMapPath())) {
            throw new FileNotFoundException($this->getAbsoluteEnvironmentTileMapPath());
        }

        if (!is_file($this->getAbsoluteEnvironmentTileMapPath())) {
            throw new FileNotFoundException($this->getAbsoluteEnvironmentTileMapPath());
        }

        // Get the contents of the file
        $this->environmentTileMapData = file_get_contents($this->getAbsoluteEnvironmentTileMapPath());
    }

    /**
     * Returns the absolute path to the environment tile map file.
     *
     * @return string The absolute path to the environment tile map file.
     */
    private function getAbsoluteEnvironmentTileMapPath(): string
    {
        return Path::join(Path::getWorkingDirectoryAssetsPath(), $this->environmentTileMapPath) . self::MAP_FILE_EXTENSION;
    }

    /**
     * @inheritDoc
     */
    public function load(): void
    {
        // Do nothing. This method is meant to be overridden.
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        // Do nothing. This method is meant to be overridden.
    }

    /**
     * @inheritDoc
     */
    public final function renderAt(?int $x = null, ?int $y = null): void
    {
        $this->camera->renderAt($x, $y);
    }

    /**
     * @inheritDoc
     */
    public final function eraseAt(?int $x = null, ?int $y = null): void
    {
        // Do nothing.
    }

    /**
     * @inheritDoc
     */
    public final function loadSceneSettings(?array $settings = null): self
    {
        foreach ($settings as $key => $value) {
            $this->settings[$key] = $value;
        }

        if (isset($this->settings['screen_width']) && isset($this->settings['screen_height'])) {
            $oldViewport = $this->camera->getViewport();
            $this->camera->setViewport(
                new Rect(
                    $this->camera->getOffset(),
                    new Vector2(
                        $this->settings['screen_width'] ?? $oldViewport->getWidth(),
                        $this->settings['screen_height'] ?? $oldViewport->getHeight()
                    )
                )
            );
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public final function update(): void
    {
        foreach ($this->rootGameObjects as $gameObject) {
            if ($gameObject->isActive()) {
                $gameObject->update();
            }
        }

        foreach ($this->uiElements as $uiElement) {
            if ($uiElement->isActive()) {
                $uiElement->update();
            }
        }

        // Update the camera
        $this->camera->update();
    }

    /**
     * @inheritDoc
     */
    public function updatePhysics(): void
    {
        foreach ($this->rootGameObjects as $gameObject) {
            if ($gameObject->isActive()) {
                $gameObject->fixedUpdate();
            }
        }
        $this->physics->simulate();
    }

    /**
     * @inheritDoc
     */
    public final function render(): void
    {
        $this->camera->render();
    }

    /**
     * @inheritDoc
     */
    public final function erase(): void
    {
        $this->camera->erase();
    }

    /**
     * @inheritDoc
     */
    public final function suspend(): void
    {
        Debug::info('Scene suspended: ' . $this->name);
        foreach ($this->rootGameObjects as $gameObject) {
            if ($gameObject->isActive()) {
                $gameObject->suspend();
            }
        }

        foreach ($this->uiElements as $uiElement) {
            if ($uiElement->isActive()) {
                $uiElement->suspend();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public final function resume(): void
    {
        Debug::info('Scene resumed: ' . $this->name);
        $this->camera->renderWorldSpace();

        foreach ($this->rootGameObjects as $gameObject) {
            if ($gameObject->isActive()) {
                $gameObject->resume();
            }
        }

        foreach ($this->uiElements as $uiElement) {
            if ($uiElement->isActive()) {
                $uiElement->resume();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public final function getRootGameObjects(): array
    {
        return $this->rootGameObjects;
    }

    /**
     * @inheritDoc
     */
    public final function getUIElements(): array
    {
        return $this->uiElements;
    }

    /**
     * @inheritDoc
     */
    public function serialize(): string
    {
        return json_encode([
            'name' => $this->name,
            'settings' => $this->settings,
            'root_game_objects' => $this->rootGameObjects,
            'ui_elements' => $this->uiElements,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function unserialize(string $data): void
    {
        $data = json_decode($data, true);

        $this->name = $data['name'];
        $this->settings = $data['settings'];
        $this->rootGameObjects = $data['root_game_objects'];
        $this->uiElements = $data['ui_elements'];
    }

    /**
     * Serializes the scene.
     *
     * @return array The serialized scene.
     */
    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'settings' => $this->settings,
            'root_game_objects' => $this->rootGameObjects,
            'ui_elements' => $this->uiElements,
        ];
    }

    /**
     * Deserializes the scene.
     *
     * @param array $data The data to unserialize.
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->settings = $data['settings'];
        $this->rootGameObjects = $data['root_game_objects'];
        $this->uiElements = $data['ui_elements'];
    }

    /**
     * @inheritDoc
     */
    public function getWorldSpace(): Grid
    {
        return $this->worldsSpace;
    }

    /**
     * Returns the collision world space.
     *
     * @return Grid The collision world space.
     */
    public function getCollisionWorldSpace(): Grid
    {
        return $this->collisionWorldSpace;
    }

    /**
     * @inheritDoc
     */
    public function add(GameObjectInterface|UIElementInterface $object): void
    {
        Debug::info('Adding game object ' . $object->getName());
        if ($object instanceof GameObjectInterface) {
            $this->rootGameObjects[] = $object;
            if ($collider = $object->getComponent(ColliderInterface::class)) {
                $this->physics->addCollider($collider);
            }
        } else {
            $this->uiElements[] = $object;
        }

        if ($this->isStarted()) {
            $object->start();
        }
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Starts the scene.
     *
     * @inheritDoc
     */
    public final function start(): void
    {
        Debug::info("Scene started: " . $this->name);

        $this->createWorldSpace();
        $this->loadStaticEnvironment();

        foreach ($this->rootGameObjects as $gameObject) {
            $gameObject->start();
        }

        foreach ($this->uiElements as $uiElement) {
            $uiElement->start();
        }

        $this->started = true;
    }

    /**
     * Creates the world space.
     */
    private function createWorldSpace(): void
    {
        Debug::info('Creating world space for ' . $this->name);
        $width = $this->settings['screen_width'];
        $height = $this->settings['screen_height'];

        $this->worldsSpace = new Grid($width, $height, ' ');
        $this->collisionWorldSpace = new Grid($width, $height, 0);
    }

    private function loadStaticEnvironment(): void
    {
        Debug::info('Loading static environment for ' . $this->name);

        // Return if no tile map data
        if (!$this->environmentTileMapData) {
            Debug::warn("No environment tile map data found for " . $this->name);
            return;
        }

        // Parse the tile map data
        $buffer = new Grid();
        $lines = explode("\n", $this->environmentTileMapData);

        foreach ($lines as $y => $line) {
            $lineLength = strlen($line);
            for ($x = 0; $x < $lineLength; $x++) {
                $buffer->set($x, $y, $line[$x]);
            }
        }

        // Fill the world space with the static tiles
        $this->setWorldSpace($buffer);

        $this->camera->renderWorldSpace();
    }

    /**
     * Sets the world space.
     *
     * @param Grid $worldSpace The new world space.
     * @return void
     */
    private function setWorldSpace(Grid $worldSpace): void
    {
        Debug::info('Setting world space for ' . $this->name);
        $this->worldsSpace = $worldSpace;
    }

    /**
     * @inheritDoc
     */
    public function remove(UIElementInterface|GameObjectInterface $object): void
    {
        Debug::info('Removing game object ' . $object->getName());
        if ($object instanceof GameObjectInterface) {
            $this->rootGameObjects = array_filter($this->rootGameObjects, fn($item) => $item !== $object, $this->rootGameObjects);
            if ($collider = $object->getComponent('Collider')) {
                $this->physics->removeCollider($collider);
            }
        } else {
            $this->uiElements = array_filter($this->uiElements, fn($item) => $item !== $object, $this->uiElements);
        }

        if ($this->isStopped()) {
            $object->stop();
        }
    }

    /**
     * @inheritDoc
     */
    public function isStopped(): bool
    {
        return !$this->isStarted();
    }

    /**
     * Stops the scene.
     *
     * @inheritDoc
     */
    public final function stop(): void
    {
        Debug::info("Scene stopped: " . $this->name);

        foreach ($this->rootGameObjects as $gameObject) {
            $gameObject->stop();
        }

        foreach ($this->uiElements as $uiElement) {
            $uiElement->stop();
        }

        $this->getCamera()->clearScreen();

        $this->started = false;
    }

    /**
     * Returns the camera.
     *
     * @return CameraInterface The camera.
     */
    public function getCamera(): CameraInterface
    {
        return $this->camera;
    }

    /**
     * @inheritDoc
     */
    public function getSettings(?string $key): mixed
    {
        return $this->settings[$key] ?? $this->settings;
    }

    /**
     * @inheritDoc
     */
    public function getSceneManager(): SceneManager
    {
        return SceneManager::getInstance();
    }

    /**
     * Sets the collision world space.
     *
     * @param Grid $worldSpace The new collision world space.
     * @return void
     */
    private function setCollisionWorldSpace(Grid $worldSpace): void
    {
        $this->collisionWorldSpace = $worldSpace;
    }
}