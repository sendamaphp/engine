<?php

use Sendama\Engine\Core\GameObject;

return [
    'name' => 'Scene With Component Data',
    'width' => 80,
    'height' => 25,
    'hierarchy' => [
        [
            'type' => GameObject::class,
            'name' => 'Probe',
            'tag' => 'Probe',
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
            'components' => [
                [
                    'class' => SceneManagerDataProbe::class,
                    'data' => [
                        'speed' => 3,
                        'power' => 7,
                    ],
                ],
            ],
        ],
    ],
];
