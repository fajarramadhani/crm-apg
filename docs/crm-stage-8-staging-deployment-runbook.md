# CRM Stage 8 Staging Deployment Runbook

## 1. Purpose and Scope

This runbook prepares the CRM revision for staging only. It does not authorize a production deployment, automatic user-role mapping, or migration of legacy tickets into dynamic workflows.

## 2. Preconditions

- Approved staging maintenance window and deployment owner.
- Reviewed source branch and immutable commit/release reference.
- CI results available for backend tests, Pint, frontend typecheck, and frontend build.
- Staging database and attachment backup destinations are writable and access-controlled.
- Staging is not connected to production notification/email recipients.
- `CRM_DYNAMIC_WORKFLOW_ENABLED=false` before application deployment.
- No role-mapping apply operation is permitted. The available command is dry-run only.
- Confirm legacy QA, UAT, approval, release, and deployment routes are included in the release.

## 3. Record Release Identity

Record these values in the deployment evidence:

```bash
git branch --show-current
git rev-parse HEAD
git status --short
```

Expected branch for this rehearsal: `feature/crm-simplified-dynamic-workflow`.

## 4. Backups

1. Stop staging writes or enable the approved maintenance mechanism.
2. Create a database backup using the staging database engine's native tooling.
3. Verify backup size, checksum, encryption, retention location, and restore permissions.
4. Back up the complete ticket attachment storage.
5. Save a pre-deployment data snapshot containing:
   - user IDs and role IDs;
   - ticket IDs, numbers, statuses, workflow fields;
   - active assignments;
   - status and assignment history counts;
   - attachment and comment counts;
   - QA/UAT record counts;
   - workflow definitions, versions, and active state;
   - application and category counts.
6. Perform or reference a successful restore test before proceeding.

## 5. Environment Variables

Keep the initial feature flag disabled:

```dotenv
CRM_DYNAMIC_WORKFLOW_ENABLED=false
CRM_WORKFLOW_SNAPSHOT_MAX_BYTES=65536
```

Do not edit production environment files. Do not commit `.env`.

After environment changes on staging:

```bash
php artisan config:clear
php artisan cache:clear
```

## 6. Application Deployment

1. Deploy the approved release artifact or commit to staging.
2. Do not run dependency upgrades. Install from the committed lockfiles.
3. Keep queue workers paused until migrations complete when required by the staging platform.
4. Do not run `migrate:fresh`.

## 7. Migration Order

Run pending migrations in their committed timestamp order:

```bash
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
```

Relevant additive CRM migrations include the workflow foundation, ticket workflow fields, assignment histories, system/category fields, requester reference/nullable fields, lifecycle fields, and current workflow stage fields.

Stop immediately if a migration fails. Do not manually mark a failed migration as complete.

## 8. Seeders

Run only explicitly approved seeders:

```bash
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=DefaultWorkflowSeeder --force
```

Safety rules:

- `RoleSeeder` adds role definitions only and must not alter existing `users.role_id`.
- `DefaultWorkflowSeeder` creates/refreshes `crm_default` v1 as `draft` and inactive.
- Do not run `MasterDataSeeder`; it is restricted to automated testing.
- Do not invoke an automatic role-mapping process.

## 9. Workflow Validation and Publication

1. Log in as a staging Admin.
2. Open `/admin/workflows`.
3. Review the default draft stages, transitions, permissions, notification recipients, and Supervisor approval rule.
4. Validate the workflow.
5. Publish only after validation succeeds.
6. Activate exactly one published default workflow on staging.
7. Run:

```bash
php artisan crm:workflow-check
php artisan crm:workflow-check --all
```

Both commands must exit successfully before enabling the runtime flag.

## 10. Smoke Test with Flag OFF

With `CRM_DYNAMIC_WORKFLOW_ENABLED=false`:

