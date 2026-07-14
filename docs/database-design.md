# Database Design

## Conventions

- MySQL 8+, InnoDB, `utf8mb4`, timestamps in UTC, display timezone `Asia/Jakarta`.
- Internal primary keys: `BIGINT UNSIGNED`; public identifiers dapat memakai ULID/UUID. Nomor tiket (`IT-YYYY-NNNNNN`) terpisah dan unique.
- Foreign key wajib, soft delete hanya untuk master data yang perlu dinonaktifkan; transactional/audit records tidak dihapus.
- Enum domain direpresentasikan sebagai PHP backed enums dengan `VARCHAR` columns dan validation/check where practical. Hindari MySQL native `ENUM` agar migration status lebih aman.
- Sensitive audit payload harus direduksi/redacted. Attachment disimpan private; database hanya metadata/key.

## Core identity and organization

| Tabel                       | Kolom utama                                                                                                                                                    | Relasi/catatan                                                               |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| `divisions`                 | id, code unique, name, parent_id nullable, supervisor_user_id nullable, is_active, timestamps                                                                  | Hierarki divisi; self-reference                                              |
| `users`                     | id, public_id, employee_no unique nullable, name, email unique, email_verified_at, password, division_id, is_active, last_login_at, remember_token, timestamps | Sanctum-compatible; password nullable hanya bila SSO diputuskan kemudian     |
| `roles`                     | id, code unique, name, description, is_system                                                                                                                  | Delapan role canonical                                                       |
| `permissions`               | id, code unique, name, module                                                                                                                                  | Permission granular                                                          |
| `role_user`                 | user_id, role_id, assigned_by, assigned_at                                                                                                                     | Mendukung multi-role; bila bisnis memilih single-role, beri unique `user_id` |
| `permission_role`           | permission_id, role_id                                                                                                                                         | Default grant                                                                |
| `user_permission_overrides` | user_id, permission_id, effect (`allow`,`deny`), reason, expires_at                                                                                            | Opsional; hindari pemakaian luas                                             |
| `applications`              | id, code unique, name, description, owner_division_id, owner_user_id nullable, criticality, is_active                                                          | Master aplikasi                                                              |
| `application_members`       | application_id, user_id, responsibility                                                                                                                        | Ownership/support scope                                                      |

Role codes: `requester`, `supervisor`, `it_lead`, `pic`, `qa`, `manager`, `executive`, `admin`.

## Ticket and collaboration

| Tabel                     | Kolom utama                                                                                                                                                                                                                                                                                                                                                                    | Relasi/catatan                                                                         |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------- |
| `tickets`                 | id, public_id, ticket_no unique, title, description, category, priority nullable, status, requester_id, requester_division_id, supervisor_id nullable, application_id, current_pic_id nullable, current_qa_id nullable, sla_policy_id nullable, due_at nullable, sla_breached_at nullable, resolved_at nullable, closed_at nullable, version default 1, created_at, updated_at | Aggregate root                                                                         |
| `ticket_assignments`      | id, ticket_id, assignment_type (`pic`,`qa`,`approver`,`uat_owner`), user_id, assigned_by, assigned_at, ended_at, note                                                                                                                                                                                                                                                          | History assignment; current fields pada ticket untuk query cepat                       |
| `ticket_status_histories` | id, ticket_id, from_status nullable, to_status, transition, actor_id nullable, reason_code nullable, comment nullable, metadata json nullable, created_at                                                                                                                                                                                                                      | Append-only workflow history                                                           |
| `ticket_activities`       | id, ticket_id, actor_id nullable, type, visibility, body, metadata json nullable, created_at, updated_at nullable                                                                                                                                                                                                                                                              | Comment/worklog/system activity; visibility `requester`,`internal`,`executive_summary` |
| `ticket_watchers`         | ticket_id, user_id, created_at                                                                                                                                                                                                                                                                                                                                                 | Subscription                                                                           |
| `ticket_tags`             | id, name unique, color nullable                                                                                                                                                                                                                                                                                                                                                | Master tag                                                                             |
| `ticket_tag`              | ticket_id, tag_id                                                                                                                                                                                                                                                                                                                                                              | Many-to-many                                                                           |
| `attachments`             | id, public_id, attachable_type, attachable_id, uploaded_by, disk, path, original_name, mime_type, size_bytes, checksum, visibility, scan_status, created_at                                                                                                                                                                                                                    | Polymorphic private file metadata                                                      |
| `ticket_links`            | id, source_ticket_id, target_ticket_id, relation_type                                                                                                                                                                                                                                                                                                                          | duplicate/blocks/blocked_by/relates_to/parent/child                                    |

Ticket category values: `incident`, `service_request`, `change`, `problem`. Jika “CRM” juga akan menangani customer/sales entity, itu adalah bounded context terpisah yang belum tampak di prototype dan tidak boleh diasumsikan dalam fase ITSM.

