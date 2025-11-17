<?php

return [
    'default' => env('LLM_PROVIDER', 'fireworkds'),

    'connections' => [
        'fireworks' => [
            'model' => env('FIREWORKS_MODEL', 'accounts/fireworks/models/kimi-k2-instruct-0905'),
            'base_url' => env('FIREWORKS_BASE_URL', 'https://api.fireworks.ai/inference/v1'),
            'api_key' => env('FIREWORKS_API_KEY'),
        ],

        // 'together' => [
        //     'model' => env('TOGETHER_MODEL', 'moonshotai/Kimi-K2-Instruct-0905'),
        //     'base_url' => env('TOGETHER_BASE_URL', 'https://api.together.xyz/v1'),
        //     'api_key' => env('TOGETHER_API_KEY'),
        // ],
    ],
];
