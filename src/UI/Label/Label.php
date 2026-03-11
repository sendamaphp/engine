<?php

namespace Sendama\Engine\UI\Label;

use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\UI\UIElement;

/**
 * Represents a label UI element.
 *
 * @package Sendama\Engine\UI\Label
 */
class Label extends UIElement
{
  /**
   * The text of the label.
   *
   * @var string
   */
  protected string $text = '';

  /**
   * Returns the text of the label.
   *
   * @return string The text of the label.
   */
  public function getText(): string
  {
    return $this->text;
  }

  /**
   * Sets the text of the label.
   *
   * @param string $text The text of the label.
   * @return void
   */
  public function setText(string $text): void
  {
    if ($this->shouldRenderWithinScene()) {
      $this->erase();
    }

    $this->text = $text;

    if ($this->shouldRenderWithinScene()) {
      $this->render();
    }
  }

  /**
   * @inheritDoc
   */
  public function render(): void
  {
    $this->renderAt($this->position->getX(), $this->position->getY());
  }

  /**
   * @inheritDoc
   */
  public function renderAt(?int $x = null, ?int $y = null): void
  {
    Console::write($this->text, $x ?? 0, $y ?? 0);
  }

  /**
   * @inheritDoc
   */
  public function erase(): void
  {
    $this->eraseAt($this->position->getX(), $this->position->getY());
  }

  /**
   * @inheritDoc
   */
  public function eraseAt(?int $x = null, ?int $y = null): void
  {
    $buffer = str_repeat(' ', strlen($this->text));
    Console::write($buffer, $x ?? 0, $y ?? 0);
  }

  /**
   * @inheritDoc
   */
  public function awake(): void
  {
    $this->setText($this->name);
  }

  /**
   * @inheritDoc
   */
  public function start(): void
  {
    // Do nothing
  }

  /**
   * @inheritDoc
   */
  public function update(): void
  {
    // Do nothing
  }
}
