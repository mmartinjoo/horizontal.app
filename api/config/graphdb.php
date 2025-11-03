<?php

return [
    'default' => env('GRAPH_DB_CONNECTION', 'memgraph'),

    'connections' => [
        'memgraph' => [
            'host'     => '',   // dynamic
            'port'     => '',   // dynamic
            'user'     => '',   // dynamic
            'password' => '',   // dynamic
            'scheme'   => '',   // dynamic, can be 'none', 'bolt' or 'bolt+s' for SSL
        ],
    ],
];
