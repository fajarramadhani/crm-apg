<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReleaseChecklistTemplate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminReleaseChecklistTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($request, 'Release checklist templates retrieved', ReleaseChecklistTemplate::query()->orderBy('sort_order')->get()->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:80', 'unique:release_checklist_templates,code'], 'label' => ['required', 'string'], 'description' => ['nullable', 'string'], 'category' => ['required', 'string'], 'is_required' => ['required', 'boolean'], 'applies_to_release_type' => ['nullable', Rule::in(['standard', 'normal', 'emergency'])], 'sort_order' => ['required', 'integer', 'min:0']]);

        return ApiResponse::success($request, 'Release checklist template created', ReleaseChecklistTemplate::create([...$data, 'is_active' => true])->toArray(), 201);
    }

    public function update(Request $request, ReleaseChecklistTemplate $template): JsonResponse
    {
        $data = $request->validate(['label' => ['sometimes', 'string'], 'description' => ['nullable', 'string'], 'category' => ['sometimes', 'string'], 'is_required' => ['sometimes', 'boolean'], 'applies_to_release_type' => ['nullable', Rule::in(['standard', 'normal', 'emergency'])], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']]);
        $template->update($data);

        return ApiResponse::success($request, 'Release checklist template updated', $template->fresh()->toArray());
    }

    public function destroy(Request $request, ReleaseChecklistTemplate $template): JsonResponse
    {
        $template->update(['is_active' => false]);

        return ApiResponse::success($request, 'Release checklist template deactivated');
    }
}
