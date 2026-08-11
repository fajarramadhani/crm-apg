# CRM Revision — Architectural Specification & Implementation Plan

**Tanggal:** 2026-07-28  
**Dokumen:** `docs/crm-revision-implementation-plan.md`  
**Target:** Finalisasi Persiapan & Stabilisasi (Tahap 1 Audit Baseline)  
**Status Working Tree:** `Untracked Dokumentasi` (**0 file kode aplikasi yang diubah**).

---

## 1. Scope Final Revisi CRM

Revisi CRM difokuskan pada penyederhanaan alur kerja operasional IT, efisiensi penanganan tiket, dan penerapan assignment dinamis tanpa menghilangkan kemampuan audit trail maupun kompatibilitas terhadap tiket legacy.

### Pilar Perubahan Utama:
1. **Penyederhanaan Role (5 Role Final):** `requester`, `supervisor_it`, `pic_it_support`, `pic_it_develop`, `admin`. Role lama tidak dihapus dari sistem, melainkan dipetakan secara terencana via keputusan manual.
2. **Kendalikan Penuh oleh Supervisor IT:** Supervisor IT bertindak sebagai pusat komando operasional IT (analisis, penentuan kategori/sistem/SLA, penunjukan PIC, re-assignment, takeover, approval, pembatalan, pengembalian, dan penyelesaian tiket).
3. **Flow Tiket Baru yang Disederhanakan (Tanpa QA/UAT/Manager Approval):**
   - Tiket baru hanya mengikuti pipeline linear: `submitted` → `under_analysis` → `assigned` → `in_progress` → `pending_approval` → `done`.
   - Menghapus tahap QA khusus, UAT, Manager Approval, dan Konfirmasi Pemohon dari flow tiket baru.
4. **Assignment Dinamis & Multi-PIC:**
   - 1 Tepat **PIC Utama (Primary)** + 0 atau beberapa **PIC Pendamping (Secondary)**.
   - Supervisor IT dapat menunjuk dirinya sendiri sebagai PIC.
   - Kandidat PIC strictly hanya user aktif bertipe role `supervisor_it`, `pic_it_support`, `pic_it_develop`.
   - PIC Support dan PIC Develop dapat ditunjuk ke sistem/kategori apa pun tanpa terkunci batasan lama (e.g. Asuransi vs Internal).
5. **Form Requester Ringkas (5 Field Wajib/Opsional):** Requester tidak lagi memilih Sistem, Kategori, Prioritas, PIC, atau Approver.
6. **Workflow Engine Additive & Backward Compatibility:**
   - Menggunakan `workflow_mode` (`legacy` vs `dynamic`).
   - Tiket baru menyimpan `workflow_snapshot`. Tiket lama tetap menggunakan `legacy transition handler`.

---

## 2. Pemisahan Flow Baru vs Flow Legacy

### A. Flow Tiket Baru (Dynamic Workflow)

Pipeline utama tiket baru:
```text
submitted
  └──> under_analysis
         └──> assigned
                └──> in_progress
                       └──> pending_approval
                              └──> done
```

Kondisi khusus / percabangan tiket baru:
- `need_info` (Meminta informasi tambahan ke Requester/Pihak Terkait)
- `waiting_external` (Menunggu pihak ketiga / vendor / dependensi luar)
- `need_revision` (Mengembalikan tiket ke Requester untuk revisi data)
- `on_hold` (Tiket ditangguhkan sementara oleh Supervisor IT)
- `rejected` (Ditolak oleh Supervisor IT)
- `cancelled` (Dibatalkan oleh Requester atau Supervisor IT)
- `reopened` (Tiket dibuka kembali setelah selesai jika masalah berulang)

> 🚫 **PENTING:** Flow tiket baru **TIDAK MEMILIKI** role QA, tahap QA khusus, UAT, Manager Approval, maupun Konfirmasi Akhir Requester.

---

### B. Flow Tiket Legacy (Compatibility Engine)

Tahapan berikut **HANYA** berlaku untuk tiket lama yang dibuat sebelum revisi (`workflow_mode = 'legacy'`):
- Validasi awal Supervisor Divisi Bisnis (`pending_validation`)
- Business Approval oleh Manager (`business_approval_queue`)
- Technical Approval oleh IT Manager / IT Lead (`technical_approval_queue`)
- QA Assignment & Testing Cycle (`qa_assignment`, `qa_in_progress`, `qa_failed`, `qa_retest`)
- UAT Assignment & Scenario Testing (`uat_assignment`, `uat_in_progress`, `uat_failed`, `uat_retest`, `uat_approved`)
- Release Readiness Checklist & Deployment Schedule
- Requester Confirmation Prompt (`awaiting_requester_confirmation`)

---

## 3. Status Awal Tiket (Initial Status)

