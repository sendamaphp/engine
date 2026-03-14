<?php

use Sendama\Engine\Core\GameObject;
use Sendama\Engine\UI\Label\Label;

return [
    'name' => 'Scene With Named Objects',
    'width' => 20,
    'height' => 10,
    'hierarchy' => [
        [
            'type' => GameObject::class,
            'name' => 'Player',
            'tag' => 'Player',
            'position' => [
                'x' => 1,
                'y' => 1,
            ],
            'rotation' => [
                'x' => 0,
                'y' => 0,
            ],
            'scale' => [
                'x' => 1,
                'y' => 1,
            ],
        ],
        [
            'type' => Label::class,
            'name' => 'Score',
            'tag' => 'UI',
            'position' => [
                'x' => 1,
                'y' => 1,
            ],
            'size' => [
                'x' => 10,
                'y' => 1,
            ],
            'text' => 'Score: 0',
        ],
    ],
];
