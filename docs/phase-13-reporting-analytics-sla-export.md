# Phase 13: Reporting, Analytics Dashboard, SLA Compliance, and Export

## Overview
Phase 13 delivers comprehensive analytics and operational reporting for the APG CRM system. It surfaces ticket volume, SLA compliance, quality metrics, Phase durations, and PIC performance via robust backend endpoints and frontend dashboards. It strictly adheres to data isolation and Role-Based Access Control (RBAC).

## Features Implemented
1. **Report Filter Validation**: `ReportFilterRequest` validates extensive parameters including `date_from`, `date_to` (max 366 days), `division_id`, `application_id`, `category_id`, `priority`, `status`, and `pic_id`.
2. **SLA Calculation**: Calculates both Response SLA (triage initialized against response_due_at) and Resolution SLA (closed_at against resolution_due_at).
3. **Ticket Volume Analytics**: Measures created, completed, closed, and reopened tickets, as well as backlog sizes and aging.
4. **Workflow Duration Analysis**: Evaluates time spent in each phase leveraging `ticket_status_histories`.
5. **PIC Performance**: Measures assignments, worklogs, and defect generation specific to individual PICs.
6. **Quality & Deployment**: Summarizes QA pass rates, UAT findings, rollback rates, defect leakage, and deployment success rates.
7. **Role-Based API Endpoints**: Dedicated routes for Managers, IT Leads, Supervisors, PICs, and Executives to prevent cross-scope leakage.
8. **Native CSV Export**: A secure, streaming CSV exporter (capable of handling up to 50k rows) with Formula Injection mitigation (prepending `'` to cells starting with `=`, `+`, `-`, `@`).
9. **Universal Frontend Dashboard**: `AnalyticsDashboard.tsx` serves as a dynamic, reusable frontend component adapted to the active role's scopes and abilities.

## Testing & Verification
### Runtime Verification (E2E & UI)
- **Commit Runtime Fix**: `2a579702e973f019bd0dc5d23c111777751b769c` applied bugfixes found during runtime.
- **Database Column Bugfixes**: Corrected mismatch in `DeploymentReportService` (`actual_start_at`/`actual_end_at` instead of `started_at`/`ended_at`) and `PicPerformanceReportService` (`minutes_spent` instead of `duration_minutes`).
- **HTTP E2E Matrix**:
  - Manager: `summary` (200), `SLA` (200), `PIC performance` (200), `CSV export` (200)
  - IT Lead: `operational reports` (200)
  - Executive: `aggregate analytics` (200), `technical report` (403)
  - PIC: `own performance` (200), `other PIC data` (403/422)
  - Supervisor: `own division` (200), `other division` (403/422)
  - Requester: `reporting endpoint` (403)
  - Invalid date range: `report endpoint` (422)
  - XLSX/PDF unsupported: `export` (422)
- **Browser Verification**:
  - Validated across all roles (Manager, IT Lead, Executive, PIC, Supervisor) on **Desktop (1440x900)** and **Mobile (390x844)** using standard React/Vite stack without Vue inconsistencies.
  - Successfully verified loading states, empty states, chart responsiveness, text summaries, filter scrolling, table overflows, and UI interactions.
  - Zero console errors and zero CORS issues encountered during HTTP requests.

### Final Regression Suite
- **API Route Count**: 225 active routes mapped.
- **PHPUnit Tests**: `139 tests`, `939 assertions`, `0 failures`.
- **Pint & Typecheck**: Fully compliant with `vendor/bin/pint --test` and frontend `pnpm typecheck` and `pnpm format:check` with `0 failures`.
- **Frontend Build**: Vite production build succeeded cleanly.

## Scope Limits
As dictated by Phase 13 requirements, no new SLA engines or database polling tasks were introduced; all metrics depend on the established Phase 1-12 workflows and recorded timelines. PDF and XLSX exports return a 422 standard format rejection. 

## Next Phase Target
Phase 14 (if scoped) is prepared to handle final production hardening, caching layers, or subsequent CRM enhancements.
