# CRM Revision Stage 8 Integration and Staging Readiness Report

**Execution date:** 2026-07-28  
**Branch:** `feature/crm-simplified-dynamic-workflow`  
**Initial commit:** `273aeb6a00abba96983aeb9a414ab7115bca9afb`  
**Final status:** **PASS WITH PENDING**

## 1. Repository Condition

The expected branch and baseline commit were confirmed. The working tree contains the uncommitted Stage 2 through Stage 8 implementation. No reset, clean, stash, checkout, restore, commit, push, merge, or deployment was performed.

`backend/.env` is ignored and is not tracked. `backend/.env.example` documents `CRM_DYNAMIC_WORKFLOW_ENABLED=false`. Runtime services read `config('crm.dynamic_workflow_enabled')`; direct runtime `env()` access is confined to Laravel configuration.

## 2. Review of Stage 7 Claims

Stage 7 was not accepted solely from its previous report. Code, schema, routes, tests, frontend service contracts, migration behavior, and disposable database behavior were checked.

Verified:

- lifecycle states `draft`, `published`, `active`, `inactive`;
- published workflow immutability and version creation;
- unique `code + version` database constraint;
- dynamic ticket fields and immutable ticket snapshot use;
- legacy/dynamic handler separation;
- centralized validator and workflow engine;
- Admin Workflow backend routes and frontend routes;
- default flag OFF;
- granular Admin workflow permissions without ticket operational permissions;
- default workflow contains 13 stages and 30 transitions;
- health check rejects unsafe configuration.

## 3. Stage 7 Carry-Over Fixed in Stage 8

The following issues were discovered and corrected:

1. Frontend workflow service response envelopes did not match backend `{ workflow }`, `{ stage }`, `{ field }`, `{ transition }`, and `{ preview }` payloads.
2. Admin Workflow detail was mostly read-only despite claiming stage/transition management. Draft editing now supports stage CRUD, internal field create/delete, transition CRUD, role permissions, and safe notification rules. Published/active/inactive versions are read-only.
3. Admin UI now displays the required warning that changes only affect new tickets.
4. Health check now fails on zero/multiple active workflows, active-state flag mismatch, invalid workflows, invalid published workflows under `--all`, and unbuildable snapshots.
5. Activation revalidates the published workflow and locks workflow rows before switching the single active version.
6. Approval config relation naming was inconsistent; `steps` is now the canonical relationship and approver role is limited to `supervisor_it` for the minimum workflow.
7. Dynamic engine previously allowed a PIC role without an active assignment and did not enforce requester ownership. Both are now enforced centrally.
8. Dynamic transition notifications were configured but not executed. They now dispatch after commit from the immutable snapshot using safe recipient types, with QA/UAT/manager excluded and failure isolated.
9. Supervisor Dashboard queried a nonexistent `target_completion_date` column. Overdue queries now use `resolution_due_at`.
10. Self-approval by Supervisor acting as primary PIC now records `metadata.self_approval=true`.
11. Lifecycle/current-stage migration rollback failed on SQLite because indexes were not dropped before columns. Both rollbacks were corrected and rehearsed.
12. Requester forbidden payload list now includes `workflow_snapshot` and `current_workflow_stage`.

Known backend limitations documented honestly:

- approval config is displayed read-only because a dedicated approval management endpoint is not present;
- internal fields support create/delete but no update endpoint;
- transition source/destination/action code are immutable after creation in the current API;
- `specific_role` notification target lacks a dedicated target-role storage field in the current schema, so the Admin UI does not offer unsafe incomplete configuration.

## 4. Test Database and Accounts

Automated integration tests used Laravel `RefreshDatabase` with disposable in-memory SQLite. Migration rehearsal used a separate SQLite file under the approved temporary directory, never production.

Testing users were created by factories for:

- Requester A and B;
- Supervisor IT;
- PIC IT Support;
- PIC IT Develop;
- Admin where required by admin tests;
- legacy roles through the existing legacy regression suites.

No existing local user role was changed.

## 5. Feature Flag OFF

Automated result: **PASS**.

Verified that requester creation with flag OFF:

- creates a compatibility ticket;
- uses `pending_validation`;
- leaves workflow ID, mode, version, snapshot, and current stage null;
- records compatibility histories;
- leaves dynamic workflow configuration available to Admin;
- preserves legacy handler/routes.

The shared local environment remains OFF. Its health check fails safely because no workflow is active.

## 6. Feature Flag ON

Automated and disposable rehearsal result: **PASS**.

With a valid active `crm_default` v1:

- a new requester ticket stores workflow ID/version/snapshot;
- mode is `dynamic`;
- initial stage/status is `submitted`;
- health check passes with flag ON;
- old tickets remain legacy.

The temporary flag ON existed only for the disposable process environment and did not edit `.env`.

## 7. End-to-End Scenarios

