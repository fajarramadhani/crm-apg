<?php

return [
    'expiry_days' => env('PUBLIC_TICKET_TRACKING_EXPIRY_DAYS'),
    'key' => env('PUBLIC_TICKET_TRACKING_KEY'),
    'keys' => env('PUBLIC_TICKET_TRACKING_KEYS'),
    'key_version' => env('PUBLIC_TICKET_TRACKING_KEY_VERSION', 1),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'frontend_path' => '/track',
    'update_text_limit' => 2000,
];