## Workflow statuses and transitions

Canonical primary statuses:

```text
new
pending_validation
validated
triage
assigned
analysis
planning
development
internal_testing
qa_testing
uat
approval
ready_to_deploy
deployed
monitoring
closed
need_revision
rejected
reopened
cancelled
transferred
waiting_user
waiting_external_party
```

`over_sla` tidak menjadi status; ia ditentukan dari `sla_breached_at`/clock. `transferred` perlu keputusan: bila transfer adalah event sementara, lebih baik tetap menyimpan current actionable status dan mencatat transfer sebagai transition/history, bukan terminal state.

Transition minimum yang diizinkan harus dikonfigurasi dalam code dan diuji, bukan diedit bebas dari UI:

| Dari               | Ke utama                                                    | Actor umum                       |
| ------------------ | ----------------------------------------------------------- | -------------------------------- |
| new                | pending_validation, cancelled                               | Requester/system                 |
| pending_validation | validated, need_revision, rejected, cancelled               | Supervisor                       |
| need_revision      | pending_validation, cancelled                               | Requester                        |
| validated          | triage                                                      | IT Lead/system                   |
| triage             | assigned, transferred, rejected                             | IT Lead                          |
| assigned           | analysis, transferred                                       | PIC/IT Lead                      |
| analysis           | planning, waiting_user, waiting_external_party, transferred | PIC                              |
| planning           | development, need_revision, waiting_external_party          | PIC/IT Lead                      |
| development        | internal_testing, waiting_external_party                    | PIC                              |
| internal_testing   | qa_testing, need_revision                                   | PIC/internal tester              |
| qa_testing         | uat, need_revision                                          | QA                               |
| uat                | approval, need_revision                                     | Requester/UAT owner              |
| approval           | ready_to_deploy, need_revision, rejected                    | Manager/Approver                 |
| ready_to_deploy    | deployed, cancelled                                         | IT Lead/deployer                 |
| deployed           | monitoring, need_revision                                   | IT Lead/deployer                 |
| monitoring         | closed, reopened                                            | PIC/IT Lead/Requester per policy |
| closed             | reopened                                                    | Authorized requester/IT Lead     |

Waiting states harus menyimpan `resume_status` pada history/transition metadata atau tabel workflow pause sehingga kembali ke tahap sebelumnya dengan deterministik.

## Analysis, testing, approval, deployment

| Tabel                    | Kolom utama                                                                                                                                                                                     | Relasi/catatan                                   |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| `root_cause_analyses`    | id, ticket_id, author_id, problem_statement, immediate_cause, root_cause, contributing_factors, impact, corrective_action, preventive_action, analysis_method, status, submitted_at, timestamps | Versioning dapat ditambah jika review dibutuhkan |
| `ticket_plans`           | id, ticket_id, owner_id, scope, solution, estimate_minutes, risk_level, risks, dependencies, target_start_at, target_end_at, rollback_outline, timestamps                                       | Planning record                                  |
| `test_runs`              | id, ticket_id, type (`internal`,`qa`,`uat`), environment, status, executed_by, started_at, completed_at, summary, recommendation, timestamps                                                    | Satu ticket dapat banyak run/retest              |
| `test_cases`             | id, test_run_id, sequence, title, steps, expected_result, actual_result, result (`pending`,`pass`,`fail`,`skip`,`blocked`), notes                                                               | Snapshot test case pada run                      |
| `defects`                | id, ticket_id, test_run_id, defect_no, title, description, severity, status, reported_by, assigned_to, resolved_at, retest_result, timestamps                                                   | Defect lifecycle                                 |
| `approvals`              | id, ticket_id, stage, sequence, approver_id, delegated_from_id nullable, decision (`pending`,`approved`,`rejected`,`revision`), comment, decided_at, expires_at, timestamps                     | Mendukung multi-level bila diperlukan            |
| `deployments`            | id, ticket_id, environment, version, release_reference, planned_at, started_at, completed_at, status, deployed_by, plan, rollback_plan, result_notes, timestamps                                | Tidak menyimpan credential                       |
| `deployment_check_items` | id, deployment_id, label, is_required, completed_by, completed_at, evidence_attachment_id nullable                                                                                              | Checklist/evidence                               |
| `monitoring_records`     | id, ticket_id, deployment_id, owner_id, started_at, planned_end_at, ended_at, result, metrics_summary, incident_found, notes, timestamps                                                        | Post-deploy monitoring                           |
| `closure_records`        | id, ticket_id unique, resolution_code, resolution_summary, closed_by, requester_confirmed_by nullable, requester_confirmed_at nullable, knowledge_article_id nullable, closed_at                | Closing evidence                                 |