- **`submitted`** adalah **satu-satunya status awal** untuk tiket baru yang dibuat oleh Requester.
- **`pending_validation`** secara ketat dikategorikan sebagai **status legacy** (untuk tiket lama yang masih berada di antrean validasi supervisor bisnis). Tiket baru ber-workflow dynamic **dilarang** menggunakan status `pending_validation`.

---

## 4. Status Publik yang Ditampilkan kepada Requester

Requester melihat status publik yang ramah pengguna. Sistem membedakan mapping antara Tiket Baru dan Tiket Legacy:

### Mapping Status Publik (Tiket Baru / Dynamic):

| Internal Status (`tickets.status`) | Status Requester (Public Display) | Deskripsi Ringkas |
|-----------------------------------|-----------------------------------|-------------------|
| `submitted` | **Diajukan** | Tiket telah dikirim dan menunggu analisis Supervisor IT |
| `under_analysis` | **Sedang Dianalisis** | Supervisor IT sedang menganalisis & menentukan kategori/SLA |
| `assigned`, `in_progress`, `waiting_external`, `need_revision`, `on_hold` | **Sedang Ditangani** | Tiket sedang dikerjakan oleh tim IT / PIC yang ditunjuk |
| `need_info` | **Memerlukan Informasi** | Tim IT membutuhkan jawaban / klarifikasi dari Pemohon |
| `pending_approval` | **Dalam Pemeriksaan Akhir** | Pekerjaan telah selesai dan sedang diproses verifikasi akhir |
| `done` | **Selesai** | Tiket telah selesai ditangani secara tuntas |
| `rejected` | **Ditolak** | Tiket ditolak dengan alasan yang tercatat di sistem |
| `cancelled` | **Dibatalkan** | Tiket dibatalkan oleh pemohon atau Supervisor IT |
| `reopened` | **Dibuka Kembali** | Tiket dibuka kembali untuk penanganan susulan |

> 🚫 Status publik tiket baru **TIDAK AKAN PERNAH** menampilkan label: *Pengujian QA*, *UAT*, *Pengujian Anda*, atau *Konfirmasi Pemohon*.

---

### Mapping Status Publik (Tiket Legacy):

| Internal Status Legacy | Status Requester Legacy |
|------------------------|-------------------------|
| `pending_validation` | Menunggu Validasi Atasan |
| `validated`, `triage` | Terverifikasi / Antrean Triage |
| `qa_assignment`, `qa_in_progress`, `qa_retest` | Pengujian Internal (QA) |
| `ready_for_uat`, `uat_assignment`, `uat_in_progress` | Siap UAT / Pengujian Anda |
| `awaiting_requester_confirmation` | Konfirmasi Pemohon |

---

## 5. Konsistensi Structure Workflow Compatibility

Setiap tiket di database akan dikelola menggunakan 4 kolom standar:

```sql
workflow_id        BIGINT UNSIGNED NULLABLE,
workflow_version   VARCHAR(20) NULLABLE,
workflow_snapshot  JSON NULLABLE,
workflow_mode      ENUM('legacy', 'dynamic') NOT NULL DEFAULT 'legacy'
```

### Aturan Eksekusi Transisi:
1. **Tiket Legacy (`workflow_mode = 'legacy'`):**
   - Transisi dijalankan oleh `LegacyTransitionHandler`.
   - Menggunakan rule status legacy (QA, UAT, Approval Manager, dll).
2. **Tiket Dynamic (`workflow_mode = 'dynamic'`):**
   - Transisi dijalankan oleh `DynamicWorkflowEngine`.
   - Menggunakan konfigurasi yang tersimpan pada `tickets.workflow_snapshot`.
3. **Imunitas Perubahan Workflow Admin:**
   - Ketika Admin mengubah konfigurasi workflow di Master Admin, perubahan tersebut **TIDAK BOLEH** merubah `workflow_snapshot` dari tiket yang sedang berjalan (`in_flight tickets`).

*(Seluruh pustaka kode/dokumentasi yang mengacu pada `workflow_version_id` dinyatakan deprecated dan dihapus).*

---

## 6. Role Final dan Manual User Mapping

### Role Final Sistem (5 Role):
1. `requester`
2. `supervisor_it`
3. `pic_it_support`
4. `pic_it_develop`
5. `admin`

### Ketentuan Mapping Manual:
- **TIDAK BOLEH** melakukan bulk mapping otomatis hanya berdasarkan nama role lama.
- **`executive`** lama **TIDAK BOLEH** dipetakan menjadi `supervisor_it` read-only. Role executive lama tetap berstatus legacy / nonaktif setelah kebutuhan laporan eksekutif dikonfirmasi.
- **`manager`** lama **TIDAK OTOMATIS** menjadi `supervisor_it` atau `admin`.
- **`qa`** lama **TIDAK OTOMATIS** menjadi `pic_it_support`.
- **`supervisor`** lama dari divisi bisnis dipetakan menjadi `requester`.
- **`supervisor`** lama dari divisi IT dipetakan menjadi `supervisor_it`.

