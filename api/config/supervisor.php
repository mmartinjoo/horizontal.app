<?php

return [
    'timeout' => env('SUPERVISOR_TIMEOUT', 3600),
    'interval' => env('SUPERVISOR_INTERVAL', 5),
    'stuck_bucket_timeout' => env('SUPERVISOR_STUCK_BUCKET_TIMEOUT', 1800),
    'stuck_item_timeout' => env('SUPERVISOR_STUCK_ITEM_TIMEOUT', 900),
];