<?php

use Sendama\Engine\Core\GameObject;

return [
    'name' => 'Scene With Nested Objects',
    'width' => 20,
    'height' => 10,
    'hierarchy' => [
        [
            'type' => GameObject::class,
            'name' => 'Player',
            'tag' => 'Player',
            'position' => [
                'x' => 2,
                'y' => 2,
            ],
            'rotation' => [
                'x' => 0,
                'y' => 0,
            ],
            'scale' => [
                'x' => 1,
                'y' => 1,
            ],
            'children' => [
                [
                    'type' => GameObject::class,
                    'name' => 'Weapon',
                    'tag' => 'Equipment',
                    'position' => [
                        'x' => 1,
                        'y' => 0,
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
            ],
        ],
    ],
];
