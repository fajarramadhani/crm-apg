# Phase 15 Knowledge Base and Resolution Reuse

## Delivered scope

Phase 15 is a complete authenticated Knowledge Base vertical slice. It adds server-authoritative article lifecycle and visibility, content/version persistence, search and taxonomy, ticket resolution reuse, recommendations, feedback, audit, notifications, and permission-gated React workflows. Phase 16 is explicitly out of scope.

## Architecture

Laravel controllers remain thin and delegate to focused article, search, review, version, redaction, ticket-link, recommendation, feedback, and notification services. PHP backed enums define lifecycle and visibility. Eloquent models/resources shape authenticated projections, gates use the existing role permission registry, domain events connect workflow transitions to the Phase 14 in-app notification infrastructure, and activity logs retain article-level audit history.

React uses a typed `knowledgeBaseApi` client and dedicated list, detail, form, review, tag-management, and ticket-panel components. Backend authorization remains authoritative; frontend permission guards only control navigation and available actions.

## Persistence

The repository currently contains **22 migrations**. One Phase 15 migration creates eight tables:

| Table                             | Purpose                                                  |
| --------------------------------- | -------------------------------------------------------- |
| `knowledge_base_number_sequences` | Row-locked annual `KB-YYYY-NNNNNN` numbering.            |
| `knowledge_base_articles`         | Current article projection and lifecycle/counter fields. |
| `knowledge_base_article_versions` | Immutable numbered snapshots.                            |
| `knowledge_base_tags`             | Unique, deactivatable taxonomy tags.                     |
| `knowledge_base_article_tag`      | Article/tag many-to-many relation.                       |
| `knowledge_base_article_ticket`   | Typed, unique article/ticket resolution links.           |
| `knowledge_base_feedback`         | One updatable helpful vote per user/article.             |
| `knowledge_base_activity_logs`    | Append-only actor/action/transition metadata.            |

Articles optionally reuse existing `ticket_categories` and `applications`; Phase 15 does not introduce a separate category tree.

## Lifecycle and versions

```text
draft -> in_review -> published -> archived -> draft
             |             |
             v             v
          rejected     revision draft
             |
             +-----> in_review
```

- Creation always persists a draft and immutable version 1. A client may request immediate submission after creation.
- `draft` and `rejected` may be submitted to `in_review`; invalid or duplicate transitions return `409`.
- Only an IT Lead with review/publish permissions may decide review. Rejection requires a reason; publication may include a change summary.
- Authors cannot publish or reject their own article, including an article authored by an IT Lead. Both self-decisions return `409`.
- Only `published` may be archived; only `archived` may be restored, and restore returns it to `draft`.
- A published article is never edited in place as published. The edit requires a change summary, increments `current_version`, snapshots the revision, and returns the article to `draft`.
- Restoring an old snapshot copies it into a newly numbered draft version. Existing versions remain immutable.
- Draft edits update the current projection. Submission adds a version only when title, summary, content, visibility, category, or application differs from the latest snapshot.

## Visibility and permissions

Visibility values are:

| Visibility          | Readers                                                                       |
| ------------------- | ----------------------------------------------------------------------------- |
| `all_authenticated` | Any authenticated active role with `knowledge_base.view`.                     |
| `business_internal` | Same authenticated role set; retained as an explicit business classification. |
| `it_internal`       | Only roles granted `knowledge_base.view_it_internal`: IT Lead, PIC, and QA.   |

Normal readers see only published articles. Authors can retrieve their own unpublished articles; IT Lead reviewers can retrieve/filter all unpublished articles allowed by visibility. Another author receives `404` for unpublished detail, while unauthorized IT-internal detail returns `403`. Published recommendations and ticket-linked lists apply the same visibility filter before returning data. Ticket relation details in article resources are emitted only to IT-internal viewers; rejection reasons are limited to the author and reviewer.

Concrete role grants are documented in `docs/role-permission-matrix.md`. Highlights:

- Requester and Manager read and submit feedback; Executive is read-only.
- Supervisor reads, submits feedback, and links ticket articles.
- PIC creates/edits/submits own articles, reads IT-internal articles, links tickets, creates redacted drafts from eligible tickets, and submits feedback.
- QA creates/edits/submits own articles, reads IT-internal articles, and submits feedback.
- IT Lead has full article authoring, review, publish, archive, restore-version, link, activity, and feedback capabilities, subject to no self-approval/rejection.
- Admin reads, manages tag activation, and views activity. Admin is not a workflow superuser.

`knowledge_base.submit_review` is present in the role registry for IT Lead, PIC, and QA. The implemented submit-review action currently gates on `knowledge_base.edit_own`/`knowledge_base.edit_any` plus ownership and lifecycle rules rather than checking that permission code independently; this distinction is retained in the permission matrix instead of overstating middleware enforcement.

## Sanitization and redaction

The server strips HTML from title and summary. Article content removes `script`, `iframe`, `object`, and `embed` blocks; inline `on*` event handlers; dangerous `javascript:` and `data:` link/source protocols; bearer/API-key/secret/password/token values; private-key blocks; and internal storage paths.

Ticket-derived drafts apply additional email and phone redaction. They combine available current RCA, current solution plan, closure resolution/business outcome, and application context only after the ticket has at least one eligible resolution source: closure state/record, current analysis, or current solution plan. The new article is always a draft and is automatically linked to the ticket as `source`. Authors must still review generated text before submission and publication.

