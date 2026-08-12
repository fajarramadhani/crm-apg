# Laporan Tahap 3 — Backend Role, Permission, dan Assignment Dinamis

**Tanggal Executed**: 28 Juli 2026  
**Status**: **PASS (SIAP MENUJU TAHAP 4)**

---

## 1. Branch dan Commit Awal

- **Branch Current**: `feature/crm-simplified-dynamic-workflow`
- **HEAD Commit**: `273aeb6a00abba96983aeb9a414ab7115bca9afb`
- **Migration Baseline**: Tahap 2 All Ran (`2026_07_28_000001` - `2026_07_28_000004`)
- **Baseline Test Results**: 187 tests PASS -> 214 tests PASS

---

## 2. Daftar File Berubah

### File Baru ([NEW])
1. `backend/app/Services/DynamicAssignmentService.php`
2. `backend/app/Http/Controllers/Api/V1/SupervisorItTicketController.php`
3. `backend/app/Http/Requests/Api/V1/AssignPrimaryTicketRequest.php`
4. `backend/app/Http/Requests/Api/V1/AddSecondaryAssigneeRequest.php`
5. `backend/app/Http/Requests/Api/V1/ReassignTicketRequest.php`
6. `backend/app/Http/Requests/Api/V1/TakeoverTicketRequest.php`
7. `backend/tests/Feature/SupervisorItAssignmentTest.php`
8. `backend/tests/Feature/PicAuthorizationTest.php`
9. `backend/tests/Feature/AssignmentIntegrityAndConcurrencyTest.php`
10. `backend/tests/Feature/LegacyRoleCompatibilityTest.php`
11. `docs/crm-revision-stage-3-backend-assignment-report.md`

### File Dimodifikasi ([MODIFY])
1. `backend/config/permissions.php` (Penambahan permission `supervisor_it`, `pic_it_support`, `pic_it_develop`, penyesuaian `admin`)
2. `backend/app/Models/User.php` (Penambahan `scopeEligibleTicketAssignees()`, `isEligibleTicketAssignee()`, `isSupervisorIt()`, `isPicSupport()`, `isPicDevelop()`, `isPic()`)
3. `backend/app/Models/Ticket.php` (Penambahan `scopeAssignedToUser()`)
4. `backend/app/Policies/TicketPolicy.php` (Penambahan `viewAny()` dan perbaikan `view()` untuk secondary assignment)
5. `backend/app/Http/Controllers/Api/V1/PicTicketController.php` (Pembaruan query index untuk mendukung active secondary assignment)
6. `backend/routes/api.php` (Pendaftaran endpoint `/api/v1/supervisor-it/...`)
7. `backend/tests/Feature/AuthenticationAndAuthorizationTest.php` (Pembaruan assertCount role permissions dari 8 menjadi 11)

---

## 3. Permission Baru

| Role | Permission Key |
| :--- | :--- |
| **`supervisor_it`** | `master_data.view`, `ticket.all.view`, `ticket.analysis.manage`, `ticket.system.set`, `ticket.category.set`, `ticket.priority.set`, `ticket.target_resolution.set`, `ticket.assignment.view`, `ticket.assignment.assign`, `ticket.assignment.assign_self`, `ticket.assignment.assign_multiple`, `ticket.assignment.set_primary`, `ticket.assignment.add_secondary`, `ticket.assignment.remove_secondary`, `ticket.assignment.reassign`, `ticket.assignment.takeover`, `ticket.request_info`, `ticket.return_for_revision`, `ticket.approve`, `ticket.reject`, `ticket.cancel`, `ticket.reopen`, `ticket.close`, `ticket.audit_trail.view` |
| **`pic_it_support`** | `master_data.view`, `ticket.assigned.view`, `ticket.assigned.update`, `ticket.work_note.create`, `ticket.attachment.create`, `ticket.progress.update`, `ticket.request_info`, `ticket.waiting_external.set`, `ticket.submit_for_approval`, `ticket.assignment.assistance_request`, `ticket.assignment.transfer_request` |
| **`pic_it_develop`** | `master_data.view`, `ticket.assigned.view`, `ticket.assigned.update`, `ticket.work_note.create`, `ticket.attachment.create`, `ticket.progress.update`, `ticket.request_info`, `ticket.waiting_external.set`, `ticket.submit_for_approval`, `ticket.assignment.assistance_request`, `ticket.assignment.transfer_request` |
| **`admin`** | Configuration permissions only: `master_data.view`, `master_data.manage`, `admin.access`, `ticket.release_checklist_template.manage`, `sla_escalation_policy.view`, `sla_escalation_policy.manage`, `knowledge_base.view`, `knowledge_base.manage_tags`, `knowledge_base.view_activity`, `workflow.view`, `workflow.manage`, `workflow.stage.manage`, `workflow.transition.manage`, `workflow.approval.manage`, `workflow.field.manage`, `workflow.notification.manage`, `application.manage`, `ticket_category.manage` |

