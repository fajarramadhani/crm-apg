<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiResponse
{
    public static function success(
        Request $request,
        string $message,
        mixed $data = [],
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => self::meta($request, $meta),
        ], $status);
    }

    public static function validationError(
        Request $request,
        array $errors,
        string $message = 'Validation failed',
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'meta' => self::meta($request),
        ], 422);
    }

    public static function error(
        Request $request,
        string $message,
        string $code,
        int $status,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => $code,
            ],
            'meta' => self::meta($request, $meta),
        ], $status);
    }

    private static function meta(Request $request, array $meta = []): array
    {
        return [
            ...$meta,
            'request_id' => $request->attributes->get('request_id'),
        ];
    }
}
