<?php

namespace Sendama\Engine\IO\Console;

use Exception;
use Sendama\Engine\Core\Grid;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Game;
use Sendama\Engine\IO\Enumerations\Color;
use Sendama\Engine\UI\Modals\ModalManager;
use Sendama\Engine\UI\Windows\Enumerations\WindowPosition;
use Sendama\Engine\Util\Unicode;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console is a static class that provides console functionality.
 */
class Console
{
  private const float TERMINAL_SIZE_POLL_INTERVAL_SECONDS = 0.1;
  private const int WINDOW_STATE_SETTLE_DELAY_MICROSECONDS = 50000;
  /**
   * @var Game|null $game The game instance.
   */
  protected static ?Game $game = null;
  /**
   * @var int $width The width of the console
   */
  protected static int $width = DEFAULT_SCREEN_WIDTH;
  /**
   * @var int $height The height of the console
   */
  protected static int $height = DEFAULT_SCREEN_HEIGHT;
  /**
   * @var int $logicalWidth The logical width of the game viewport.
   */
  protected static int $logicalWidth = DEFAULT_SCREEN_WIDTH;
  /**
   * @var int $logicalHeight The logical height of the game viewport.
   */
  protected static int $logicalHeight = DEFAULT_SCREEN_HEIGHT;
  /**
   * @var float $renderScale The current scale applied to logical coordinates.
   */
  protected static float $renderScale = 1.0;
  /**
   * @var int $renderOffsetX The x-offset used to center the logical viewport.
   */
  protected static int $renderOffsetX = 1;
  /**
   * @var int $renderOffsetY The y-offset used to center the logical viewport.
   */
  protected static int $renderOffsetY = 1;
  /**
   * @var ConsoleOutput|null $output The console output stream
   */
  protected static ?ConsoleOutput $output = null;
  /**
   * @var float $lastSizeCheckAt The last time the terminal size was polled.
   */
  protected static float $lastSizeCheckAt = 0.0;

  /**
   * @var Grid<string> $buffer The buffer.
   */
  private static Grid $buffer;
  /**
   * @var string $previousTerminalSettings The previous terminal settings.
   */
  private static string $previousTerminalSettings = '';

  /**
   * Console constructor.
   */
  private function __construct()
  {
    // Prevent instantiation.
  }

  /**
   * Initializes the console.
   *
   * @param Game $game
   * @param array $options
   * @return void
   */
  public static function init(Game $game, array $options = [
    'width' => DEFAULT_SCREEN_WIDTH,
    'height' => DEFAULT_SCREEN_HEIGHT,
  ]): void
  {
    self::$game = $game;
    self::$logicalWidth = $options['width'] ?? DEFAULT_SCREEN_WIDTH;
    self::$logicalHeight = $options['height'] ?? DEFAULT_SCREEN_HEIGHT;
    self::refreshLayout(self::$logicalWidth, self::$logicalHeight, clearWhenChanged: false);
    self::clear();
    Console::cursor()->disableBlinking();
    self::$output = new ConsoleOutput();
  }

  /**
   * Clears the console.
   *
   * @return void
   */
  public static function clear(): void
  {
    self::$buffer = self::getEmptyBuffer();
    if (PHP_OS_FAMILY === 'Windows') {
      system('cls');
    } else {
      system('clear');
    }
  }

  /**
   * Returns an empty buffer.
   *
   * @return Grid<string> The empty buffer.
   */
  private static function getEmptyBuffer(): Grid
  {
    return new Grid(self::$width, self::$height, ' ');
  }

  /**
   * Returns the cursor.
   *
   * @return Cursor The cursor.
   */
  public static function cursor(): Cursor
  {
    return Cursor::getInstance();
  }

  /**
   * Resets the console.
   *
   * @return void
   */
  public static function reset(): void
  {
    if (false === system('reset')) {
      echo "System reset failed";
      echo "\033c";
      self::cursor()->enableBlinking();
    }
  }

  /* Scrolling */

  /**
   * Enables the line wrap.
   *
   * @return void
   */
  public static function enableLineWrap(): void
  {
    echo "\033[7h";
  }

  /**
   * Disables the line wrap.
   *
   * @return void
   */
  public static function disableLineWrap(): void
  {
    echo "\033[7l";
  }

  /**
   * Enables scrolling.
   *
   * @param int|null $start The line to start scrolling.
   * @param int|null $end The line to end scrolling.
   * @return void
   */
  public static function enableScrolling(?int $start = null, ?int $end = null): void
  {
    if ($start !== null && $end !== null) {
      echo "\033[$start;{$end}r";
    } else if ($start !== null) {
      echo "\033[{$start}r";
    } else if ($end !== null) {
      echo "\033[;{$end}r";
    } else {
      echo "\033[r";
    }
  }

