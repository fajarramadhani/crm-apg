<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Master\StoreApplicationModuleRequest;
use App\Http\Requests\Api\V1\Master\StoreApplicationRequest;
use App\Http\Requests\Api\V1\Master\StoreBranchRequest;
use App\Http\Requests\Api\V1\Master\StoreDivisionRequest;
use App\Http\Requests\Api\V1\Master\StoreHolidayRequest;
use App\Http\Requests\Api\V1\Master\StoreSlaPolicyRequest;
use App\Http\Requests\Api\V1\Master\StoreTicketCategoryRequest;
use App\Http\Requests\Api\V1\Master\StoreTicketPriorityRequest;
use App\Http\Requests\Api\V1\Master\StoreWorkingCalendarRequest;
use App\Http\Requests\Api\V1\Master\UpdateApplicationModuleRequest;
use App\Http\Requests\Api\V1\Master\UpdateApplicationRequest;
use App\Http\Requests\Api\V1\Master\UpdateBranchRequest;
use App\Http\Requests\Api\V1\Master\UpdateDivisionRequest;
use App\Http\Requests\Api\V1\Master\UpdateHolidayRequest;
use App\Http\Requests\Api\V1\Master\UpdateSlaPolicyRequest;
use App\Http\Requests\Api\V1\Master\UpdateTicketCategoryRequest;
use App\Http\Requests\Api\V1\Master\UpdateTicketPriorityRequest;
use App\Http\Requests\Api\V1\Master\UpdateWorkingCalendarRequest;
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
use App\Models\ApplicationModule;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Holiday;
use App\Models\SlaPolicy;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\WorkingCalendar;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMasterDataController extends Controller
{
    public function divisions(Request $r): JsonResponse
    {
        return $this->listing($r, Division::query()->with('parent'), DivisionResource::class);
    }

    public function branches(Request $r): JsonResponse
    {
        return $this->listing($r, Branch::query(), BranchResource::class);
    }

    public function applications(Request $r): JsonResponse
    {
        return $this->listing($r, Application::query()->with(['ownerDivision', 'modules']), ApplicationResource::class);
    }

    public function modules(Request $r): JsonResponse
    {
        return $this->listing($r, ApplicationModule::query(), ApplicationModuleResource::class);
    }

    public function categories(Request $r): JsonResponse
    {
        return $this->listing($r, TicketCategory::query(), TicketCategoryResource::class);
    }

    public function priorities(Request $r): JsonResponse
    {
        return $this->listing($r, TicketPriority::query(), TicketPriorityResource::class, 'level');
    }

    public function slaPolicies(Request $r): JsonResponse
    {
        return $this->listing($r, SlaPolicy::query()->with(['priority', 'workingCalendar']), SlaPolicyResource::class, 'id', ['priority.key']);
    }

    public function calendars(Request $r): JsonResponse
    {
        return $this->listing($r, WorkingCalendar::query(), WorkingCalendarResource::class);
    }

    public function holidays(Request $r): JsonResponse
    {
        return $this->listing($r, Holiday::query()->with('workingCalendar'), HolidayResource::class, 'date', [], false);
    }

    public function showDivision(Request $r, Division $division): JsonResponse
    {
        return $this->show($r, $division->load('parent'), DivisionResource::class);
    }

    public function showBranch(Request $r, Branch $branch): JsonResponse
    {
        return $this->show($r, $branch, BranchResource::class);
    }

    public function showApplication(Request $r, Application $application): JsonResponse
    {
        return $this->show($r, $application->load(['ownerDivision', 'modules']), ApplicationResource::class);
    }

    public function storeDivision(StoreDivisionRequest $r): JsonResponse
    {
        return $this->created($r, Division::create($r->validated()), DivisionResource::class);
    }

    public function updateDivision(UpdateDivisionRequest $r, Division $division): JsonResponse
    {
        return $this->updated($r, $division, $r->validated(), DivisionResource::class);
    }

    public function deleteDivision(Request $r, Division $division): JsonResponse
    {
        if ($division->children()->active()->exists() || $division->applications()->active()->exists() || $division->users()->where('is_active', true)->exists()) {
            return ApiResponse::validationError($r, ['division' => ['Deactivate active child divisions, applications, and assigned users first.']]);
        }

        return $this->deactivate($r, $division, DivisionResource::class);
    }

    public function storeBranch(StoreBranchRequest $r): JsonResponse
    {
        return $this->created($r, Branch::create($r->validated()), BranchResource::class);
    }

    public function updateBranch(UpdateBranchRequest $r, Branch $branch): JsonResponse
    {
        return $this->updated($r, $branch, $r->validated(), BranchResource::class);
    }

    public function deleteBranch(Request $r, Branch $branch): JsonResponse
    {
        if ($branch->users()->where('is_active', true)->exists()) {
            return ApiResponse::validationError($r, ['branch' => ['Reassign active users before deactivating this branch.']]);
        }

        return $this->deactivate($r, $branch, BranchResource::class);
    }

    public function storeApplication(StoreApplicationRequest $r): JsonResponse
    {
        return $this->created($r, Application::create($r->validated()), ApplicationResource::class);
    }

    public function updateApplication(UpdateApplicationRequest $r, Application $application): JsonResponse
    {
        return $this->updated($r, $application, $r->validated(), ApplicationResource::class);
    }

    public function deleteApplication(Request $r, Application $application): JsonResponse
    {
        DB::transaction(function () use ($application): void {
            $application->modules()->active()->update(['is_active' => false]);
            $application->update(['is_active' => false]);
        });

        return ApiResponse::success($r, 'Master data deactivated', new ApplicationResource($application->refresh()->load('modules')));
    }

    public function storeModule(StoreApplicationModuleRequest $r, Application $application): JsonResponse
    {
        return $this->created($r, $application->modules()->create($r->validated()), ApplicationModuleResource::class);
    }

    public function updateModule(UpdateApplicationModuleRequest $r, ApplicationModule $module): JsonResponse
    {
        return $this->updated($r, $module, $r->validated(), ApplicationModuleResource::class);
    }

    public function deleteModule(Request $r, ApplicationModule $module): JsonResponse
    {
        return $this->deactivate($r, $module, ApplicationModuleResource::class);
    }

    public function storeCategory(StoreTicketCategoryRequest $r): JsonResponse
    {
        return $this->created($r, TicketCategory::create($r->validated()), TicketCategoryResource::class);
    }

    public function updateCategory(UpdateTicketCategoryRequest $r, TicketCategory $category): JsonResponse
    {
        return $this->updated($r, $category, $r->validated(), TicketCategoryResource::class);
    }

    public function deleteCategory(Request $r, TicketCategory $category): JsonResponse
    {
        return $this->deactivate($r, $category, TicketCategoryResource::class);
    }

    public function storePriority(StoreTicketPriorityRequest $r): JsonResponse
    {
        return $this->created($r, TicketPriority::create($r->validated()), TicketPriorityResource::class);
    }

    public function updatePriority(UpdateTicketPriorityRequest $r, TicketPriority $priority): JsonResponse
    {
        return $this->updated($r, $priority, $r->validated(), TicketPriorityResource::class);
    }

    public function deletePriority(Request $r, TicketPriority $priority): JsonResponse
    {
        if ($priority->slaPolicies()->active()->exists()) {
            return ApiResponse::validationError($r, ['priority' => ['Deactivate active SLA policies before deactivating this priority.']]);
        }

        return $this->deactivate($r, $priority, TicketPriorityResource::class);
    }

    public function storeSlaPolicy(StoreSlaPolicyRequest $r): JsonResponse
    {
        return $this->created($r, SlaPolicy::create($r->validated())->load(['priority', 'workingCalendar']), SlaPolicyResource::class);
    }

    public function updateSlaPolicy(UpdateSlaPolicyRequest $r, SlaPolicy $policy): JsonResponse
    {
        return $this->updated($r, $policy, $r->validated(), SlaPolicyResource::class, ['priority', 'workingCalendar']);
    }

    public function deleteSlaPolicy(Request $r, SlaPolicy $policy): JsonResponse
    {
        return $this->deactivate($r, $policy, SlaPolicyResource::class);
    }

    public function storeCalendar(StoreWorkingCalendarRequest $r): JsonResponse
    {
        return $this->created($r, WorkingCalendar::create($r->validated()), WorkingCalendarResource::class);
    }

    public function updateCalendar(UpdateWorkingCalendarRequest $r, WorkingCalendar $calendar): JsonResponse
    {
        return $this->updated($r, $calendar, $r->validated(), WorkingCalendarResource::class);
    }

    public function deleteCalendar(Request $r, WorkingCalendar $calendar): JsonResponse
    {
        if ($calendar->slaPolicies()->active()->exists()) {
            return ApiResponse::validationError($r, ['calendar' => ['Deactivate active SLA policies before deactivating this calendar.']]);
        }

        return $this->deactivate($r, $calendar, WorkingCalendarResource::class);
    }

    public function storeHoliday(StoreHolidayRequest $r, WorkingCalendar $calendar): JsonResponse
    {
        if ($calendar->holidays()->whereDate('date', $r->validated('date'))->exists()) {
            return ApiResponse::validationError($r, ['date' => ['A holiday already exists on this date for the selected calendar.']]);
        }

        return $this->created($r, $calendar->holidays()->create($r->safe()->except('working_calendar_id'))->load('workingCalendar'), HolidayResource::class);
    }

    public function updateHoliday(UpdateHolidayRequest $r, Holiday $holiday): JsonResponse
    {
        if (Holiday::query()->where('working_calendar_id', $holiday->working_calendar_id)->whereDate('date', $r->validated('date'))->whereKeyNot($holiday->id)->exists()) {
            return ApiResponse::validationError($r, ['date' => ['A holiday already exists on this date for the selected calendar.']]);
        }

        return $this->updated($r, $holiday, $r->safe()->except('working_calendar_id'), HolidayResource::class, ['workingCalendar']);
    }

    public function deleteHoliday(Request $r, Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return ApiResponse::success($r, 'Holiday deleted', []);
    }

    private function listing(Request $request, Builder $query, string $resource, string $order = 'name', array $relatedSearch = [], bool $activeFilter = true): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'is_active' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function (Builder $q) use ($search, $relatedSearch) {
                if (in_array('priority.key', $relatedSearch, true)) {
                    $q->whereHas('priority', fn ($p) => $p->where('key', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                } else {
                    $q->where('name', 'like', "%{$search}%");
                    if ($q->getModel()->getTable() !== 'holidays') {
                        $q->orWhere($q->getModel()->getTable() === 'ticket_priorities' ? 'key' : 'code', 'like', "%{$search}%");
                    }
                }
            });
        }
        if ($activeFilter && $request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        $paginator = $query->orderBy($order)->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success($request, 'Master data retrieved', $resource::collection($paginator->getCollection()), 200, ['pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()]]);
    }

    private function show(Request $r, Model $m, string $resource): JsonResponse
    {
        return ApiResponse::success($r, 'Master data retrieved', new $resource($m));
    }

    private function created(Request $r, Model $m, string $resource): JsonResponse
    {
        return ApiResponse::success($r, 'Master data created', new $resource($m), 201);
    }

    private function updated(Request $r, Model $m, array $data, string $resource, array $with = []): JsonResponse
    {
        $m->update($data);
        $m->refresh();
        if ($with) {
            $m->load($with);
        }

        return ApiResponse::success($r, 'Master data updated', new $resource($m));
    }

    private function deactivate(Request $r, Model $m, string $resource): JsonResponse
    {
        $m->update(['is_active' => false]);

        return ApiResponse::success($r, 'Master data deactivated', new $resource($m->refresh()));
    }
}
