<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

$configuredDomains = array_filter(array_map(
    'trim',
    explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', 'localhost:5173,127.0.0.1:5173')),
));
$frontendDomains = array_filter(array_map(function (string $url): ?string {
    $parts = parse_url(trim($url));
    if (! is_array($parts) || empty($parts['host'])) {
        return null;
    }

    return $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
}, explode(',', (string) env('FRONTEND_URL', ''))));

return [
    'stateful' => array_values(array_unique([...$configuredDomains, ...$frontendDomains])),
    'guard' => ['web'],
    'expiration' => null,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
