<?php

return [
    'timeout' => env('SUPERVISOR_TIMEOUT', 3600),
    'interval' => env('SUPERVISOR_INTERVAL', 5),
    'stuck_bucket_timeout' => env('SUPERVISOR_STUCK_BUCKET_TIMEOUT', 600),
];