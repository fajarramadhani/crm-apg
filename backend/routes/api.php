<?php

use App\Http\Controllers\Api\V1\AdminMasterDataController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ItLeadQaController;
use App\Http\Controllers\Api\V1\ItLeadTicketController;
use App\Http\Controllers\Api\V1\MasterDataController;
use App\Http\Controllers\Api\V1\PicDevelopmentController;
use App\Http\Controllers\Api\V1\PicInternalTestingController;
use App\Http\Controllers\Api\V1\PicReworkController;
use App\Http\Controllers\Api\V1\PicTicketController;
use App\Http\Controllers\Api\V1\ProtectedAccessController;
use App\Http\Controllers\Api\V1\QaController;
use App\Http\Controllers\Api\V1\SupervisorTicketController;
use App\Http\Controllers\Api\V1\TicketAttachmentController;
use App\Http\Controllers\Api\V1\TicketController;
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
        Route::prefix('tickets')->name('api.v1.tickets.')->group(function (): void {
            Route::get('/', [TicketController::class, 'index'])->middleware('permission:ticket.own.view')->name('index');
            Route::post('/', [TicketController::class, 'store'])->middleware('permission:ticket.create')->name('store');
            Route::get('/{ticket}', [TicketController::class, 'show'])->name('show');
            Route::put('/{ticket}', [TicketController::class, 'update'])->name('update');
            Route::post('/{ticket}/resubmit', [TicketController::class, 'resubmit'])->name('resubmit');
            Route::post('/{ticket}/cancel', [TicketController::class, 'cancel'])->name('cancel');
            Route::get('/{ticket}/history', [TicketController::class, 'history'])->name('history');
            Route::post('/{ticket}/attachments', [TicketAttachmentController::class, 'store'])->name('attachments.store');
            Route::get('/{ticket}/attachments/{attachment}/download', [TicketAttachmentController::class, 'download'])->name('attachments.download');
            Route::delete('/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'destroy'])->name('attachments.destroy');
        });

        Route::prefix('supervisor')->middleware('permission:ticket.validation_queue.view')->name('api.v1.supervisor.')->group(function (): void {
            Route::get('/validation-queue', [SupervisorTicketController::class, 'queue']);
            Route::get('/tickets/{ticket}', [SupervisorTicketController::class, 'show']);
            Route::post('/tickets/{ticket}/validate', [SupervisorTicketController::class, 'validate'])->middleware('permission:ticket.validate');
            Route::post('/tickets/{ticket}/request-revision', [SupervisorTicketController::class, 'requestRevision'])->middleware('permission:ticket.request_revision');
            Route::post('/tickets/{ticket}/reject', [SupervisorTicketController::class, 'reject'])->middleware('permission:ticket.reject');
            Route::post('/tickets/{ticket}/transfer', [SupervisorTicketController::class, 'transfer'])->middleware('permission:ticket.transfer');
        });

        Route::prefix('it-lead')->name('api.v1.it-lead.')->group(function (): void {
            Route::get('/triage-queue', [ItLeadTicketController::class, 'queue'])->middleware('permission:ticket.triage_queue.view');
            Route::get('/tickets/{ticket}', [ItLeadTicketController::class, 'show'])->middleware('permission:ticket.triage_queue.view');
            Route::post('/tickets/{ticket}/start-triage', [ItLeadTicketController::class, 'startTriage'])->middleware('permission:ticket.triage.start');
            Route::post('/tickets/{ticket}/assign', [ItLeadTicketController::class, 'assign'])->middleware('permission:ticket.assign');
            Route::get('/pic-options', [ItLeadTicketController::class, 'picOptions'])->middleware('permission:ticket.pic_options.view');
            Route::get('/pic-workloads', [ItLeadTicketController::class, 'picWorkloads'])->middleware('permission:ticket.workload.view');
            Route::get('/plan-review-queue', [ItLeadTicketController::class, 'planReviewQueue'])->middleware('permission:ticket.plan_review_queue.view');
            Route::get('/tickets/{ticket}/solution-plan', [ItLeadTicketController::class, 'solutionPlan'])->middleware('permission:ticket.solution_plan.view');
            Route::post('/tickets/{ticket}/solution-plan/{plan}/approve', [ItLeadTicketController::class, 'approvePlan'])->middleware('permission:ticket.solution_plan.approve');
            Route::post('/tickets/{ticket}/solution-plan/{plan}/request-revision', [ItLeadTicketController::class, 'requestPlanRevision'])->middleware('permission:ticket.solution_plan.request_revision');
            Route::get('/development-queue', [ItLeadTicketController::class, 'developmentQueue'])->middleware('permission:ticket.development_queue.view');
            Route::get('/tickets/{ticket}/development', [ItLeadTicketController::class, 'development'])->middleware('permission:ticket.development_queue.view');

            // QA Assignment routes
            Route::get('/qa-assignment-queue', [ItLeadQaController::class, 'queue'])->middleware('permission:ticket.qa_assignment_queue.view');
            Route::get('/qa-options', [ItLeadQaController::class, 'qaOptions'])->middleware('permission:ticket.qa_options.view');
            Route::get('/qa-workloads', [ItLeadQaController::class, 'qaWorkloads'])->middleware('permission:ticket.qa_workload.view');
            Route::post('/tickets/{ticket}/assign-qa', [ItLeadQaController::class, 'assignQa'])->middleware('permission:ticket.qa.assign');
        });

        Route::prefix('pic')->middleware('permission:ticket.assigned.view')->name('api.v1.pic.')->group(function (): void {
            Route::get('/assignments', [PicTicketController::class, 'index']);
            Route::get('/tickets/{ticket}', [PicTicketController::class, 'show']);
            Route::post('/tickets/{ticket}/start-analysis', [PicTicketController::class, 'startAnalysis'])->middleware('permission:ticket.analysis.start');
            Route::get('/tickets/{ticket}/analysis', [PicTicketController::class, 'analysis'])->middleware('permission:ticket.analysis.view');
            Route::post('/tickets/{ticket}/analysis', [PicTicketController::class, 'storeAnalysis'])->middleware('permission:ticket.analysis.manage');
            Route::put('/tickets/{ticket}/analysis/{analysis}', [PicTicketController::class, 'updateAnalysis'])->middleware('permission:ticket.analysis.manage');
            Route::post('/tickets/{ticket}/analysis/{analysis}/complete', [PicTicketController::class, 'completeAnalysis'])->middleware('permission:ticket.analysis.manage');
            Route::get('/tickets/{ticket}/solution-plan', [PicTicketController::class, 'solutionPlan'])->middleware('permission:ticket.solution_plan.view');
            Route::post('/tickets/{ticket}/solution-plan', [PicTicketController::class, 'storeSolutionPlan'])->middleware('permission:ticket.solution_plan.manage');
            Route::put('/tickets/{ticket}/solution-plan/{plan}', [PicTicketController::class, 'updateSolutionPlan'])->middleware('permission:ticket.solution_plan.manage');
            Route::post('/tickets/{ticket}/solution-plan/{plan}/submit', [PicTicketController::class, 'submitSolutionPlan'])->middleware('permission:ticket.solution_plan.submit');
            Route::post('/tickets/{ticket}/start-development', [PicDevelopmentController::class, 'start'])->middleware('permission:ticket.development.start');
            Route::get('/tickets/{ticket}/worklogs', [PicDevelopmentController::class, 'worklogs'])->middleware('permission:ticket.worklog.view');
            Route::post('/tickets/{ticket}/worklogs', [PicDevelopmentController::class, 'addWorklog'])->middleware('permission:ticket.worklog.manage');
            Route::get('/tickets/{ticket}/development-updates', [PicDevelopmentController::class, 'updates'])->middleware('permission:ticket.development.view');
            Route::post('/tickets/{ticket}/development-updates', [PicDevelopmentController::class, 'addUpdate'])->middleware('permission:ticket.development.update');
            Route::post('/tickets/{ticket}/development-evidence', [PicDevelopmentController::class, 'evidence'])->middleware('permission:ticket.development_evidence.manage');
            Route::get('/tickets/{ticket}/internal-test-cases', [PicInternalTestingController::class, 'cases'])->middleware('permission:ticket.internal_test_case.view');
            Route::post('/tickets/{ticket}/internal-test-cases', [PicInternalTestingController::class, 'storeCase'])->middleware('permission:ticket.internal_test_case.manage');
            Route::put('/tickets/{ticket}/internal-test-cases/{case}', [PicInternalTestingController::class, 'updateCase'])->middleware('permission:ticket.internal_test_case.manage');
            Route::delete('/tickets/{ticket}/internal-test-cases/{case}', [PicInternalTestingController::class, 'deactivateCase'])->middleware('permission:ticket.internal_test_case.manage');
            Route::post('/tickets/{ticket}/internal-test-runs', [PicInternalTestingController::class, 'startRun'])->middleware('permission:ticket.internal_test_run.manage');
            Route::get('/tickets/{ticket}/internal-test-runs', [PicInternalTestingController::class, 'runs'])->middleware('permission:ticket.internal_test_run.view');
            Route::get('/tickets/{ticket}/internal-test-runs/{run}', [PicInternalTestingController::class, 'run'])->middleware('permission:ticket.internal_test_run.view');
            Route::post('/tickets/{ticket}/internal-test-runs/{run}/results', [PicInternalTestingController::class, 'result'])->middleware('permission:ticket.internal_test_run.manage');
            Route::post('/tickets/{ticket}/internal-test-runs/{run}/complete', [PicInternalTestingController::class, 'complete'])->middleware('permission:ticket.internal_test.complete');

            // PIC Rework/Defects and Retest routes
            Route::get('/tickets/{ticket}/qa-defects', [PicReworkController::class, 'defects'])->middleware('permission:ticket.qa_defect.view');
            Route::post('/tickets/{ticket}/qa-defects/{defect}/start', [PicReworkController::class, 'startDefect'])->middleware('permission:ticket.qa_defect.manage');
            Route::post('/tickets/{ticket}/qa-defects/{defect}/resolve', [PicReworkController::class, 'resolveDefect'])->middleware('permission:ticket.qa_defect.manage');
            Route::post('/tickets/{ticket}/submit-qa-retest', [PicReworkController::class, 'submitRetest'])->middleware('permission:ticket.qa_retest.submit');
        });

        Route::prefix('qa')->middleware('permission:ticket.assigned.view')->name('api.v1.qa.')->group(function (): void {
            Route::get('/assignments', [QaController::class, 'assignments']);
            Route::get('/tickets/{ticket}', [QaController::class, 'show']);
            Route::post('/tickets/{ticket}/start', [QaController::class, 'start']);
            Route::get('/tickets/{ticket}/test-cases', [QaController::class, 'cases']);
            Route::post('/tickets/{ticket}/test-cases', [QaController::class, 'storeCase']);
            Route::put('/tickets/{ticket}/test-cases/{case}', [QaController::class, 'updateCase']);
            Route::delete('/tickets/{ticket}/test-cases/{case}', [QaController::class, 'destroyCase']);
            Route::get('/tickets/{ticket}/test-runs', [QaController::class, 'runs']);
            Route::post('/tickets/{ticket}/test-runs', [QaController::class, 'storeRun']);
            Route::get('/tickets/{ticket}/test-runs/{run}', [QaController::class, 'showRun']);
            Route::post('/tickets/{ticket}/test-runs/{run}/results', [QaController::class, 'storeResult']);
            Route::post('/tickets/{ticket}/test-runs/{run}/complete', [QaController::class, 'completeRun']);
            Route::get('/tickets/{ticket}/defects', [QaController::class, 'defects']);
            Route::post('/tickets/{ticket}/defects', [QaController::class, 'storeDefect']);
            Route::put('/tickets/{ticket}/defects/{defect}', [QaController::class, 'updateDefect']);
            Route::post('/tickets/{ticket}/defects/{defect}/verify', [QaController::class, 'verifyDefect']);
            Route::post('/tickets/{ticket}/defects/{defect}/reopen', [QaController::class, 'reopenDefect']);
            Route::post('/tickets/{ticket}/evidence', [QaController::class, 'uploadEvidence']);
        });

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
