<?php

return [
    'queue_based_auto_scaling' => [
        'active' => env('FEATURE_QUEUE_BASED_AUTOSCALING_ACTIVE', false),
        'description' => 'API pods are scled based on the number of jobs in queues. If this is active, a scheduled job counts the jobd and puts them in to a Redis list.'
    ],
    'registration' => [
        'active' => env('FEATURE_REGISTRATION_ACTIVE', false),
        'description' => 'If this is active, registration is available on the landing page. Otherwise, only Book a demo is active.',
    ],
    'emergency_graph_building' => [
        'active' => env('FEATURE_EMERGENCY_GRAPH_BUILDING_ACTIVE', false),
        'description' => 'If this is active, a scheduled job checks the graph every X minutes and builds it, if empty',
    ],
];
