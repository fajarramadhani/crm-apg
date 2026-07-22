<?php

use App\Http\Controllers\Api\V1\AdminMasterDataController;
use App\Http\Controllers\Api\V1\AdminReleaseChecklistTemplateController;
use App\Http\Controllers\Api\V1\AdminSlaEscalationPolicyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ExecutiveAlertController;
use App\Http\Controllers\Api\V1\ExecutiveAnalyticsController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ItLeadAlertController;
use App\Http\Controllers\Api\V1\ItLeadQaController;
use App\Http\Controllers\Api\V1\ItLeadReleaseController;
use App\Http\Controllers\Api\V1\ItLeadReportController;
use App\Http\Controllers\Api\V1\ItLeadTicketController;
use App\Http\Controllers\Api\V1\ItLeadUatController;
use App\Http\Controllers\Api\V1\ManagerAlertController;
use App\Http\Controllers\Api\V1\ManagerApprovalController;
use App\Http\Controllers\Api\V1\ManagerReportController;
use App\Http\Controllers\Api\V1\MasterDataController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\PicDevelopmentController;
use App\Http\Controllers\Api\V1\PicInternalTestingController;
use App\Http\Controllers\Api\V1\PicPerformanceController;
use App\Http\Controllers\Api\V1\PicReleaseController;
use App\Http\Controllers\Api\V1\PicReworkController;
use App\Http\Controllers\Api\V1\PicTicketController;
use App\Http\Controllers\Api\V1\PicUatReworkController;
use App\Http\Controllers\Api\V1\ProtectedAccessController;
use App\Http\Controllers\Api\V1\QaController;
use App\Http\Controllers\Api\V1\RequesterUatController;
use App\Http\Controllers\Api\V1\SupervisorReportController;
use App\Http\Controllers\Api\V1\SupervisorTicketController;
use App\Http\Controllers\Api\V1\TicketAttachmentController;
use App\Http\Controllers\Api\V1\TicketClosureController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketDeploymentController;
use App\Http\Controllers\Api\V1\TicketMonitoringController;
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

            // Phase 12: Deployment & Rollback
            Route::post('/{ticket}/deployments/schedule', [TicketDeploymentController::class, 'schedule'])->middleware('permission:ticket.deployment.schedule');
            Route::post('/{ticket}/deployments/start', [TicketDeploymentController::class, 'start'])->middleware('permission:ticket.deployment.start');
            Route::post('/{ticket}/deployments/step', [TicketDeploymentController::class, 'manageStep'])->middleware('permission:ticket.deployment.step.manage');
            Route::post('/{ticket}/deployments/complete', [TicketDeploymentController::class, 'complete'])->middleware('permission:ticket.deployment.complete');
            Route::post('/{ticket}/deployments/fail', [TicketDeploymentController::class, 'fail'])->middleware('permission:ticket.deployment.fail');

            Route::post('/{ticket}/rollbacks/start', [TicketDeploymentController::class, 'startRollback'])->middleware('permission:ticket.rollback.start');
            Route::post('/{ticket}/rollbacks/complete', [TicketDeploymentController::class, 'completeRollback'])->middleware('permission:ticket.rollback.complete');

            // Phase 12: Monitoring
            Route::post('/{ticket}/monitoring/start', [TicketMonitoringController::class, 'start'])->middleware('permission:ticket.monitoring.start');
            Route::post('/{ticket}/monitoring/check', [TicketMonitoringController::class, 'recordCheck'])->middleware('permission:ticket.monitoring.check.manage');
            Route::post('/{ticket}/monitoring/incident', [TicketMonitoringController::class, 'recordIncident'])->middleware('permission:ticket.monitoring.incident.manage');
            Route::post('/{ticket}/monitoring/complete', [TicketMonitoringController::class, 'complete'])->middleware('permission:ticket.monitoring.complete');

            // Phase 12: Confirmation & Closure
            Route::post('/{ticket}/confirmation', [TicketClosureController::class, 'respondToConfirmation'])->middleware('permission:ticket.requester_confirmation.respond');
            Route::post('/{ticket}/close', [TicketClosureController::class, 'close'])->middleware('permission:ticket.closure.confirm');
        });

        // Phase 14: Notifications
        Route::prefix('notifications')->name('api.v1.notifications.')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
            Route::post('/archive-read', [NotificationController::class, 'archiveRead']);
            Route::get('/{notification}', [NotificationController::class, 'show']);
            Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/{notification}/unread', [NotificationController::class, 'markAsUnread']);
            Route::post('/{notification}/archive', [NotificationController::class, 'archive']);
        });

        Route::prefix('notification-preferences')->name('api.v1.notification_preferences.')->group(function (): void {
            Route::get('/', [NotificationPreferenceController::class, 'index']);
            Route::put('/{notificationType}', [NotificationPreferenceController::class, 'update']);
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
            Route::get('/alerts', [ItLeadAlertController::class, 'index']);
            Route::get('/alerts/sla', [ItLeadAlertController::class, 'sla']);
            Route::get('/alerts/inactivity', [ItLeadAlertController::class, 'inactivity']);

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

            // UAT Assignment routes
            Route::get('/uat-assignment-queue', [ItLeadUatController::class, 'queue'])->middleware('permission:ticket.uat_assignment_queue.view');
            Route::post('/tickets/{ticket}/assign-uat', [ItLeadUatController::class, 'assignUat'])->middleware('permission:ticket.uat.assign');
            Route::get('/tickets/{ticket}/uat', [ItLeadUatController::class, 'show'])->middleware('permission:ticket.uat_assignment_queue.view');
            Route::get('/approval-request-queue', [ItLeadReleaseController::class, 'approvalQueue'])->middleware('permission:ticket.approval_request.view');
            Route::post('/tickets/{ticket}/request-release-approval', [ItLeadReleaseController::class, 'requestApproval'])->middleware('permission:ticket.approval_request.create');
            Route::get('/technical-approval-queue', [ItLeadReleaseController::class, 'technicalQueue'])->middleware('permission:ticket.technical_approval_queue.view');
            Route::post('/tickets/{ticket}/technical-approval/{decision}', [ItLeadReleaseController::class, 'technicalDecision'])->whereIn('decision', ['approve', 'reject'])->middleware('permission:ticket.technical_approval.approve');
            Route::get('/tickets/{ticket}/release-preparation', [ItLeadReleaseController::class, 'preparation'])->middleware('permission:ticket.release_plan.view');
            Route::get('/tickets/{ticket}/release-plan', [ItLeadReleaseController::class, 'preparation'])->middleware('permission:ticket.release_plan.view');
            Route::post('/tickets/{ticket}/release-plan', [ItLeadReleaseController::class, 'storePlan'])->middleware('permission:ticket.release_plan.manage');
            Route::put('/tickets/{ticket}/release-plan/{plan}', [ItLeadReleaseController::class, 'updatePlan'])->middleware('permission:ticket.release_plan.manage');
            Route::post('/tickets/{ticket}/release-plan/{plan}/submit', [ItLeadReleaseController::class, 'submitPlan'])->middleware('permission:ticket.release_plan.manage');
            Route::post('/tickets/{ticket}/release-plan/{plan}/{decision}', [ItLeadReleaseController::class, 'reviewPlan'])->whereIn('decision', ['approve', 'request-revision'])->middleware('permission:ticket.release_plan.review');
            Route::get('/tickets/{ticket}/rollback-plan', [ItLeadReleaseController::class, 'preparation'])->middleware('permission:ticket.rollback_plan.view');
            Route::post('/tickets/{ticket}/rollback-plan', [ItLeadReleaseController::class, 'storeRollback'])->middleware('permission:ticket.rollback_plan.manage');
            Route::post('/tickets/{ticket}/rollback-plan/{plan}/submit', [ItLeadReleaseController::class, 'submitRollback'])->middleware('permission:ticket.rollback_plan.manage');
            Route::post('/tickets/{ticket}/rollback-plan/{plan}/{decision}', [ItLeadReleaseController::class, 'reviewRollback'])->whereIn('decision', ['approve', 'request-revision'])->middleware('permission:ticket.rollback_plan.review');
            Route::get('/tickets/{ticket}/release-checklist', [ItLeadReleaseController::class, 'checklist'])->middleware('permission:ticket.release_checklist.view');
            Route::post('/tickets/{ticket}/release-checklist/{item}/decision', [ItLeadReleaseController::class, 'checklistDecision'])->middleware('permission:ticket.release_checklist.manage');
            Route::post('/tickets/{ticket}/confirm-release-ready', [ItLeadReleaseController::class, 'confirmReady'])->middleware('permission:ticket.release_readiness.confirm');

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

            // PIC UAT Rework routes
            Route::get('/tickets/{ticket}/uat-findings', [PicUatReworkController::class, 'findings'])->middleware('permission:ticket.uat_rework.view');
            Route::post('/tickets/{ticket}/uat-findings/{finding}/start', [PicUatReworkController::class, 'startFinding'])->middleware('permission:ticket.uat_finding.resolve');
            Route::post('/tickets/{ticket}/uat-findings/{finding}/resolve', [PicUatReworkController::class, 'resolveFinding'])->middleware('permission:ticket.uat_finding.resolve');
            Route::post('/tickets/{ticket}/submit-uat-retest', [PicUatReworkController::class, 'submitRetest'])->middleware('permission:ticket.uat_retest.submit');
            Route::post('/tickets/{ticket}/uat-evidence', [PicUatReworkController::class, 'uploadEvidence'])->middleware('permission:ticket.uat_rework.view');
            Route::get('/tickets/{ticket}/release-preparation', [PicReleaseController::class, 'preparation'])->middleware('permission:ticket.release_plan.view');
            Route::post('/tickets/{ticket}/release-plan', [PicReleaseController::class, 'storePlan'])->middleware('permission:ticket.release_plan.manage');
            Route::post('/tickets/{ticket}/rollback-plan', [PicReleaseController::class, 'storeRollback'])->middleware('permission:ticket.rollback_plan.manage');
            Route::post('/tickets/{ticket}/release-checklist/{item}/complete', [PicReleaseController::class, 'checklistDecision'])->middleware('permission:ticket.release_checklist.complete');
            Route::post('/tickets/{ticket}/release-evidence', [PicReleaseController::class, 'uploadEvidence'])->middleware('permission:ticket.release_evidence.manage');
        });

        Route::prefix('manager')->middleware('permission:ticket.business_approval_queue.view')->name('api.v1.manager.')->group(function (): void {
            Route::get('/alerts', [ManagerAlertController::class, 'index']);

            Route::get('/business-approval-queue', [ManagerApprovalController::class, 'queue']);
            Route::get('/tickets/{ticket}/approval', [ManagerApprovalController::class, 'show']);
            Route::post('/tickets/{ticket}/business-approval/{decision}', [ManagerApprovalController::class, 'decide'])->whereIn('decision', ['approve', 'reject'])->middleware('permission:ticket.business_approval.approve');
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

        Route::prefix('requester')->middleware('permission:ticket.uat_assignment.view')->name('api.v1.requester.')->group(function (): void {
            Route::get('/uat-assignments', [RequesterUatController::class, 'assignments']);
            Route::get('/tickets/{ticket}/uat', [RequesterUatController::class, 'show']);
            Route::post('/tickets/{ticket}/uat/start', [RequesterUatController::class, 'start'])->middleware('permission:ticket.uat.start');

            // Scenarios
            Route::get('/tickets/{ticket}/uat-scenarios', [RequesterUatController::class, 'scenarios'])->middleware('permission:ticket.uat_scenario.view');
            Route::post('/tickets/{ticket}/uat-scenarios', [RequesterUatController::class, 'storeScenario'])->middleware('permission:ticket.uat_scenario.manage');
            Route::put('/tickets/{ticket}/uat-scenarios/{scenario}', [RequesterUatController::class, 'updateScenario'])->middleware('permission:ticket.uat_scenario.manage');
            Route::delete('/tickets/{ticket}/uat-scenarios/{scenario}', [RequesterUatController::class, 'destroyScenario'])->middleware('permission:ticket.uat_scenario.manage');

            // Runs
            Route::get('/tickets/{ticket}/uat-runs', [RequesterUatController::class, 'runs'])->middleware('permission:ticket.uat_run.view');
            Route::post('/tickets/{ticket}/uat-runs', [RequesterUatController::class, 'storeRun'])->middleware('permission:ticket.uat_run.manage');
            Route::get('/tickets/{ticket}/uat-runs/{run}', [RequesterUatController::class, 'showRun'])->middleware('permission:ticket.uat_run.view');
            Route::post('/tickets/{ticket}/uat-runs/{run}/results', [RequesterUatController::class, 'storeResult'])->middleware('permission:ticket.uat_run.manage');
            Route::post('/tickets/{ticket}/uat-runs/{run}/complete', [RequesterUatController::class, 'completeRun'])->middleware('permission:ticket.uat.complete');

            // Findings
            Route::get('/tickets/{ticket}/uat-findings', [RequesterUatController::class, 'findings'])->middleware('permission:ticket.uat_finding.view');
            Route::post('/tickets/{ticket}/uat-findings', [RequesterUatController::class, 'storeFinding'])->middleware('permission:ticket.uat_finding.create');
            Route::post('/tickets/{ticket}/uat-findings/{finding}/verify', [RequesterUatController::class, 'verifyFinding'])->middleware('permission:ticket.uat_finding.verify');
            Route::post('/tickets/{ticket}/uat-findings/{finding}/reopen', [RequesterUatController::class, 'reopenFinding'])->middleware('permission:ticket.uat_finding.reopen');

            // Evidence
            Route::post('/tickets/{ticket}/uat-evidence', [RequesterUatController::class, 'uploadEvidence'])->middleware('permission:ticket.uat_run.manage');
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
            Route::get('/sla-escalation-policies', [AdminSlaEscalationPolicyController::class, 'index']);
            Route::post('/sla-escalation-policies', [AdminSlaEscalationPolicyController::class, 'store']);
            Route::put('/sla-escalation-policies/{policy}', [AdminSlaEscalationPolicyController::class, 'update']);
            Route::delete('/sla-escalation-policies/{policy}', [AdminSlaEscalationPolicyController::class, 'destroy']);

            Route::get('/release-checklist-templates', [AdminReleaseChecklistTemplateController::class, 'index'])->middleware('permission:ticket.release_checklist_template.manage');
            Route::post('/release-checklist-templates', [AdminReleaseChecklistTemplateController::class, 'store'])->middleware('permission:ticket.release_checklist_template.manage');
            Route::put('/release-checklist-templates/{template}', [AdminReleaseChecklistTemplateController::class, 'update'])->middleware('permission:ticket.release_checklist_template.manage');
            Route::delete('/release-checklist-templates/{template}', [AdminReleaseChecklistTemplateController::class, 'destroy'])->middleware('permission:ticket.release_checklist_template.manage');
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

        // Phase 13: Reports
        Route::prefix('reports')->name('api.v1.reports.')->group(function (): void {
            Route::prefix('manager')->group(function (): void {
                Route::get('summary', [ManagerReportController::class, 'getSummary']);
                Route::get('ticket-volume', [ManagerReportController::class, 'ticketVolume']);
                Route::get('sla', [ManagerReportController::class, 'sla']);
                Route::get('workflow-duration', [ManagerReportController::class, 'workflowDuration']);
                Route::get('quality', [ManagerReportController::class, 'quality']);
                Route::get('deployment', [ManagerReportController::class, 'deployment']);
                Route::get('pic-performance', [ManagerReportController::class, 'picPerformance']);
                Route::get('aging-tickets', [ManagerReportController::class, 'agingTickets']);
                Route::get('export', [ManagerReportController::class, 'export']);
            });

            Route::prefix('it-lead')->group(function (): void {
                Route::get('summary', [ItLeadReportController::class, 'getSummary']);
                Route::get('ticket-volume', [ItLeadReportController::class, 'ticketVolume']);
                Route::get('sla', [ItLeadReportController::class, 'sla']);
                Route::get('workflow-duration', [ItLeadReportController::class, 'workflowDuration']);
                Route::get('quality', [ItLeadReportController::class, 'quality']);
                Route::get('deployment', [ItLeadReportController::class, 'deployment']);
                Route::get('pic-performance', [ItLeadReportController::class, 'picPerformance']);
                Route::get('aging-tickets', [ItLeadReportController::class, 'agingTickets']);
                Route::get('export', [ItLeadReportController::class, 'export']);
            });

            Route::prefix('supervisor')->group(function (): void {
                Route::get('summary', [SupervisorReportController::class, 'summary']);
            });

            Route::prefix('pic')->group(function (): void {
                Route::get('performance', [PicPerformanceController::class, 'summary']);
                Route::get('export', [PicPerformanceController::class, 'export']);
            });

            Route::prefix('executive')->group(function (): void {
                Route::get('/alerts/summary', [ExecutiveAlertController::class, 'summary']);
                Route::get('summary', [ExecutiveAnalyticsController::class, 'summary']);
                Route::get('export', [ExecutiveAnalyticsController::class, 'export']);
            });
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
