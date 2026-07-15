# API Contract Plan

## Phase 6 canonical endpoints

All routes use `/api/v1`, Sanctum authentication, active-user and permission middleware, the standard envelope, and `meta.request_id`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/it-lead/triage-queue` | Paginated `validated`/`triage` queue with search/status/master/date filters |
| GET | `/it-lead/tickets/{ticket}` | Technical triage detail |
| POST | `/it-lead/tickets/{ticket}/start-triage` | Locked `validated -> triage` transition |
| POST | `/it-lead/tickets/{ticket}/assign` | Server-authoritative priority, PIC, SLA/calendar, and deadline transaction |
| GET | `/it-lead/pic-options` | Active PIC identities and aggregate workload |
| GET | `/it-lead/pic-workloads` | Active/critical/high counts and simple indicator |
| GET | `/pic/assignments` | Logged-in PIC active assignment list; status/priority filters |
| GET | `/pic/tickets/{ticket}` | Read-only detail for the current assigned PIC only |

Stale workflow actions return `409 INVALID_TRANSITION`; inactive/invalid master or PIC choices return `422`. Deadlines are never accepted from clients.

## Phase 5 canonical ticket endpoints

All routes require Sanctum, an active account, standard envelopes, and request IDs. Requester routes are `POST/GET /api/v1/tickets`, `GET/PUT /api/v1/tickets/{ticket}`, `POST /{ticket}/resubmit`, `POST /{ticket}/cancel`, `GET /{ticket}/history`, and nested attachment upload/download/delete. Creation intentionally submits immediately to `pending_validation`; there is no competing draft UX.

Requester filters are `search`, `status`, `category`, `application`, `date_from`, `date_to`, `page`, and `per_page` (maximum 100). Supervisor routes are `GET /api/v1/supervisor/validation-queue`, `GET /supervisor/tickets/{ticket}`, and actions `validate`, `request-revision`, `reject`, `transfer`. Queue filters are `search`, `category`, `application`, `requester`, `submitted_from`, `submitted_to`, and pagination.

Transfer changes `current_division_id`, writes `action=transferred`, and keeps `status=pending_validation`, so the target queue receives it immediately. Invalid/double transitions return `409 INVALID_TRANSITION`. Revision, rejection, and transfer require `notes`; transfer also requires an active, different `target_division_id`.

Multipart attachment input is `file` and optional category `evidence|screenshot|document|log|other`. Allowed types are PNG/JPEG/PDF/TXT/CSV/DOC/DOCX/XLS/XLSX, maximum 10 MB and 10 files per ticket. ZIP and executables are rejected. Resources never expose `disk`, `path`, or `stored_name`.

## Phase 4 master data

All routes below require Sanctum authentication, an active account, and a request ID. `master_data.view` protects active dropdown routes; `master_data.manage` protects Admin routes.

Read-only active collections: `GET /api/v1/master/divisions`, `/branches`, `/applications`, `/applications/{application}/modules`, `/ticket-categories`, `/ticket-priorities`, `/sla-policies`, `/working-calendars`, and `/holidays`.

Admin collections use `/api/v1/admin/{resource}` and accept `search`, `is_active`, `page`, and `per_page` (default 15, maximum 100). Admin mutations use POST for creation, PUT on `/{id}` for updates, and DELETE for deactivation. Nested creation routes are `/admin/applications/{application}/modules` and `/admin/working-calendars/{calendar}/holidays`; subsequent updates/deletes use `/admin/application-modules/{module}` and `/admin/holidays/{holiday}`. Holidays are date exceptions and are physically deletable because no ticket references them in Phase 4; other DELETE actions set `is_active=false`.

Collection pagination is returned in `meta.pagination`; every response retains the Phase 2 `success`, `message`, `data`, and `meta.request_id` envelope. Validation failures use HTTP 422 and the standard field-keyed `errors` object.

## Conventions

- Base path: `/api/v1`
- JSON uses `snake_case`; timestamps ISO-8601 UTC; display conversion occurs in frontend.
- Authentication: Laravel Sanctum SPA cookies. Frontend calls `/sanctum/csrf-cookie`, then session endpoints with credentials.
- List endpoints: `page`, `per_page` (default 20, max 100), `sort`, filters; response includes `data`, `links`, `meta`.
- Mutation response returns the refreshed resource and `allowed_actions` when relevant.
- Use `Idempotency-Key` for ticket creation, transition submission where retry is plausible, and deployment confirmation.
- Use `If-Match`/`version` for conflicting ticket mutations; stale mutation returns 409.

### Success envelope

```json
{
  "success": true,
  "message": "Human-readable message",
  "data": {},
  "meta": {
    "request_id": "..."
  }
}
```

### Error envelope

```json
{
  "success": false,
  "message": "Safe error message",
  "error": {
    "code": "ERROR_CODE"
  },
  "meta": {
    "request_id": "..."
  }
}
```

Validation errors replace `error` with an `errors` object keyed by field while retaining `success`, `message`, and `meta.request_id`.

Expected status codes: 200/201/204, 401 unauthenticated, 403 forbidden, 404 missing, 409 invalid transition/stale version/idempotency conflict, 422 validation, 429 throttled.

## Authentication and current user

| Method    | Endpoint                       | Purpose                                                   |
| --------- | ------------------------------ | --------------------------------------------------------- |
| GET       | `/sanctum/csrf-cookie`         | Initialize CSRF cookie (Laravel route outside `/api/v1`)  |
| POST      | `/auth/login`                  | Create SPA session; email/password                        |
| POST      | `/auth/logout`                 | Revoke current session                                    |
| GET       | `/auth/me`                     | Current user, primary role, and effective permissions     |
| GET       | `/me/notifications`            | Paginated own notifications                               |
| PATCH     | `/me/notifications/{id}/read`  | Mark one as read                                          |
| POST      | `/me/notifications/read-all`   | Mark scoped notifications read                            |
| GET/PATCH | `/me/notification-preferences` | Read/update own preferences                               |

SSO, reset password, MFA, and session management endpoint ditambahkan hanya setelah identity policy diputuskan.

Phase 3 login memakai email/password lokal dan session cookie HttpOnly. Credential salah, user nonaktif, dan role nonaktif semuanya memakai pesan aman yang sama dengan code `INVALID_CREDENTIALS`. Login berhasil meregenerasi session dan memperbarui `last_login_at`; logout menginvalidasi session serta CSRF token. Login dibatasi lima percobaan per menit untuk kombinasi email/IP.

Response user Phase 3:

```json
{
  "id": 1,
  "name": "Requester Demo",
  "email": "requester@tichub.local",
  "role": { "key": "requester", "name": "Requester" },
  "permissions": ["dashboard.requester.view", "ticket.own.view"]
}
```

## Tickets

| Method          | Endpoint                              | Permission/purpose                                                                                                                                  |
| --------------- | ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| GET             | `/tickets`                            | Policy-scoped list; filters `search,status[],priority[],category[],application_id,division_id,requester_id,pic_id,over_sla,created_from,created_to` |
| POST            | `/tickets`                            | Create ticket; requester/application/category/title/description/attachments                                                                         |
| GET             | `/tickets/{ticket}`                   | Policy-shaped detail; executive projection/redaction                                                                                                |
| PATCH           | `/tickets/{ticket}`                   | Edit only fields allowed in draft/revision; requires version                                                                                        |
| POST            | `/tickets/{ticket}/cancel`            | Cancel with reason                                                                                                                                  |
| POST            | `/tickets/{ticket}/reopen`            | Reopen with reason and policy window                                                                                                                |
| GET             | `/tickets/{ticket}/allowed-actions`   | Optional explicit refresh; usually embedded in detail                                                                                               |
| GET             | `/tickets/{ticket}/activities`        | Visibility-scoped timeline, paginated                                                                                                               |
| POST            | `/tickets/{ticket}/comments`          | Public/internal comment based on permission                                                                                                         |
| GET/POST/DELETE | `/tickets/{ticket}/watchers[/{user}]` | Subscription management                                                                                                                             |
| GET             | `/tickets/{ticket}/status-history`    | Authorized transition history                                                                                                                       |
| GET             | `/tickets/{ticket}/links`             | Related tickets                                                                                                                                     |
| POST/DELETE     | `/tickets/{ticket}/links[/{link}]`    | Manage relations                                                                                                                                    |

Create payload minimum:

```json
{
  "title": "Gagal Generate PDF Polis",
  "description": "...",
  "category": "incident",
  "application_id": "01...",
  "priority_suggestion": "critical",
  "attachment_ids": ["01..."]
}
```

Ticket resource minimum:

```json
{
  "id": "01...",
  "ticket_no": "IT-2026-000001",
  "title": "...",
  "description": "...",
  "category": "incident",
  "priority": "critical",
  "status": "uat",
  "requester": { "id": "01...", "name": "..." },
  "application": { "id": "01...", "code": "SMP", "name": "..." },
  "current_pic": { "id": "01...", "name": "..." },
  "sla": { "due_at": "...Z", "remaining_work_seconds": 0, "is_breached": true },
  "version": 7,
  "allowed_actions": ["comment_public", "submit_uat"]
}
```

## Workflow actions

Gunakan action endpoints daripada generic `PATCH status`, sehingga validation, audit, dan policy jelas.

| Method | Endpoint                                    | Payload utama                                            |
| ------ | ------------------------------------------- | -------------------------------------------------------- |
| POST   | `/tickets/{ticket}/validation`              | `decision: validated                                     | need_revision                           | rejected`, comment, reason_code |
| POST   | `/tickets/{ticket}/triage`                  | category, priority, sla_policy_id, impact, urgency, note |
| POST   | `/tickets/{ticket}/assignments`             | assignment_type, user_id, note                           |
| POST   | `/tickets/{ticket}/transfer`                | target user/division/application, reason                 |
| POST   | `/tickets/{ticket}/start-analysis`          | version                                                  |
| POST   | `/tickets/{ticket}/wait`                    | type `user                                               | external_party`, reason, expected_until |
| POST   | `/tickets/{ticket}/resume`                  | comment, version                                         |
| POST   | `/tickets/{ticket}/submit-planning`         | plan_id, version                                         |
| POST   | `/tickets/{ticket}/submit-development`      | summary, release_reference, version                      |
| POST   | `/tickets/{ticket}/ready-for-internal-test` | test_run_id/version                                      |
| POST   | `/tickets/{ticket}/ready-for-qa`            | test_run_id/version                                      |
| POST   | `/tickets/{ticket}/ready-for-uat`           | test_run_id, uat_owner_id                                |
| POST   | `/tickets/{ticket}/request-approval`        | approver_ids or resolved rule                            |
| POST   | `/tickets/{ticket}/mark-ready-to-deploy`    | approval reference/version                               |
| POST   | `/tickets/{ticket}/mark-deployed`           | deployment_id/idempotency key                            |
| POST   | `/tickets/{ticket}/start-monitoring`        | deployment_id, planned_end_at                            |
| POST   | `/tickets/{ticket}/close`                   | resolution_code, summary, KB reference, version          |

Invalid state returns `409 INVALID_TRANSITION` with `current_status` and latest `version`, never silently coerces.

## RCA, planning, tests, approvals, deployment, monitoring

| Method    | Endpoint                                | Purpose                                                     |
| --------- | --------------------------------------- | ----------------------------------------------------------- |
| GET/PUT   | `/tickets/{ticket}/rca`                 | Read/upsert RCA subject to state/policy                     |
| GET/PUT   | `/tickets/{ticket}/plan`                | Read/upsert implementation plan                             |
| GET/POST  | `/tickets/{ticket}/test-runs`           | List/create internal/QA/UAT run                             |
| GET/PATCH | `/test-runs/{run}`                      | Run metadata/status                                         |
| POST      | `/test-runs/{run}/cases`                | Add/snapshot case                                           |
| PATCH     | `/test-runs/{run}/cases/{case}`         | Result/actual/notes                                         |
| POST      | `/test-runs/{run}/submit`               | Finalize run; recommendation                                |
| GET/POST  | `/tickets/{ticket}/defects`             | List/create defect                                          |
| PATCH     | `/defects/{defect}`                     | Assign/resolve/retest transition                            |
| GET       | `/approvals`                            | Approver queue; filter decision/stage                       |
| GET       | `/tickets/{ticket}/approvals`           | Approval history                                            |
| POST      | `/approvals/{approval}/decision`        | approved/rejected/revision + mandatory comment where needed |
| GET/POST  | `/tickets/{ticket}/deployments`         | List/create deployment plan                                 |
| PATCH     | `/deployments/{deployment}`             | Update plan/window/checklist fields                         |
| POST      | `/deployments/{deployment}/start`       | Begin deployment                                            |
| POST      | `/deployments/{deployment}/complete`    | success/failed/rolled_back + evidence                       |
| GET/POST  | `/tickets/{ticket}/monitoring-records`  | Monitoring updates                                          |
| POST      | `/monitoring-records/{record}/complete` | pass/issue_found and notes                                  |
| GET       | `/tickets/{ticket}/closure`             | Closure record                                              |

## Attachments

| Method | Endpoint                             | Purpose                                                              |
| ------ | ------------------------------------ | -------------------------------------------------------------------- |
| POST   | `/attachments`                       | Multipart upload to private temporary storage; returns attachment ID |
| GET    | `/attachments/{attachment}/download` | Authorized streamed/signed download                                  |
| DELETE | `/attachments/{attachment}`          | Only unattached/draft attachment or authorized removal               |

Upload validates size/MIME/extension, records checksum and scan state. Ticket mutation may only attach IDs owned by current user/session and not already bound elsewhere.

## SLA, escalation, dashboards, reports

| Method | Endpoint                               | Purpose                                      |
| ------ | -------------------------------------- | -------------------------------------------- |
| GET    | `/sla/monitoring`                      | Policy-scoped clocks/at-risk/breached queues |
| GET    | `/tickets/{ticket}/sla`                | Clock and pause history                      |
| GET    | `/tickets/{ticket}/escalations`        | Triggered events                             |
| POST   | `/tickets/{ticket}/escalations/manual` | Authorized manual escalation                 |
| GET    | `/dashboards/requester`                | Own KPI/queues                               |
| GET    | `/dashboards/supervisor`               | Division KPI/validation                      |
| GET    | `/dashboards/it-lead`                  | Operational KPI/triage/SLA                   |
| GET    | `/dashboards/pic`                      | Assigned workload                            |
| GET    | `/dashboards/qa`                       | QA queue/metrics                             |
| GET    | `/dashboards/manager`                  | Approval/SLA summary                         |
| GET    | `/dashboards/executive`                | Redacted aggregates only                     |
| GET    | `/reports/tickets`                     | Authorized aggregate/list report             |
| POST   | `/exports`                             | Async authorized export; returns job ID      |
| GET    | `/exports/{export}`                    | Own/authorized export status/download        |

Dashboard endpoint bukan sumber truth terpisah; ia query read model dari domain tables. Cache singkat dapat ditambahkan setelah correctness.

## Admin/master data

| Resource            | Endpoints                                                                                                                               |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Users               | `GET/POST /users`, `GET/PATCH /users/{user}`, `POST /users/{user}/activate`, `POST /users/{user}/deactivate`, `PUT /users/{user}/roles` |
| Roles/permissions   | `GET /roles`, `GET /permissions`, `PUT /roles/{role}/permissions`                                                                       |
| Divisions           | CRUD-ish `/divisions`; use deactivate rather than delete when referenced                                                                |
| Applications        | CRUD-ish `/applications`; membership `/applications/{app}/members`                                                                      |
| SLA policies        | `/sla-policies`, activate/deactivate; preview calculation endpoint optional                                                             |
| Calendars           | `/business-calendars`, nested hours and holidays                                                                                        |
| Escalation rules    | `/escalation-rules`, nested targets, activate/deactivate                                                                                |
| Audit               | `GET /audit-logs`, `GET /audit-logs/{log}`; never create/update through public API                                                      |
| System dictionaries | `GET /meta/ticket-options` for allowed categories/status labels/priorities, not authorization                                           |

## Knowledge Base

| Method      | Endpoint                                           | Purpose                         |
| ----------- | -------------------------------------------------- | ------------------------------- |
| GET/POST    | `/knowledge/articles`                              | Search/list; create draft       |
| GET/PATCH   | `/knowledge/articles/{article}`                    | Visibility-scoped detail/update |
| POST        | `/knowledge/articles/{article}/submit-review`      | Draft to review                 |
| POST        | `/knowledge/articles/{article}/publish`            | Publish authorized version      |
| POST        | `/knowledge/articles/{article}/archive`            | Archive                         |
| GET         | `/knowledge/articles/{article}/versions`           | Version history                 |
| POST        | `/knowledge/articles/{article}/feedback`           | Helpful/comment                 |
| GET/POST    | `/knowledge/categories`                            | List/manage categories          |
| POST/DELETE | `/tickets/{ticket}/knowledge-articles[/{article}]` | Link solution/source article    |

## Contract delivery strategy

Implement endpoint groups per roadmap vertical slice, not sekaligus. Setiap endpoint wajib memiliki Form Request, Policy, Resource, feature test (success/validation/forbidden/not-found/conflict), OpenAPI update, dan contoh frontend integration. OpenAPI menjadi contract source setelah backend skeleton dibuat; TypeScript client/types dapat digenerate atau divalidasi terhadap schema pada fase berikutnya.

## Phase 7 implemented endpoints

All routes are under `/api/v1`, require a Sanctum session, use policy plus permission checks, return the standard request ID, and use `409 Conflict` for stale or duplicate transitions.

| Actor | Method | Endpoint | Purpose |
| --- | --- | --- | --- |
| PIC | POST | `/pic/tickets/{ticket}/start-analysis` | Move an owned assigned ticket to analysis |
| PIC | GET/POST | `/pic/tickets/{ticket}/analysis` | Read or create the current analysis |
| PIC | PUT | `/pic/tickets/{ticket}/analysis/{analysis}` | Explicit draft save with `expected_lock_version` |
| PIC | POST | `/pic/tickets/{ticket}/analysis/{analysis}/complete` | Complete RCA and enter solution planning |
| PIC | GET/POST | `/pic/tickets/{ticket}/solution-plan` | Read versions or create the current plan |
| PIC | PUT | `/pic/tickets/{ticket}/solution-plan/{plan}` | Update the current draft with optimistic locking |
| PIC | POST | `/pic/tickets/{ticket}/solution-plan/{plan}/submit` | Submit the plan for IT Lead review |
| IT Lead | GET | `/it-lead/plan-review-queue` | Paginated/filterable submitted-plan queue |
| IT Lead | GET | `/it-lead/tickets/{ticket}/solution-plan` | RCA, plan, attachment, and history review projection |
| IT Lead | POST | `/it-lead/tickets/{ticket}/solution-plan/{plan}/request-revision` | Require a reason and return to solution planning |
| IT Lead | POST | `/it-lead/tickets/{ticket}/solution-plan/{plan}/approve` | Approve and move to ready for development |

Requester ticket resources expose only generic workflow progress for these phases. RCA, solution text, risk, rollback, testing, technical history notes, and internal event metadata are omitted.
