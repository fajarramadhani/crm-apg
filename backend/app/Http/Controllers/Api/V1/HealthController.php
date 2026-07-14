<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            Log::error('Database health check failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return ApiResponse::error(
                $request,
                'Tic Hub API database is unavailable',
                'DATABASE_UNAVAILABLE',
                503,
                ['timestamp' => now()->toIso8601String()],
            );
        }

        return ApiResponse::success(
            $request,
            'Tic Hub API is healthy',
            [
                'status' => 'ok',
                'database' => 'connected',
            ],
            meta: ['timestamp' => now()->toIso8601String()],
        );
    }
}