## SLA and escalation

| Tabel                | Kolom utama                                                                                                                                                                               | Relasi/catatan                               |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| `business_calendars` | id, name, timezone, is_default                                                                                                                                                            | Calendar definition                          |
| `business_hours`     | id, calendar_id, weekday, start_time, end_time, is_working_day                                                                                                                            | Dapat memiliki lebih dari satu interval/hari |
| `holidays`           | id, calendar_id, holiday_date, name                                                                                                                                                       | Unique calendar/date                         |
| `sla_policies`       | id, name, category nullable, priority, application_id nullable, division_id nullable, response_minutes nullable, resolution_minutes, calendar_id, is_active, effective_from, effective_to | Specificity/order rule harus deterministik   |
| `sla_clocks`         | id, ticket_id, metric, policy_id, started_at, due_at, paused_at nullable, paused_seconds, completed_at nullable, breached_at nullable, status                                             | Clock instance/snapshot policy               |
| `sla_clock_pauses`   | id, sla_clock_id, reason, status, started_at, ended_at nullable, initiated_by nullable                                                                                                    | Audit pause intervals                        |
| `escalation_rules`   | id, sla_policy_id nullable, priority nullable, threshold_type, threshold_value, level, is_active                                                                                          | Warning %/minutes/breach offsets             |
| `escalation_targets` | id, rule_id, target_type (`role`,`user`,`ticket_relation`), target_value, channel                                                                                                         | Recipients                                   |
| `escalation_events`  | id, ticket_id, sla_clock_id, rule_id, triggered_at, status, payload json, processed_at                                                                                                    | Idempotency unique clock/rule                |

Policy matching order yang direkomendasikan: application+category+priority, category+priority, lalu priority default. Simpan policy/target snapshot pada clock agar perubahan admin tidak mengubah deadline tiket berjalan secara retroaktif.

## Notifications, audit, and Knowledge Base

| Tabel                        | Kolom utama                                                                                                                                                                                    | Relasi/catatan                                    |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| `notifications`              | Laravel standard UUID id, type, notifiable_type/id, data json, read_at, created_at                                                                                                             | In-app notification                               |
| `notification_deliveries`    | id, notification_id, channel, recipient, status, provider_message_id nullable, attempts, sent_at, failed_at, error_code nullable                                                               | Delivery tracking tanpa sensitive body berlebihan |
| `notification_preferences`   | id, user_id, event_code, channel, enabled                                                                                                                                                      | Unique user/event/channel                         |
| `audit_logs`                 | id/public_id, actor_id nullable, event, auditable_type/id nullable, request_id, ip_address, user_agent, old_values json nullable, new_values json nullable, metadata json nullable, created_at | Append-only, redacted                             |
| `knowledge_categories`       | id, parent_id nullable, name, slug unique, is_active                                                                                                                                           | KB taxonomy                                       |
| `knowledge_articles`         | id, public_id, title, slug unique, summary, content, category_id, status, visibility, author_id, reviewer_id nullable, published_at, version, timestamps                                       | draft/in_review/published/archived                |
| `knowledge_article_versions` | id, article_id, version, title, summary, content, edited_by, created_at                                                                                                                        | Immutable revisions                               |
| `knowledge_article_ticket`   | article_id, ticket_id, relation_type                                                                                                                                                           | source/solution/related                           |
| `knowledge_feedback`         | id, article_id, user_id nullable, helpful, comment nullable, created_at                                                                                                                        | Optional later phase                              |

## Indexes and constraints

- `tickets`: unique ticket_no/public_id; indexes `(status, priority)`, `(requester_id, created_at)`, `(current_pic_id, status)`, `(application_id, status)`, `(requester_division_id, status)`, `due_at`, `sla_breached_at`.
- Histories/activities: `(ticket_id, created_at)`; assignments `(user_id, ended_at, assignment_type)`.
- Approvals: `(approver_id, decision, created_at)`; test runs `(ticket_id, type, created_at)`.
- Notifications: `(notifiable_type, notifiable_id, read_at, created_at)`.
- Audit: `(auditable_type, auditable_id, created_at)`, `(actor_id, created_at)`, `request_id`.
- Unique active assignment constraints may require transaction/application enforcement because MySQL partial indexes are unavailable.
- Validate no self-link/duplicate ticket link; attachment size nonnegative; sequence positive; completion times not before start.

## Data retention and deletion

User/division/application dinonaktifkan, bukan dihapus bila sudah direferensikan. Ticket, status history, approval, deployment, SLA event, dan audit log memiliki retention policy yang harus ditentukan oleh compliance. File attachment perlu retention/virus scan/legal hold policy. Personal data export/erasure harus mempertimbangkan kewajiban audit; anonymization lebih aman daripada cascade delete.
