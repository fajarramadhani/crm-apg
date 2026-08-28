<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

$tests = [
    'http://localhost:5173/login',
    'http://localhost:5173/admin/users',
    'http://localhost:5173',
    'http://localhost:5173/',
    'http://127.0.0.1:5173/login',
    null
];

foreach ($tests as $referer) {
    $server = ['HTTP_HOST' => '127.0.0.1:8000'];
    if ($referer !== null) {
        $server['HTTP_REFERER'] = $referer;
    }
    $req = Request::create('/api/v1/auth/me', 'GET', [], [], [], $server);
    $result = EnsureFrontendRequestsAreStateful::fromFrontend($req) ? 'TRUE' : 'FALSE';
    echo sprintf("%-40s => %s\n", $referer ?? '[NO REFERER]', $result);
}
