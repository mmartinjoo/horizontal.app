<?php

return [
    'default' => env('GRAPH_DB_CONNECTION', 'memgraph'),

    'connections' => [
        'memgraph' => [
            'host'     => env('GRAPH_DB_HOST', 'memgraph'),
            'port'     => env('GRAPH_DB_PORT', 7687),
            'user'     => env('GRAPH_DB_USER', null),
            'password' => env('GRAPH_DB_PASSWORD', null),
            'scheme'   => env('GRAPH_DB_SCHEME', 'none'), // 'none', 'bolt' or 'bolt+s' for SSL
        ],
    ],
];
