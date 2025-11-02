<?php

use Sendama\Engine\Core\GameObject;
use Sendama\Engine\Core\Scenes\SceneManager;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Events\Enumerations\GameEventType;
use Sendama\Engine\Events\EventManager;
use Sendama\Engine\Events\GameEvent;
use Sendama\Engine\Events\Interfaces\EventInterface;
use Sendama\Engine\Exceptions\Scenes\SceneNotFoundException;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\Messaging\Notifications\Enumerations\NotificationChannel;
use Sendama\Engine\Messaging\Notifications\Enumerations\NotificationDuration;
use Sendama\Engine\Messaging\Notifications\Notification;
use Sendama\Engine\Messaging\Notifications\NotificationsManager;
use Sendama\Engine\UI\Windows\Enumerations\WindowPosition;
use Sendama\Engine\Util\Config\AppConfig;
use Sendama\Engine\Util\Config\ConfigStore;
use Sendama\Engine\Util\Config\PlayerPreferences;


/* Application */
function getGameName(): string
{
  return SceneManager::getInstance()->getSettings('game_name') ?? $_ENV['GAME_NAME'];
}

/**
 * Quits the game with the given exit code.
 *
 * @param int|null $code The exit code. Defaults to null.
 * @return void
 */
function quitGame(?int $code = null): void
{
  EventManager::getInstance()->dispatchEvent(new GameEvent(GameEventType::QUIT, data: $code));
}

/**
 * Pauses the game.
 *
 * @return void
 */
function pauseGame(): void
{
  EventManager::getInstance()->dispatchEvent(new GameEvent(GameEventType::PAUSE));
}

/**
 * Resumes the game.
 *
 * @return void
 */
function resumeGame(): void
{
  EventManager::getInstance()->dispatchEvent(new GameEvent(GameEventType::RESUME));
}

/**
 * Loads the scene with the given index.
 *
 * @param string|int $index The index of the scene to load.
 * @return void
 * @throws SceneNotFoundException
 */
function loadScene(string|int $index): void
{
  SceneManager::getInstance()->loadScene($index);
}

/**
 * Loads the next scene.
 *
 * @return void
 * @throws SceneNotFoundException If the previous scene is not found.
 */
function loadPreviousScene(): void
{
  SceneManager::getInstance()->loadPreviousScene();
}

/* Math */
if (! function_exists('clamp') ) {
  /**
   * Returns the given value clamped between the given min and max values.
   *
   * @param int|float $value The value to clamp.
   * @param int|float $min The minimum value.
   * @param int|float $max The maximum value.
   * @return int|float The clamped value.
   */
  function clamp(int|float $value, int|float $min, int|float $max): int|float
  {
    return max($min, min($max, $value));
  }
}

if (! function_exists('lerp') ) {
  /**
   * Linearly interpolates between the given start and end values.
   *
   * @param float $start The start value.
   * @param float $end The end value.
   * @param float $amount The amount to interpolate.
   * @return float The interpolated value.
   */
  function lerp(float $start, float $end, float $amount): float
  {
    $amount = clamp($amount, 0, 1);

    return $start + ($end - $start) * $amount;
  }
}

if (! function_exists('wrap') ) {
  /**
   * Wraps the given value between the given min and max values.
   *
   * @param int $value The value to wrap.
   * @param int $min The minimum value.
   * @param int $max The maximum value.
   * @return int The wrapped value.
   */
  function wrap(int $value, int $min, int $max): int
  {
    $range = $max - $min + 1;

    if ($range == 0) {
      return $min;
    }

    if ($value < $min) {
      $value += $range * ceil(($min - $value) / $range);
    }

    return $min + (($value - $min) % $range + $range) % $range;
  }
}

if (! function_exists('wrap_text') ) {
  /**
   * Returns the given text wrapped to the given width.
   *
   * @param string $text The text to wrap.
   * @param int $width The width to wrap to.
   * @param bool $breakWords Whether to break words or not.
   * @return string The wrapped text.
   */
  function wrap_text(string $text, int $width, bool $breakWords = true): string
  {
    $lines = explode("\n", $text);
    $wrappedLines = [];

    foreach ($lines as $line) {
      $wrappedLines = array_merge($wrappedLines, explode("\n", wordwrap($line, $width, "\n", $breakWords)));
    }

    return implode("\n", $wrappedLines);
  }
}