  /**
   * Disables scrolling.
   *
   * @return void
   */
  public static function disableScrolling(): void
  {
    echo "\033[?7l";
  }

  /**
   * Sets the terminal name.
   *
   * @param string $name The name of the terminal.
   * @return void
   */
  public static function setName(string $name): void
  {
    echo "\033]0;$name\007";
  }

  /**
   * Sets the terminal size.
   *
   * @param int $width The width of the terminal.
   * @param int $height The height of the terminal.
   * @return void
   */
  public static function setSize(int $width, int $height): void
  {
    echo "\033[8;$height;{$width}t";
  }

  /**
   * Requests the terminal window to maximize when the terminal emulator supports it.
   *
   * This is an xterm-compatible window operation; terminals that do not support it
   * will safely ignore the request and keep their current size.
   *
   * @return void
   */
  public static function maximizeWindow(): void
  {
    echo "\033[9;1t";
    flush();
    self::$lastSizeCheckAt = 0.0;
    usleep(self::WINDOW_STATE_SETTLE_DELAY_MICROSECONDS);
  }

  /**
   * Returns the terminal size.
   *
   * @return Rect The terminal size.
   * @throws Exception If the terminal size cannot be retrieved.
   */
  public static function getSize(bool $force = false): Rect
  {
    if (
      !$force &&
      self::$lastSizeCheckAt > 0 &&
      (microtime(true) - self::$lastSizeCheckAt) < self::TERMINAL_SIZE_POLL_INTERVAL_SECONDS
    ) {
      return new Rect(new Vector2(1, 1), new Vector2(self::$width, self::$height));
    }

    $width = (int)trim(shell_exec("tput cols 2>/dev/null") ?? '');
    $height = (int)trim(shell_exec("tput lines 2>/dev/null") ?? '');
    self::$lastSizeCheckAt = microtime(true);

    if ($width < 1) {
      $width = self::$width;
    }

    if ($height < 1) {
      $height = self::$height;
    }

    return new Rect(new Vector2(1, 1), new Vector2($width, $height));
  }

  /**
   * Refreshes the logical viewport within the current terminal size.
   *
   * @param int $logicalWidth The logical width of the game.
   * @param int $logicalHeight The logical height of the game.
   * @param Rect|null $terminalSize The terminal size override.
   * @param bool $clearWhenChanged Whether to clear the terminal when the layout changes.
   * @return bool True when the layout changed.
   */
  public static function refreshLayout(
    int $logicalWidth,
    int $logicalHeight,
    ?Rect $terminalSize = null,
    bool $clearWhenChanged = true,
  ): bool
  {
    $terminalSize ??= self::getSize();

    $terminalWidth = max(1, $terminalSize->getWidth());
    $terminalHeight = max(1, $terminalSize->getHeight());
    $logicalWidth = max(1, $logicalWidth);
    $logicalHeight = max(1, $logicalHeight);

    $renderScale = 1.0;
    $offsetX = (int)floor(($terminalWidth - $logicalWidth) / 2) + 1;
    $offsetY = (int)floor(($terminalHeight - $logicalHeight) / 2) + 1;

    $changed =
      self::$width !== $terminalWidth ||
      self::$height !== $terminalHeight ||
      self::$logicalWidth !== $logicalWidth ||
      self::$logicalHeight !== $logicalHeight ||
      abs(self::$renderScale - $renderScale) > 0.0001 ||
      self::$renderOffsetX !== $offsetX ||
      self::$renderOffsetY !== $offsetY;

    self::$width = $terminalWidth;
    self::$height = $terminalHeight;
    self::$logicalWidth = $logicalWidth;
    self::$logicalHeight = $logicalHeight;
    self::$renderScale = $renderScale;
    self::$renderOffsetX = $offsetX;
    self::$renderOffsetY = $offsetY;

    if ($changed && $clearWhenChanged) {
      self::clear();
    } elseif ($changed) {
      self::$buffer = self::getEmptyBuffer();
    } elseif (!isset(self::$buffer)) {
      self::$buffer = self::getEmptyBuffer();
    }

    return $changed;
  }

  /**
   * Returns the current render offset.
   *
   * @return Vector2
   */
  public static function getRenderOffset(): Vector2
  {
    return new Vector2(self::$renderOffsetX, self::$renderOffsetY);
  }

  /**
   * Returns the current uniform render scale.
   *
   * @return float
   */
  public static function getRenderScale(): float
  {
    return self::$renderScale;
  }

  /**
   * Saves the terminal settings.
   *
   * @return void
   */
  public static function saveSettings(): void
  {
    self::$previousTerminalSettings = shell_exec('stty -g') ?? '';
  }