### Scenario A: Normal Ticket

**PASS through automated integration.** Creation, snapshot, analysis, assignment, work, result summary, approval, `done`, audit histories, and close metadata were verified.

Existing Stage 4–6 tests also cover notes, attachments, internal checks, public status resources, and notifications. A live browser smoke test is still pending for staging.

### Scenario B: Request Information

**PASS.** Primary PIC transitions to `need_info`; only the owning requester can execute `requester_reply`; ticket returns to `in_progress`.

### Scenario C: Waiting External

**PASS.** `wait_external` and `resume_work` were executed. Support and Develop PIC eligibility is independent of system/category.

### Scenario D: Revision

**PASS.** Submit, Supervisor revision request, PIC resume revision, resubmit, and approval were verified with non-duplicated histories.

### Scenario E: Reopen

**PASS.** Reopen requires notes, transitions to `reopened`, reassigns through the assignment service, and returns to `assigned`.

## 8. Supervisor Acting as PIC

**PASS.** Assignment records `role_at_assignment=supervisor_it` and `acting_as_pic=true`. Supervisor can work and submit as primary PIC. Approval by the same Supervisor is explicitly marked as self-approval in audit metadata. Assignment service remains the only assignment mutation path used by the integration scenario.

UI-specific self-approval warning remains a staging browser verification item.

## 9. Primary and Secondary PIC

**PASS through Stage 3, Stage 6, and Stage 8 automated tests.** Exactly one active primary, multiple secondary assignments, duplicate active assignment rejection, reassignment closure/history, takeover history, released-assignment authorization, and default secondary submit denial were verified.

## 10. Cross-System and Cross-Category Assignment

**PASS.** Testing-only master data includes:

- `Bug Sistem Internal`;
- `Bug Sistem dari Asuransi`;
- `Sistem Internal`;
- `Sistem Asuransi atau Eksternal`.

Both Support and Develop PICs were assigned across both categories/systems. No role-category filter was found in the assignment service.

## 11. Authorization Matrix

**PASS through automated suites.** Verified requester ownership/IDOR restrictions, Supervisor cross-division access and operations, assignment-scoped PIC access, Admin workflow authorization, Admin absence of operational ticket permissions, and legacy role route compatibility.

Central dynamic transition authorization now recalculates role, assignment, primary/secondary rule, and requester ownership on every request.

## 12. Security Testing

**PASS for automated scope.** Tests cover:

- requester mass-assignment of identity/status/workflow/assignment fields;
- unsafe URL protocols;
- executable/script uploads and file size limits;
- ticket and attachment IDOR;
- unassigned and secondary PIC transition attempts;
- fake workflow actions;
- immutable snapshot input rejection;
- stale-stage HTTP 409;
- safe audit metadata and notification recipient rules.

No stack trace or credential is returned by expected API errors.

MIME/content-signature inspection beyond Laravel MIME validation remains a staging security hardening item.

## 13. Concurrency and Idempotency

**PASS for implemented automated simulations.** Ticket and workflow row locks, expected-stage conflict, one-primary invariant, activation locking, duplicate failed/conflicting transition behavior, and notification/history non-creation for failed transitions were verified.

True parallel multi-process tests against the staging MySQL engine remain pending. SQLite sequential/transaction tests cannot fully reproduce MySQL lock scheduling and deadlocks.

Double requester submission and attachment retry do not currently use an explicit idempotency key. This is a documented noncritical pending risk for staging review.

## 14. Workflow Versioning and Snapshot Immutability

**PASS.** Ticket A retains its v1 snapshot while v2 is created, edited, activated, and used by Ticket B. The old snapshot remains unchanged and published workflow editing/deletion guards are covered.

## 15. Legacy Regression

**PASS through the complete existing test suite and dedicated regression tests.** Legacy mode remains null/`legacy`, receives no snapshot, rejects the dynamic transition endpoint, retains legacy statuses, and keeps QA/UAT/release routes.

The shared local database's single legacy ticket remained unchanged before/after Stage 8 verification.

## 16. Role Mapping Dry Run

Created:

- `docs/crm-role-mapping-dry-run.csv`;
- `php artisan crm:role-mapping --file=... --dry-run`.

The CSV uses the requested columns. The command validates user identity, current/proposed role, duplicate rows, and the final five roles. It has no apply mode and performs no writes. The repository template is header-only because no manual decisions were approved.

Dry-run result: **PASS, zero proposed changes, zero database writes**.

## 17. Migration Rehearsal

**PASS on disposable SQLite with a documented caveat.** The rehearsal:

1. created a new disposable database;
2. ran all 29 migrations;
3. ran RoleSeeder, testing master data, and DefaultWorkflowSeeder;
4. activated the workflow only in disposable testing;
5. passed health check OFF and ON;
6. rolled back lifecycle/current-stage migrations;
7. remigrated them;
8. confirmed health check fails until workflow is explicitly reactivated;
9. reactivated and passed health checks again.

