<?php

use Sendama\Engine\Core\Grid;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Scenes\AbstractScene;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\IO\Enumerations\Color;
use Sendama\Engine\UI\GUITexture\GUITexture;

beforeEach(function () {
  $reflection = new ReflectionClass(Console::class);
  $bufferProperty = $reflection->getProperty('buffer');
  $bufferProperty->setValue(null, new Grid(DEFAULT_SCREEN_HEIGHT, DEFAULT_SCREEN_WIDTH, ' '));
  Console::refreshLayout(
    DEFAULT_SCREEN_WIDTH,
    DEFAULT_SCREEN_HEIGHT,
    new Rect(new Vector2(1, 1), new Vector2(DEFAULT_SCREEN_WIDTH, DEFAULT_SCREEN_HEIGHT)),
    clearWhenChanged: false
  );
});

it('renders gui textures with their configured color after the owning scene starts', function () {
  $scene = new class('Test Scene') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  };

  $guiTexture = new GUITexture(
    $scene,
    'HUD Logo',
    new Vector2(2, 2),
    new Vector2(1, 1),
    'HUD',
    getcwd() . '/tests/Mocks/Textures/test.texture',
    Color::YELLOW,
  );
  $scene->add($guiTexture);
  $scene->loadSceneSettings([
    'screen_width' => DEFAULT_SCREEN_WIDTH,
    'screen_height' => DEFAULT_SCREEN_HEIGHT,
  ]);
  $scene->start();

  ob_start();
  $guiTexture->render();
  $output = ob_get_clean();

  expect($output)
    ->toContain("\033[2;2H")
    ->toContain('>')
    ->toContain(Color::YELLOW->value);
});

it('normalizes gui texture sizes to at least one cell', function () {
  $scene = new class('Test Scene') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  };

  $guiTexture = new GUITexture(
    $scene,
    'HUD Logo',
    new Vector2(2, 2),
    new Vector2(0, 0),
    'HUD',
  );

  expect($guiTexture->getSize()->getX())->toBe(1)
    ->and($guiTexture->getSize()->getY())->toBe(1);

  $guiTexture->setSize(new Vector2(0, 0));

  expect($guiTexture->getSize()->getX())->toBe(1)
    ->and($guiTexture->getSize()->getY())->toBe(1);
});
