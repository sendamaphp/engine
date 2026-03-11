<?php

use Sendama\Engine\Core\Grid;
use Sendama\Engine\Core\Rect;
use Sendama\Engine\Core\Scenes\AbstractScene;
use Sendama\Engine\Core\Vector2;
use Sendama\Engine\IO\Console\Console;
use Sendama\Engine\UI\Label\Label;

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

it('does not render labels before their scene starts', function () {
  $scene = new class('Test Scene') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  };

  ob_start();
  new Label($scene, 'Score', new Vector2(1, 1), new Vector2(10, 1));
  $output = ob_get_clean();

  expect($scene->isStarted())->toBeFalse()
    ->and($output)->toBe('');
});

it('renders label updates after the owning scene starts', function () {
  $scene = new class('Test Scene') extends AbstractScene {
    public function awake(): void
    {
      // Do nothing.
    }
  };

  $label = new Label($scene, 'Score', new Vector2(1, 1), new Vector2(10, 1));
  $scene->add($label);
  $scene->loadSceneSettings([
    'screen_width' => DEFAULT_SCREEN_WIDTH,
    'screen_height' => DEFAULT_SCREEN_HEIGHT,
  ]);
  $scene->start();

  ob_start();
  $label->setText('Score: 1');
  $output = ob_get_clean();
  $plainTextOutput = preg_replace('/\e\[[0-9;]*[A-Za-z]/', '', $output);

  expect($output)->not()->toBe('')
    ->and($plainTextOutput)->toContain('Score: 1');
});
