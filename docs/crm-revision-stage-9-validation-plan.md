# CRM Revision Stage 9 Validation Plan

**Date:** 2026-07-28  
**Branch:** `feature/crm-simplified-dynamic-workflow`  
**Baseline commit:** `273aeb6a00abba96983aeb9a414ab7115bca9afb`

## 1. Working Tree

The working tree contains the uncommitted Stage 2 through Stage 8 implementation: 29 tracked files are modified and 83 files are currently untracked. These changes are intentionally preserved. No reset, clean, stash, restore, checkout, commit, push, merge, or deployment will be performed during Stage 9.

All Stage 2 through Stage 7 migrations are reported as already run on the shared local SQLite database. Stage 9 tests must not mutate that shared dataset except through deliberately isolated disposable environments.

## 2. Pending Items from Stage 8

Stage 8 ended as `PASS WITH PENDING`. The remaining gates are:

1. MySQL/MariaDB migration and rollback rehearsal on an engine equivalent to staging.
2. True parallel assignment, transition, approval, activation, and reassignment tests on InnoDB.
3. Browser verification in Chrome and Edge across desktop, laptop, tablet, mobile, and small-mobile viewports.
4. Manual/automated verification of the Supervisor self-approval warning.
5. Representative 100-user/1,000-ticket performance dataset and measured endpoint/query results.
6. Fix only performance issues demonstrated by measurement, including resource counts, assignment workload, snapshot hydration, audit pagination, recipient resolution, and page-size limits.
7. Decide and implement requester ticket-creation idempotency if duplicate-submit risk is not adequately mitigated.
8. Decide whether minimum approval configuration must become editable for staging.
9. Create role-mapping review and release-candidate evidence.

## 3. Available Environment

- Operating system: Windows.
- PHP/Laravel and Composer dependencies are installed in the repository.
- Node.js `v24.18.0` and npm `11.16.0` are available.
- Current application database: SQLite local/testing.
- Docker CLI: not available.
- MySQL CLI/server: not found.
- MariaDB CLI/server: not found.
- Windows MySQL/MariaDB service: not found.
- Project browser-test dependency: none; dependencies and lockfiles will not be changed.

## 4. Database Engines

### Local

The shared local database is SQLite and contains existing legacy test data. It will be treated as read-only for invariance checks.

### Staging-Equivalent

No MySQL/MariaDB engine is currently discoverable through `PATH`, Windows services, or Docker. The next step is to search standard local installation locations and determine whether a portable disposable server can be used without changing project dependencies or system configuration.

SQLite results will not be presented as MySQL concurrency or migration evidence.

## 5. Browsers

Available executables:

- Chrome: `C:\Program Files\Google\Chrome\Application\chrome.exe`
- Edge: `C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe`
- Firefox: not found.

Browser validation will use isolated browser profiles, staging-only fixture users, sanitized screenshots/logs, and either browser-native headless mode or Chrome DevTools Protocol driven by a temporary script outside the application dependency graph.

## 6. Concurrency Test Plan

The required tests will use two or more independent PHP/HTTP processes against a disposable InnoDB database:

- competing primary assignments;
- duplicate submit-for-approval;
- duplicate Supervisor approval;
- competing workflow activation;
- competing reassignment.

Each scenario will synchronize process start, record response/status/exception, then assert the final database invariant and unique history/notification counts. Sequential SQLite tests are only regression support and will not satisfy this gate.

## 7. Performance Test Plan

Create a disposable dataset with at least:

- 100 staging-only users;
- 1,000 tickets;
- one to three assignments per ticket;
- five to twenty histories per ticket;
- zero to ten attachment metadata records per ticket;
- multiple workflow versions.

Measure Supervisor/PIC dashboards, lists, details, assignment candidates, Admin Workflow endpoints, audit timeline, and notification resolution. Record response time, query count, memory delta/peak, serialized payload size, SQL plans, and index use. Apply changes only when measurements prove a bottleneck, then rerun the same benchmark and full regression suite.

## 8. Browser and Accessibility Plan

Use Chrome and Edge at 1440x900, 1366x768, 768x1024, 390x844, and 360x800. Cover login, requester flow, Supervisor/PIC workspaces, Admin Workflow, legacy tickets, self-approval warning, errors, loading/empty states, long text/files, and multiple secondary PICs.

Capture console errors, failed/looping requests, viewport overflow, modal scrolling, keyboard focus/navigation, accessible names, labels/errors, table headers, status semantics, and external-link behavior. Critical accessibility blockers will be repaired before release-candidate status is considered.

## 9. Risks and Environment Limits

- Stage 9 cannot be marked `PASS` without a real MySQL/MariaDB rehearsal and true parallel InnoDB test.
- Browser automation must not add Playwright or alter lockfiles.
- Browser fixture data must not use production identities or notification recipients.
- Headless rendering can verify layout and behavior but does not replace final human UX review.
- Performance numbers from SQLite are not representative of MySQL query plans; they may be used only to identify framework-level query-count and payload issues.
- The shared local feature flag and production feature flag must remain OFF.

## 10. Initial Decision Gate

Proceed with all feasible Stage 9 work while actively searching for a usable disposable MySQL/MariaDB engine. If no engine is available and no authorized remote staging-equivalent connection is provided, migration and concurrency acceptance remain critical failures and the final Stage 9 status must be `FAIL`, regardless of other successful checks.
