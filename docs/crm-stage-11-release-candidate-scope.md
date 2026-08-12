# CRM Revision — Stage 11 Release Candidate Scope & Freeze Document

**Tanggal Freeze:** 2026-07-29  
**Branch:** `feature/crm-simplified-dynamic-workflow`  
**Baseline Commit Hash:** `273aeb6a00abba96983aeb9a414ab7115bca9afb`  
**Release Candidate Version:** `RC-1.0.0-staging`  
**Target Merge Destination:** `development` (Draft PR — **NO AUTOMATIC MERGE**)  

---

## 1. Metadata Freeze & Scope Definition

Scope release candidate ini membekukan seluruh perubahan fitur revisi CRM dari Tahap 2 hingga Tahap 10. Tidak ada penambahan fitur baru setelah scope freeze disahkan.

| Parameter | Identitas Baseline Release Candidate |
|-----------|-------------------------------------|
| **Branch** | `feature/crm-simplified-dynamic-workflow` |
| **Commit Baseline** | `273aeb6a00abba96983aeb9a414ab7115bca9afb` |
| **Feature Flag Default** | `CRM_DYNAMIC_WORKFLOW_ENABLED=false` |
| **Target Database Engine** | MySQL 8.0 / MariaDB 10.6 (InnoDB engine) |
| **Role Mapping Policy** | `dry-run` only (Zero automatic user role writes) |
| **Legacy Ticket Strategy** | 100% Retained (Nullable snapshot, no forced migration) |

---

## 2. File Scope Manifest

Seluruh file yang termasuk dalam Release Candidate `RC-1.0.0-staging` dikelompokkan sebagai berikut:

### 2.1 Database & Migrations
- `backend/database/migrations/2026_07_28_000001_create_workflow_engine_tables.php` [NEW]
- `backend/database/migrations/2026_07_28_000002_add_workflow_fields_to_tickets_table.php` [NEW]
- `backend/database/migrations/2026_07_28_000003_add_assignment_foundation_and_histories.php` [NEW]
- `backend/database/migrations/2026_07_28_000004_add_system_and_category_foundation_fields.php` [NEW]
- `backend/database/migrations/2026_07_28_000005_add_reference_and_nullable_category_to_tickets_table.php` [NEW]
- `backend/database/migrations/2026_07_28_100001_add_workflow_lifecycle_to_workflow_definitions.php` [NEW]
- `backend/database/migrations/2026_07_28_100002_add_current_workflow_stage_to_tickets.php` [NEW]
- `backend/database/migrations/2026_07_28_110000_create_idempotency_records_table.php` [NEW]
- `backend/database/migrations/2026_07_28_110003_add_admin_fields_to_workflow_approval_configs.php` [NEW]
- `backend/database/migrations/2026_07_22_120000_create_knowledge_base_tables.php` [MODIFY]

### 2.2 Seeders & Factories
- `backend/database/seeders/DefaultWorkflowSeeder.php` [NEW]
- `backend/database/seeders/RoleSeeder.php` [MODIFY]
- `backend/database/seeders/MasterDataSeeder.php` [MODIFY]
- `backend/database/factories/RoleFactory.php` [NEW]
- `backend/database/factories/WorkflowDefinitionFactory.php` [NEW]
- `backend/database/factories/TicketFactory.php` [MODIFY]

