# Role Permission Matrix

## Phase 9 permissions

| Capability | QA | PIC | IT Lead | Requester/Executive |
| --- | --- | --- | --- | --- |
| Assign QA / Workload View | No | No | Yes (`ticket.qa.assign`, `ticket.qa_workload.view`) | No |
| Start Testing / Retesting | Yes (`ticket.qa.start`) | No | No | No |
| Manage Test Cases / Runs | Yes (`ticket.qa_test_case.manage`, `ticket.qa_test_run.manage`) | No | No | No |
| Report & Verify QA Defects | Yes (`ticket.qa_defect.manage`, `ticket.qa_defect.verify`) | No | No | No |
| Upload QA Evidence | Yes (`ticket.assigned.view`) | No | No | No |
| PIC Defect Start & Resolution | No | Yes (`ticket.qa_defect.manage`) | No | No |
| Submit Ticket for Retesting | No | Yes (`ticket.qa_retest.submit`) | No | No |
| View Defect Comments/Files | Yes | Yes | Yes | Redacted/Not Visible |

Added permissions: `ticket.qa.assign`, `ticket.qa_assignment_queue.view`, `ticket.qa_options.view`, `ticket.qa_workload.view`, `ticket.qa_test_case.view`, `ticket.qa_test_case.manage`, `ticket.qa_test_run.view`, `ticket.qa_test_run.manage`, `ticket.qa_defect.view`, `ticket.qa_defect.manage`, `ticket.qa_defect.verify`, `ticket.qa_retest.submit`.

## Phase 8 permissions

| Permission | Requester | Supervisor | IT Lead | PIC | Executive |
| --- | ---: | ---: | ---: | ---: | ---: |
| `ticket.triage_queue.view` | No | No | Yes | No | No |
| `ticket.triage.start` | No | No | Yes | No | No |
| `ticket.priority.finalize` | No | No | Yes | No | No |
| `ticket.assign` | No | No | Yes | No | No |
| `ticket.pic_options.view` | No | No | Yes | No | No |
| `ticket.workload.view` | No | No | Yes | No | No |
| `ticket.assigned.view` | No | No | No | Own active assignment | No |

Requester ownership continues after assignment but internal assignment notes/metadata are redacted. Executive has no technical triage/PIC access. Until application ownership is modeled, IT Lead scope is explicitly all `validated` and `triage` tickets.

## Phase 5 ticket permissions

| Permission | Requester | Supervisor |
| --- | --- | --- |
| `ticket.create` | Yes | No |
| `ticket.own.view` | Own only | No |
| `ticket.own.update` | Draft/revision | No |
| `ticket.own.cancel` | Before validated | No |
| `ticket.own.attachment.manage` | Own/uploader before validated | No |
| `ticket.validation_queue.view` | No | Same current division |
| `ticket.validate` | No | Scoped |
| `ticket.request_revision` | No | Scoped |
| `ticket.reject` | No | Scoped |
| `ticket.transfer` | No | Scoped |
| `ticket.division.view` | No | Same current division |

Laravel `TicketPolicy` is authoritative for ownership, mutable states, attachment parent access, and Supervisor division scope. Admin is not implicitly granted Supervisor actions.

## Phase 4 additions

| Permission | Requester | Supervisor | IT Lead | PIC | QA | Manager | Executive | Admin |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `master_data.view` | Yes | Yes | Yes | Yes | Yes | Yes | Yes (read-only) | Yes |
| `master_data.manage` | No | No | No | No | No | No | No | Yes |

Frontend route guards improve navigation, but the Laravel permission middleware is authoritative. Executive receives only active form-reference master data and cannot call Admin mutations.

## Legend and scope

- **O**: own/requested-by user
- **D**: division scope
- **A**: assigned responsibility
- **G**: global operational scope
- **S**: executive-safe summary only
- **—**: denied

Matrix adalah baseline untuk implementasi Policy dan test. Permission efektif juga bergantung pada current ticket status, division/application assignment, dan `allowed_actions` dari workflow engine. Admin mengelola konfigurasi/identity tetapi tidak otomatis menjadi approver atau pelaksana workflow; separation of duties tetap berlaku.

## Phase 3 authentication permissions

Phase 3 memakai satu `role_id` utama per user. Registry konfigurasi menjadi sumber effective permission sementara, sehingga controller tidak menyebarkan pemeriksaan role. Struktur `User -> Role`, `hasRole()`, `hasPermission()`, dan middleware dapat dikembangkan menjadi relasi multi-role pada fase berikutnya tanpa mengubah kontrak frontend.

