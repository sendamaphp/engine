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
    private const string TMUX_CHILD_ENV_KEY = 'SENDAMA_TMUX_CHILD';
    private const string TMUX_SESSION_ENV_KEY = 'SENDAMA_TMUX_SESSION';
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
    private ?Cursor $consoleCursor = null;
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

    private ?SplashScreen $splashScreen = null;
    private bool $consoleInitialized = false;

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
            $this->initializeConfigStore();
            $this->initializeManagers();
            $this->initializeSettings();
            $this->initializeGameStates();
            $this->configureWindowChangeSignalHandler();
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
        Debug::info("Stopping game");

        if ($this->consoleInitialized) {
            Console::reset();

            // Disable non-blocking input mode
            InputManager::disableNonBlockingMode();

            // Enable echo
            InputManager::enableEcho();

            // Show cursor
            $this->consoleCursor?->show();

            // Restore the terminal settings
            Console::restoreSettings();
        }

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

        if ($key === null) {
            return $this->settings;
        }

        return array_key_exists($key, $this->settings) ? $this->settings[$key] : null;
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
    protected function initializeConsole(bool $clearOnInit = true): void
    {
        $this->consoleCursor = Console::cursor();
        Console::init($this, [
            'width' => $this->getLogicalScreenWidth(),
            'height' => $this->getLogicalScreenHeight(),
            'clear_on_init' => $clearOnInit,
        ]);
        $this->splashScreen = new SplashScreen($this->consoleCursor, $this->settings);
        $this->consoleInitialized = true;
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
        $environmentDirectory = $this->workingDirectory ?? getcwd();

        if (file_exists(Path::join($environmentDirectory, '.env'))) {
            $dotenv = Dotenv::createImmutable($environmentDirectory);
            $dotenv->load();
        }

        $this->settings[SettingsKey::GAME_NAME->value] = $_ENV['GAME_NAME'] ?? $this->name;
        $this->settings[SettingsKey::SCREEN_WIDTH->value] = self::resolveConfiguredIntSetting(
            'SCREEN_WIDTH',
            ['player.screen.width', 'screenWidth', SettingsKey::SCREEN_WIDTH->value],
            $this->screenWidth
        );
        $this->settings[SettingsKey::SCREEN_HEIGHT->value] = self::resolveConfiguredIntSetting(
            'SCREEN_HEIGHT',
            ['player.screen.height', 'screenHeight', SettingsKey::SCREEN_HEIGHT->value],
            $this->screenHeight
        );
        $this->settings[SettingsKey::FPS->value] = DEFAULT_FPS;
        $this->settings[SettingsKey::ASSETS_DIR->value] = Path::getWorkingDirectoryAssetsPath();

        $this->settings[SettingsKey::INITIAL_SCENE->value] = null;

        // Load environment settings
        $this->settings[SettingsKey::DEBUG->value] = self::resolveConfiguredSetting(
            'DEBUG_MODE',
            [SettingsKey::DEBUG->value, 'debugMode'],
            false
        );
        $this->settings[SettingsKey::DEBUG_INFO->value] = self::resolveConfiguredSetting(
            'SHOW_DEBUG_INFO',
            ['showDebugInfo', SettingsKey::DEBUG_INFO->value, 'show_debug_info', 'debug.showInfo', 'debug.showDebugInfo'],
            false
        );
        $this->settings[SettingsKey::LOG_LEVEL->value] = self::resolveConfiguredLogLevelValue();
        Debug::setLogLevel(LogLevel::tryFrom((string)$this->getSettings('log_level')) ?? LogLevel::DEBUG);

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
            $settings ??= [];

            $this->settings[SettingsKey::LOG_LEVEL->value] = self::resolveConfiguredLogLevelValue(
                $settings,
                $this->settings[SettingsKey::LOG_LEVEL->value] ?? null
            );
            $this->settings[SettingsKey::LOG_DIR->value] = $settings[SettingsKey::LOG_DIR->value]
                ?? $_ENV['LOG_DIR']
                ?? $this->settings[SettingsKey::LOG_DIR->value]
                ?? Path::join(getcwd(), DEFAULT_LOGS_DIR);

            Debug::setLogDirectory($this->settings[SettingsKey::LOG_DIR->value]);
            Debug::setLogLevel(LogLevel::tryFrom((string)$this->settings[SettingsKey::LOG_LEVEL->value]) ?? LogLevel::DEBUG);

            Debug::info("Loading environment settings");
            // Environment
            $this->settings[SettingsKey::DEBUG->value] = $settings[SettingsKey::DEBUG->value]
                ?? $settings['debugMode']
                ?? self::resolveConfiguredSetting(
                    'DEBUG_MODE',
                    [SettingsKey::DEBUG->value, 'debugMode'],
                    false
                );
            $this->settings[SettingsKey::DEBUG_INFO->value] = $settings[SettingsKey::DEBUG_INFO->value]
                ?? $settings['showDebugInfo']
                ?? $settings['show_debug_info']
                ?? self::resolveConfiguredSetting(
                    'SHOW_DEBUG_INFO',
                    ['showDebugInfo', SettingsKey::DEBUG_INFO->value, 'show_debug_info', 'debug.showInfo', 'debug.showDebugInfo'],
                    false
                );
            Debug::info("Loading game settings");
            // Game
            $this->settings[SettingsKey::GAME_NAME->value] = $settings[SettingsKey::GAME_NAME->value] ?? $this->name;
            $this->settings[SettingsKey::SCREEN_WIDTH->value] = self::resolveIntSettingValue(
                $settings[SettingsKey::SCREEN_WIDTH->value]
                    ?? $settings['screenWidth']
                    ?? self::resolveConfiguredSetting(
                        'SCREEN_WIDTH',
                        ['player.screen.width', 'screenWidth', SettingsKey::SCREEN_WIDTH->value],
                        $this->screenWidth
                    ),
                $this->screenWidth
            );
            $this->settings[SettingsKey::SCREEN_HEIGHT->value] = self::resolveIntSettingValue(
                $settings[SettingsKey::SCREEN_HEIGHT->value]
                    ?? $settings['screenHeight']
                    ?? self::resolveConfiguredSetting(
                        'SCREEN_HEIGHT',
                        ['player.screen.height', 'screenHeight', SettingsKey::SCREEN_HEIGHT->value],
                        $this->screenHeight
                    ),
                $this->screenHeight
            );
            $this->settings[SettingsKey::FPS->value] = $settings[SettingsKey::FPS->value] ?? DEFAULT_FPS;
            $this->settings[SettingsKey::ASSETS_DIR->value] = $settings[SettingsKey::ASSETS_DIR->value]
                ?? Path::getWorkingDirectoryAssetsPath();

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
        pcntl_async_signals(true);
        pcntl_signal(SIGWINCH, function () {
            $this->refreshConsoleLayout(forceTerminalSize: true);
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
        if ($this->consoleInitialized) {
            Console::restoreSettings();
        }

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
            if ($this->handoffToTmuxSessionIfAvailable()) {
                return;
            }

            $this->initializeConsole(clearOnInit: !$this->isTmuxChildProcess());
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

        if (!$this->consoleInitialized) {
            $this->initializeConsole(clearOnInit: !$this->isTmuxChildProcess());
        }

        if (!$this->consoleCursor instanceof Cursor || !$this->splashScreen instanceof SplashScreen) {
            throw new InitializationException('Console runtime was not initialized.');
        }

        // Save the terminal settings
        Console::saveSettings();

        // Set the terminal name
        Console::setName($this->getSettings('game_name'));

        // Treat the terminal as the container and center the scene within it.
        Console::maximizeWindow();
        $this->refreshConsoleLayout(forceTerminalSize: true);

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
        $this->refreshConsoleLayout();
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

            $this->debugWindow->setPosition([0, max(0, $this->getLogicalScreenHeight() - self::DEBUG_WINDOW_HEIGHT)]);
            $this->debugWindow->setContent($content);
            $this->debugWindow->render();
        }
    }

    /**
     * Refresh the console layout using the active scene viewport when available.
     *
     * @param bool $forceTerminalSize Whether to re-read the terminal size immediately.
     * @return void
     * @throws Exception
     */
    private function refreshConsoleLayout(bool $forceTerminalSize = false): void
    {
        Console::refreshLayout(
            $this->getLogicalScreenWidth(),
            $this->getLogicalScreenHeight(),
            $forceTerminalSize ? Console::getSize(force: true) : null
        );
    }

    /**
     * Returns the current logical render width.
     *
     * @return int
     */
    private function getLogicalScreenWidth(): int
    {
        $activeScene = $this->sceneManager->getActiveScene();

        if ($activeScene) {
            return max(1, $activeScene->getCamera()->getViewport()->getWidth());
        }

        return max(1, (int)$this->getSettings(SettingsKey::SCREEN_WIDTH->value));
    }

    /**
     * Returns the current logical render height.
     *
     * @return int
     */
    private function getLogicalScreenHeight(): int
    {
        $activeScene = $this->sceneManager->getActiveScene();

        if ($activeScene) {
            return max(1, $activeScene->getCamera()->getViewport()->getHeight());
        }

        return max(1, (int)$this->getSettings(SettingsKey::SCREEN_HEIGHT->value));
    }

    /**
     * Resolves the configured log level, allowing env to override file settings.
     *
     * @param array<string, mixed> $settings
     * @param string|null $existingValue
     * @return string
     */
    private static function resolveConfiguredLogLevelValue(array $settings = [], ?string $existingValue = null): string
    {
        $envValue = isset($_ENV['LOG_LEVEL']) ? trim((string)$_ENV['LOG_LEVEL']) : null;
        $settingsValue = $settings[SettingsKey::LOG_LEVEL->value]
            ?? $settings['logLevel']
            ?? null;

        return trim((string)($envValue ?? $settingsValue ?? $existingValue ?? DEFAULT_LOG_LEVEL));
    }

    /**
     * Get the debug status.
     *
     * @return bool The debug status.
     */
    private function isDebug(): bool
    {
        return self::isTruthySetting($this->getSettings(SettingsKey::DEBUG));
    }

    private function showDebugInfo(): bool
    {
        return self::isTruthySetting($this->getSettings(SettingsKey::DEBUG_INFO));
    }

    /**
     * Resolve a setting from the environment first, then the app config file.
     *
     * @param string $envKey The environment variable name.
     * @param string[] $configPaths Candidate config paths to try.
     * @param mixed $default The default value.
     * @return mixed
     */
    private static function resolveConfiguredSetting(string $envKey, array $configPaths, mixed $default = null): mixed
    {
        if (array_key_exists($envKey, $_ENV)) {
            return $_ENV[$envKey];
        }

        if (ConfigStore::doesntHave(AppConfig::class)) {
            return $default;
        }

        $config = ConfigStore::get(AppConfig::class);

        foreach ($configPaths as $path) {
            $value = $config->get($path);

            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Resolve an integer setting from the environment first, then the app config file.
     *
     * @param string $envKey The environment variable name.
     * @param string[] $configPaths Candidate config paths to try.
     * @param int $default The default value.
     * @return int
     */
    private static function resolveConfiguredIntSetting(string $envKey, array $configPaths, int $default): int
    {
        return self::resolveIntSettingValue(
            self::resolveConfiguredSetting($envKey, $configPaths, $default),
            $default
        );
    }

    /**
     * Normalize an integer-like config value.
     *
     * @param mixed $value The value to normalize.
     * @param int $default The default value.
     * @return int
     */
    private static function resolveIntSettingValue(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * Normalize mixed config values to booleans.
     *
     * @param mixed $value The value to normalize.
     * @return bool
     */
    private static function isTruthySetting(mixed $value): bool
    {
        return match (gettype($value)) {
            'boolean' => $value,
            'string' => in_array(strtolower($value), ['true', '1', 'yes', 'on'], true),
            'integer' => $value === 1,
            default => false
        };
    }

    /**
     * Hands off the game runtime to a dedicated tmux session when supported.
     *
     * @return bool True when control was transferred and the current process should return.
     */
    private function handoffToTmuxSessionIfAvailable(): bool
    {
        if (
            $this->isTmuxChildProcess()
            || !self::isTmuxInstalled()
            || !self::canRelaunchCurrentCommand($_SERVER['argv'] ?? null)
        ) {
            return false;
        }

        $sessionName = self::buildTmuxSessionName((string)$this->getSettings(SettingsKey::GAME_NAME->value));
        $workingDirectory = getcwd() ?: $this->workingDirectory ?? '.';
        $command = self::buildTmuxRuntimeCommand($sessionName, $_SERVER['argv'] ?? []);

        if (self::tmuxSessionExists($sessionName)) {
            self::destroyTmuxSession($sessionName);
        }

        $createExitCode = 0;
        exec(
            sprintf(
                'tmux new-session -d -s %s -c %s %s',
                escapeshellarg($sessionName),
                escapeshellarg($workingDirectory),
                escapeshellarg($command),
            ),
            result_code: $createExitCode,
        );

        if ($createExitCode !== 0) {
            return false;
        }

        $attachCommand = getenv('TMUX')
            ? sprintf('tmux switch-client -t %s', escapeshellarg($sessionName))
            : sprintf('tmux attach-session -t %s', escapeshellarg($sessionName));

        passthru($attachCommand, $attachExitCode);

        if ($attachExitCode !== 0) {
            self::destroyTmuxSession($sessionName);
            return false;
        }

        return true;
    }

    /**
     * Determine whether this process is already running inside a Sendama-managed tmux session.
     *
     * @return bool
     */
    private function isTmuxChildProcess(): bool
    {
        $envValue = $_ENV[self::TMUX_CHILD_ENV_KEY]
            ?? getenv(self::TMUX_CHILD_ENV_KEY)
            ?? false;

        return self::isTruthySetting($envValue);
    }

    /**
     * Checks whether tmux is available in the executing environment.
     *
     * @return bool
     */
    private static function isTmuxInstalled(): bool
    {
        $tmuxPath = shell_exec('command -v tmux 2>/dev/null');

        return is_string($tmuxPath) && trim($tmuxPath) !== '';
    }

    /**
     * Determine whether the current runtime command can be relaunched safely.
     *
     * @param mixed $argv
     * @return bool
     */
    private static function canRelaunchCurrentCommand(mixed $argv): bool
    {
        return PHP_SAPI === 'cli' && is_array($argv) && $argv !== [];
    }

    /**
     * Builds a tmux-safe session name from the configured game title.
     *
     * @param string $gameName
     * @return string
     */
    private static function buildTmuxSessionName(string $gameName): string
    {
        $sanitizedName = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($gameName)) ?? '';
        $sanitizedName = trim($sanitizedName, '-_');

        return $sanitizedName !== '' ? $sanitizedName : 'sendama-game';
    }

    /**
     * Reconstructs the current PHP command line for tmux handoff.
     *
     * @param string $sessionName
     * @param array<int, string> $argv
     * @return string
     */
    private static function buildTmuxRuntimeCommand(string $sessionName, array $argv): string
    {
        $commandParts = array_merge(
            [
                self::TMUX_CHILD_ENV_KEY . '=1',
                self::TMUX_SESSION_ENV_KEY . '=' . escapeshellarg($sessionName),
                escapeshellarg(PHP_BINARY),
            ],
            array_map(
                static fn(mixed $argument): string => escapeshellarg((string)$argument),
                $argv,
            ),
        );

        return implode(' ', $commandParts);
    }

    /**
     * Checks whether a tmux session already exists.
     *
     * @param string $sessionName
     * @return bool
     */
    private static function tmuxSessionExists(string $sessionName): bool
    {
        exec(
            sprintf('tmux has-session -t %s 2>/dev/null', escapeshellarg($sessionName)),
            result_code: $exitCode,
        );

        return $exitCode === 0;
    }

    /**
     * Destroys an existing tmux session.
     *
     * @param string $sessionName
     * @return void
     */
    private static function destroyTmuxSession(string $sessionName): void
    {
        exec(sprintf('tmux kill-session -t %s 2>/dev/null', escapeshellarg($sessionName)));
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