### 2.3 Models & Enums
- `backend/app/Models/Ticket.php` [MODIFY]
- `backend/app/Models/User.php` [MODIFY]
- `backend/app/Models/TicketAssignment.php` [MODIFY]
- `backend/app/Models/TicketCategory.php` [MODIFY]
- `backend/app/Models/Application.php` [MODIFY]
- `backend/app/Models/TicketAssignmentHistory.php` [NEW]
- `backend/app/Models/WorkflowDefinition.php` [NEW]
- `backend/app/Models/WorkflowStage.php` [NEW]
- `backend/app/Models/WorkflowTransition.php` [NEW]
- `backend/app/Models/WorkflowStageField.php` [NEW]
- `backend/app/Models/WorkflowCondition.php` [NEW]
- `backend/app/Models/WorkflowTransitionPermission.php` [NEW]
- `backend/app/Models/WorkflowTransitionNotification.php` [NEW]
- `backend/app/Models/WorkflowApprovalConfig.php` [NEW]
- `backend/app/Models/WorkflowApprovalStep.php` [NEW]
- `backend/app/Models/IdempotencyRecord.php` [NEW]
- `backend/app/Enums/TicketStatus.php` [MODIFY]

### 2.4 Services & Core Logic
- `backend/app/Services/DynamicAssignmentService.php` [NEW]
- `backend/app/Services/RequesterTicketService.php` [NEW]
- `backend/app/Services/WorkflowEngineService.php` [NEW]
- `backend/app/Services/WorkflowSnapshotBuilder.php` [NEW]
- `backend/app/Services/WorkflowValidatorService.php` [NEW]
- `backend/app/Services/WorkflowActivationService.php` [NEW]
- `backend/app/Services/LegacyTransitionHandler.php` [NEW]
- `backend/app/Services/TicketNotificationRecipientResolver.php` [MODIFY]

### 2.5 Controllers & API Resources
- `backend/app/Http/Controllers/Api/V1/RequesterTicketController.php` [NEW]
- `backend/app/Http/Controllers/Api/V1/SupervisorItTicketController.php` [NEW]
- `backend/app/Http/Controllers/Api/V1/PicTicketController.php` [MODIFY]
- `backend/app/Http/Controllers/Api/V1/DynamicWorkflowTransitionController.php` [NEW]
- `backend/app/Http/Controllers/Api/V1/AdminWorkflowController.php` [NEW]
- `backend/app/Http/Controllers/Api/V1/AdminWorkflowStageController.php` [NEW]
- `backend/app/Http/Controllers/Api/V1/AdminWorkflowTransitionController.php` [NEW]
- `backend/app/Http/Controllers/Api/V1/AdminWorkflowApprovalController.php` [NEW]
- `backend/app/Http/Resources/Api/V1/TicketResource.php` [MODIFY]

### 2.6 Requests & Middleware / Policy / Exceptions
- `backend/app/Http/Requests/Api/V1/StoreRequesterTicketRequest.php` [NEW]
- `backend/app/Http/Requests/Api/V1/AnalyzeTicketRequest.php` [NEW]
- `backend/app/Http/Requests/Api/V1/AssignPrimaryTicketRequest.php` [NEW]
- `backend/app/Http/Requests/Api/V1/AddSecondaryAssigneeRequest.php` [NEW]
- `backend/app/Http/Requests/Api/V1/ReassignTicketRequest.php` [NEW]
- `backend/app/Http/Requests/Api/V1/TakeoverTicketRequest.php` [NEW]
- `backend/app/Http/Requests/Api/V1/SupervisorActionRequest.php` [NEW]
- `backend/app/Http/Requests/Api/V1/PicActionRequest.php` [NEW]
- `backend/app/Http/Requests/Api/V1/UpdateWorkflowApprovalRequest.php` [NEW]
- `backend/app/Policies/TicketPolicy.php` [MODIFY]
- `backend/app/Policies/WorkflowDefinitionPolicy.php` [NEW]
- `backend/app/Exceptions/IdempotencyConflict.php` [NEW]
- `backend/config/crm.php` [NEW]
- `backend/config/permissions.php` [MODIFY]
- `backend/routes/api.php` [MODIFY]
- `backend/bootstrap/app.php` [MODIFY]
- `backend/.env.example` [MODIFY]

