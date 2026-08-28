<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// Simulate exactly what Vite proxy sends to backend:
$headers = [
    'HTTP_HOST' => '127.0.0.1:8000',
    'HTTP_X_FORWARDED_HOST' => 'localhost:5173',
    'HTTP_X_FORWARDED_PROTO' => 'http',
    'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
    'HTTP_REFERER' => 'http://localhost:5173/login',
    'REMOTE_ADDR' => '127.0.0.1',
];

$req = Request::create('/api/v1/auth/me', 'GET', [], [], [], $headers);
echo "From frontend: " . (EnsureFrontendRequestsAreStateful::fromFrontend($req) ? 'TRUE' : 'FALSE') . "\n";
echo "getHttpHost: " . $req->getHttpHost() . "\n";
echo "getHost: " . $req->getHost() . "\n";
echo "getPort: " . $req->getPort() . "\n";
echo "Scheme and HTTP Host: " . $req->getSchemeAndHttpHost() . "\n";
