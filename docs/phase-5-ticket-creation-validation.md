# Phase 5 — Ticket Creation and Supervisor Validation

## Delivered scope

This phase implements the first real Tic Hub ticket aggregate. Requester create submits directly to `pending_validation`, matching the prototype without adding a competing draft screen. Requesters can list/open only their tickets, edit in `need_revision`, resubmit, cancel before validation, and manage their own attachments before validation. Supervisors see only `pending_validation` tickets whose `current_division_id` equals their division and can validate, request revision, reject, or transfer.

Not delivered: IT Lead triage, final priority, SLA clocks/escalation, PIC assignment, RCA/planning execution, testing, QA, UAT, approval, deployment, monitoring/closing, Notification Center, Knowledge Base, SSO, and multi-role users.

## Schema and relationships

- `tickets` owns requester/division snapshots, current handling division, branch/application/module/category, requested priority, type fields, enum status, and workflow timestamps. `final_priority_id` remains null.
- `ticket_number_sequences` safely allocates a monthly counter.
- `ticket_status_histories` is append-only through application services and records actor role, notes, and redacted metadata.
- `ticket_comments` stores revision, rejection, transfer, and resubmission notes. Requester queries exclude internal comments.
- `ticket_attachments` stores uploader and safe metadata; bytes stay on the configured filesystem.
- `Ticket` belongs to Requester, Division, Current Division, Branch, Application, Module, Category, and Requested Priority, and has many histories, comments, and attachments.

## Status and transition decisions

Implemented enum values are `draft`, `pending_validation`, `need_revision`, `validated`, `rejected`, `transferred`, and `cancelled`. `draft` and `transferred` remain domain values, but direct creation records draft in history then persists `pending_validation`; transfer is an action instead of a parked state.

`TicketTransitionService` is the workflow mutation path. Each action starts a transaction, reloads the ticket `FOR UPDATE`, verifies expected status, updates state/timestamps, writes history and any visible comment, then dispatches an event. A stale/double transition returns `409 INVALID_TRANSITION`.

Transfer keeps `pending_validation`, changes `current_division_id`, and records `from_division_id`/`to_division_id`. This prevents stranded tickets and hands the ticket directly to the target division queue.

## Ticket number concurrency

`TicketNumberGenerator` uses `TIC-YYYYMM-NNNNNN`. Inside the ticket transaction it inserts the period row if absent, locks that row, increments it, and formats the result. Allocation is serialized within the month; a unique database constraint is the final defense. Tests verify unique sequential allocation without ticket counts.

## Upload security

`TICKET_ATTACHMENT_DISK` defaults to private `local`. Stored names are UUIDs and paths are generated below `tickets/{id}`. MIME allow-list, 10 MB per-file limit, ten-file limit, authenticated parent policy, uploader deletion check, and private controller download are enforced. Resources omit disk/path/stored name. ZIP is deferred until archive inspection and malware scanning exist.

## API, policy, resources, and filters

Routes and filters are in `docs/api-contract.md`. `TicketPolicy` enforces ownership, mutable states, parent attachment access, and same-current-division Supervisor scope. `TicketResource`, `TicketAttachmentResource`, `TicketCommentResource`, and `TicketStatusHistoryResource` prevent raw model/path exposure and provide `allowed_actions`. Lists eager-load relations and bound page size to avoid primary N+1 risks.

## Events

The application dispatches `TicketSubmitted`, `TicketRevisionRequested`, `TicketResubmitted`, `TicketValidated`, `TicketRejected`, and `TicketTransferred`. Phase 5 uses a lightweight log listener; email, queues, preferences, and business Notification Center are deferred.

## Frontend

Create Ticket loads Phase 4 master data, renders category-dependent fields, creates without requester/division IDs, uploads attachments, and redirects to real detail. History uses server filtering/pagination. Detail renders history/comments/attachments and supports edit/resubmit/cancel from `allowed_actions`.

Supervisor Validation Queue uses scoped API data, server search/pagination, authenticated downloads, confirmation modals, mandatory reasons, division lookup, and refresh after mutations. Existing routes and visual hierarchy remain.

## Automated coverage

`TicketCreationValidationTest` covers identity sourcing, unique numbers, category/master/module validation, ownership/filter/pagination, resources/request IDs, revision/update/resubmit, generic status protection, attachment MIME/upload/download/delete authorization, Supervisor scope/queue, required reasons, validate/reject/transfer/cancel, invalid/double transitions, history/comments, internal comment hiding, and events. Storage is faked.

## Verification record

- Baseline: backend 36 tests / 347 assertions passed; frontend install, typecheck, format check, and build passed.
- Final backend: `migrate:fresh --seed --force`, migration status, 68 API routes, 46 tests / 441 assertions, and `vendor/bin/pint --test` passed.
- Final frontend: frozen install, typecheck, Prettier check, and production build passed. Vite reports the pre-existing large-chunk advisory (814.62 kB main JS), not a build failure.
- Actual HTTP with Sanctum cookies and CSRF passed login, create/list/detail, PDF upload, Supervisor queue, revision, resubmit, validate, reject, transfer to HR while remaining actionable, and unauthenticated attachment download (`401`). The primary ticket was `TIC-202607-000002`.
- Manual browser verification completed the primary Requester → Supervisor flow using real API master data: create with attachment, persistence in Ticket History as `pending_validation`, same-division Supervisor queue visibility, mandatory-reason revision, requester revision display/edit/resubmit, validation, removal from the queue, and complete status history with the correct transitions and actors.
- Additional manual flows passed: rejection requires a reason; transfer changes `current_division_id` while retaining `pending_validation`; transfer history is recorded; and the target-division Supervisor scope is enforced.
- Visual verification passed at desktop 1440×900 and mobile 390×844 without primary horizontal overflow. The browser console showed no primary application errors, CORS errors, or HTTP 500 responses. Refresh preserved both data and the authenticated session.
- `composer install --no-interaction --prefer-dist` timed out after 120 seconds during the local Composer process, as in Phase 4. No dependency changed; the locked existing vendor tree ran every Laravel command successfully. No PHP static-analysis package/script is configured in this repository.

## Phase 6

Next scope is IT Lead triage, final priority, SLA policy selection (not SLA calculation), PIC assignment/transfer, analysis/RCA, and planning with optimistic concurrency. It must retain the ownership, attachment, history, and row-lock invariants established here.
