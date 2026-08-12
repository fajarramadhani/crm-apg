<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOfficeRequest;
use App\Http\Requests\Api\V1\UpdateOfficeRequest;
use App\Http\Resources\Api\V1\OfficeResource;
use App\Models\Office;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminOfficeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = Validator::make($request->query(), [
            'office_type' => ['nullable', 'in:pusat,cabang'],
        ])->validate();

        $offices = Office::query()
            ->when($validated['office_type'] ?? null, fn ($query, $type) => $query->where('office_type', $type))
            ->orderByRaw("case when office_type = 'pusat' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        return ApiResponse::success($request, 'Offices retrieved', OfficeResource::collection($offices));
    }

    public function store(StoreOfficeRequest $request): JsonResponse
    {
        $office = DB::transaction(fn () => Office::query()->create($request->validated()));

        return ApiResponse::success($request, 'Kantor berhasil ditambahkan.', new OfficeResource($office), 201);
    }

    public function show(Request $request, Office $office): JsonResponse
    {
        return ApiResponse::success($request, 'Office retrieved', new OfficeResource($office));
    }

    public function update(UpdateOfficeRequest $request, Office $office): JsonResponse
    {
        $response = DB::transaction(function () use ($request, $office): ?JsonResponse {
            $lockedOffice = Office::query()->lockForUpdate()->findOrFail($office->id);
            if (
                $lockedOffice->office_type === 'pusat'
                && $request->validated('office_type') !== 'pusat'
                && Office::query()->pusat()->lockForUpdate()->get()->count() <= 1
            ) {
                return ApiResponse::error(
                    $request,
                    'Satu-satunya Kantor Pusat tidak dapat diubah menjadi Kantor Cabang.',
                    'LAST_HEAD_OFFICE',
                    409,
                );
            }

            $lockedOffice->update($request->validated());

            return null;
        });

        if ($response) {
            return $response;
        }

        return ApiResponse::success($request, 'Kantor berhasil diperbarui.', new OfficeResource($office->refresh()));
    }

    public function destroy(Request $request, Office $office): JsonResponse
    {
        return DB::transaction(function () use ($request, $office): JsonResponse {
            $lockedOffice = Office::query()->lockForUpdate()->findOrFail($office->id);

            if ($lockedOffice->users()->exists()) {
                return ApiResponse::error(
                    $request,
                    'Kantor tidak dapat dihapus karena masih digunakan oleh akun pengguna.',
                    'OFFICE_IN_USE',
                    409,
                );
            }

            if ($lockedOffice->office_type === 'pusat' && Office::query()->pusat()->lockForUpdate()->get()->count() <= 1) {
                return ApiResponse::error(
                    $request,
                    'Satu-satunya Kantor Pusat tidak dapat dihapus.',
                    'LAST_HEAD_OFFICE',
                    409,
                );
            }

            $lockedOffice->delete();

            return ApiResponse::success($request, 'Kantor berhasil dihapus.');
        });
    }
}
