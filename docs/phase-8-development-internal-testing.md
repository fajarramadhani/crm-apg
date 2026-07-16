# Phase 8 — Development Execution, Worklog, and Internal Testing

## Workflow

```text
ready_for_development -> development_in_progress -> internal_testing
                                                   | failed
                                                   v
                                      development_in_progress
                                                   | retest passed
                                                   v
                                             ready_for_qa
```

All status changes pass through `TicketTransitionService` inside transactions with row locks. Invalid and stale mutations return HTTP 409. `over_sla` remains derived.

## Persistence and progress

- Ticket summary columns hold development/test timestamps, progress, and latest progress time.
- Worklogs are actual-effort audit records owned by the authenticated active PIC; the API never accepts `user_id`.
- Development updates are append-only snapshots containing completed/remaining items, blockers, and next steps.
- Existing private attachment storage is reused. Development/test evidence defaults to `internal` visibility and never exposes storage paths.
- Test cases are ticket-scoped and case-number unique. Used cases cannot be deleted or edited.
- Runs are ticket-numbered and only one may be active. Results are unique per case/run. Finished runs/results are immutable.

Normal updates can only maintain or increase progress and include `expected_progress` as an optimistic concurrency precondition. Internal testing requires progress 100 and an active case. Failed/blocked completion returns the ticket to development and atomically caps progress at 90. This is the sole decrease mechanism and records rework. A new run requires progress to return to 100.

## Authorization and redaction

PIC mutations require both the current assignee key and an active assignment row. IT Lead endpoints are read-only. Requester resources expose only status, percentage, update time, SLA, and safe test state; worklogs, blockers, cases/results, build references, actual effort, and internal evidence are excluded. Executive technical access is denied.

## API, frontend, history, and events

The endpoint contract is in `docs/api-contract.md`. `/pic/testing` is the real development workspace, `/pic/workspace` links development-stage tickets into it, and `/itlead/development` is the read-only monitor. Requester detail renders generic Indonesian progress messages.

History covers development start/progress/worklog/evidence, case creation, test start/result, failure/rework, pass, and Ready for QA with compact metadata. Events are `TicketDevelopmentStarted`, `TicketDevelopmentProgressUpdated`, `TicketInternalTestingStarted`, `TicketInternalTestingFailed`, and `TicketReadyForQa`; listeners only log in Phase 8.

## Verification

### Feature tests
Feature tests cover ownership/RBAC, validation, monotonic/stale progress, evidence redaction, case/run invariants, fail/rework/retest/pass, duplicate completion, queue filters/pagination, events, history, and request IDs.

### Browser Verification
Manual browser verification was successfully performed after restarting the backend and frontend with a freshly migrated database:

#### PIC Flow
1. PIC successfully opened a ticket with "Ready for Development" status.
2. "Start Development" succeeded, and status transitioned to "Development In Progress".
3. Worklog successfully added.
4. Development update and progress successfully saved.
5. Internal evidence successfully uploaded.
6. Progress successfully reached 100%.
7. Internal test cases successfully created.
8. First internal test run executed with a failed result.
9. Failed run saved and the ticket returned to "Development In Progress".
10. Progress successfully capped at 90% by the server.
11. Rework successfully recorded.
12. Second internal test run executed with all cases passing.
13. Ticket status transitioned to "Ready for QA".
14. All data remained consistent after page refresh.

#### IT Lead Verification
1. Development Monitoring Queue successfully loaded.
2. Ticket displayed with correct PIC, progress, effort, SLA, worklog, evidence, and internal test results.
3. IT Lead pages are read-only.
4. IT Lead cannot modify the PIC's worklog or test results.

#### Requester Verification
1. Requester can see the general status and progress.
2. Requester can see that the ticket is "Ready for QA".
3. Technical data such as worklogs, blockers, test cases, test results, build references, and internal evidence are hidden from the Requester.

#### Visual Verification
- Desktop 1440×900: Pass.
- Mobile 390×844: Pass.
- No major horizontal overflow.
- No Vite overlay error.
- No main application errors in the browser console.
- No CORS errors or HTTP 500 responses.

## Phase 9 Boundary

Phase 9 may consume `ready_for_qa` for QA assignment/execution and defects. UAT, approvals, deployment, notifications, knowledge base, GitHub, and CI/CD remain deferred.