  /**
   * Restores the terminal settings.
   *
   * @return void
   */
  public static function restoreSettings(): void
  {
    shell_exec('stty ' . self::$previousTerminalSettings);
    Console::cursor()->enableBlinking();
  }

  /**
   * Writes a single character to the console at the specified position.
   *
   * @param string $character The character to write.
   * @param int $x The x position.
   * @param int $y The y position.
   * @return void
   */
  public static function writeChar(string $character, int $x, int $y): void
  {
    self::write($character, $x, $y);
  }

  /**
   * Writes a message to the console at the specified position.
   *
   * @param string $message The character to write.
   * @param int $x The x position.
   * @param int $y The y position.
   * @return void
   */
  public static function write(string $message, int $x, int $y): void
  {
    self::writeLine($message, $x, $y);
  }

  /**
   * Writes text to the console at the specified position.
   *
   * @param array<string> $linesOfText The lines of text to write.
   * @param int $x The x position.
   * @param int $y The y position.
   * @return void
   */
  public static function writeLines(array $linesOfText, int $x, int $y): void
  {
    foreach ($linesOfText as $rowIndex => $text) {
      self::writeLine($text, $x, $y + $rowIndex);
    }
  }

  /**
   * Writes text to the console at the specified position.
   *
   * @param string $message The text to write.
   * @param int $x The x position.
   * @param int $y The y position.
   * @return void
   */
  public static function writeLine(string $message, int $x, int $y): void
  {
    $row = self::getTerminalRow($y);

    if ($row < 1 || $row > self::$height) {
      return;
    }

    $columnStart = self::getTerminalColumn($x);
    $skipVisibleChars = max(0, 1 - $columnStart);
    $columnStart = max(1, $columnStart);
    $availableWidth = self::$width - $columnStart + 1;
    $containsAnsi = str_contains($message, "\033");

    if ($availableWidth < 1) {
      return;
    }

    if (!$containsAnsi && $skipVisibleChars === 0 && Unicode::length($message) <= $availableWidth) {
      $visibleMessage = $message;
    } else {
      $visibleMessage = self::sliceTextForDisplay($message, $skipVisibleChars, $availableWidth);
    }

    if ($visibleMessage === '') {
      return;
    }

    $cursor = self::cursor();
    $cursor->moveTo($columnStart, $row);
    echo $visibleMessage;
  }

  /**
   * Writes text to the console at the specified position in the specified color.
   *
   * @param Color $color The color.
   * @param string $message The text to write.
   * @param int $x The x position.
   * @param int $y The y position.
   * @return void
   */
  public static function writeInColor(Color $color, string $message, int $x, int $y): void
  {
    self::writeLine(Color::apply($color, $message), $x, $y);
  }

  /**
   * Erases the character at the specified position.
   *
   * @param int $x The x position.
   * @param int $y The y position.
   * @return void
   */
  public static function erase(int $x, int $y): void
  {
    self::writeLine(' ', $x, $y);
  }

  /**
   * Returns the buffer.
   *
   * @return Grid The buffer.
   */
  public static function getBuffer(): Grid
  {
    return self::$buffer;
  }

  /**
   * Returns the character at the specified position.
   *
   * @param int $x The x position.
   * @param int $y The y position.
   * @return string The character at the specified position.
   */
  public static function charAt(int $x, int $y): string
  {
    if ($x < 0 || $x > DEFAULT_SCREEN_WIDTH || $y < 1 || $y > DEFAULT_SCREEN_HEIGHT) {
      return '';
    }

    $char = substr(self::$buffer[$y], $x, 1);
    return ord($char) === 0 ? ' ' : $char;
  }

  /**
   * Shows a prompt dialog with the given message and title. Returns the user's input.
   *
   * @param string $message The message to show.
   * @param string $title The title of the dialog. Defaults to "Prompt".
   * @param int $width The width of the dialog. Defaults to 34.
   * @return void
   */
  public static function alert(string $message, string $title = 'Alert', int $width = DEFAULT_DIALOG_WIDTH): void
  {
    ModalManager::getInstance()->alert($message, $title, $width);
  }

  /**
   * Shows a confirm dialog with the given message and title. Returns true if the user confirmed, false otherwise.
   *
   * @param string $message The message to show.
   * @param string $title The title of the dialog. Defaults to "Confirm".
   * @param int $width The width of the dialog. Defaults to 34.
   * @return bool Whether the user confirmed or not.
   */
  public static function confirm(string $message, string $title = 'Confirm', int $width = DEFAULT_DIALOG_WIDTH): bool
  {
    return ModalManager::getInstance()->confirm($message, $title, $width);
  }

