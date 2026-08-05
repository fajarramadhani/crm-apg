<?php

return [
    'driver' => env('PUBLIC_HISTORY_DRIVER', 'mail'),
    'otp_expiry_minutes' => (int) env('PUBLIC_HISTORY_OTP_EXPIRY_MINUTES', 10),
    'max_attempts' => (int) env('PUBLIC_HISTORY_MAX_ATTEMPTS', 5),
    'resend_cooldown_seconds' => (int) env('PUBLIC_HISTORY_RESEND_COOLDOWN_SECONDS', 60),
    'access_ttl_minutes' => (int) env('PUBLIC_HISTORY_ACCESS_TTL_MINUTES', 15),
    'action_access_ttl_minutes' => (int) env('PUBLIC_ACTION_ACCESS_TTL_MINUTES', 10),
    'identity_key' => env('PUBLIC_HISTORY_IDENTITY_KEY'),
    'otp_pepper' => env('PUBLIC_HISTORY_OTP_PEPPER'),
];