### Skema Tabel Keputusan Manual User (`user_role_mappings`):

| user_id | nama | email | role_lama | role_baru | alasan | disetujui_oleh |
|---------|------|-------|-----------|-----------|--------|----------------|
| 12 | Ahmad Supardi | `ahmad@apg.local` | `supervisor` | `requester` | Supervisor Divisi Ops Bisnis | Head of Ops |
| 15 | Budi Santoso | `budi@apg.local` | `supervisor` | `supervisor_it` | Supervisor Unit IT Infra/Support | Head of IT |
| 22 | Citra Dewi | `citra@apg.local` | `it_lead` | `supervisor_it` | Lead Software Engineer / IT Supervisor | Head of IT |
| 31 | Doni Pratama | `doni@apg.local` | `pic` | `pic_it_support` | Helpdesk & Application Support | Lead IT Support |
| 35 | Eko Prasetyo | `eko@apg.local` | `pic` | `pic_it_develop` | Backend Developer | Lead IT Develop |
| 42 | Fani Rahma | `fani@apg.local` | `qa` | `pic_it_support` | Alih tugas ke App Support & Testing | Head of IT |
| 50 | Gunawan | `gunawan@apg.local` | `executive` | *legacy_inactive* | Akses laporan dialihkan via Admin Report | Head of HR |

---

## 7. Logic Assignment Dinamis

### Kandidat PIC Valid (Candidate Rules):
Kandidat PIC yang dapat dipilih oleh Supervisor IT **STRICTLY** dibatasi hanya untuk user aktif dengan role:
- `supervisor_it`
- `pic_it_support`
- `pic_it_develop`

> 🚫 `requester` dan `admin` **DILARANG HARAM** ditunjuk sebagai PIC Tiket.

### Struktur Assignment Tiket:
Satu tiket aktif wajib memiliki:
- **Tepat 1 PIC Utama (Primary PIC)** aktif (`is_current = true`, `assignment_type = 'primary'`).
- **Nol atau Beberapa PIC Pendamping (Secondary PICs)** aktif (`is_current = true`, `assignment_type = 'secondary'`).

### Prinsip Implementasi Kode (Backend Execution):
1. **Database Transaction:** Seluruh operasi assignment/reassignment dibungkus dalam `DB::transaction(...)`.
2. **Pessimistic Row Locking:** Menggunakan `Ticket::query()->lockForUpdate()->findOrFail($ticketId)` untuk mencegah race condition.
3. **Validasi Strict Single Primary:** Menjamin hanya ada 1 Primary PIC aktif per tiket pada satu waktu.
4. **Validasi User Aktif:** Menolak assignment jika status user `is_active = false`.
5. **Lintas Sistem & Kategori:** PIC IT Support maupun PIC IT Develop dapat menangani sistem/kategori apa pun sesuai instruksi Supervisor IT.
6. **Concurrent Assignment Test:** Diuji khusus dengan simulasi request bersamaan.

---

## 8. Struktur Riwayat Assignment (`ticket_assignment_histories`)

Perubahan assignment tidak lagi hanya menumpang pada `ticket_status_histories`. Sistem menggunakan tabel khusus **`ticket_assignment_histories`**:

```sql
CREATE TABLE ticket_assignment_histories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    assignment_id BIGINT UNSIGNED NULLABLE,
    action ENUM('assigned_primary', 'reassigned_primary', 'added_secondary', 'removed_secondary', 'takeover') NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    from_user_id BIGINT UNSIGNED NULLABLE,
    to_user_id BIGINT UNSIGNED NOT NULL,
    assignment_type ENUM('primary', 'secondary') NOT NULL DEFAULT 'primary',
    notes TEXT NULLABLE,
    metadata JSON NULLABLE,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id),
    FOREIGN KEY (from_user_id) REFERENCES users(id),
    FOREIGN KEY (to_user_id) REFERENCES users(id)
);
```

*(Tabel `ticket_status_histories` tetap digunakan khusus mencatat perubahan status tiket seperti `submitted` -> `under_analysis`).*

---

## 9. Granular Permission System (Supervisor IT)

Supervisor IT tidak menggunakan permission tunggal yang samar (`ticket.supervisor.full_control`). Sebaliknya, digunakan **Granular Permissions Matrix**:

| Permission Code | Deskripsi Aksi | Role Granted |
|-----------------|----------------|--------------|
| `ticket.all.view` | Melihat seluruh tiket di sistem | `supervisor_it`, `admin` |
| `ticket.analysis.manage` | Melakukan analisis detail & rekomendasi | `supervisor_it` |
| `ticket.triage.classify` | Menentukan Sistem & Kategori tiket | `supervisor_it` |
| `ticket.priority.finalize` | Menentukan Prioritas & Target SLA | `supervisor_it` |
| `ticket.assign` | Menunjuk PIC Utama (Primary) | `supervisor_it` |
| `ticket.assign.self` | Menunjuk diri sendiri sebagai PIC | `supervisor_it` |
| `ticket.assign.secondary` | Menunjuk beberapa PIC Pendamping | `supervisor_it` |
| `ticket.reassign` | Mengganti / mengalihkan PIC Utama | `supervisor_it` |
| `ticket.takeover` | Mengambil alih penanganan tiket | `supervisor_it` |
| `ticket.request_info` | Meminta informasi tambahan ke pemohon | `supervisor_it`, `pic_it_support`, `pic_it_develop` |
| `ticket.request_revision` | Mengembalikan tiket untuk revisi form | `supervisor_it` |
| `ticket.approve` | Memperbaiki & menuntaskan tiket (`pending_approval` -> `done`) | `supervisor_it` |
| `ticket.reject` | Menolak tiket dengan alasan eksplisit | `supervisor_it` |
| `ticket.cancel` | Membatalkan tiket | `supervisor_it` |
| `ticket.reopen` | Membuka kembali tiket yang sudah selesai | `supervisor_it` |
| `ticket.close` | Penutupan permanen tiket | `supervisor_it`, `admin` |
| `ticket.audit_trail.view` | Melihat riwayat rinci perubahan assignment & status | `supervisor_it`, `admin` |

> ⚠️ Permission flow legacy (seperti `ticket.qa.assign`, `ticket.uat.assign`, `ticket.business_approval.approve`, `ticket.solution_plan.review`) **HANYA** digunakan jika `ticket.workflow_mode = 'legacy'`.

---

## 10. Form Pembuatan Tiket Requester (5 Field)

Form Requester pada tiket baru disederhanakan murni menjadi:

1. **`title`** (String, Mandatory, Max 200) — Judul Tiket / Ringkasan Masalah.
2. **`description`** (Text, Mandatory, Max 10.000) — Penjelasan detail kendala / kebutuhan.
3. **`attachments`** (File Array, Mandatory, Min 1 file) — Upload screenshot error / dokumen pendukung.
4. **`affected_url`** (URL, Mandatory, Max 2048) — Link halaman web / sistem yang error.
5. **`reference_number`** (String, Optional, Max 100) — Nomor dokumen / ID transaksi referensi.

---

## 11. Batasan Ketat Implementasi Tahap 2 (Database Foundation Additive Only)

Rencana Tahap 2 dispesifikasikan secara **ADDITIVE MURNI**. 

### Tahap 2 TERDIRI DARI:
- Membuat migration tabel baru: `workflows`, `workflow_nodes`, `workflow_transitions`, `ticket_assignment_histories`, `user_role_mappings`.
- Membuat migration kolom additive pada tabel `tickets`: `workflow_id`, `workflow_version`, `workflow_snapshot`, `workflow_mode`.
- Menambahkan key role baru (`supervisor_it`, `pic_it_support`, `pic_it_develop`) ke tabel `roles` via Seeder Additive.

### Tahap 2 DILARANG HARD / HARAM UNTUK:
- ❌ Mengubah data user eksistensi di database.
- ❌ Memetakan role user secara otomatis.
- ❌ Mengubah status tiket yang ada.
- ❌ Menghapus role lama (`supervisor`, `it_lead`, `pic`, `qa`, `manager`, `executive`).
- ❌ Menghapus tabel / kolom lama.
- ❌ Menonaktifkan API route lama.
- ❌ Mengubah alur / komponen frontend.
- ❌ Mengaktifkan dynamic workflow engine secara global.

---

## 12. Daftar Test yang Harus Dibuat pada Tahap Implementasi

1. `tests/Feature/DynamicAssignmentTest.php`:
   - Test Supervisor IT assign Primary PIC.
   - Test Supervisor IT assign dirinya sendiri sebagai PIC.
   - Test Supervisor IT assign Multi Secondary PICs.
   - Test Re-assignment & pencatatan ke `ticket_assignment_histories`.
   - Test penolakan assignment terhadap Requester, Admin, dan User nonaktif.
   - Test race condition (Concurrent assignment dengan row locking `lockForUpdate`).
2. `tests/Feature/RequesterSimplifiedFormTest.php`:
   - Test submit tiket baru dengan 5 field (title, description, attachments, affected_url, reference_number).
   - Test auto-set status awal `submitted` dan `workflow_mode = 'dynamic'`.
3. `tests/Feature/LegacyCompatibilityTest.php`:
   - Test tiket legacy (`workflow_mode = 'legacy'`) tetap dapat diproses melalui legacy transition handler.

---

**Selesai Dokumen Rencana Implementasi Tahap 1 (Revisi Final).**