/* Dialog functions */
if (! function_exists('alert') ) {
  /**
   * Shows an alert dialog with the given message and title.
   *
   * @param string $message The message to show.
   * @param string $title The title of the dialog. Defaults to "Alert".
   * @param int $width The width of the dialog. Defaults to 34.
   * @return void
   */
  function alert(string $message, string $title = '', int $width = DEFAULT_DIALOG_WIDTH): void
  {
    Console::alert($message, $title, $width);
  }
}

if (! function_exists('confirm') ) {
  /**
   * Shows a confirm dialog with the given message and title. Returns true if the user confirmed, false otherwise.
   *
   * @param string $message The message to show.
   * @param string $title The title of the dialog. Defaults to "Confirm".
   * @param int $width The width of the dialog. Defaults to 34.
   * @return bool Whether the user confirmed or not.
   */
  function confirm(string $message, string $title = 'Confirm', int $width = DEFAULT_DIALOG_WIDTH): bool
  {
    return Console::confirm($message, $title, $width);
  }
}

if (! function_exists('prompt') ) {
  /**
   * Shows a prompt dialog with the given message and title. Returns the user's input.
   *
   * @param string $message The message to show.
   * @param string $title The title of the dialog. Defaults to "Prompt".
   * @param string $default The default value of the input. Defaults to an empty string.
   * @param int $width The width of the dialog. Defaults to 34.
   * @return string The user's input.
   */
  function prompt(
    string $message,
    string $title = 'Prompt',
    string $default = '',
    int    $width = DEFAULT_DIALOG_WIDTH
  ): string
  {
    return Console::prompt($message, $title, $default, $width);
  }
}

if (! function_exists('select') ) {
  /**
   * Shows a select dialog with the given message and title. Returns the index of the selected option.
   *
   * @param string $message The message to show.
   * @param string[] $options The options to show.
   * @param string $title The title of the dialog. Defaults to "Select".
   * @param int $default The default option. Defaults to 0.
   * @param Vector2|null $position The position of the dialog. Defaults to null.
   * @param int $width The width of the dialog. Defaults to 34.
   * @return int The index of the selected option.
   */
  function select(
    string   $message,
    array    $options,
    string   $title = '',
    int      $default = 0,
    ?Vector2 $position = null,
    int      $width = DEFAULT_SELECT_DIALOG_WIDTH
  ): int
  {
    return Console::select($message, $options, $title, $default, $position, $width);
  }
}

if (! function_exists('show_text') ) {
  /**
   * Shows a text dialog with the given message and title.
   *
   * @param string $message The message to show.
   * @param string $title The title of the dialog. Defaults to "".
   * @param string $help The help text to show. Defaults to "".
   * @param WindowPosition $position The position of the dialog. Defaults to BOTTOM (i.e. the bottom of the screen).
   * @param float $charactersPerSecond The number of characters to display per second.
   * @return void
   */
  function show_text(
    string         $message,
    string         $title = '',
    string         $help = '',
    WindowPosition $position = WindowPosition::BOTTOM,
    float          $charactersPerSecond = 1
  ): void
  {
    Console::showText($message, $title, $help, $position, $charactersPerSecond);
  }
}

if (! function_exists('notify') ) {
  /**
   * Creates a new notification with the given channel, title, text, and duration.
   *
   * @param NotificationChannel $channel The channel to send the notification to.
   * @param string $title The title of the notification.
   * @param string $text The text of the notification.
   * @param NotificationDuration|float $duration The duration of the notification.
   * @return void
   */
  function notify(
    NotificationChannel        $channel,
    string                     $title,
    string                     $text,
    NotificationDuration|float $duration = NotificationDuration::SHORT
  ): void
  {
    $notification = new Notification($channel, $title, $text, $duration);
    NotificationsManager::getInstance()->notify($notification);
  }
}
/* Events */

if (! function_exists('broadcast') ) {
  /**
   * Broadcasts the given event.
   *
   * @param EventInterface $event The event to broadcast.
   * @return void
   */
  function broadcast(EventInterface $event): void
  {
    $eventManager = EventManager::getInstance();
    $eventManager->dispatchEvent($event);
  }
}

