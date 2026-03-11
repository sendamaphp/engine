<?php

use Sendama\Engine\Core\Behaviours\SimpleQuitListener;
use Sendama\Pong\Scripts\PaddleController;

return [
    "name" => "Level",
    "width" => 120,
    "height" => 30,
    "environmentTileMapPath" => "Maps/example",
    "hierarchy" => [
        [
            "name" => "Game Manager",
            "tag" => "GameManager",
            "components" => [
                [ "class" => SimpleQuitListener::class ]
            ]
        ],
        [
            "name" => "PaddleLeft",
            "tag" => "Player",
            "position" => ["x" => 3, "y" => 15],
            "rotation" => ["x" => 0, "y" => 0],
            "scale" => ["x" => 1, "y" => 1],
            "sprite" => [
                "texture" => [
                    "path" => "Textures/paddle",
                    "position" => ["x" => 0, "y" => 0],
                    "size" => ["x" => 1, "y" => 5]
                ]
            ],
            "components" => [
                [
                    "class" => PaddleController::class
                ]
            ]
        ]
    ]
];
