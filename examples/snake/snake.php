<?php

use Sendama\Engine\Game;

require '../../vendor/autoload.php';

function bootstrap(): void
{
  $gameName = 'Snake'; // This will be overwritten by the .env file
  $game = new Game($gameName);
  $game
	->loadScene("Scenes/Level")
	->loadSettings();

  $game->run();
}

bootstrap();
