# Phase 4 — Master Data Foundation

## Scope and architecture

Phase 4 replaces the master-data dummy layer with Laravel-backed data without implementing tickets, workflow, SLA timers, escalation, QA/UAT, approval, deployment, or knowledge base features. One migration creates the master tables and adds nullable organization keys to users. Foreign keys use restrict or null-on-delete semantics according to historical safety.

## Schema and relationships

- `divisions`: stable unique code, optional self-referencing parent, active flag. Parent and children are Eloquent relations; self-parent is rejected.
- `branches`: stable unique code, address/city, active flag; has many users.
- `applications`: belongs to an optional owner division and has many application modules.
- `application_modules`: code is unique within its application.
- `ticket_categories`: validated type is `incident`, `request`, `change`, or `problem`.
- `ticket_priorities`: stable key and explicit unique level. Seeds are critical (1), high (2), medium (3), and low (4).
- `working_calendars`: timezone, workday start/end, and JSON ISO weekday array. `[1,2,3,4,5]` means Monday–Friday.
- `sla_policies`: belongs to priority and calendar. Resolution and optional response durations are working minutes.
- `holidays`: date exception per calendar; `(working_calendar_id,date)` is unique.
- `users`: optional `division_id` and `branch_id` assignments.

All active-flag models provide `active()` and boolean casts. Calendar working days are cast to arrays and holiday dates to dates.

## API and permissions

Authenticated roles with `master_data.view` can read active data under `/api/v1/master`. Admin alone receives `master_data.manage` and can list, create, update, and deactivate under `/api/v1/admin`. Backend middleware returns HTTP 403 for all other roles, including Executive. API Resources define every serialized shape rather than returning raw models.

Admin collections support `search`, `is_active`, `page`, and `per_page`. The default size is 15 and the hard maximum is 100. Read-only dropdown endpoints intentionally return small unpaginated active lists.

## Validation and consistency

Store and update use separate Form Request classes sharing domain rules. Codes normalize to uppercase and allow letters, digits, `_`, and `-`; priority keys normalize to lowercase. Rules cover uniqueness, valid foreign keys, inactive relations for active records, division self-parenting, module/application ownership, category types, positive SLA minutes, one active SLA policy per priority/calendar, start-before-end working hours, unique valid weekdays, and duplicate holiday dates. Standard Phase 2 validation envelopes and request IDs remain unchanged.

`DELETE` deactivates reusable master records. This preserves IDs for future ticket history and keeps inactive records visible to Admin while removing them from active endpoints. Holidays are the exception: because they have no `is_active` field and are calendar exceptions rather than ticket references, DELETE removes the holiday record; calendar deletion itself deactivates the calendar.

Deactivation also protects active relations: divisions/branches with active dependents, and priorities/calendars with active SLA policies, are rejected with 422 until dependents are reassigned or deactivated. Deactivating an application deactivates its modules atomically.

## Development seed

`MasterDataSeeder` is idempotent. It provides five example divisions, three clearly local development branches, five example applications, four ticket categories, four priorities, one Jakarta weekday calendar, and SLA resolutions of 240, 480, 960, and 2400 working minutes. It does not claim production holidays. Development users are assigned to the IT division and Jakarta branch. The 08:00–17:00 window does not yet subtract breaks; exact clock calculation is deferred.

## Frontend integration

`masterDataService` supplies active getters for divisions, branches, applications, modules, categories, priorities, SLA policies, calendars, and holidays without hard-coded IDs. The Admin master page integrates real Divisions, Applications/Modules, and Ticket Categories, including loading, empty, error, create/edit where exposed, deactivation confirmation, backend validation display, and post-mutation refresh. The SLA page reads real policy/calendar data and shows working-minute values.

Branches, priorities, working calendars, and holidays have complete backend management APIs but dedicated Admin editing screens are deferred. SLA policy mutation UI is also deferred; the current vertical proof is read-only because the existing prototype did not contain a safe calendar-aware editor.

## Tests and verification

`MasterDataFoundationTest` covers authentication, role read access, Admin-only mutation, division CRUD/deactivation, uniqueness and self-parent validation, applications/modules, categories/priorities, positive and duplicate SLA policies, calendar/holiday validation, inactive filtering, pagination/search, API Resource fields, request IDs, standard errors, seeder idempotence, and relational integrity. Authentication tests from Phase 3 remain part of the full suite.

Verification commands are recorded in the final Phase 4 handoff. MySQL remains the development target and disposable SQLite is configured for automated tests.

Final local verification used disposable SQLite: `migrate:fresh --seed` and migration status passed; all 52 API routes were listed; Pint passed; and the complete backend suite passed 36 tests. Frontend install, typecheck, Prettier check, and production build passed. Browser verification passed for Admin login, Divisions, Applications/Modules, Categories, and SLA Policies at 1440×900 and 390×844 with no application console errors, error overlay, or horizontal overflow. A real HTTP smoke test also confirmed health, unauthenticated 401, Admin login, pagination, create, and request IDs. Composer dependency installation was attempted with the lock file but the local Composer process timed out during post-install; the existing locked `vendor` tree was sufficient for all Laravel verification commands.

## Deferred to Phase 5 or later

Ticket CRUD and number generation, attachment uploads, workflow transitions, assignment, RCA, SLA calculation/timers, escalation, QA/UAT, approval, deployment, business notifications/audit, knowledge base, SSO, full user management, multi-role users, and complex organization hierarchy remain out of scope.
