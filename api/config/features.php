<?php

use App\Enums\Integration\Category;
use App\Enums\Integration\Provider;

return [
    'queue_based_auto_scaling' => [
        'active' => env('FEATURE_QUEUE_BASED_AUTOSCALING_ACTIVE', false),
        'description' => 'API pods are scaled based on the number of jobs in queues. If this is active, a scheduled job counts the jobs and puts them in to a Redis list.'
    ],
    'registration' => [
        'active' => env('FEATURE_REGISTRATION_ACTIVE', false),
        'description' => 'If this is active, registration is available on the landing page. Otherwise, only Book a demo is active.',
    ],
    'graph_monitoring' => [
        'active' => env('FEATURE_GRAPH_MONITORING_ACTIVE', true),
        'description' => 'If active, a scheduled job supervises the health of the knowledge graphs',
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
    'llm_rotation' => [
        'active' => env('FEATURE_LLM_ROTATION_ACTIVE', true),
        'description' => 'If active, we rotate LLM providers every X minutes to avoid rate limits',
        'scheduling_frequency_in_minutes' => [
            'value' => env('FEATURE_LLM_ROTATION_FREQUENCY_IN_MINUTES', 15),
            'description' => '15 means, LLM provider is rotated every 15 minute',
        ],
    ],
    'automatic_indexing' => [
        'active' => env('FEATURE_AUTOMATIC_INDEXING_ACTIVE', true),
        'description' => 'If active, we schedule an indexing workflow every day for each tenant',
        'scheduling_daily_at' => [
            'value' => env('FEATURE_AUTOMATIC_INDEXING_SCHEDULING_DAILY_AT', '03:00'),
            'description' => 'The time of scheduling in 24h format. 03:00 means 3AM at night',
            // the server is in UTC. when it's 3AM:
            //  - 7PM-11PM (previous day) in the US
            //  - 1AM-4AM in Europe
        ],
    ],
    'integrations' => [
        Provider::Slack->value => [
            'active' => true,
            'category' => Category::Communication->value,
        ],
        Provider::GoogleChat->value => [
            'active' => true,
            'category' => Category::Communication->value,
        ],
        Provider::Jira->value => [
            'active' => true,
            'category' => Category::TaskManagement->value,
        ],
        Provider::Linear->value => [
            'active' => true,
            'category' => Category::TaskManagement->value,
        ],
        Provider::Github->value => [
            'active' => true,
            'category' => Category::CodeRepository->value,
        ],
        Provider::GoogleDrive->value => [
            'active' => true,
            'category' => Category::Storage->value,
        ],
        Provider::GithubProjects->value => [
            'active' => false,
            'category' => Category::TaskManagement->value,
        ],
        Provider::Confluence->value => [
            'active' => false,
            'category' => Category::Documentation->value,
        ],
    ],
];