Two rollback index-order bugs were discovered and fixed.

Pending: repeat the rehearsal using a disposable MySQL/MariaDB instance matching staging. No staging/production database was available in this environment.

## 18. Performance Verification

Read-only code/query audit identified staging risks:

- `TicketResource` can issue QA/UAT count queries per ticket;
- assignment candidate workload uses count-per-user and no pagination;
- list/dashboard queries hydrate unused `workflow_snapshot` JSON;
- Supervisor/PIC dashboards issue many separate aggregate/list queries;
- audit timeline is unbounded and sorted in memory;
- notification recipient/preference resolution can produce batch N+1 queries;
- PIC/Admin list `per_page` limits need hard caps;
- likely indexes should be confirmed with staging `EXPLAIN ANALYZE`.

The incorrect overdue column was fixed. Other performance items remain pending because this environment did not generate the required representative 100-user/1,000-ticket dataset or staging-engine query plans. These do not currently indicate data corruption or authorization failure, but must be measured before production readiness.

## 19. Frontend and Responsive Verification

Automated compile/build result: **PASS**. Admin Workflow editor now has functional loading, error states, responsive tables/modals, and read-only lifecycle states.

Pending manual browser matrix:

- desktop/laptop/tablet/mobile;
- browser console and network-loop inspection;
- dropdown/modal clipping;
- Supervisor/PIC/Requester and Admin end-to-end interaction;
- self-approval warning presentation.

No browser automation tool was available in this execution environment, so these claims are not marked as completed.

## 20. Automated Verification Results

- `php artisan test`: **312 passed, 1663 assertions**.
- `vendor/bin/pint --test`: **PASS**.
- `php artisan route:list`: **327 routes**, including all workflow, Supervisor/PIC, QA, UAT, and legacy routes.
- Shared local `php artisan crm:workflow-check`: expected safe failure because no active workflow and flag OFF.
- Disposable OFF health check: **PASS after test workflow activation**.
- Disposable ON health check and `--all`: **PASS**.
- `npm run typecheck`: **PASS**.
- `npm run build`: **PASS**, 1866 modules transformed.

No dependency or lockfile was updated.

## 21. Data Invariance

Shared local database snapshot before and after verification had identical SHA-256:

`e09cea0921d114c7d1913942eb712164d315dcc6e5ebc5e73184819c4ce0dd28`

Counts remained:

- users: 8;
- tickets: 1;
- active assignments: 1;
- status histories: 27;
- assignment histories: 0;
- attachments: 0;
- comments: 0;
- workflows: 1;
- categories: 4;
- applications: 5.

The hash includes user role IDs/active flags, ticket number/status/workflow fields, active assignment identity, and workflow version/status. Existing data was not changed.

## 22. Deployment and Rollback Documentation

Created `docs/crm-stage-8-staging-deployment-runbook.md` with prerequisites, backups, environment, migration/seeder sequence, flag OFF/ON smoke tests, health checks, monitoring, stop criteria, and four rollback levels.

Database rollback is explicitly prohibited after important dynamic workflow data exists unless a preservation/recovery plan is approved.

## 23. Errors and Constraints

Resolved errors:

- frontend workflow envelope/runtime mismatch;
- incomplete Admin Workflow editor;
- dynamic assignment authorization gap;
- missing post-commit dynamic notifications;
- Supervisor overdue SQL column error;
- self-approval audit omission;
- SQLite rollback index-order failures;
- testing fixture regression after adding minimum category names.

Environment constraints:

- no production/staging database was used;
- no MySQL disposable service was available;
- no interactive browser/device test harness was available;
- no true multi-process concurrency harness was run;
- no representative 1,000-ticket load test was run.

## 24. Pending and Risks

Mandatory before production, recommended before final staging sign-off:

1. Run migration/rollback rehearsal on staging-equivalent MySQL/MariaDB.
2. Execute manual browser/device matrix and capture network/console evidence.
3. Execute true parallel assignment/transition/activation tests on MySQL.
4. Measure Supervisor/PIC/Admin endpoints with representative data and fix confirmed N+1/query-plan issues.
5. Decide whether requester creation and attachment upload require explicit idempotency keys.
6. Add an approval configuration mutation endpoint only if staging users must configure approval rather than consume seeded/default configuration.
7. Verify self-approval warning in the Supervisor UI.

## 25. Final Decision

**Status: PASS WITH PENDING**

Recommendation: **ready to enter controlled staging review, not ready for production deployment**.

The critical authorization, data-integrity, legacy-compatibility, workflow, migration-foundation, and automated integration checks pass. Pending items require staging-equivalent infrastructure or manual browser evidence and should be tracked as staging review gates. Do not proceed to Stage 9 or production based solely on this report.
