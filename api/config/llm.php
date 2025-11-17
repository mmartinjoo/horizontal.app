<?php

return [
    'default' => env('LLM_PROVIDER', 'fireworks'),

    'connections' => [
        'fireworks' => [
            'chat_model' => env('FIREWORKS_CHAT_MODEL', 'accounts/fireworks/models/kimi-k2-instruct-0905'),
            'embedding_model' => env('FIREWORKS_EMBEDDING_MODEL', 'accounts/fireworks/models/qwen3-embedding-8b'),
            'base_url' => env('FIREWORKS_BASE_URL', 'https://api.fireworks.ai/inference/v1'),
            'api_key' => env('FIREWORKS_API_KEY'),
        ],

        'together' => [
            'chat_model' => env('TOGETHER_CHAT_MODEL', 'moonshotai/Kimi-K2-Instruct-0905'),
            'embedding_model' => env('TOGETHER_EMBEDDING_MODEL', 'Alibaba-NLP/gte-modernbert-base'),
            'base_url' => env('TOGETHER_BASE_URL', 'https://api.together.xyz/v1'),
            'api_key' => env('TOGETHER_API_KEY'),
        ],
    ],
];