### 2.7 Console Commands & Safety Helpers
- `backend/app/Console/Commands/WorkflowHealthCheck.php` [NEW]
- `backend/app/Console/Commands/CrmRoleMappingCommand.php` [NEW]
- `backend/app/Console/Commands/ConcurrencyCheckCommand.php` [NEW]
- `backend/app/Console/Commands/ConcurrencyCheckWorkerCommand.php` [NEW]
- `backend/app/Console/Commands/PerformanceCheckCommand.php` [NEW]
- `backend/app/Console/Commands/CleanupExpiredIdempotencyRecordsCommand.php` [NEW]
- `backend/app/Console/Commands/SeedStage9BrowserFixturesCommand.php` [NEW]
- `backend/app/Support/ConcurrencyCheckSafety.php` [NEW]
- `backend/app/Support/PerformanceCheckSafety.php` [NEW]

### 2.8 Backend Test Suite (341 Tests, 100% Pass)
- `backend/tests/Feature/RequesterTicketCreationTest.php` [NEW]
- `backend/tests/Feature/RequesterTicketIdempotencyTest.php` [NEW]
- `backend/tests/Feature/RequesterAuthorizationTest.php` [NEW]
- `backend/tests/Feature/RequesterTransactionAndNotificationTest.php` [NEW]
- `backend/tests/Feature/RequesterSecurityPayloadTest.php` [NEW]
- `backend/tests/Feature/SupervisorItControlCenterTest.php` [NEW]
- `backend/tests/Feature/SupervisorItAssignmentTest.php` [NEW]
- `backend/tests/Feature/PicWorkspaceTest.php` [NEW]
- `backend/tests/Feature/PicAuthorizationTest.php` [NEW]
- `backend/tests/Feature/DynamicWorkflowDefinitionTest.php` [NEW]
- `backend/tests/Feature/DynamicWorkflowTransitionTest.php` [NEW]
- `backend/tests/Feature/DynamicWorkflowTransitionNotificationTest.php` [NEW]
- `backend/tests/Feature/WorkflowSnapshotTest.php` [NEW]
- `backend/tests/Feature/WorkflowValidatorTest.php` [NEW]
- `backend/tests/Feature/WorkflowActivationTest.php` [NEW]
- `backend/tests/Feature/WorkflowHealthCheckTest.php` [NEW]
- `backend/tests/Feature/AdminWorkflowApprovalTest.php` [NEW]
- `backend/tests/Feature/LegacyRoleCompatibilityTest.php` [NEW]
- `backend/tests/Feature/LegacyWorkflowCompatibilityRegressionTest.php` [NEW]
- `backend/tests/Feature/AssignmentIntegrityAndConcurrencyTest.php` [NEW]
- `backend/tests/Feature/ConcurrencyCheckCommandTest.php` [NEW]
- `backend/tests/Feature/PerformanceCheckCommandTest.php` [NEW]
- `backend/tests/Feature/CrmRoleMappingCommandTest.php` [NEW]
- `backend/tests/Feature/SeedStage9BrowserFixturesCommandTest.php` [NEW]
- `backend/tests/Feature/Stage8DynamicWorkflowIntegrationTest.php` [NEW]
- `backend/tests/Feature/AuthenticationAndAuthorizationTest.php` [MODIFY]
- `backend/tests/Feature/DatabaseSeederSafetyTest.php` [MODIFY]

