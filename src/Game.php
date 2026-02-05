<?php

namespace Sendama\Engine;

use Assegai\Collections\ItemList;
use Dotenv\Dotenv;
use Error;
use Exception;
use Sendama\Engine\Core\Enumerations\ChronoUnit;
use Sendama\Engine\Core\Enumerations\SettingsKey;
use Sendama\Engine\Core\Rendering\SplashScreen;
use Sendama\Engine\Core\Scenes\Interfaces\SceneInterface;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Time;
use Sendama\Engine\Debug\Debug;
use Sendama\Engine\Debug\Enumerations\LogLevel;
use Sendama\Engine\Events\Enumerations\EventType;
use Sendama\Engine\Events\Enumerations\GameEventType;
use Sendama\Engine\Events\EventManager;
use Sendama\Engine\Events\GameEvent;
use Sendama\Engine\Events\Interfaces\EventInterface;
use Sendama\Engine\Events\Interfaces\ObservableInterface;
use Sendama\Engine\Events\Interfaces\ObserverInterface;
use Sendama\Engine\Events\Interfaces\StaticObserverInterface;
use Sendama\Engine\Exceptions\InitializationException;
use Sendama\Engine\Exceptions\IOException;
use Sendama\Engine\Exceptions\Scenes\SceneNotFoundException;
use Sendama\Engine\Interfaces\GameStateInterface;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Console\Cursor;
use Sendama\Engine\IO\Enumerations\KeyCode;
use Sendama\Engine\IO\InputManager;
use Sendama\Engine\Messaging\Notifications\NotificationsManager;
use Sendama\Engine\States\GameStateContext;
use Sendama\Engine\States\ModalState;
use Sendama\Engine\States\PausedState;
use Sendama\Engine\States\SceneState;
use Sendama\Engine\UI\Modals\ModalManager;
use Sendama\Engine\UI\UIManager;
use Sendama\Engine\UI\Windows\Window;
use Sendama\Engine\Util\Config\AppConfig;
use Sendama\Engine\Util\Config\ConfigStore;
use Sendama\Engine\Util\Config\InputConfig;
use Sendama\Engine\Util\Config\PlayerPreferences;
use Sendama\Engine\Util\Path;
use Throwable;

/**
 * The main Game engine class.
 *
 * @package Sendama\Engine;
 */
class Game implements ObservableInterface
{
    const int DEBUG_WINDOW_HEIGHT = 5;
    /**
     * @var SceneState $sceneState
     */
    protected SceneState $sceneState;
    /**
     * @var ModalState $modalState
     */
    protected ModalState $modalState;
    /**
     * @var PausedState $pausedState
     */
    protected PausedState $pausedState;
    /**
     * @var GameStateInterface|null $previousState The previous state of the game.
     */
    protected ?GameStateInterface $previousState = null;
    /**
     * @var array<string, mixed>
     */
    private array $settings = [];

    /* == Managers == */
    /**
     * @var ItemList<ObserverInterface>
     */
    private ItemList $observers;
    /**
     * @var ItemList<StaticObserverInterface>
     */
    private ItemList $staticObservers;
    /**
     * @var int The number of frames that have been rendered.
     */
    private int $frameCount = 0;
    /**
     * @var int The frame rate of the game.
     */
    private int $frameRate = 0;
    /**
     * @var SceneManager $sceneManager
     */
    private SceneManager $sceneManager;
    /**
     * @var EventManager $eventManager
     */
    private EventManager $eventManager;
    /**
     * @var ModalManager $modalManager
     */
    private ModalManager $modalManager;

    /* Sentinel properties */
    /**
     * @var NotificationsManager $notificationsManager
     */
    private NotificationsManager $notificationsManager;
    /**
     * @var UIManager $uiManager
     */
    private UIManager $uiManager;
    /**
     * @var Cursor $consoleCursor
     */
    private Cursor $consoleCursor;
    /**
     * @var Window $debugWindow
     */
    private Window $debugWindow;
    /**
     * @var bool Determines if the game engine is running.
     */
    private bool $isRunning = false;
    /**
     * @var bool Determines if a modal is showing or not.
     */
    private bool $isShowingModal = false;
    /**
     * @var GameStateInterface $state
     */
    private GameStateInterface $state;