| Role         | Dashboard permission          | Ticket scope          | Additional permission      |
| ------------ | ----------------------------- | --------------------- | -------------------------- |
| `requester`  | `dashboard.requester.view`    | `ticket.own.view`     | —                          |
| `supervisor` | `dashboard.supervisor.view`   | `ticket.division.view`| —                          |
| `it_lead`    | `dashboard.it_lead.view`      | `ticket.all.view`     | `ticket.technical.view`    |
| `pic`        | `dashboard.pic.view`          | `ticket.assigned.view`| `ticket.technical.view`    |
| `qa`         | `dashboard.qa.view`           | `ticket.assigned.view`| `ticket.technical.view`    |
| `manager`    | `dashboard.manager.view`      | `ticket.all.view`     | `ticket.technical.view`    |
| `executive`  | `dashboard.executive.view`    | —                     | `executive.aggregate.view` |
| `admin`      | `dashboard.admin.view`        | `ticket.all.view`     | `ticket.technical.view`, `admin.access` |

Executive sengaja tidak menerima `ticket.technical.view`. Endpoint dan route bisnis pada fase selanjutnya tetap wajib memakai policy/resource projection; permission frontend hanya mengatur tampilan.

## Ticket and workflow permissions

| Capability                           |       Requester |    Supervisor |    IT Lead |             PIC |            QA |    Manager |   Executive |          Admin |
| ------------------------------------ | --------------: | ------------: | ---------: | --------------: | ------------: | ---------: | ----------: | -------------: |
| View ticket summary                  |               O |             D |          G |               A |             A |          G |           S |              G |
| View full technical detail           |       O limited |     D limited |          G |               A |             A |          G |           — |      G audited |
| View internal comments/RCA/raw logs  |               — |             — |          G |               A |             A |          G |           — |      G audited |
| Create ticket                        |               O |             O |          O |               O |             O |          O |           — |              O |
| Edit draft/revision ticket           |               O |             — |          — |               — |             — |          — |           — |   Support only |
| Cancel before work starts            |               O |             D |          G |               — |             — |          G |           — |   Support only |
| Comment requester-visible            |               O |             D |          G |               A |             A |          G |           — |              G |
| Add internal work log                |               — |             — |          G |               A |             A |          G |           — |              — |
| Upload requester-visible attachment  |               O |             D |          G |               A |             A |          G |           — |              G |
| Upload internal attachment           |               — |             — |          G |               A |             A |          G |           — |      G audited |
| Validate ticket                      |               — |             D |   Override |               — |             — |          — |           — |              — |
| Request revision/reject validation   |               — |             D |   Override |               — |             — |          — |           — |              — |
| Triage/category/priority/SLA policy  |               — |             — |          G |     Recommend A |   Recommend A |   Override |           — | Configure only |
| Assign/transfer PIC                  |               — |             — |          G |               — |             — |   Override |           — |              — |
| Accept/start assigned work           |               — |             — |   Override |               A |             — |          — |           — |              — |
| Submit analysis/RCA                  |               — |             — |   Review G |               A |             — |   Review G |           — |              — |
| Submit planning/development progress |               — |             — |   Review G |               A |             — |   Review G |           — |              — |
| Internal testing                     |               — |             — |   Override |               A | A if assigned |          — |           — |              — |
| QA testing/create defect/retest      |               — |             — |     View G |           Fix A |             A |     View G |           — |              — |
| Execute UAT                          | O when assigned | D if assigned | Coordinate |         Support |       Support |       View |           — |              — |
| Approve/reject deployment            |               — |             — |  Recommend |               — |     Recommend | A/assigned |           — |              — |
| Prepare deployment                   |               — |             — |          G |               A |      Verify A |    Approve |           — |              — |
| Execute/confirm deployment           |               — |             — |          G | A if authorized |        Verify |       View |           — |              — |
| Record monitoring                    |       Confirm O |        View D |          G |               A |      Verify A |     View G |           S |              — |
| Close ticket                         |       Confirm O |    D optional |          G |     Recommend A |             — |   Override |           — |              — |
| Reopen ticket                        | O within policy |             D |          G |     Recommend A |             — |          G |           — |   Support only |
| View SLA operational detail          |               O |             D |          G |               A |             A |          G | S aggregate |              G |
| Trigger/manual escalation            |               — |     D request |          G |       A request |             — |          G |           — | Configure only |

`O limited` untuk requester berarti informasi tiket sendiri, tetapi internal security notes, infrastructure secrets, internal-only RCA/worklog, dan data user lain tetap disembunyikan.

## Administration, reporting, notification, and KB

