<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicationModuleResource;
use App\Http\Resources\Api\V1\ApplicationResource;
use App\Http\Resources\Api\V1\BranchResource;
use App\Http\Resources\Api\V1\DivisionResource;
use App\Http\Resources\Api\V1\HolidayResource;
use App\Http\Resources\Api\V1\SlaPolicyResource;
use App\Http\Resources\Api\V1\TicketCategoryResource;
use App\Http\Resources\Api\V1\TicketPriorityResource;
use App\Http\Resources\Api\V1\WorkingCalendarResource;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Holiday;
use App\Models\SlaPolicy;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\WorkingCalendar;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    public function divisions(Request $request): JsonResponse
    {
        return $this->active($request, Division::query()->with('parent')->orderBy('name'), DivisionResource::class, 'Divisions retrieved');
    }

    public function branches(Request $request): JsonResponse
    {
        return $this->active($request, Branch::query()->orderBy('name'), BranchResource::class, 'Branches retrieved');
    }

    public function applications(Request $request): JsonResponse
    {
        return $this->active($request, Application::query()->with('ownerDivision')->orderBy('name'), ApplicationResource::class, 'Applications retrieved');
    }

    public function modules(Request $request, Application $application): JsonResponse
    {
        $query = $application->is_active ? $application->modules()->orderBy('name') : $application->modules()->whereRaw('1 = 0');

        return $this->active($request, $query, ApplicationModuleResource::class, 'Application modules retrieved');
    }

    public function categories(Request $request): JsonResponse
    {
        return $this->active($request, TicketCategory::query()->orderBy('name'), TicketCategoryResource::class, 'Ticket categories retrieved');
    }

    public function priorities(Request $request): JsonResponse
    {
        return $this->active($request, TicketPriority::query()->orderBy('level'), TicketPriorityResource::class, 'Ticket priorities retrieved');
    }

    public function slaPolicies(Request $request): JsonResponse
    {
        $query = SlaPolicy::query()
            ->with(['priority', 'workingCalendar'])
            ->whereHas('priority', fn ($priority) => $priority->active())
            ->whereHas('workingCalendar', fn ($calendar) => $calendar->active())
            ->orderBy('resolution_minutes');

        return $this->active($request, $query, SlaPolicyResource::class, 'SLA policies retrieved');
    }

    public function calendars(Request $request): JsonResponse
    {
        return $this->active($request, WorkingCalendar::query()->orderBy('name'), WorkingCalendarResource::class, 'Working calendars retrieved');
    }

    public function holidays(Request $request): JsonResponse
    {
        $items = Holiday::query()->with('workingCalendar')->whereHas('workingCalendar', fn ($query) => $query->active())->orderBy('date')->get();

        return ApiResponse::success($request, 'Holidays retrieved', HolidayResource::collection($items));
    }

    private function active(Request $request, $query, string $resource, string $message): JsonResponse
    {
        return ApiResponse::success($request, $message, $resource::collection($query->active()->get()));
    }
}
