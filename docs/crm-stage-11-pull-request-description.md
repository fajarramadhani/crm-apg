# Draft Pull Request Description

**Source Branch:** `feature/crm-simplified-dynamic-workflow`  
**Target Branch:** `development`  
**PR Title:** `feat(crm): simplified roles, assignment, requester flow, and dynamic workflow`  

---

## Ringkasan

Pull Request ini menghadirkan perombakan alur operasional CRM APG menjadi lima role utama, form Requester 5-field yang intuitif, assignment primary/secondary berbasis user, Supervisor IT Control Center, Unified PIC Workspace, dan Dynamic Workflow Engine dengan jaminan kompatibilitas 100% terhadap tiket legacy.

---

## Perubahan Utama

- **Role & Permission:** Penyederhanaan menjadi 5 role operasional utama (`requester`, `supervisor_it`, `pic_it_support`, `pic_it_develop`, `admin`) sambil mempertahankan 9 role legacy.
- **Supervisor IT Control Center:** Kendali penuh seluruh tiket, rekomendasi kandidat PIC, opsi "Tangani Sendiri", dan penandaan self-approval transparan pada audit trail.
- **Assignment Engine:** Tepat 1 primary PIC aktif dan multiple secondary PICs per tiket.
- **PIC Workspace:** Dashboard PIC lintas sistem, visual transition action buttons dynamic per stage, serta pemisahan catatan internal vs publik.
- **Requester Intake:** Intake form 5-field (Judul, Deskripsi, Error URL, Referensi, Attachment) dilengkapi dukungan `Idempotency-Key` (UUIDv4).
- **Dynamic Workflow Engine:** Versioning workflow (`crm_default` v1), 13 stages, 30 transitions, immutable workflow snapshot pada tiket, dan Admin Workflow Builder.
- **Approval Engine:** Single-step approval `supervisor_it` dengan catatan wajib.
- **Legacy Compatibility:** Route, controller, data QA, dan UAT legacy tetap aktif dan terlindungi tanpa modifikasi schema/data lama.
- **Idempotency & Resilience:** Menghindari duplicate submission dan menangani deadlock DB secara aman.

---

## Pengujian & Verifikasi Lokal

- **Backend Unit & Feature Tests:** 341/341 tests passed (1783 assertions, 100% pass rate).
- **Code Style (Pint):** Clean format (0 violations).
- **Frontend Typecheck:** 0 TypeScript compilation errors (`tsc --noEmit`).
- **Frontend Build:** Production build bundle verified successfully (`dist/`).
- **Workflow Health Check:** `php artisan crm:workflow-check --all` PASSED.
- **Role Mapping Safety:** `php artisan crm:role-mapping --dry-run` PASSED (0 DB writes).
- **Security Audit:** Zero secret/token/credential leaks tracked in git.

---

## Panduan Deployment Staging

1. Deployment diawali dengan `CRM_DYNAMIC_WORKFLOW_ENABLED=false` di `.env` staging untuk smoke test compatibility.
2. Jalankan migration additive (`php artisan migrate --force`).
3. Cetak seeder dasar (`php artisan db:seed --class=RoleSeeder --force` dan `php artisan db:seed --class=DefaultWorkflowSeeder --force`).
4. Admin staging mem-publish dan mengaktifkan tepat 1 workflow definition via Admin UI (`/admin/workflows`).
5. Jalankan `php artisan crm:workflow-check --all`.
6. Setelah health check pass, aktifkan `CRM_DYNAMIC_WORKFLOW_ENABLED=true` di environment Staging.

---

## Condition & Pending Staging Items

- [ ] Menjalankan `php artisan crm:concurrency-check` pada host Staging MySQL/MariaDB InnoDB aktual saat maintenance window.
- [ ] Menjalankan `php artisan crm:performance-check` pada dataset 1.000 tiket Staging representatif.
- [ ] Sign-off review akhir oleh Tim IT APG sebelum merger disetujui.

---

> **PENTING:** PR ini dibuat sebagai **Draft** untuk keperluan Review Tim IT. **DILARANG MERGE** ke `development` atau `main` secara otomatis sebelum seluruh kondisi staging dan review tertulis selesai.