---

## 4. Compatibility Role Legacy

- Role legacy (`supervisor`, `it_lead`, `pic`, `qa`, `manager`, `executive`) **dipertahankan 100%**.
- Tidak ada akun lama yang dinonaktifkan atau diubah `role_id`-nya.
- Route dan Controller legacy (`/api/v1/it-lead/...`, `/api/v1/pic/...`, `/api/v1/qa/...`, `/api/v1/supervisor/...`) **tetap berfungsi tanpa hambatan**.
- Method helper compatibility `isPic()` pada `User` model dapat mengenali baik role legacy `pic` maupun role baru `pic_it_support` & `pic_it_develop`.

---

## 5. Eligibility Kandidat PIC

Kandidat PIC dikelola melalui query scope terpusat `User::eligibleTicketAssignees()`:
```php
public function scopeEligibleTicketAssignees($query)
{
    return $query->where('is_active', true)
        ->whereHas('role', function ($q): void {
            $q->whereIn('key', ['supervisor_it', 'pic_it_support', 'pic_it_develop']);
        });
}
```
User dengan role `requester` dan `admin` atau user `is_active = false` secara ketat ditolak oleh `DynamicAssignmentService` dengan `ValidationException` (HTTP 422).

---

## 6. Implementasi Supervisor sebagai PIC

Supervisor IT dapat di-assign sebagai Primary PIC maupun Secondary PIC melalui flow assignment normal:
- Tersimpan di `ticket_assignments`:
  ```json
  {
    "role_at_assignment": "supervisor_it",
    "acting_as_pic": true
  }
  ```
- Supervisor IT bertindak sebagai PIC tetap mempertahankan hak akses Supervisor IT (seperti menambahkan secondary PIC atau melakukan reassign/takeover kepada user lain).

---

## 7. Implementasi Primary dan Secondary PIC

- Multi-PIC Assignment dikelola di tabel `ticket_assignments`:
  - **Primary PIC**: Tepat 1 record aktif (`assignment_type = 'primary'`, `is_current = true`).
  - **Secondary PIC**: 0 atau beberapa record aktif (`assignment_type = 'secondary'`, `is_current = true`).
- PIC Support maupun PIC Develop dapat di-assign ke kategori tiket atau aplikasi apapun (Internal maupun Asuransi) tanpa pembatasan hardcode sistem.

---

## 8. Transaction dan Concurrency Safety

Setiap perubahan assignment pada `DynamicAssignmentService` menggunakan:
1. `DB::transaction()` untuk menjamin atomisitas.
2. `Ticket::query()->lockForUpdate()->findOrFail($ticket->id)` untuk locking tingkat baris database (Pessimistic Locking).
3. Evaluasi kandidat dan pembaruan assignment lama (`ended_at = now()`, `is_current = false`) di dalam transaksi.
4. Pembuatan record `ticket_assignments` dan `ticket_assignment_histories` dalam transaksi yang sama.
5. Pembaruan `current_assignee_id` pada tiket secara konsisten.

Hal ini menjamin dua request bersamaan tidak akan pernah menghasilkan dua Primary PIC aktif pada tiket yang sama.

---

## 9. Assignment History

Setiap aksi assignment mencatat audit trail secara presisi di `ticket_assignment_histories`:
- `action`: `assigned`, `secondary_added`, `secondary_removed`, `reassigned`, `taken_over`, `assignment_ended`.
- Kolom tercatat: `ticket_id`, `assignment_id`, `action`, `actor_id`, `from_user_id`, `to_user_id`, `assignment_type`, `notes`, `metadata` (berisi reason, role_at_assignment, acting_as_pic), `created_at`.
- `ticket_status_histories` tetap khusus untuk perubahan status tiket.

---

## 10. Endpoint Baru

Endpoint baru didaftarkan secara terpisah di bawah prefix `/api/v1/supervisor-it/`:

```text
GET    /api/v1/supervisor-it/tickets
GET    /api/v1/supervisor-it/tickets/{ticket}
GET    /api/v1/supervisor-it/assignees
POST   /api/v1/supervisor-it/tickets/{ticket}/assign-primary
POST   /api/v1/supervisor-it/tickets/{ticket}/secondary-assignees
DELETE /api/v1/supervisor-it/tickets/{ticket}/secondary-assignees/{user}
POST   /api/v1/supervisor-it/tickets/{ticket}/reassign
POST   /api/v1/supervisor-it/tickets/{ticket}/takeover
```

Setiap endpoint menggunakan Form Request khusus (`AssignPrimaryTicketRequest`, `AddSecondaryAssigneeRequest`, `ReassignTicketRequest`, `TakeoverTicketRequest`) untuk validasi payload.

