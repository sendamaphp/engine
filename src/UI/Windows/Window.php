<?php

namespace Sendama\Engine\UI\Windows;

use Assegai\Collections\ItemList;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\Debug\Debug;
use Sendama\Engine\Events\Interfaces\EventInterface;
use Sendama\Engine\Events\Interfaces\ObserverInterface;
use Sendama\Engine\Events\Interfaces\StaticObserverInterface;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Console\Cursor;
use Sendama\Engine\IO\Enumerations\Color;
use Sendama\Engine\UI\Windows\Enumerations\HorizontalAlignment;
use Sendama\Engine\UI\Windows\Enumerations\VerticalAlignment;
use Sendama\Engine\UI\Windows\Interfaces\BorderPackInterface;
use Sendama\Engine\UI\Windows\Interfaces\WindowInterface;

/**
 * Window class.The base class for all windows.
 *
 * @package Sendama\Engine\UI\Windows
 */
class Window implements WindowInterface
{
  /**
   * @var string[] $content
   */
  protected array $content = [];
  /**
   * @var ItemList<ObserverInterface> $observers
   */
  protected ItemList $observers;

  /**
   * @var ItemList<StaticObserverInterface> $staticObservers
   */
  protected ItemList $staticObservers;
  /**
   * @var Cursor $cursor
   */
  protected Cursor $cursor;

  /**
   * Constructs a window.
   *
   * @param string $title The window title.
   * @param string $help The window's help message.
   * @param Vector2 $position The position of the window.
   * @param int $width The width of the window.
   * @param int $height The height of the window.
   * @param BorderPackInterface $borderPack The border pack to use when rendering the window border.
   * @param WindowAlignment $alignment The window's alignment.
   * @param WindowPadding $padding The window's padding.
   * @param Color $backgroundColor The window's background color.
   * @param Color|null $foregroundColor The window's foreground color.
   */
  public function __construct(
    protected string $title = '',
    protected string $help = '',
    protected Vector2 $position = new Vector2(),
    protected int $width = DEFAULT_WINDOW_WIDTH,
    protected int $height = DEFAULT_WINDOW_HEIGHT,
    protected BorderPackInterface $borderPack = new BorderPack(''),
    protected WindowAlignment $alignment = new WindowAlignment(HorizontalAlignment::LEFT, VerticalAlignment::MIDDLE),
    protected WindowPadding $padding = new WindowPadding(rightPadding: 1, leftPadding: 1),
    protected Color $backgroundColor = Color::BLACK,
    protected ?Color $foregroundColor = null
  )
  {
    $this->cursor = Console::cursor();
    $this->observers = new ItemList(ObserverInterface::class);
  }

  /**
   * @inheritDoc
   */
  public function getTitle(): string
  {
    return substr($this->title, 0, $this->width - 3);
  }

  /**
   * @inheritDoc
   */
  public function setTitle(string $title): void
  {
    $this->title = $title;
  }

  /**
   * @inheritDoc
   */
  public function getHelp(): string
  {
    return substr($this->help, 0, $this->width - 3);
  }

  /**
   * @inheritDoc
   */
  public function setHelp(string $help): void
  {
    $this->help = $help;
  }

  /**
   * @inheritDoc
   */
  public function setPosition(Vector2|array $position): void
  {
    $this->position = is_array($position) ? Vector2::fromArray($position) : $position;
  }

  /**
   * @inheritDoc
   */
  public function getPosition(): Vector2
  {
    return $this->position;
  }

  /**
   * @inheritDoc
   */
  public function getBorderPack(): BorderPackInterface
  {
    return $this->borderPack;
  }

  /**
   * @inheritDoc
   */
  public function setBorderPack(BorderPackInterface $borderPack): void
  {
    $this->borderPack = $borderPack;
  }

  /**
   * @inheritDoc
   */
  public function getAlignment(): WindowAlignment
  {
    return $this->alignment;
  }

  /**
   * @inheritDoc
   */
  public function setAlignment(WindowAlignment $alignment): void
  {
    $this->alignment = $alignment;
  }

  /**
   * @inheritDoc
   */
  public function getBackgroundColor(): Color
  {
    return $this->backgroundColor;
  }

  /**
   * @inheritDoc
   */
  public function setBackgroundColor(Color $backgroundColor): void
  {
    $this->backgroundColor = $backgroundColor;
  }

