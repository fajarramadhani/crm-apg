<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProtectedAccessController extends Controller
{
    public function admin(Request $request): JsonResponse
    {
        return ApiResponse::success($request, 'Admin access granted');
    }

    public function executive(Request $request): JsonResponse
    {
        return ApiResponse::success($request, 'Executive aggregate access granted');
    }

    public function technicalTicketDetails(Request $request): JsonResponse
    {
        return ApiResponse::success($request, 'Technical ticket detail access granted');
    }
}
