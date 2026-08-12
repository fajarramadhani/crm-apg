# CRM Revision — Baseline Verification & Audit Report

**Tanggal:** 2026-07-28  
**Dibuat untuk:** Tahap 1 — Persiapan dan Stabilisasi (Verifikasi Baseline & Audit Dokumen)  
**Status Working Tree:** `Untracked Dokumentasi` (Dokumen laporan di folder `docs/` terdeteksi sebagai untracked files; **0 file kode aplikasi yang diubah**).

---

## 1. Status Repository & Git Baseline

| Parameter | Hasil Verifikasi |
|-----------|------------------|
| Branch Sebelumnya | `development` |
| Branch Aktif | `feature/crm-simplified-dynamic-workflow` |
| Commit Hash Baseline (HEAD) | `273aeb6a00abba96983aeb9a414ab7115bca9afb` |
| Commit Message | `fix: align strict backend checks and format frontend files for CI pipeline` |
| Repository Remote | `origin -> https://github.com/fajarramadhani/crm-apg.git` |
| Branch Remote Terdaftar | `origin/HEAD -> origin/development`, `origin/development`, `origin/main`, `origin/fajarramadhani-musical-sniffle` |

### Status Working Tree Detail:
```text
On branch feature/crm-simplified-dynamic-workflow
Untracked files:
  docs/crm-revision-baseline-report.md
  docs/crm-revision-implementation-plan.md
```
> **Catatan Git:** Working tree tidak memiliki perubahan pada file kode sumber (backend/frontend). Hanya terdapat 2 file dokumentasi Markdown baru di folder `docs/`. File ini tidak di-commit, di-stash, atau di-push pada Tahap 1 sesuai instruksi.

---

## 2. Versi Runtime dan Package Manager Baseline

| Komponen | Versi | Status |
|----------|-------|--------|
| PHP | 8.5.0 (cli) (NTS VC2022 x64) | OK |
| Composer | 2.8.12 | OK |
| Laravel Framework | 12.x (Composer Vendor) | OK |
| Node.js | v24.18.0 | OK |
| npm | 11.16.0 | OK |
| pnpm | *Belum terdaftar di PATH sistem Windows* | Warning (pnpm store tersedia di LocalAppData) |
| TypeScript | ^5.7.0 | OK |
| Vite | ^8.0.16 | OK |
| React | ^19.0.0 | OK |
| Tailwind CSS | ^4.0.0 | OK |

---

## 3. Hasil Verification Execution Baseline

### Backend Test Suite
```text
PHPUnit / Laravel Artisan Test:
Tests: 187 passed (187 total)
Assertions: 1156 passed
Duration: 11.182s
Status: ✅ PASSED (100% Lulus)
```

### Backend Code Style (Pint)
```text
Laravel Pint:
Status: ✅ PASSED (0 violation)
```

### Frontend Type Check & Formatting & Build
| Check | Perintah | Hasil |
|-------|----------|-------|
| TypeScript TypeCheck | `npx tsc --noEmit` | ✅ **PASSED** (0 error) |
| Prettier Code Style | `npx prettier --check .` | ✅ **PASSED** (All matched files conform) |
| Vite Production Build | `npx vite build` | ✅ **PASSED** (Built `dist/assets/index-C-R6fH8V.js` in 460ms) |

---

## 4. Route API dan Database Migration Baseline

- **Total API Routes:** **270 routes** terdaftar di under `/api/v1/`.
- **Database Migrations:** **22 migrations** telah sukses berjalan (`Batch 1`). Migration terakhir adalah `2026_07_22_120000_create_knowledge_base_tables`. Tidak ada migration yang pending.

---

## 5. Audit Pre-Existing Errors & Keterbatasan Local Runtime

1. **Pre-existing Application Code Error:** **0 Error.** Kode backend dan frontend sebelum revisi berada dalam kondisi sangat sehat dan stabil.
2. **Keterbatasan Runtime Lokal:** Server lokal frontend tidak dapat di-spin up via `pnpm dev` karena binary `pnpm` belum ditambahkan ke PATH environment Windows. Namun, `npx tsc` dan `npx vite build` terbukti 100% lulus.
3. **Database Data Baseline:** Database SQLite lokal (`backend/database/database.sqlite`) hanya berisi 1 record tiket sampel. Query audit komprehensif disiapkan untuk dijalankan pada environment Staging/Production.

---

## 6. Query Audit Tiket Eksistensi (Untuk Staging/Production)

Query berikut disiapkan untuk meng-audit data tiket legacy pada database staging sebelum implementasi Tahap 2:

```sql
-- 1. Jumlah tiket per status legacy
SELECT status, COUNT(*) AS total 
FROM tickets 
WHERE deleted_at IS NULL 
GROUP BY status 
ORDER BY total DESC;

-- 2. Tiket pada status QA legacy
SELECT id, ticket_number, status, qa_assignee_id 
FROM tickets 
WHERE status IN ('qa_assignment', 'qa_in_progress', 'qa_failed', 'qa_retest') 
  AND deleted_at IS NULL;

-- 3. Tiket pada status UAT legacy
SELECT id, ticket_number, status, uat_assignee_id 
FROM tickets 
WHERE status IN ('uat_assignment', 'uat_in_progress', 'uat_failed', 'uat_retest', 'uat_approved') 
  AND deleted_at IS NULL;

-- 4. Tiket pada proses Business/Technical Approval legacy
SELECT id, ticket_number, status 
FROM tickets 
WHERE status IN ('approval_pending', 'approval_revision') 
  AND deleted_at IS NULL;

-- 5. Tiket aktif tanpa assignment aktif
SELECT t.id, t.ticket_number, t.status 
FROM tickets t
LEFT JOIN ticket_assignments ta ON ta.ticket_id = t.id AND ta.is_current = 1
WHERE t.deleted_at IS NULL
  AND t.status NOT IN ('draft', 'pending_validation', 'need_revision', 'rejected', 'cancelled', 'closed')
  AND ta.id IS NULL;

-- 6. Tiket dengan lebih dari 1 assignment primary aktif (anomali)
SELECT ticket_id, COUNT(*) AS total_primary 
FROM ticket_assignments 
WHERE is_current = 1 AND assignment_type = 'primary' 
GROUP BY ticket_id 
HAVING total_primary > 1;
```

---

## 7. Kesimpulan Baseline Status

Project dalam kondisi **SANGAT STABIL** dan **SIAP** untuk dilanjutkan ke Tahap 2 (Database Additive Foundation). Tidak ada hambatan teknis dari sisi codebase eksistensi.