1. Requester creates a five-field ticket with a safe attachment.
2. Confirm `workflow_mode`, `workflow_id`, `workflow_snapshot`, and `current_workflow_stage` are null.
3. Confirm compatibility status is `pending_validation`.
4. Open representative legacy tickets, including QA and UAT tickets.
5. Confirm Admin Workflow pages remain accessible.
6. Confirm no production recipients are notified.

## 11. Enable Dynamic Workflow on Staging

Only after the OFF smoke test and health check pass:

```dotenv
CRM_DYNAMIC_WORKFLOW_ENABLED=true
```

Then:

```bash
php artisan config:clear
php artisan crm:workflow-check
```

## 12. Dynamic End-to-End Smoke Test

Create staging-only users for Requester, Supervisor IT, PIC IT Support, PIC IT Develop, and Admin. Do not repurpose production identities.

Verify:

1. Requester creation stores `crm_default` snapshot at `submitted`.
2. Supervisor starts analysis and classifies system/category/priority/target.
3. Supervisor assigns a primary PIC and optional secondary PICs.
4. Primary PIC starts work, adds a note and attachment, requests information, waits for an external party, and resumes.
5. Primary PIC submits for approval.
6. Supervisor requests revision, PIC resumes and resubmits.
7. Supervisor approves and ticket reaches `done`.
8. Exercise reject, cancel, reopen, reassignment, hold, and resume paths.
9. Verify requester public status and complete audit history.
10. Verify secondary PIC cannot submit for approval.
11. Verify legacy tickets remain legacy.

## 13. Monitoring

Monitor during and after the smoke test:

- application and queue logs;
- HTTP 4xx/5xx rates;
- failed jobs;
- notification delivery logs;
- database lock/deadlock errors;
- duplicate histories or assignments;
- response times for Supervisor/PIC lists and dashboards;
- workflow health-check output.

Save sanitized logs and request IDs. Never preserve passwords, tokens, session cookies, or attachment contents in deployment evidence.

## 14. Stop Criteria

Stop activation or roll back the feature flag if any of these occur:

- unauthorized user can view or transition a ticket;
- more than one active primary assignment or active workflow exists;
- legacy ticket status/data changes without an explicit legacy action;
- workflow snapshot changes for an existing dynamic ticket;
- migration, health check, or dynamic end-to-end test fails;
- duplicate transition histories appear;
- attachment access control fails;
- data count/hash mismatch cannot be explained by test records;
- critical database or queue errors occur.

## 15. Rollback Plan

### Level 1: Feature Flag

Set:

```dotenv
CRM_DYNAMIC_WORKFLOW_ENABLED=false
```

Then clear config. This sends newly created tickets back to compatibility intake. Existing dynamic tickets retain snapshots and must continue through the dynamic handler.

### Level 2: Application Rollback

Deploy the previously approved application artifact/commit. Preserve database and attachments. Confirm whether the prior application can safely display existing dynamic tickets before rollback.

### Level 3: Database Rollback

Database rollback is permitted only for additive migrations that have not accumulated important data. Do not drop workflow tables or ticket workflow columns after dynamic tickets exist. Prefer restoring the verified backup to a separate database and performing a controlled recovery.

The lifecycle/current-stage migrations have rollback coverage on disposable SQLite, but this is not authorization to roll them back after staging usage.

### Level 4: User Mapping Rollback

Not applicable in Stage 8. No user mapping is applied. The command `crm:role-mapping` has dry-run mode only.

## 16. Post-Deployment Data Verification

Compare the post-test snapshot with the baseline. Differences must be limited to deliberate staging test records. Existing user IDs/role IDs, legacy tickets, assignments, histories, QA/UAT data, attachments, and comments must remain unchanged.

## 17. Deployment Ownership and Evidence

Record:

- deployment owner and approver;
- database/attachment backup owners;
- start/end timestamps;
- branch, commit, artifact checksum;
- migration and seeder logs;
- workflow health-check output;
- flag OFF and ON smoke-test evidence;
- browser/network evidence;
- data comparison result;
- monitoring screenshots/log references;
- rollback decision and outcome if used.

No production deployment is authorized by this runbook.