---

## 11. Policy dan Query Scope

- `TicketPolicy::viewAny`: Mengizinkan Supervisor IT (`ticket.all.view`), Requester (`ticket.own.view`), Supervisor Divisi (`ticket.division.view`), dan PIC (`ticket.assigned.view`).
- `TicketPolicy::view`: Mengizinkan Supervisor IT melihat tiket lintas cabang & divisi; PIC dapat melihat tiket jika menjadi Primary PIC (`current_assignee_id`) atau Secondary PIC (`assignments` where `is_current = true`).
- `PicTicketController@index`: Menggunakan filter query assignment aktif `whereHas('assignments', ...)` tanpa membatasi hanya pada `current_assignee_id`.

---

## 12. Hasil Test Assignment (Supervisor IT)

Suite `SupervisorItAssignmentTest`:
- `test_supervisor_it_can_view_all_tickets_across_divisions_and_branches` -> **PASS**
- `test_supervisor_it_can_assign_self_as_primary_pic` -> **PASS**
- `test_supervisor_it_can_assign_self_as_secondary_pic` -> **PASS**
- `test_supervisor_it_can_assign_pic_it_support_and_pic_it_develop` -> **PASS**
- `test_supervisor_it_can_add_two_secondary_pics` -> **PASS**
- `test_supervisor_it_can_replace_primary_pic` -> **PASS**
- `test_supervisor_it_can_takeover_ticket` -> **PASS**
- `test_rejects_requester_as_pic_candidate` -> **PASS**
- `test_rejects_admin_as_pic_candidate` -> **PASS**
- `test_rejects_inactive_user_as_pic_candidate` -> **PASS**

---

## 13. Hasil Test Concurrency dan Integritas

Suite `AssignmentIntegrityAndConcurrencyTest`:
- `test_exactly_one_primary_pic_active_at_a_time` -> **PASS**
- `test_same_user_cannot_have_duplicate_active_assignments_on_same_ticket` -> **PASS**
- `test_old_assignment_has_ended_at_and_is_current_false` -> **PASS**
- `test_reassignment_history_is_recorded` -> **PASS**
- `test_secondary_removal_history_is_recorded` -> **PASS**
- `test_supervisor_acting_as_pic_is_recorded` -> **PASS**
- `test_transaction_rolls_back_if_assignment_fails` -> **PASS**
- `test_concurrency_locking_prevents_duplicate_primary_assignments` -> **PASS**

---

## 14. Hasil Test Permission (PIC)

Suite `PicAuthorizationTest`:
- `test_primary_pic_can_view_ticket` -> **PASS**
- `test_secondary_pic_can_view_ticket` -> **PASS**
- `test_pic_without_assignment_gets_403` -> **PASS**
- `test_pic_support_can_handle_internal_systems_if_assigned` -> **PASS**
- `test_pic_develop_can_handle_insurance_systems_if_assigned` -> **PASS**

---

## 15. Hasil Test Legacy Compatibility

Suite `LegacyRoleCompatibilityTest`:
- `test_legacy_it_lead_can_still_access_legacy_it_lead_routes` -> **PASS**
- `test_legacy_pic_can_still_open_legacy_assigned_tickets` -> **PASS**
- `test_legacy_qa_route_remains_functional` -> **PASS**
- `test_ticket_status_is_not_modified_by_new_assignment_engine` -> **PASS**

---

## 16. Hasil Backend Test Lengkap

```text
Tests:    214 passed (1269 assertions)
Duration: 12.27s
Result:   PASS
```

Formatting check (`vendor/bin/pint --test`):
```text
{"tool":"pint","result":"passed"}
```

---

## 17. Hasil Frontend Verification

```bash
npm run typecheck
npm run build
```

Hasil:
- `tsc --noEmit` -> **0 errors**
- `vite build` -> **Built in 450ms (dist/ index.html, index.css, JS chunks)**

---

## 18. Verifikasi Data Tidak Berubah

- User `role_id` existing: **Tidak Berubah**
- Status tiket existing: **Tidak Berubah**
- Workflow status/engine: **Tidak Berubah (Belum diaktifkan)**
- Assignment existing: **Tidak Berubah**
- Legacy accounts: **Seluruhnya Aktif & Berfungsi**

---

## 19. Risiko dan Kendala

- **Pint Formatting**: Terdeteksi 2 file (`routes/api.php` dan `AssignmentIntegrityAndConcurrencyTest.php`) dengan masukan imbas urutan import. Telah diformat menggunakan Pint dan kini seluruh check Pint `passed`.
- **Tidak ada kendala tersisa.**

---

## 20. Status Kesiapan Menuju Tahap 4

**STATUS: SIAP (READY FOR STAGE 4)**  
Tahap 3 telah selesai 100% dan seluruh suite test backend maupun frontend build berstatus PASS.
