<?php

use App\Http\Controllers\Api\V1\AdminMasterDataController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MasterDataController;
use App\Http\Controllers\Api\V1\ProtectedAccessController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])->middleware('active')->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::prefix('master')->middleware('permission:master_data.view')->name('api.v1.master.')->group(function (): void {
            Route::get('divisions', [MasterDataController::class, 'divisions'])->name('divisions');
            Route::get('branches', [MasterDataController::class, 'branches'])->name('branches');
            Route::get('applications', [MasterDataController::class, 'applications'])->name('applications');
            Route::get('applications/{application}/modules', [MasterDataController::class, 'modules'])->name('application-modules');
            Route::get('ticket-categories', [MasterDataController::class, 'categories'])->name('ticket-categories');
            Route::get('ticket-priorities', [MasterDataController::class, 'priorities'])->name('ticket-priorities');
            Route::get('sla-policies', [MasterDataController::class, 'slaPolicies'])->name('sla-policies');
            Route::get('working-calendars', [MasterDataController::class, 'calendars'])->name('working-calendars');
            Route::get('holidays', [MasterDataController::class, 'holidays'])->name('holidays');
        });

        Route::prefix('admin')->middleware('permission:master_data.manage')->name('api.v1.admin.')->group(function (): void {
            Route::get('divisions', [AdminMasterDataController::class, 'divisions']);
            Route::post('divisions', [AdminMasterDataController::class, 'storeDivision']);
            Route::get('divisions/{division}', [AdminMasterDataController::class, 'showDivision']);
            Route::put('divisions/{division}', [AdminMasterDataController::class, 'updateDivision']);
            Route::delete('divisions/{division}', [AdminMasterDataController::class, 'deleteDivision']);
            Route::get('branches', [AdminMasterDataController::class, 'branches']);
            Route::post('branches', [AdminMasterDataController::class, 'storeBranch']);
            Route::get('branches/{branch}', [AdminMasterDataController::class, 'showBranch']);
            Route::put('branches/{branch}', [AdminMasterDataController::class, 'updateBranch']);
            Route::delete('branches/{branch}', [AdminMasterDataController::class, 'deleteBranch']);
            Route::get('applications', [AdminMasterDataController::class, 'applications']);
            Route::post('applications', [AdminMasterDataController::class, 'storeApplication']);
            Route::get('applications/{application}', [AdminMasterDataController::class, 'showApplication']);
            Route::put('applications/{application}', [AdminMasterDataController::class, 'updateApplication']);
            Route::delete('applications/{application}', [AdminMasterDataController::class, 'deleteApplication']);
            Route::get('application-modules', [AdminMasterDataController::class, 'modules']);
            Route::post('applications/{application}/modules', [AdminMasterDataController::class, 'storeModule']);
            Route::put('application-modules/{module}', [AdminMasterDataController::class, 'updateModule']);
            Route::delete('application-modules/{module}', [AdminMasterDataController::class, 'deleteModule']);
            Route::get('ticket-categories', [AdminMasterDataController::class, 'categories']);
            Route::post('ticket-categories', [AdminMasterDataController::class, 'storeCategory']);
            Route::put('ticket-categories/{category}', [AdminMasterDataController::class, 'updateCategory']);
            Route::delete('ticket-categories/{category}', [AdminMasterDataController::class, 'deleteCategory']);
            Route::get('ticket-priorities', [AdminMasterDataController::class, 'priorities']);
            Route::post('ticket-priorities', [AdminMasterDataController::class, 'storePriority']);
            Route::put('ticket-priorities/{priority}', [AdminMasterDataController::class, 'updatePriority']);
            Route::delete('ticket-priorities/{priority}', [AdminMasterDataController::class, 'deletePriority']);
            Route::get('sla-policies', [AdminMasterDataController::class, 'slaPolicies']);
            Route::post('sla-policies', [AdminMasterDataController::class, 'storeSlaPolicy']);
            Route::put('sla-policies/{policy}', [AdminMasterDataController::class, 'updateSlaPolicy']);
            Route::delete('sla-policies/{policy}', [AdminMasterDataController::class, 'deleteSlaPolicy']);
            Route::get('working-calendars', [AdminMasterDataController::class, 'calendars']);
            Route::post('working-calendars', [AdminMasterDataController::class, 'storeCalendar']);
            Route::put('working-calendars/{calendar}', [AdminMasterDataController::class, 'updateCalendar']);
            Route::delete('working-calendars/{calendar}', [AdminMasterDataController::class, 'deleteCalendar']);
            Route::get('holidays', [AdminMasterDataController::class, 'holidays']);
            Route::post('working-calendars/{calendar}/holidays', [AdminMasterDataController::class, 'storeHoliday']);
            Route::put('holidays/{holiday}', [AdminMasterDataController::class, 'updateHoliday']);
            Route::delete('holidays/{holiday}', [AdminMasterDataController::class, 'deleteHoliday']);
        });
    });

    if (app()->environment(['local', 'testing'])) {
        Route::prefix('protected')->middleware(['auth:sanctum', 'active'])->group(function (): void {
            Route::get('/admin', [ProtectedAccessController::class, 'admin'])->middleware('role:admin');
            Route::get('/executive', [ProtectedAccessController::class, 'executive'])
                ->middleware('permission:executive.aggregate.view');
            Route::get('/technical-ticket-details', [ProtectedAccessController::class, 'technicalTicketDetails'])
                ->middleware('permission:ticket.technical.view');
        });
    }
});
