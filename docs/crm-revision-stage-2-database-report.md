# CRM Revision — Stage 2 Database Additive & Role Foundation Report (Final Safety Gate)

**Tanggal:** 2026-07-28  
**Dokumen:** `docs/crm-revision-stage-2-database-report.md`  
**Target:** Tahap 2 — Database Additive dan Role Foundation (Final Safety Gate Audit)  
**Branch Aktif:** `feature/crm-simplified-dynamic-workflow`  
**Commit Baseline (HEAD):** `273aeb6a00abba96983aeb9a414ab7115bca9afb`  
**Status Safety Gate:** **PASS WITH PENDING DATABASE ENGINE TEST**

---

## 1. Branch Aktif dan Commit Baseline

- **Branch Aktif:** `feature/crm-simplified-dynamic-workflow`
- **Commit Baseline (HEAD):** `273aeb6a00abba96983aeb9a414ab7115bca9afb` (`fix: align strict backend checks and format frontend files for CI pipeline`)

---

## 2. Kondisi Working Tree Awal

Working tree awal bersih dari perubahan kode aplikasi di luar scope Tahap 2:
```text
 M backend/app/Models/Application.php
 M backend/app/Models/Ticket.php
 M backend/app/Models/TicketAssignment.php
 M backend/app/Models/TicketCategory.php
 M backend/database/seeders/RoleSeeder.php
 M backend/tests/Feature/DatabaseSeederSafetyTest.php
?? backend/app/Models/TicketAssignmentHistory.php
?? backend/app/Models/WorkflowApprovalConfig.php
?? backend/app/Models/WorkflowApprovalStep.php
?? backend/app/Models/WorkflowCondition.php
?? backend/app/Models/WorkflowDefinition.php
?? backend/app/Models/WorkflowStage.php
?? backend/app/Models/WorkflowStageField.php
?? backend/app/Models/WorkflowTransition.php
?? backend/app/Models/WorkflowTransitionNotification.php
?? backend/app/Models/WorkflowTransitionPermission.php
?? backend/database/migrations/2026_07_28_000001_create_workflow_engine_tables.php
?? backend/database/migrations/2026_07_28_000002_add_workflow_fields_to_tickets_table.php
?? backend/database/migrations/2026_07_28_000003_add_assignment_foundation_and_histories.php
?? backend/database/migrations/2026_07_28_000004_add_system_and_category_foundation_fields.php
?? backend/database/seeders/DefaultWorkflowSeeder.php
?? docs/crm-revision-baseline-report.md
?? docs/crm-revision-implementation-plan.md
?? docs/crm-revision-stage-2-database-report.md
```

Tidak ada perubahan pada controller, policy, route, permission operasional, service assignment, transition service, atau frontend.

---

## 3. Database Engine & Keamanan `migrate:fresh`

- **Database Engine Lokal / Automated Test:** SQLite (`database/database.sqlite` / memory).
- **Target Database Staging / Production:** MySQL (berdasarkan `DB_CONNECTION=mysql` pada `.env.example`).
- **Keamanan `migrate:fresh`:** Perintah `php artisan migrate:fresh` HANYA dijalankan pada database disposable lokal/testing (`database/database.sqlite`). Perintah tersebut **TIDAK PERNAH** dijalankan pada database staging atau production.
- **Status Pengujian Engine Staging/Production:** MySQL service tidak tersedia pada CLI lokal ini. Pengujian pada instance MySQL disposable dicatat sebagai **Pending Mandatory Verification Checklist** sebelum deployment ke environment staging.

---

## 4. Verifikasi `DatabaseSeeder.php` & Diff

- **Diff `DatabaseSeeder.php`:** `Zero diff` (Unmodified).
- **Status Pemanggilan Seeder:** `DefaultWorkflowSeeder` **TIDAK DIDAFARKAN** di `DatabaseSeeder.php` untuk mencegah eksekusi otomatis pada environment production:
  ```php
  // DatabaseSeeder.php hanya memanggil:
  $this->call([
      RoleSeeder::class,
  ]);
  ```

