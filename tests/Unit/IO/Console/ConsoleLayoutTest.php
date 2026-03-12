<?php

use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;

it('computes a centered render frame from the terminal size', function () {
  Console::refreshLayout(
    80,
    24,
    new Rect(new Vector2(1, 1), new Vector2(120, 60)),
    clearWhenChanged: false
  );

  $offset = Console::getRenderOffset();

  expect(round(Console::getRenderScale(), 2))->toBe(1.0)
    ->and($offset->getX())->toBe(21)
    ->and($offset->getY())->toBe(19);
});

it('requests terminal maximization instead of resizing the character grid', function () {
  ob_start();
  Console::maximizeWindow();
  $output = ob_get_clean();

  expect($output)->toBe("\033[9;1t");
});

it('renders text from the centered viewport origin without duplicating glyphs', function () {
  Console::refreshLayout(
    80,
    24,
    new Rect(new Vector2(1, 1), new Vector2(200, 40)),
    clearWhenChanged: false
  );

  ob_start();
  Console::write('A', 1, 1);
  $output = ob_get_clean();

  expect($output)->toContain("\033[9;61HA")
    ->not()->toContain('AA');
});

it('clips centered output when the terminal is smaller than the scene', function () {
  Console::refreshLayout(
    6,
    4,
    new Rect(new Vector2(1, 1), new Vector2(4, 4)),
    clearWhenChanged: false
  );

  ob_start();
  Console::write('ABCDEF', 1, 1);
  $output = ob_get_clean();

  expect($output)->toContain("\033[1;1HBCDE");
});

it('renders unclipped unicode glyphs without breaking them into replacement characters', function () {
  Console::refreshLayout(
    1,
    1,
    new Rect(new Vector2(1, 1), new Vector2(1, 1)),
    clearWhenChanged: false
  );

  ob_start();
  Console::write('→', 1, 1);
  $output = ob_get_clean();

  expect($output)->toContain("\033[1;1H→")
    ->not()->toContain('�')
    ->not()->toContain('?');
});
