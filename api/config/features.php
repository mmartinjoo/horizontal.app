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
    'graph_monitoring' => [
        'active' => env('FEATURE_GRAPH_MONITORING_ACTIVE', false),
        'description' => 'If this is active, a scheduled job supervises the health of the knowledge graphs',
        'emergency_action' => [
            'slack_warning' => [
                'active' => env('FEATURE_GRAPH_MONITORING_EMERGENCY_ACTION_SLACK_WARNING_ACTIVE', true),
                'log_level' => 'critical',
                'description' => 'If the graph is unhealthy we send a Slack notification',
            ],
            'graph_building' => [
                'active' => env('FEATURE_GRAPH_MONITORING_EMERGENCY_ACTION_GRAPH_BUILDING_ACTIVE', false),
                'description' => 'If the graph is unhealthy we schedule an emergency graph building process',
            ],
        ],
    ],
];
