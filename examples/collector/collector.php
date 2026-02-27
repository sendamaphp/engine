<?php

use Amasiye\Figlet\FontName;
use Sendama\Engine\Core\Scenes\TitleScene;
use Sendama\Engine\Game;
use Sendama\Engine\UI\Menus\MenuItems\MenuItem;
use Sendama\Examples\Collector\Scenes\Level01;
use Sendama\Examples\Collector\Scenes\Level02;
use Sendama\Examples\Collector\Scenes\SettingsScene;

require '../../vendor/autoload.php';

function bootstrap(): void
{
  $gameName = 'The Collector'; // This will be overwritten by the .env file
  $game = new Game($gameName);
  $game->loadSettings();

  $settingsScene = new SettingsScene('Settings');
  $titleScene = new TitleScene('Title Screen');
  $titleScene->setMenuTitle($gameName);
  $titleScene
    ->setTitleFont(FontName::BASIC)
    ->setNewGameSceneIndex(2)
    ->addMenuItems(
      new MenuItem(
        'Settings',
        'The main settings menu',
        '⚙️',
        fn() => loadScene('Settings')
      )
    );

  $game->addScenes(
      $titleScene,
      $settingsScene,
      new Level01('Level01'),
      new Level02('Level02'),
    )
    ->run();
}

bootstrap();