  /**
   * @inheritDoc
   */
  public function getForegroundColor(): ?Color
  {
    return $this->foregroundColor;
  }

  /**
   * @inheritDoc
   */
  public function setForegroundColor(Color $color): void
  {
    $this->foregroundColor = $color;
  }

  /**
   * @inheritDoc
   */
  public function getContent(): array
  {
    return $this->content;
  }

  /**
   * @inheritDoc
   */
  public function setContent(array $content): void
  {
    $this->content = $content;
  }

  /**
   * @inheritDoc
   */
  public function render(): void
  {
    $this->renderAt(0, 0);
  }

  /**
   * @inheritDoc
   */
  public function renderAt(?int $x = null, ?int $y = null): void
  {
    $leftMargin = $this->position->getX() + ($x ?? 0);
    $topMargin = $this->position->getY() + ($y ?? 0);

    // Render the top border
    $topBorderHeight = 1;
    $output = $this->getTopBorder();
    Console::writeLine($output, $leftMargin, $topMargin);

    // Render content
    $linesOfContent = $this->getLinesOfContent();
    if (!$linesOfContent) {
      $linesOfContent = [''];
    }

    foreach ($linesOfContent as $index => $line) {
      Console::writeLine(
        mb_substr($line, 0, $this->width),
        $leftMargin,
        $topMargin + $index + $topBorderHeight
      );
    }

    // Render the bottom border
    $topMargin = $topMargin + count($linesOfContent) + $topBorderHeight;
    $output = $this->getBottomBorder();
    Console::writeLine($output, $leftMargin, $topMargin);
  }

  /**
   * @inheritDoc
   */
  public function erase(): void
  {
    $this->eraseAt(0, 0);
  }

  /**
   * @inheritDoc
   */
  public function eraseAt(?int $x = null, ?int $y = null): void
  {
    $leftMargin = $this->position->getX() + ($x ?? 0);
    $topMargin = $this->position->getY() + ($y ?? 0);

    for ($i = 0; $i < $this->height; $i++) {
      Console::writeLine(str_repeat(' ', $this->width), $leftMargin, $topMargin + $i);
    }
  }

