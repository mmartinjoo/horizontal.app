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

    'test_memgraph_cluster_host' => env('TEST_MEMGRAPH_CLUSTER_HOST'),
    'test_memgraph_cluster_password' => env('TEST_MEMGRAPH_CLUSTER_PASSWORD'),
];