    private SplashScreen $splashScreen;

    /**
     * Game constructor.
     *
     * @param string $name The name of the game.
     * @param int $screenWidth The width of the game screen.
     * @param int $screenHeight The height of the game screen.
     * @throws Exception
     */
    public function __construct(private readonly string $name, private readonly int $screenWidth = DEFAULT_SCREEN_WIDTH, private readonly int $screenHeight = DEFAULT_SCREEN_HEIGHT, private readonly ?string $workingDirectory = null)
    {
        try {
            $this->initializeObservers();
            $this->configureErrorAndExceptionHandlers();
            $this->initializeConsole();
            $this->initializeConfigStore();
            $this->initializeManagers();
            $this->initializeSettings();
            $this->initializeGameStates();
            $this->configureWindowChangeSignalHandler();
            $this->splashScreen = new SplashScreen($this->consoleCursor, $this->settings);
        } catch (Error|Throwable $exception) {
            $this->handleException($exception);
        }
    }

    /**
     * @return void
     */
    protected function initializeObservers(): void
    {
        $this->observers = new ItemList(ObserverInterface::class);
        $this->staticObservers = new ItemList(StaticObserverInterface::class);
    }

    /**
     * Configure error and exception handlers.
     *
     * @return void
     * @throws IOException
     */
    protected function configureErrorAndExceptionHandlers(): void
    {
        error_reporting(E_ALL);

        set_exception_handler(function (Throwable|Exception|Error $exception) {
            $this->handleException($exception);
        });

        // Handle errors
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            $this->handleError($errno, $errstr, $errfile, $errline);
        });

        $this->debugWindow = new Window();
    }

    /**
     * Handle game exceptions.
     *
     * @param Throwable|Error $exception The exception to be handled.
     * @return never
     * @throws IOException
     */
    private function handleException(Throwable|Error $exception): never
    {
        Debug::error($exception);
        $this->stop();

        if ($this->getSettings('debug')) {
            exit($exception);
        }

        exit("$exception\n");
    }

    /**
     * Stop the game.
     *
     * @return void
     * @throws IOException
     */
    public function stop(): void
    {
        Console::reset();

        Debug::info("Stopping game");

        // Disable non-blocking input mode
        InputManager::disableNonBlockingMode();

        // Enable echo
        InputManager::enableEcho();

        // Show cursor
        $this->consoleCursor->show();

        // Restore the terminal settings
        Console::restoreSettings();

        // Remove observers
        $this->removeObservers();

        // Stop the game
        $this->isRunning = false;

        // Notify listeners that the game has stopped
        $this->notify(new GameEvent(GameEventType::STOP));

        Debug::info("Game stopped");
    }

    /**
     * @inheritDoc
     */
    public function removeObservers(ObserverInterface|StaticObserverInterface|string|null ...$observers): void
    {
        if (is_null($observers)) {
            $this->observers->clear();
            $this->staticObservers->clear();
            return;
        }

        foreach ($observers as $observer) {
            if ($observer instanceof ObserverInterface) {
                $this->observers->remove($observer);
            } else {
                $this->staticObservers->remove($observer);
            }
        }
    }

    /**
     * @inheritDoc
     *
     * @throws IOException
     */
    public function notify(EventInterface $event): void
    {
        try {
            /** @var ObserverInterface $observer */
            foreach ($this->observers as $observer) {
                $observer->onNotify($this, $event);
            }

            /** @var StaticObserverInterface $observer */
            foreach ($this->staticObservers as $observer) {
                $observer::onNotify($this, $event);
            }
        } catch (Exception $exception) {
            $this->handleException($exception);
        }
    }

    /**
     * Retrieve game settings.
     *
     * @param string|SettingsKey|null $key The key of the setting to retrieve.
     * @return mixed The game settings.
     */
    public function getSettings(string|SettingsKey|null $key = null): mixed
    {
        $key = match (true) {
            is_null($key) => null,
            is_string($key) => $key,
            default => $key->value
        };
        return $this->settings[$key] ?? $this->settings;
    }

    /**
     * Handles game errors.
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return never
     * @throws IOException
     */
    private function handleError(int $errno, string $errstr, string $errfile, int $errline): never
    {
        $errorMessage = "[$errno] $errstr in $errfile on line $errline";
        Debug::error($errorMessage);
        $this->stop();

        if ($this->getSettings('debug')) {
            exit($errorMessage);
        }

        exit($errno);
    }

    /**
     * @return void
     */
    protected function initializeConsole(): void
    {
        $this->consoleCursor = Console::cursor();
        Console::init($this, []);
    }

    /**
     * @return void
     */
    protected function initializeConfigStore(): void
    {
        ConfigStore::put(AppConfig::class, new AppConfig());
        ConfigStore::put(InputConfig::class, new InputConfig());
        ConfigStore::put(PlayerPreferences::class, new PlayerPreferences());
    }

    /**
     * @return void
     */
    protected function initializeManagers(): void
    {
        $this->sceneManager = SceneManager::getInstance();
        $this->eventManager = EventManager::getInstance();
        $this->modalManager = ModalManager::getInstance();
        $this->notificationsManager = NotificationsManager::getInstance();
        $this->uiManager = UIManager::getInstance();
    }

    /**
     * Initialize game settings.
     *
     * @return void
     */
    private function initializeSettings(): void
    {
        // Load environment variables
        if (file_exists($this->workingDirectory ?? getcwd() . '/.env')) {
            $dotenv = Dotenv::createImmutable(getcwd());
            $dotenv->load();
        }

        $this->settings[SettingsKey::GAME_NAME->value] = $_ENV['GAME_NAME'] ?? $this->name;
        $this->settings[SettingsKey::SCREEN_WIDTH->value] = $this->screenWidth;
        $this->settings[SettingsKey::SCREEN_HEIGHT->value] = $this->screenHeight;
        $this->settings[SettingsKey::FPS->value] = DEFAULT_FPS;
        $this->settings[SettingsKey::ASSETS_DIR->value] = Path::join(getcwd(), DEFAULT_ASSETS_PATH);

        $this->settings[SettingsKey::INITIAL_SCENE->value] = null;

        // Load environment settings
        $this->settings[SettingsKey::DEBUG->value] = $_ENV['DEBUG_MODE'] ?? false;
        $this->settings[SettingsKey::DEBUG_INFO->value] = $_ENV['SHOW_DEBUG_INFO'] ?? false;
        $this->settings[SettingsKey::LOG_LEVEL->value] = $_ENV['LOG_LEVEL'] ?? 'info';
        Debug::setLogLevel(LogLevel::tryFrom($this->getSettings('log_level')) ?? LogLevel::DEBUG);

        $this->settings[SettingsKey::LOG_DIR->value] = Path::join(getcwd(), DEFAULT_LOGS_DIR);
        Debug::info("Log directory initialized: {$this->settings[SettingsKey::LOG_DIR->value]}");

        // Debug settings
        Debug::setLogDirectory($this->getSettings(SettingsKey::LOG_DIR->value));
        $this->debugWindow->setPosition([0, $this->settings[SettingsKey::SCREEN_HEIGHT->value] - self::DEBUG_WINDOW_HEIGHT]);

        // Input settings
        $this->settings[SettingsKey::BUTTONS->value] = [];
        $this->settings[SettingsKey::PAUSE_KEY->value] = $_ENV['PAUSE_KEY'] ?? KeyCode::ESCAPE;

        // Splash screen settings
        $this->settings[SettingsKey::SPLASH_TEXTURE->value] = Path::join($this->settings[SettingsKey::ASSETS_DIR->value], basename(DEFAULT_SPLASH_TEXTURE_PATH));
        Debug::info("Splash screen texture init: {$this->settings[SettingsKey::SPLASH_TEXTURE->value]}");
        $this->settings[SettingsKey::SPLASH_DURATION->value] = DEFAULT_SPLASH_SCREEN_DURATION;

        // UI Settings
        $this->settings[SettingsKey::BORDER_PACK->value] = null;

        $this->sceneManager->loadSettings($this->settings);
        Debug::info("Game settings initialized");
    }

    /**
     * Load game settings.
     *
     * @param array<string, mixed>|null $settings The settings to load. If null will load default settings.
     * @return $this The current instance of the game engine.
     * @throws IOException
     */
    public function loadSettings(?array $settings = null): self
    {
        try {
            Debug::info("Loading environment settings");
            // Environment
            $this->settings[SettingsKey::DEBUG->value] = $_ENV['DEBUG_MODE'] ?? false;
            $this->settings[SettingsKey::DEBUG_INFO->value] = $_ENV['SHOW_DEBUG_INFO'] ?? false;
            $this->settings[SettingsKey::LOG_LEVEL->value] = $_ENV['LOG_LEVEL'] ?? DEFAULT_LOG_LEVEL;
            $this->settings[SettingsKey::LOG_DIR->value] = $_ENV['LOG_DIR'] ?? Path::join(getcwd(), DEFAULT_LOGS_DIR);

            Debug::info("Loading game settings");
            // Game
            $this->settings[SettingsKey::GAME_NAME->value] = $settings[SettingsKey::GAME_NAME->value] ?? $this->name;
            $this->settings[SettingsKey::SCREEN_WIDTH->value] = $settings[SettingsKey::SCREEN_WIDTH->value] ?? $this->screenWidth;
            $this->settings[SettingsKey::SCREEN_HEIGHT->value] = $settings[SettingsKey::SCREEN_HEIGHT->value] ?? $this->screenHeight;
            $this->settings[SettingsKey::FPS->value] = $settings[SettingsKey::FPS->value] ?? DEFAULT_FPS;
            $this->settings[SettingsKey::ASSETS_DIR->value] = $settings[SettingsKey::ASSETS_DIR->value] ?? getcwd() . DEFAULT_ASSETS_PATH;

            Debug::info('Loading scene settings');
            // Scene
            $this->settings[SettingsKey::INITIAL_SCENE->value] = 0 ?? throw new InitializationException("Initial scene not found");

            Debug::info('Loading splash screen settings');
            if (isset($settings[SettingsKey::SPLASH_TEXTURE->value])) {
                $this->settings[SettingsKey::SPLASH_TEXTURE->value] = Path::join(getcwd(), $settings[SettingsKey::SPLASH_TEXTURE->value]);
            }

            $this->settings[SettingsKey::SPLASH_DURATION->value] = $settings[SettingsKey::SPLASH_DURATION->value] ?? DEFAULT_SPLASH_SCREEN_DURATION;

            // Debug settings
            Debug::info('Loading debug settings');
            Debug::setLogDirectory($this->getSettings('log_dir'));
            Debug::setLogLevel(LogLevel::tryFrom($this->getSettings('log_level')) ?? LogLevel::DEBUG);
            $this->debugWindow->setPosition([0, $this->settings[SettingsKey::SCREEN_HEIGHT->value] - self::DEBUG_WINDOW_HEIGHT]);

            // Input settings
            $this->settings[SettingsKey::BUTTONS->value] = $settings[SettingsKey::BUTTONS->value] ?? $this->settings[SettingsKey::BUTTONS->value] ?? [];
            $this->settings[SettingsKey::PAUSE_KEY->value] = $settings[SettingsKey::PAUSE_KEY->value] ?? $_ENV['PAUSE_KEY'] ?? KeyCode::ESCAPE;

            $this->sceneManager->loadSettings($this->settings);
            Debug::info("Game settings loaded");
        } catch (Exception $exception) {
            $this->handleException($exception);
        }

        return $this;
    }

    /**
     * Initialize game states.
     *
     * @return void
     */
    protected function initializeGameStates(): void
    {
        $context = new GameStateContext($this, $this->sceneManager, $this->eventManager, $this->modalManager, $this->notificationsManager, $this->uiManager);
        $this->sceneState = new SceneState($context);
        $this->modalState = new ModalState($context);
        $this->pausedState = new PausedState($context);
        $this->state = $this->sceneState;
    }

    /**
     * Configure the window change signal handler.
     *
     * @return void
     * @throws Exception
     */
    protected function configureWindowChangeSignalHandler(): void
    {
        pcntl_signal(SIGWINCH, function () {
            $terminalSize = Console::getSize();
            $currentScreenWidth = $terminalSize->getWidth();
            $currentScreenHeight = $terminalSize->getHeight();

            $this->screenWidth = min($currentScreenWidth, $this->screenWidth, DEFAULT_SCREEN_WIDTH);
            $this->screenHeight = min($currentScreenHeight, $this->screenHeight, DEFAULT_SCREEN_HEIGHT);

            Debug::info("SIGWINCH received");
        });
    }

    /**
     * Quit the game.
     *
     * @return void
     */
    public static function quit(): void
    {
        if (confirm("Are you sure you want to quit?", "", 40)) {
            dispatchEvent(new GameEvent(GameEventType::QUIT));
        }
    }

    /**
     * Destruct the game engine.
     *
     * @throws IOException
     */
    public function __destruct()
    {
        Console::restoreSettings();

        if ($lastError = error_get_last()) {
            $this->handleError($lastError['type'], $lastError['message'], $lastError['file'], $lastError['line']);
        }
    }

    /**
     * Run the game.
     *
     * @return void
     * @throws IOException
     */
    public function run(): void
    {
        try {
            $sleepTime = (int)(1000000 / $this->getSettings('fps'));
            $this->start();
            $nextFrameTime = microtime(true) + 1;
            $lastFrameCountSnapShot = $this->frameCount;

            Debug::info("Running game");
            while ($this->isRunning) {
                $this->handleInput();
                $this->update();

                if (!$this->isRunning) {
                    break;
                }

                $this->render();

                usleep($sleepTime);

                if (microtime(true) >= $nextFrameTime) {
                    $this->frameRate = $this->frameCount - $lastFrameCountSnapShot;
                    $lastFrameCountSnapShot = $this->frameCount;
                    $nextFrameTime = microtime(true) + 1;
                }
            }
        } catch (Exception $exception) {
            $this->handleException($exception);
        }
    }

    /**
     * Start the game.
     *
     * @return void
     * @throws IOException
     */
    private function start(): void
    {
        Debug::info("Starting game");

        // Save the terminal settings
        Console::saveSettings();

        // Set the terminal name
        Console::setName($this->getSettings('game_name'));

        // Set the terminal size
        Console::setSize($this->getSettings('screen_width'), $this->getSettings('screen_height'));

        // Hide the cursor
        $this->consoleCursor->hide();

        // Disable echo
        InputManager::disableEcho();

        // Enable non-blocking input mode
        InputManager::enableNonBlockingMode();

        // Show the splash screen
        $this->splashScreen->show();

        // Handle game events
        $this->handleGameEvents();

        // Load the first scene
        try {
            $this->sceneManager->loadScene($this->getSettings('initial_scene'));
        } catch (SceneNotFoundException $exception) {
            $this->handleException($exception);
        }

        // Add game observers
        $this->addObservers(Time::class);

        // Start the game
        $this->isRunning = true;

        // Notify listeners that the game has started
        $this->notify(new GameEvent(GameEventType::START));

        Debug::info("Game started");
    }

    /**
     * @return void
     * @throws IOException
     */
    private function handleGameEvents(): void
    {
        try {
            // Handle game events
            $this->eventManager->addEventListener(EventType::GAME, function (GameEvent $event) {
                Debug::info("Game event received");
                if ($event->gameEventType === GameEventType::QUIT) {
                    Debug::info("Game quit event received");
                    $this->notify(new GameEvent(GameEventType::QUIT));
                    $this->stop();
                }
            });

        } catch (Exception $exception) {
            $this->handleException($exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function addObservers(ObserverInterface|StaticObserverInterface|string ...$observers): void
    {
        foreach ($observers as $observer) {
            if ($observer instanceof ObserverInterface) {
                $this->observers->add($observer);
            } else {
                $this->staticObservers->add($observer);
            }
        }
    }

    /**
     * Handle game input.
     *
     * @return void
     */
    private function handleInput(): void
    {
        InputManager::handleInput();
    }

    /**
     * Update the game state.
     *
     * @return void
     * @throws IOException
     */
    private function update(): void
    {
        $this->state->update();
        $this->uiManager->update();
        $this->notify(new GameEvent(GameEventType::UPDATE));
    }

    /**
     * Render the game.
     *
     * @return void
     * @throws IOException
     */
    private function render(): void
    {
        $this->frameCount++;
        $this->state->render();
        $this->uiManager->render();
        $this->renderDebugInfo();
        $this->notify(new GameEvent(GameEventType::RENDER));
    }

    /**
     * Renders Debug Info.
     *
     * @return void
     */
    private function renderDebugInfo(): void
    {
        if ($this->isDebug() && $this->showDebugInfo()) {
            $content = ["FPS: $this->frameRate", "Delta: " . round(Time::getDeltaTime(), 2), "Time: " . Time::getPrettyTime(ChronoUnit::SECONDS)];

            $this->debugWindow->setContent($content);
            $this->debugWindow->render();
        }
    }

    /**
     * Get the debug status.
     *
     * @return bool The debug status.
     */
    private function isDebug(): bool
    {
        return match (gettype($this->getSettings('debug'))) {
            'boolean' => $this->getSettings('debug'),
            'string' => strtolower($this->getSettings('debug')) === 'true',
            'integer' => $this->getSettings('debug') === 1,
            default => false
        };
    }

    private function showDebugInfo(): bool
    {
        return match (gettype($this->getSettings('debug_info'))) {
            'boolean' => $this->getSettings('debug_info'),
            'string' => strtolower($this->getSettings('debug_info')) === 'true',
            'integer' => $this->getSettings('debug_info') === 1,
            default => false
        };
    }

    /**
     * Add scenes to the game.
     *
     * @param SceneInterface ...$scenes The scenes to add.
     * @return $this
     */
    public function addScenes(SceneInterface ...$scenes): self
    {
        foreach ($scenes as $scene) {
            $this->sceneManager->addScene($scene);
        }

        return $this;
    }

    /**
     * @param string ...$paths
     * @return $this
     * @throws SceneNotFoundException
     */
    public function loadScenes(string ...$paths): self
    {
        foreach ($paths as $path) {
            $canonicalPath = Path::join(Path::getWorkingDirectoryAssetsPath(), $path);
            $this->sceneManager->loadSceneFromFile($canonicalPath);
        }
        return $this;
    }

    /**
     * Retrieve a game state.
     *
     * @param string $stateName The name of the state to retrieve.
     * @return GameStateInterface|null The game state or null if not found.
     */
    public function getState(string $stateName): ?GameStateInterface
    {
        return match ($stateName) {
            'scene' => $this->sceneState,
            'modal' => $this->modalState,
            'paused' => $this->pausedState,
            default => null
        };
    }

    /**
     * Set the current game state.
     *
     * @param GameStateInterface $state The game state to set.
     * @return void
     */
    public function setState(GameStateInterface $state): void
    {
        $context = new GameStateContext(
            $this,
            $this->sceneManager,
            $this->eventManager,
            $this->modalManager,
            $this->notificationsManager,
            $this->uiManager
        );
        $this->previousState = $this->state;
        $this->state->exit($context);
        $this->state = $state;
        $this->state->enter($context);
    }

    /**
     * Get the previous game state.
     *
     * @return GameStateInterface|null The previous game state or null if not found.
     */
    public function getPreviousState(): GameStateInterface|null
    {
        return $this->previousState;
    }
}
