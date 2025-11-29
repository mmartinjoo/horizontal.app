<?php

return [
    'queue_based_auto_scaling' => [
        'active' => env('FEATURE_QUEUE_BASED_AUTOSCALING_ACTIVE', false),
    ],
    'registration' => [
        'active' => env('FEATURE_REGISTRATION_ACTIVE', false),
    ],
];