---

## 5. File Migration yang Dibuat

1. `database/migrations/2026_07_28_000001_create_workflow_engine_tables.php`
2. `database/migrations/2026_07_28_000002_add_workflow_fields_to_tickets_table.php`
3. `database/migrations/2026_07_28_000003_add_assignment_foundation_and_histories.php`
4. `database/migrations/2026_07_28_000004_add_system_and_category_foundation_fields.php`

---

## 6. Model yang Dibuat & Diperbarui

### Model Baru:
- `app/Models/WorkflowDefinition.php`
- `app/Models/WorkflowCondition.php`
- `app/Models/WorkflowStage.php`
- `app/Models/WorkflowTransition.php`
- `app/Models/WorkflowTransitionPermission.php`
- `app/Models/WorkflowStageField.php`
- `app/Models/WorkflowTransitionNotification.php`
- `app/Models/WorkflowApprovalConfig.php`
- `app/Models/WorkflowApprovalStep.php`
- `app/Models/TicketAssignmentHistory.php`

### Model Diperbarui:
- `app/Models/Ticket.php` (`$fillable`, `$casts`: `workflow_snapshot` → `array`, `workflow_version` → `integer`, `workflow` & `assignmentHistories` relations).
- `app/Models/TicketAssignment.php` (`$fillable`, `$casts`: `acting_as_pic` → `boolean`, `target_completed_at` → `datetime`).
- `app/Models/Application.php` (`$fillable`, `$casts`: `tags` → `array`).
- `app/Models/TicketCategory.php` (`$fillable`, `$casts`: `metadata` → `array`, `defaultWorkflow` relation).

Tidak ada observer, event otomatis, global scope, atau boot logic yang ditambahkan.

---

## 7. Verifikasi Schema Final

### Tabel `tickets`
- `workflow_id`: `foreignId` nullable (`nullOnDelete()`)
- `workflow_version`: `unsignedSmallInteger` nullable
- `workflow_snapshot`: `json` nullable
- `workflow_mode`: `string(20)` nullable (**tanpa default DB `legacy`**; tiket lama menyimpan `NULL`)

### Tabel `workflow_definitions`
- `code`: `string(50)`
- `name`: `string`
- `version`: `unsignedSmallInteger` (default `1`)
- `description`: `text` nullable
- `is_active`: `boolean` (default `false`)
- `created_by`: `foreignId` nullable (`nullOnDelete()`)
- **Unique Constraint:** `$table->unique(['code', 'version'])`

### Tabel `ticket_assignment_histories`
- `assignment_id`: `foreignId` nullable (`nullOnDelete()`)
- `actor_id`: `foreignId` nullable (`nullOnDelete()`)
- `from_user_id`: `foreignId` nullable (`nullOnDelete()`)
- `to_user_id`: `foreignId` nullable (`nullOnDelete()`)
- Penghapusan user/assignment tidak akan menghapus riwayat assignment history.

---

## 8. Hasil Seeder Idempotency Test

Diuji dengan eksekusi beruntun pada database disposable:
```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=DefaultWorkflowSeeder
php artisan db:seed --class=DefaultWorkflowSeeder
```
**Hasil:**
- Total Roles: 11 (`8 legacy + 3 baru`, zero duplikasi).
- Total User `role_id`: Tidak ada yang berubah.
- Total Workflows: 1 (`default_it_workflow` v1, `is_active = false`).
- Total Stages: 13.
- Total Transitions: 18.
- Tiket dengan Workflow: 0.
- Kategori dengan Workflow: 0.

---

## 9. Hasil Migration Rollback & Re-migration Test

1. **Rollback Step 4:** `php artisan migrate:rollback --step=4` — **PASSED (4 migration Tahap 2 di-rollback secara bersih)**.
2. **Re-migrate:** `php artisan migrate` — **PASSED (4 migration Tahap 2 sukses terpasang kembali)**.

