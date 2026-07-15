# Phase 6 — IT Lead Triage, SLA Initialization, and PIC Assignment

## Delivered workflow

Phase 6 adds `validated -> triage -> assigned`. IT Leads receive a real filtered/paginated queue, start triage through `TicketTransitionService`, finalize priority, inspect active PIC workload, and assign one primary PIC. PICs receive a real read-only assignment dashboard/workspace. Requesters see Assigned, final priority, PIC display name, service deadline, and a redacted assignment event.

IT Lead scope is temporarily all validated/triage tickets because application responsibility mapping does not yet exist. Executive technical access is denied.

## Schema and transaction

Tickets now store policy/calendar, response/resolution deadlines, calculation timezone, triage/assignment timestamps, current assignee/assigner, and the pre-existing final-priority key. `ticket_assignments` preserves primary/support-ready history. Assignment locks the ticket row and atomically validates state/master data/PIC role, calculates SLA, inserts assignment, updates the ticket, and appends history. A second/stale action returns HTTP 409.

## Working calendar behavior

`WorkingTimeCalculator` normalizes starts before/after working hours, skips non-working ISO weekdays and registered holidays, and adds policy minutes across days in the calendar timezone (`Asia/Jakarta` by default). No lunch break is assumed; the full configured interval counts. `SlaDeadlineService` calculates nullable response and required resolution deadlines. No breach scheduler or escalation exists in Phase 6.

Seeded SLA resolutions remain Critical 240, High 480, Medium 960, and Low 2400 working minutes. Requested priority never becomes final priority automatically.

## API, permission, resources, and frontend

IT Lead endpoints cover queue/detail/start/assign/PIC options/workload; PIC endpoints cover own assignment list/detail. `TicketResource` exposes final priority, policy summary, deadlines, assignee, timestamps, and role/status-aware actions. Assignment notes/metadata stay internal. `TicketTriageStarted` and `TicketAssigned` provide event foundations without production notifications.

The existing visual hierarchy is retained. Triage Queue now has real loading/empty/error/filter/pagination, start triage, priority/PIC workload selection, SLA preview, validation feedback, and server-authoritative assignment. PIC dashboard/workspace is real and read-only. Requester detail shows the safe assignment summary.

## Verification and deferred work

Automated coverage includes queue scope/RBAC/filter/pagination, locked conflicts, history/events, active PIC filtering/workload, priority/PIC validation, atomic assignment, SLA selection, deterministic deadlines, after-hours/weekend/holiday behavior, Requester redaction, PIC isolation, Executive denial, and request IDs. Full Phase 5 regression remains required.

Deferred: reassignment/transfer action, RCA, analysis/planning/progress, wait/resume, testing, QA/UAT, approval, deployment, monitoring/closing, escalation scheduler, real notifications, Knowledge Base, SSO, multi-role users, and AI assignment.
