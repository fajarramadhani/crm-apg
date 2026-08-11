# CRM Supervisor Analysis and PIC Workspace Revision Report

Date: 2026-07-29

## Scope

This revision separates Supervisor IT analysis from requester classification and PIC assignment, then aligns the simplified PIC workspace controls with the actions authorized by its backend detail response.

## Supervisor Field Decisions

| Field | Decision | Implementation |
| --- | --- | --- |
| Request category | READ ONLY | Displayed from the requester submission and prohibited in the analysis request. |
| Application/system | READ ONLY | Displayed from the requester submission and prohibited in the analysis request. |
| Urgency | READ ONLY | Displayed from the requester submission and prohibited in the analysis request. |
| Application module | LEGACY ONLY | Preserved in the schema/resource but removed from the Supervisor analysis form. |
| Technical category and priority | REMOVE FROM FORM | Existing values remain available for legacy flows, but the simplified analysis endpoint cannot mutate them. |
| Analysis summary | KEEP | Required internal analysis record. |
| Handling note | KEEP | Optional internal direction for the assigned PIC. |
| Supervisor completion target | KEEP, OPTIONAL | Updates `resolution_due_at` only and does not overwrite requester-owned `target_needed_at`. |
| Primary/secondary PIC | SEPARATE ACTION | Managed only through the existing assignment panel and assignment service endpoints. |
| Workflow selection | REMOVE FROM FORM | Explicitly prohibited in the analysis payload. |

## Backend Changes

- `AnalyzeTicketRequest` accepts only `analysis_summary`, `handling_note`, and optional `target_completion_date`.
- Classification, workflow, priority, and assignment fields are rejected with HTTP 422 instead of being silently ignored.
- `SupervisorItTicketController::analyze` records internal comments and audit history without changing requester classification or assigning PICs.
- Analysis can move an initial ticket to `under_analysis`; assignment remains an independent operation.
- PIC detail responses now provide workspace-specific `allowed_actions` based on active assignment role and ticket status.
- Terminal PIC tickets expose no workspace mutation actions.

## Frontend Changes

- Supervisor requester context displays system, requester category, and urgency as read-only values.
- The Supervisor analysis form contains only analysis summary, handling direction, and optional target.
- Duplicate one-step assignment controls and unnecessary master-data requests were removed.
- PIC controls render from backend `allowed_actions`, including primary-only actions.
- PIC upload uses the fetch client's `postForm()` transport so the browser supplies the multipart boundary.
- PIC forms display custom fetch client errors correctly.
- HTTP 409 responses show a conflict notice with a reload action.
- Internal PIC navigation uses React Router links instead of full-page anchors.

## Compatibility

- No database column or legacy application-module relationship was removed.
- Dynamic assignment candidate selection and assignment invariants were not changed.
- Dynamic and legacy analysis/solution-plan endpoints remain available.
- Requester submission fields and `target_needed_at` remain requester-owned.

## Verification

- Focused backend tests cover immutable analysis fields, separated assignment, and PIC role/status actions.
- Frontend TypeScript typecheck passes.
- Focused backend suite: 27 tests, 126 assertions, passed.
- Full backend suite: 379 tests, 2,235 assertions, passed.
- Laravel Pint: passed.
- Frontend TypeScript typecheck: passed.
- Frontend production build: passed.
