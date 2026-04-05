<?php

use Sendama\Engine\IO\Enumerations\KeyCode;

return [
  "action" => [
    "description" => "Perform an action.",
    "keys" => [KeyCode::SPACE, KeyCode::ENTER],
  ],
  "cancel" => [
    "description" => "Cancel the current action.",
    "keys" => [KeyCode::C, KeyCode::c, KeyCode::ESCAPE],
  ],
  "back" => [
    "description" => "Go back.",
    "keys" => [KeyCode::ESCAPE],
  ],
  "confirm" => [
    "description" => "Confirm the current action.",
    "keys" => [KeyCode::ENTER],
  ],
  "quit" => [
    "description" => "Quit the game.",
    "keys" => [KeyCode::Q, KeyCode::q],
  ],
];