### 2.9 Frontend Components, Pages, and Services
- `frontend/package.json` [MODIFY]
- `frontend/src/App.tsx` [MODIFY]
- `frontend/src/api/types.ts` [MODIFY]
- `frontend/src/types.ts` [MODIFY]
- `frontend/src/presentation.ts` [MODIFY]
- `frontend/src/components/Layout.tsx` [MODIFY]
- `frontend/src/services/ticketService.ts` [MODIFY]
- `frontend/src/services/workflowService.ts` [NEW]
- `frontend/src/pages/user/CreateTicket.tsx` [MODIFY]
- `frontend/src/pages/user/TicketDetail.tsx` [MODIFY]
- `frontend/src/pages/user/TicketHistory.tsx` [MODIFY]
- `frontend/src/pages/itlead/DeploymentQueue.tsx` [MODIFY]
- `frontend/src/pages/supervisorIt/SupervisorDashboard.tsx` [NEW]
- `frontend/src/pages/supervisorIt/SupervisorTicketList.tsx` [NEW]
- `frontend/src/pages/supervisorIt/SupervisorTicketDetail.tsx` [NEW]
- `frontend/src/pages/pic/PicUnifiedDashboard.tsx` [NEW]
- `frontend/src/pages/pic/PicTicketList.tsx` [NEW]
- `frontend/src/pages/pic/PicTicketDetail.tsx` [NEW]
- `frontend/src/pages/admin/workflows/WorkflowList.tsx` [NEW]
- `frontend/src/pages/admin/workflows/WorkflowDetail.tsx` [NEW]
- `frontend/src/pages/admin/workflows/WorkflowForm.tsx` [NEW]
- `frontend/src/components/supervisor/CandidateRecommendationModal.tsx` [NEW]
- `frontend/src/components/supervisor/PrimaryAssignmentModal.tsx` [NEW]
- `frontend/src/components/supervisor/SecondaryAssignmentModal.tsx` [NEW]
- `frontend/src/components/supervisor/SelfHandlingConfirmationModal.tsx` [NEW]
- `frontend/src/components/pic/PicTransitionActions.tsx` [NEW]
- `frontend/src/components/pic/PicInternalNotesTab.tsx` [NEW]
- `frontend/stage9-browser-fixtures.json` [NEW]

### 2.10 Reports & Documentation
- `docs/crm-revision-baseline-report.md`
- `docs/crm-revision-stage-2-database-report.md`
- `docs/crm-revision-stage-3-backend-assignment-report.md`
- `docs/crm-revision-stage-4-requester-form-report.md`
- `docs/crm-revision-stage-5-supervisor-control-center-report.md`
- `docs/crm-revision-stage-6-pic-workspace-report.md`
- `docs/crm-revision-stage-7-dynamic-workflow-report.md`
- `docs/crm-revision-stage-7-opencode-handoff.md`
- `docs/crm-revision-stage-8-integration-staging-readiness-report.md`
- `docs/crm-stage-8-staging-deployment-runbook.md`
- `docs/crm-role-mapping-dry-run.csv`
- `docs/crm-revision-stage-9-validation-plan.md`
- `docs/crm-revision-stage-9-final-report.md`
- `docs/stage9-browser-validation.md`
- `docs/crm-stage-10-release-candidate-review.md`
- `docs/crm-revision-stage-10-staging-release-candidate-report.md`
- `docs/crm-stage-11-release-candidate-scope.md` [THIS DOCUMENT]

---

## 3. Fitur Utama Release Candidate

1. **Penyederhanaan Role & Permission:** 5 role operasional utama (`requester`, `supervisor_it`, `pic_it_support`, `pic_it_develop`, `admin`) didukung oleh kompatibilitas 9 role legacy tanpa konflik.
2. **Requester Intake 5 Field:** Minimalist form (Judul, Deskripsi, Error URL, Referensi, Attachment) dengan dukungan Idempotency Key (`UUIDv4`).
3. **Supervisor IT Control Center:** Dashboard 9 matriks, candidate recommendations, penugasan single-click, opsi "Tangani Sendiri", dan penandaan self-approval transparan.
4. **Assignment Engine:** Maksimal 1 primary PIC aktif, multiple secondary PICs, reassignment otomatis, dan takeover aman.
5. **Unified PIC Workspace:** Dashboard PIC lintas aplikasi/kategori, visual transition action buttons dynamic per stage, dan pemisahan catatan internal vs publik.
6. **Dynamic Workflow Engine:** Versioning workflow (`crm_default` v1), 13 stages, 30 transitions, snapshot immutable per tiket, state machine validator.
7. **Admin Workflow Management:** Visual workflow builder, validation engine, dan single-step approval configuration (`supervisor_it`).
8. **Compatibility Legacy:** Support penuh 9 status tiket legacy tanpa modifikasi schema/data lama.
9. **Tooling & Commands:** `crm:workflow-check`, `crm:role-mapping`, `crm:concurrency-check`, `crm:performance-check`, `crm:cleanup-idempotency`.