## Search and recommendations

Article search covers number, title, summary, content, tags, existing ticket category, and application. Filters cover status, visibility, category, application, author, tag, and publication date; sorting is allow-listed and page size is capped at 100.

Ticket recommendations query only published, caller-visible articles and score each candidate deterministically:

| Signal                                                   |                    Weight |
| -------------------------------------------------------- | ------------------------: |
| Same application                                         |                       +30 |
| Same ticket category                                     |                       +25 |
| Each tag exactly matching a normalized ticket-title word |                       +10 |
| Each ticket-title/article-title word overlap             |                        +5 |
| Each ticket-title/article-summary word overlap           |                        +2 |
| Helpful ratio                                            | Integer bonus from 0 to 5 |

Results sort by descending composed score/publication key and return at most ten. The ticket panel excludes already linked results and displays at most three recommendations, while the API retains the top-ten contract.

## Feedback, audit, and notifications

Feedback is accepted only for published articles. `(article_id, user_id)` is unique, so another submission changes the existing vote/comment instead of duplicating it. Comments are stripped of HTML and capped at 500 characters. Helpful/not-helpful counters are recalculated transactionally; the ratio is rounded to two decimals and is `null` when there are no votes.

Activity logs cover create/update/revision, lifecycle transitions, version restore, ticket link/unlink, and feedback. Only IT Lead and Admin have activity access; metadata is permission-shaped.

Submission emits an in-app notification to active IT Leads. Publish, reject, and archive outcomes notify the active article author. Notifications honor Phase 14 in-app preferences and mute windows, default to enabled, deduplicate by event/article/recipient/current version for 1,440 minutes, and record delivered or skipped delivery logs.

## API and frontend

Phase 15 contributes **25 authenticated API routes** under `/api/v1/knowledge-base`, `/api/v1/knowledge-base-tags`, and `/api/v1/tickets/{ticket}/knowledge-base`. The application currently has **268 API routes total**. The complete endpoint table is in `docs/api-contract.md`.

Frontend routes and workflows:

| Route                           | Workflow                                                                                            |
| ------------------------------- | --------------------------------------------------------------------------------------------------- |
| `/knowledge-base`               | Published reader search, visibility/tag filters, pagination, cards, related articles, and feedback. |
| `/knowledge-base/manage`        | Author/reviewer status views and review-queue shortcut.                                             |
| `/knowledge-base/new`           | Draft creation or save-and-submit with category/application/tag selection.                          |
| `/knowledge-base/:id/edit`      | Owned/any-authorized edit; published revisions require a change summary.                            |
| `/knowledge-base/:id/review`    | IT Lead article/version/activity review and publish/reject decision.                                |
| `/knowledge-base/:slug`         | Detail, lifecycle actions, related articles, visible ticket relations, and feedback.                |
| `/settings/knowledge-base/tags` | Admin create, rename, activate/deactivate tag workflow.                                             |
| Ticket detail                   | `Knowledge & Previous Resolutions` panel for linked and recommended articles.                       |

The pages include loading, empty, retry/error, validation, toast, responsive grid, and mobile action-stack states. Link/unlink and ticket-draft APIs exist in the typed client; the currently mounted ticket panel is read-oriented and displays links/recommendations.

## Verification status

Executed for this documentation update:

- `php artisan test`: **182 tests passed, 1,145 assertions**.
- Route inventory: **25 Knowledge Base routes; 268 API routes total**.
- `corepack pnpm typecheck`: passed.
- `corepack pnpm format:check`: passed.
- `corepack pnpm build`: passed; 648 modules transformed.
- Production output included a 945.85 kB minified main JavaScript chunk (254.53 kB gzip), so Vite emitted its chunk-over-500-kB warning. Code splitting remains a performance follow-up.
- Stateful Sanctum HTTP E2E: **12 checks passed** against an actual Laravel server and disposable SQLite database. Coverage included unauthenticated access, role denial, draft creation, unpublished visibility, review submission, duplicate-transition conflict, publication, published search/detail, feedback, Executive denial, activity access, stored-XSS sanitization, cookies, and request IDs.
- Chrome browser verification: **3 tests passed in 5.7 seconds**. Desktop PIC at 1440x900 covered login, published Knowledge Base, author management, overflow, console/page errors, HTTP 500 detection, and logout. Mobile Requester at 390x844 covered login, mobile navigation, published Knowledge Base, overflow, runtime errors, and logout. Desktop Admin at 1440x900 covered login and tag management without unauthorized notification requests.

Laravel feature tests cover role visibility, ownership, lifecycle conflicts, self-approval/rejection, numbering/slugs, immutable versions/restore, search/filter/sort/pagination, tag deactivation, stored-XSS sanitization, ticket links and draft redaction, deterministic recommendations, feedback counts, notifications/deduplication, activity authorization, and request IDs.

The browser run identified and fixed one frontend authorization-noise regression: the notification bell previously requested unread data for Admin even though Admin does not have `notification.view_own`. The layout now renders the bell only when that backend-issued permission is present. Runtime scripts, SQLite data, logs, and Playwright artifacts were temporary and removed after verification.

## Out of scope

Phase 16 and any Phase 16 capabilities are explicitly out of scope. Phase 15 also makes no claim of public/anonymous knowledge access, external search indexing, article attachment support, exhaustive browser sign-off for every role/page combination, or resolution of the frontend chunk-size warning.
