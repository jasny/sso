<?php

declare(strict_types=1);

/**
 * Configuration for the demo server.
 * Don't copy this to your own project.
 */

return [
    'brokers' => [
        'Alice' => [
            'secret' => '8iwzik1bwd',
            'domains' => ['localhost'],
        ],
        'Greg' => [
            'secret' => '7pypoox2pc',
            'domains' => ['localhost'],
        ],
        'Julius' => [
            'secret' => 'ceda63kmhp',
            'domains' => ['localhost'],
        ],
    ],
    'users' => [
        'jackie' => [
            'fullname' => 'Jackie Black',
            'email' => 'jackie.black@example.com',
            'password' => '$2y$10$lVUeiphXLAm4pz6l7lF9i.6IelAqRxV4gCBu8GBGhCpaRb6o0qzUO' // jackie123
        ],
        'john' => [
            'fullname' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => '$2y$10$RU85KDMhbh8pDhpvzL6C5.kD3qWpzXARZBzJ5oJ2mFoW7Ren.apC2' // john123
        ],
    ],
];
