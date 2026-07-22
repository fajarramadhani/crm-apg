# Phase 14: Notification Center, SLA Escalation, and Operational Alerts

## 1. Notification Architecture
Phase 14 implements a robust internal notification system and automated SLA escalations. When key events occur, the system resolves recipients, filters based on roles and preferences, checks for duplicates, sends in-app notifications, and logs status in the delivery logs.

## 2. Notification Types and Severity
All notifications are validated against the server-side `NotificationType` enum:
- `ticket_submitted`
- `supervisor_validation_required`
- `triage_required`
- `pic_assigned`
- `qa_assignment_required`
- `qa_failed`
- `qa_passed`
- `uat_assignment_required`
- `uat_rejected`
- `uat_approved`
- `business_approval_required`
- `technical_approval_required`
- `approval_rejected`
- `release_ready`
- `deployment_scheduled`
- `deployment_failed`
- `rollback_required`
- `monitoring_issue_detected`
- `requester_confirmation_required`
- `requester_rejected`
- `ticket_closed`
- `sla_approaching`
- `sla_breached`
- `ticket_inactive`
- `ticket_escalated`

Severities are managed server-side:
- `info`
- `success`
- `warning`
- `critical`

## 3. Recipient Resolver and Scope Constraints
The `TicketNotificationRecipientResolver` resolves recipients dynamically:
- **Requester:** Receives non-technical updates (e.g. `ticket_submitted`, `ticket_rejected`, `uat_assignment_required`, `requester_confirmation_required`, `ticket_closed`).
- **Supervisor:** Scoped to division supervisors.
- **PIC (IT Member):** Receives workspace alerts and assignments.
- **IT Lead:** Receives triage and system-level alerts.
- **Manager:** Receives critical business alerts scoped to their division.
- **Executive:** Redacted from direct ticket-level notification alerts. Only views aggregate summary dashboards.
- **Admin:** Does not automatically receive notifications unless they are the direct target.

Inactive users are automatically excluded, and duplicate user IDs are filtered out.

## 4. Deduplication & Delivery Log
- **Deduplication:** The `NotificationDeduplicationService` generates keys based on `type`, `ticket_id`, `recipient_id`, and optional `reference_id` (e.g. `sla_approaching:response:ticket-123:pct-75`). It prevents double notifications during retries or multiple cron runs.
- **Delivery Log:** The `notification_delivery_logs` table logs:
  - `notification_id`
  - `user_id`
  - `notification_type`
  - `status` (`pending`, `delivered`, `skipped`, `failed`)
  - `delivery_channel` (`database` only)
  - `deduplication_key`
  - `failure_reason`
  - `delivered_at`

## 5. SLA Escalation & Working Time Calculation
- SLA alerts check the `WorkingTimeCalculator` using the existing calendars, weekends, and holidays.
- Response and resolution SLAs are tracked and triggered separately.
- Inactive tickets are scanned using `tickets:scan-inactivity` command based on the latest activity timestamp.
- Blocked/closed/resolved states are excluded from the SLA scanner.

## 6. Console Commands & Scheduler
Commands run every 15 minutes using `withoutOverlapping()`:
- `php artisan tickets:scan-sla-alerts`
- `php artisan tickets:scan-inactivity`

## 7. API Endpoints
### Notifications
- `GET /api/v1/notifications` (paginated, max 100 per page)
- `GET /api/v1/notifications/unread-count`
- `GET /api/v1/notifications/{notification}`
- `POST /api/v1/notifications/{notification}/read`
- `POST /api/v1/notifications/{notification}/unread`
- `POST /api/v1/notifications/read-all`
- `POST /api/v1/notifications/{notification}/archive`
- `POST /api/v1/notifications/archive-read`

### Preferences
- `GET /api/v1/notification-preferences`
- `PUT /api/v1/notification-preferences/{notificationType}` (enforces max 30 days mute, blocks critical notification silencing)

### Role-Specific Alerts
- `GET /api/v1/it-lead/alerts`
- `GET /api/v1/it-lead/alerts/sla`
- `GET /api/v1/it-lead/alerts/inactivity`
- `GET /api/v1/manager/alerts` (scoped by division)
- `GET /api/v1/reports/executive/alerts/summary` (aggregate only, no ticket-level details)

## 8. Verification Results
- **Backend Tests:** 156 tests, 984 assertions. All passing.
- **Frontend Build:** Successfully built under Vite. Prettier and TypeScript checks are green.