  /**
   * @inheritDoc
   */
  public function addObservers(ObserverInterface|StaticObserverInterface|string ...$observers): void
  {
    foreach ($observers as $observer) {
      if ( is_object($observer) ) {
        if (get_class($observer) === ObserverInterface::class) {
          $this->observers->add($observer);
        }

        if (get_class($observer) === StaticObserverInterface::class) {
          $this->staticObservers->add($observer);
        }
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function removeObservers(ObserverInterface|StaticObserverInterface|string|null ...$observers): void
  {
    foreach ($observers as $observer) {
      if ( is_object($observer) ) {
        if (get_class($observer) === ObserverInterface::class) {
          $this->observers->remove($observer);
        }

        if (get_class($observer) === StaticObserverInterface::class) {
          $this->staticObservers->remove($observer);
        }
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function notify(EventInterface $event): void
  {
    foreach ($this->observers as $observer) {
      $observer->onNotify($event);
    }

    foreach ($this->staticObservers as $observer) {
      $observer::onNotify($event);
    }
  }

  /**
   * Returns the window's top border.
   *
   * @return string The window's top border.
   */
  private function getTopBorder(): string
  {
    $titleLength = strlen($this->getTitle());
    $borderLength = $this->width - $titleLength  - 3;
    $output = $this->borderPack->getTopLeftCorner() . $this->borderPack->getHorizontalBorder() . $this->title;
    $output .= str_repeat($this->borderPack->getHorizontalBorder(), $borderLength);
    $output .= $this->borderPack->getTopRightCorner();

    if ($this->foregroundColor)
    {
      return $this->foregroundColor->value . $output . Color::RESET->value;
    }

    return  $output;
  }

  /**
   * Returns the window's lines of content
   *
   * @return string[] The window's lines of content.
   */
  private function getLinesOfContent(): array
  {
    $content = [];

    // Top padding
    for ($row = 0; $row < $this->padding->getTopPadding(); $row++)
    {
      $output = $this->borderPack->getVerticalBorder();
      $output .= str_repeat(' ', $this->width - 2);
      $output .= $this->borderPack->getVerticalBorder();
      $content[]  = $output;
    }

    $alignedContent = match ($this->alignment->horizontalAlignment) {
      HorizontalAlignment::LEFT => $this->getLeftAlignedContent(),
      HorizontalAlignment::CENTER => $this->getCenterAlignedContent(),
      HorizontalAlignment::RIGHT => $this->getRightAlignedContent(),
    };

    foreach ($alignedContent as $line)
    {
      if ($this->foregroundColor)
      {
        $content[] = $this->foregroundColor->value . $line . Color::RESET->value;
      }
      else
      {
        $content[] = $line;
      }
    }

    // Bottom padding
    for ($row = 0; $row < $this->padding->getBottomPadding(); $row++)
    {
      $output = $this->borderPack->getVerticalBorder();
      $output .= str_repeat(' ', $this->width - 2);
      $output .= $this->borderPack->getVerticalBorder();
      $content[] = $output;
    }

    return $content;
  }

  /**
   * Returns the window's bottom border.
   *
   * @return string The window's bottom border.
   */
  private function getBottomBorder(): string
  {
    $helpLength = strlen($this->getHelp());
    $output = $this->borderPack->getBottomLeftCorner() . $this->borderPack->getHorizontalBorder() . $this->help;
    $output .= str_repeat($this->borderPack->getHorizontalBorder(), $this->width - $helpLength - 3);
    $output .= $this->borderPack->getBottomRightCorner();

    if ($this->foregroundColor)
    {
      return $this->foregroundColor->value . $output . Color::RESET->value;
    }

    return $output;
  }

  /**
   * Returns the window's left aligned content.
   *
   * @return string[] The window's left aligned content.
   */
  private function getLeftAlignedContent(): array
  {
    $leftAlignedContent = [];

    foreach ($this->content as $content)
    {
      $contentLength = mb_strlen($content);
      $leftPaddingLength = $this->padding->getLeftPadding();
      $rightPaddingLength = $this->width - $contentLength - $this->padding->getRightPadding() - 2;

      $output = $this->borderPack->getVerticalBorder();
      $output .= str_repeat(' ', max($leftPaddingLength, 0));
      $output .= $content;
      $output .= str_repeat(' ', max($rightPaddingLength, 0));
      $output .= $this->borderPack->getVerticalBorder();

      $leftAlignedContent[] = $output;
    }

    return $leftAlignedContent;
  }

  /**
   * Returns the window's center aligned content.
   *
   * @return string[] The window's center aligned content.
   */
  private function getCenterAlignedContent(): array
  {
    $centerAlignedContent = [];

    foreach ($this->content as $content)
    {
      $contentLength = mb_strlen($content);
      $totalPadding = $this->width - $this->padding->getLeftPadding() - $contentLength - $this->padding->getRightPadding() - 2;
      $leftPaddingLength = max(floor($totalPadding / 2), 0);
      $rightPaddingLength = max(ceil($totalPadding / 2), 0);

      $output = $this->borderPack->getVerticalBorder();
      $contentRender = str_repeat(' ', (int)max($leftPaddingLength, 0));
      $contentRender .= $content;
      $contentRender .= str_repeat(' ', (int)max($rightPaddingLength, 0));

      $output .= str_pad($contentRender, $this->width - 2, ' ', STR_PAD_BOTH);
      $output .= $this->borderPack->getVerticalBorder();

      $centerAlignedContent[] = $output;
    }

    return $centerAlignedContent;
  }

  /**
   * Returns the window's right aligned content.
   *
   * @return string[] The window's right aligned content.
   */
  private function getRightAlignedContent(): array
  {
    $rightAlignedContent = [];

    foreach ($this->content as $content)
    {
      $contentLength = mb_strlen($content);
      $leftPaddingLength = $this->width - $contentLength - $this->padding->getLeftPadding() - 2;
      $rightPaddingLength = $this->padding->getRightPadding(); // -1 for the border

      $output = $this->borderPack->getVerticalBorder();
      $output .= str_repeat(' ', max($leftPaddingLength, 0));
      $output .= $content;
      $output .= str_repeat(' ', max($rightPaddingLength, 0));
      $output .= $this->borderPack->getVerticalBorder();

      $rightAlignedContent[] = $output;
    }

    return $rightAlignedContent;
  }

}