---

## 4. Migration & Seeder Manifest

### Migrations Tambahan (9 Migration)
1. `2026_07_28_000001_create_workflow_engine_tables.php` (workflow definitions, stages, transitions, conditions, permissions, notifications, approval configs).
2. `2026_07_28_000002_add_workflow_fields_to_tickets_table.php` (`workflow_id`, `workflow_version_id`, `workflow_snapshot`, `workflow_mode`).
3. `2026_07_28_000003_add_assignment_foundation_and_histories.php` (`is_primary`, `role_at_assignment`, `acting_as_pic`, `ticket_assignment_histories`).
4. `2026_07_28_000004_add_system_and_category_foundation_fields.php` (`application_id`, `ticket_category_id`).
5. `2026_07_28_000005_add_reference_and_nullable_category_to_tickets_table.php` (`reference_number`, nullable category).
6. `2026_07_28_100001_add_workflow_lifecycle_to_workflow_definitions.php` (`config_status`, `is_active`, `published_at`).
7. `2026_07_28_100002_add_current_workflow_stage_to_tickets.php` (`current_workflow_stage_id`).
8. `2026_07_28_110000_create_idempotency_records_table.php` (`idempotency_records`).
9. `2026_07_28_110003_add_admin_fields_to_workflow_approval_configs.php` (approval config metadata).

### Seeders Diperbarui
- `RoleSeeder.php`: Memastikan ketersediaan role tanpa mengubah `users.role_id`.
- `DefaultWorkflowSeeder.php`: Mencetak draft `crm_default` v1 (13 stages, 30 transitions).

---

## 5. File yang Sengaja Tidak Dimasukkan (Exclusions)

| Category / File | Alasan Excluded |
|-----------------|-----------------|
| `.env`, `.env.local` | Configuration sensitif environment |
| `database.sqlite` / `*.sql` | File database lokal / dump temporary |
| `storage/logs/*` | Application runtime logs |
| `node_modules/*`, `vendor/*` | External dependencies |
| `frontend/dist/*` | Production build output (generated on target deploy) |
| `coverage/*` | Local test coverage output |
| Browser session / cookies | Data transient pengujian |

---

## 6. Risk Matrix & Rollback Points

| Risk Scenario | Risk Level | Rollback Trigger | Rollback Mechanism |
|---------------|------------|------------------|--------------------|
| Unhandled exception pada Dynamic Intake | LOW | Fail rate > 1% di Staging | **Level 1:** Set `CRM_DYNAMIC_WORKFLOW_ENABLED=false` |
| Invalid state machine transition | LOW | Transition error | Fix workflow via Admin UI draft or restore v1 |
| DB lock conflict pada concurrency tinggi | LOW | Deadlock log | InnoDB row locking & application HTTP 409 handling |
| Application deployment failure | VERY LOW | Build / boot failure | **Level 2:** Revert git commit ke release baseline |
| Database corruption during migration | VERY LOW | Migration exception | **Level 3:** Restore staging DB snapshot pre-deployment |

---

## 7. Declaration of Freeze

Scope Release Candidate **RC-1.0.0-staging** resmi dibekukan pada commit `273aeb6a00abba96983aeb9a414ab7115bca9afb`. Seluruh perubahan berikutnya pada branch `feature/crm-simplified-dynamic-workflow` hanya diizinkan untuk perbaikan bug blocker hasil review Tim IT atau pengujian staging.

*Approved by CRM Revision Engineering Team*