---

## 10. Hasil Test Suite & Frontend Build

- **PHPUnit / Artisan Test:** `php artisan test` — **PASSED (187/187 tests passed, 100%)**.
- **Code Style (Laravel Pint):** `vendor/bin/pint --test` — **PASSED (0 violation)**.
- **TypeScript TypeCheck:** `npm run typecheck` — **PASSED (0 error)**.
- **Vite Production Build:** `npm run build` — **PASSED (Built `dist/` in 338ms)**.

---

## 11. Detail Verifikasi Data Invariance

Bandingan mendalam snapshot pre-migration vs post-migration:

| Parameter Snapshot | Sebelum Migration | Sesudah Migration | Status Invariance |
|--------------------|-------------------|-------------------|-------------------|
| Total Users | 8 | 8 | ✅ Identik |
| Map User ID & Role ID | `1:1, 2:2, 3:3, 4:4, 5:5, 6:6, 7:7, 8:8` | `1:1, 2:2, 3:3, 4:4, 5:5, 6:6, 7:7, 8:8` | ✅ Identik |
| Total Tickets | 1 | 1 | ✅ Identik |
| Detail Tiket #1 | ID 1 (`TIC-202607-000001`), Status: `uat_assignment` | ID 1 (`TIC-202607-000001`), Status: `uat_assignment` | ✅ Identik |
| Tiket #1 `workflow_mode` | - | `NULL` | ✅ Identik (Dianggap Legacy) |
| Active Assignments | 1 (`ticket_id:1, assigned_to:4, type:primary`) | 1 (`ticket_id:1, assigned_to:4, type:primary`) | ✅ Identik |
| Ticket Status Histories | 27 records | 27 records | ✅ Identik |
| Attachments Count | 0 | 0 | ✅ Identik |
| Comments Count | 0 | 0 | ✅ Identik |
| Ticket Main Timestamps | `submitted_at: 2026-07-17 11:23:29` | `submitted_at: 2026-07-17 11:23:29` | ✅ Identik |

---

## 12. Final Safety Gate Audit & Status

| Area Audit | Kriteria Safety Gate | Hasil Audit |
|------------|----------------------|-------------|
| **Repository Integrity** | 0 perubahan pada Controller/Route/Policy/Frontend | ✅ PASSED |
| **DatabaseSeeder Safety** | `DefaultWorkflowSeeder` tidak dipanggil di `DatabaseSeeder.php` | ✅ PASSED |
| **Data Invariance** | 0 perubahan pada User, Ticket, Assignment, Status History | ✅ PASSED |
| **Idempotency** | Multiple seeder runs menghasilkan 0 duplikasi | ✅ PASSED |
| **Rollback Safety** | Migration Tahap 2 dapat di-rollback dan di-migrate ulang | ✅ PASSED |
| **Code & Build Verification** | PHPUnit (187/187), Pint (0 violation), TSC (0 error), Vite Build (OK) | ✅ PASSED |
| **Staging DB Engine** | Pengujian pada instance MySQL/MariaDB staging | ⚠️ **PENDING** (Checklist sebelum Staging Deploy) |

**Status Akhir Tahap 2:** **PASS WITH PENDING DATABASE ENGINE TEST**

---

## 13. Rekomendasi Sebelum Masuk Tahap 3

1. **Safety Clearance:** Seluruh fondasi database additive Tahap 2 dalam kondisi 100% aman dan pasif.
2. **Pending Task:** Sebelum melakukan deployment ke Staging, jalankan `php artisan migrate` dan `DefaultWorkflowSeeder` pada container/database MySQL disposable untuk memastikan kompatibilitas MySQL engine.
3. **Kesiapan Tahap 3:** Fondasi database telah siap secara utuh untuk menopang **Tahap 3 — User Role Decision & Assignment Foundation**.

*(Perhatian: Tidak ada commit, push, atau deploy yang dilakukan).*