  /**
   * Shows a prompt dialog with the given message and title. Returns the user's input.
   *
   * @param string $message The message to show.
   * @param string $title The title of the dialog. Defaults to "Prompt".
   * @param string $default The default value of the input. Defaults to an empty string.
   * @param int $width The width of the dialog. Defaults to 34.
   * @return string The user's input.
   */
  public static function prompt(string $message, string $title = 'Prompt', string $default = '', int $width = DEFAULT_DIALOG_WIDTH): string
  {
    return ModalManager::getInstance()->prompt($message, $title, $default, $width);
  }

  /**
   * Shows a select dialog with the given message and title. Returns the index of the selected option.
   *
   * @param string $message The message to show.
   * @param array $options The options to show.
   * @param string $title The title of the dialog. Defaults to "Select".
   * @param int $default The default option. Defaults to 0.
   * @param Vector2|null $position The position of the dialog. Defaults to null.
   * @param int $width The width of the dialog. Defaults to 34.
   * @return int The index of the selected option.
   */
  public static function select(string $message, array $options, string $title = '', int $default = 0, ?Vector2 $position = null, int $width = DEFAULT_DIALOG_WIDTH): int
  {
    return ModalManager::getInstance()->select($message, $options, $title, $default, $position, $width);
  }

  /**
   * Shows a text dialog with the given message and title.
   *
   * @param string $message The message to show.
   * @param string $title The title of the dialog.
   * @param string $help The help text.
   * @param WindowPosition $position The position of the dialog.
   * @param float $charactersPerSecond The number of characters to show per second.
   * @return void
   */
  public static function showText(string $message, string $title = '', string $help = '', WindowPosition $position = WindowPosition::BOTTOM, float $charactersPerSecond = 1): void
  {
    ModalManager::getInstance()->showText($message, $title, $help, $position, $charactersPerSecond);
  }

  /**
   * Returns a new output stream.
   *
   * @return OutputInterface The output stream.
   */
  public static function output(int $verbosity = OutputInterface::VERBOSITY_NORMAL, ?bool $decorated = null, ?OutputFormatterInterface $formatter = null): OutputInterface
  {
    return new ConsoleOutput($verbosity, $decorated, $formatter);
  }

  /**
   * Returns the terminal column for the given logical column.
   *
   * @param int $x The logical x position.
   * @return int
   */
  private static function getTerminalColumn(int $x): int
  {
    return self::$renderOffsetX + max(1, $x) - 1;
  }

  /**
   * Returns the terminal row for the given logical row.
   *
   * @param int $y The logical y position.
   * @return int
   */
  private static function getTerminalRow(int $y): int
  {
    return self::$renderOffsetY + max(1, $y) - 1;
  }

  /**
   * Returns a clipped slice of text for terminal output.
   *
   * @param string $message The message to clip.
   * @param int $skipVisibleChars The number of visible characters to skip.
   * @param int $maxVisibleChars The maximum number of visible characters to keep.
   * @return string
   */
  private static function sliceTextForDisplay(string $message, int $skipVisibleChars, int $maxVisibleChars): string
  {
    if ($maxVisibleChars < 1 || $message === '') {
      return '';
    }

    if (!str_contains($message, "\033")) {
      return Unicode::substring($message, $skipVisibleChars, $maxVisibleChars);
    }

    return self::sliceStyledText($message, $skipVisibleChars, $maxVisibleChars);
  }

  /**
   * Returns a clipped slice of a styled string while preserving ANSI color sequences.
   *
   * @param string $message The styled message to clip.
   * @param int $skipVisibleChars The number of visible characters to skip.
   * @param int $maxVisibleChars The maximum number of visible characters to keep.
   * @return string
   */
  private static function sliceStyledText(string $message, int $skipVisibleChars, int $maxVisibleChars): string
  {
    $glyphs = self::toStyledGlyphs($message);

    if ($glyphs === [] || $maxVisibleChars < 1 || $skipVisibleChars >= count($glyphs)) {
      return '';
    }

    return implode('', array_slice($glyphs, $skipVisibleChars, $maxVisibleChars));
  }

  /**
   * Breaks a styled string into visible glyphs with ANSI color preserved per glyph.
   *
   * @param string $message The styled string.
   * @return string[]
   */
  private static function toStyledGlyphs(string $message): array
  {
    preg_match_all('/\033\[[0-9;]*m|./us', $message, $matches);

    $glyphs = [];
    $activeStyle = '';

    foreach ($matches[0] ?? [] as $token) {
      if (preg_match('/^\033\[[0-9;]*m$/', $token) === 1) {
        if ($token === Color::RESET->value) {
          $activeStyle = '';
        } else {
          $activeStyle .= $token;
        }

        continue;
      }

      $glyphs[] = $activeStyle !== ''
        ? $activeStyle . $token . Color::RESET->value
        : $token;
    }

    return $glyphs;
  }
}