| Capability                            |     Requester | Supervisor |       IT Lead |        PIC |             QA |        Manager |     Executive |       Admin |
| ------------------------------------- | ------------: | ---------: | ------------: | ---------: | -------------: | -------------: | ------------: | ----------: |
| Personal dashboard/report             |             O |          D | G operational |          A |              A |              G |             S |    G system |
| Export ticket data                    |     O limited |          D |             G |  A limited |      A limited |              G |             S |   G audited |
| View executive analytics              |             — |          — |   Operational |          — |     QA metrics |     Management |             S |           G |
| View notification/read own            |             O |          O |             O |          O |              O |              O |             O |           O |
| Configure own notification preference |             O |          O |             O |          O |              O |              O |             O |           O |
| Manage users/roles                    |             — |          — |             — |          — |              — |              — |             — |           G |
| Manage divisions/applications         |             — |          — |     Recommend |          — |              — |              — |             — |           G |
| Manage SLA policy/calendar/holiday    |             — |          — |     Recommend |          — |              — | Approve policy |             — | G configure |
| Manage escalation rules/templates     |             — |          — |     Recommend |          — |              — | Approve policy |             — | G configure |
| View audit log                        | Own auth only | D workflow |    G workflow | A workflow |     A workflow |              G |  S compliance |           G |
| Create KB draft                       |  O suggestion |          D |             G |          G |              G |              G |             — |           G |
| Review KB article                     |             — |   Domain D |             G |          — | QA if assigned |              G |             — |           G |
| Publish/archive KB                    |             — |          — |             G |          — |              — |              G |             — |           G |
| Read published KB                     |     G allowed |  G allowed |             G |          G |              G |              G | S/public-safe |           G |
| Manage system settings                |             — |          — |             — |          — |              — |              — |             — |           G |

## Permission codes

Backend sebaiknya memakai granular codes berikut (minimum):

```text
tickets.view_own, tickets.view_division, tickets.view_assigned, tickets.view_all
tickets.view_technical, tickets.create, tickets.update_own, tickets.cancel
tickets.comment_public, tickets.comment_internal, tickets.attach_public, tickets.attach_internal
tickets.validate, tickets.triage, tickets.assign, tickets.transfer
tickets.work, tickets.submit_rca, tickets.plan, tickets.test_internal
tickets.test_qa, tickets.uat, tickets.approve, tickets.deploy, tickets.monitor
tickets.close, tickets.reopen, tickets.escalate
sla.view_own, sla.view_division, sla.view_all, sla.manage
reports.view_operational, reports.view_executive, reports.export
notifications.manage_own
users.manage, roles.manage, organization.manage, applications.manage
escalations.manage, audit.view, settings.manage
knowledge.view, knowledge.create, knowledge.review, knowledge.publish, knowledge.archive
```

## Mandatory policy rules

1. Query scope harus diterapkan di database query, bukan filter setelah mengambil semua row.
2. Executive resource tidak boleh memuat description teknis, RCA, internal activity, raw attachment/log, requester PII yang tidak diperlukan, atau deployment detail.
3. Actor tidak boleh approve pekerjaan sendiri bila separation-of-duties berlaku; minimal creator/deployer dan final approver sebaiknya berbeda untuk change berisiko.
4. Supervisor hanya melihat divisi yang berada dalam scope supervisinya, bukan semua tiket.
5. PIC/QA hanya melihat assigned ticket kecuali diberikan operational lead permission.
6. Admin action atas ticket untuk support harus eksplisit, beralasan, dan diaudit; role Admin bukan bypass universal tersembunyi.
7. Attachment download menjalankan policy pada parent resource dan visibility attachment.
8. Setiap transition authorization dites untuk delapan role, both allowed and denied, termasuk cross-division/cross-assignment.

## Decisions deferred after Phase 3

- Phase 3 menetapkan satu primary role; multi-role ditunda dengan kontrak permission tetap dipertahankan.
- Phase 3 menetapkan Supervisor satu divisi; model division dan enforcement query scope ditunda sampai master data.
- Siapa final closer: requester, IT Lead, atau auto-close setelah periode monitoring?
- Apakah Manager/Approver hanya IT Manager atau business owner juga dapat menjadi approver?
- Apakah PIC boleh menjalankan internal testing sendiri, atau wajib actor terpisah?
- Phase 3 menetapkan Executive aggregate-only tanpa detail teknis; bentuk dashboard aggregate diimplementasikan pada fase bisnis berikutnya.

## Phase 7 enforced permissions

| Capability | Requester | Supervisor | IT Lead | Assigned PIC | QA | Manager | Executive | Admin |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Start/manage/complete analysis | — | — | — | Allow | — | — | — | — |
| Create/update/submit solution plan | — | — | — | Allow | — | — | — | — |
| View Plan Review queue and technical detail | — | — | Allow | Own assigned ticket only | — | — | Deny | — |
| Request plan revision / approve plan | — | — | Allow | — | — | — | Deny | — |
| View generic Phase 7 progress on own ticket | Allow | Existing scope | Allow | Allow | Existing scope | Existing scope | Aggregate only | Existing scope |

Concrete permission codes are `ticket.analysis.start`, `ticket.analysis.view`, `ticket.analysis.manage`, `ticket.solution_plan.view`, `ticket.solution_plan.manage`, `ticket.solution_plan.submit`, `ticket.plan_review_queue.view`, `ticket.solution_plan.request_revision`, and `ticket.solution_plan.approve`. Ownership is rechecked under row lock during mutations; a permission alone never grants a PIC access to another PIC's assignment.