/* Text */
if (! function_exists('strip_ansi') ) {
  /**
   * Returns the given text with all ANSI escape sequences removed.
   *
   * @param string $input The text to remove the escape sequences from.
   * @return string The text with all escape sequences removed.
   */
  function strip_ansi(string $input): string
  {
    $pattern = "/\e\[[0-9;]*m/";
    return preg_replace($pattern, '', $input) ?? '';
  }
}

/* Game Objects */
if (! function_exists('instantiate') ) {
  /**
   * Instantiates a new game object from the given original game object at the given position.
   *
   * @param GameObject $original The original game object.
   * @param Vector2 $position The position to instantiate the new game object at.
   * @return GameObject The new game object.
   */
  function instantiate(GameObject $original, Vector2 $position): GameObject
  {
    $newObject = clone $original;
    $newObject->getTransform()->setPosition($position);

    return $newObject;
  }
}

if (! function_exists('in_range') ) {
  /**
   * Checks if the given value is within the given range.
   *
   * @param int $value The value to check.
   * @param int $min The minimum value.
   * @param int $max The maximum value.
   * @return bool Whether the value is within the range or not.
   */
  function in_range(int $value, int $min, int $max): bool
  {
    return $value >= $min && $value <= $max;
  }
}

if (! function_exists('within_bounds') ) {
  /**
   * Checks if the given point is within the given bounds.
   *
   * @param Vector2 $point The point to check.
   * @param Vector2 $min The minimum bounds.
   * @param Vector2 $max The maximum bounds.
   * @return bool Whether the point is within the bounds or not.
   */
  function within_bounds(Vector2 $point, Vector2 $min, Vector2 $max): bool
  {
    return in_range($point->getX(), $min->getX(), $max->getX()) && in_range($point->getY(), $min->getY(), $max->getY());
  }
}

if (!function_exists('env')) {
  /**
   * Gets the value of an environment variable.
   *
   * @param string $key The environment variable key.
   * @param mixed|null $default The default value to return if the environment variable is not set.
   * @return mixed
   */
  function env(string $key, mixed $default = null): mixed
  {
    return $_ENV[$key] ?? $default;
  }
}

if (! function_exists('config') ) {
  /**
   * Gets the value of the given configuration path.
   *
   * @param class-string $configClassname The classname of the configuration.
   * @param string $path The path of the configuration.
   * @param mixed $default The default value.
   *
   * @return mixed The value of the configuration.
   */
  function config(string $configClassname, string $path, mixed $default = null): mixed
  {
    return ConfigStore::get($configClassname)->get($path, $default);
  }
}

if (! function_exists('get_screen_width')) {
  /**
   * Returns the screen width.
   *
   * @return int The screen width.
   * @deprecated 0.5.0 Use get_configured_screen_width() instead.
   */
  function get_screen_width(): int
  {
    return get_configured_screen_width();
  }
}

if (! function_exists('get_configured_screen_width') ) {
  /**
   * Returns the screen width.
   *
   * @return int The screen width.
   */
  function get_configured_screen_width(): int
  {
    return config(AppConfig::class, 'player.screen.width', DEFAULT_SCREEN_WIDTH);
  }
}

if (! function_exists('get_screen_height')) {
  /**
   * Returns the screen height.
   *
   * @return int The screen height.
   * @deprecated 0.5.0 Use get_configured_screen_height() instead.
   */
  function get_screen_height(): int
  {
    return get_configured_screen_height();
  }
}

if (! function_exists('get_configured_screen_height') ) {
  /**
   * Returns the screen height.
   *
   * @return int The screen height.
   */
  function get_configured_screen_height(): int
  {
    return config(AppConfig::class, 'player.screen.height', DEFAULT_SCREEN_HEIGHT);
  }
}

if (! function_exists('get_player_pref') ) {
  /**
   * Gets the player preference with the given key.
   *
   * @param string $key The key of the player preference.
   * @param mixed|null $default The default value to return if the player preference is not set.
   * @return mixed The value of the player preference.
   */
  function get_player_pref(string $key, mixed $default = null): mixed {
    return ConfigStore::get(PlayerPreferences::class)->get($key, $default);
  }
}

if (! function_exists('set_player_pref') ) {
  /**
   * Sets the player preference with the given key to the given value.
   *
   * @param string $key The key of the player preference.
   * @param mixed $value The value to set the player preference to.
   * @return void
   */
  function set_player_pref(string $key, mixed $value): void {
    ConfigStore::get(PlayerPreferences::class)->set($key, $value);
  }
}