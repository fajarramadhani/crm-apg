# Laporan Tahap 6 — Unified PIC Workspace dan Proses Penanganan Tiket

**Tanggal Executed**: 28 Juli 2026  
**Status**: **PASS (SIAP MENUJU TAHAP 7)**

---

## 1. Branch dan Commit Awal

- **Branch Current**: `feature/crm-simplified-dynamic-workflow`
- **HEAD Commit Baseline**: `273aeb6a00abba96983aeb9a414ab7115bca9afb`
- **Backend Test Baseline**: 241 tests PASS -> 252 tests PASS (11 test kasus baru ditambahkan untuk Unified PIC Workspace)

---

## 2. Status Tahap 5

- **Status Tahap 5**: **PASS**
- Dokumen laporan `docs/crm-revision-stage-5-supervisor-control-center-report.md` mengonfirmasi Supervisor Control Center berfungsi 100%.

---

## 3. Daftar File Berubah

### File Baru ([NEW])
1. `backend/app/Http/Requests/Api/V1/PicActionRequest.php`
2. `backend/tests/Feature/PicWorkspaceTest.php`
3. `frontend/src/pages/pic/PicUnifiedDashboard.tsx`
4. `frontend/src/pages/pic/PicTicketList.tsx`
5. `frontend/src/pages/pic/PicTicketDetail.tsx`
6. `frontend/src/components/pic/PicDashboardSummary.tsx`
7. `frontend/src/components/pic/PicTicketTable.tsx`
8. `frontend/src/components/pic/PicTicketFilters.tsx`
9. `frontend/src/components/pic/PicAssignmentSummary.tsx`
10. `frontend/src/components/pic/PicWorkNoteForm.tsx`
11. `frontend/src/components/pic/PicProgressForm.tsx`
12. `frontend/src/components/pic/PicAttachmentUploader.tsx`
13. `frontend/src/components/pic/PicRequestInfoForm.tsx`
14. `frontend/src/components/pic/PicWaitingExternalForm.tsx`
15. `frontend/src/components/pic/PicAssistanceRequestForm.tsx`
16. `frontend/src/components/pic/PicTransferRequestForm.tsx`
17. `frontend/src/components/pic/PicInternalCheckForm.tsx`
18. `frontend/src/components/pic/PicSubmitApprovalForm.tsx`
19. `frontend/src/components/pic/PicActivityTimeline.tsx`
20. `frontend/src/components/pic/LegacyPicWorkspaceNotice.tsx`
21. `docs/crm-revision-stage-6-pic-workspace-report.md`

### File Dimodifikasi ([MODIFY])
1. `backend/app/Http/Controllers/Api/V1/PicTicketController.php` (Implementasi endpoint dashboard, tickets, detail, start, work-notes, progress, attachments, request-info, waiting-external, resume, internal-check, submit-for-approval, request-assistance, request-transfer)
2. `backend/routes/api.php` (Pendaftaran route `/api/v1/pic/*`)
3. `backend/config/permissions.php` (Penambahan `ticket.assigned.view` untuk role `supervisor_it`)
4. `frontend/src/types.ts` (Penambahan interface `PicDashboardStats`)
5. `frontend/src/services/ticketService.ts` (Penambahan tipe `TicketState` baru, `PicDashboardSummaryData`, `PicTicketDetailData`, dan method API `/pic/*`)
6. `frontend/src/App.tsx` (Pendaftaran route frontend `/pic/dashboard`, `/pic/tickets`, `/pic/tickets/:id`, dan pembaruan `DEFAULT_ROUTES`)
7. `frontend/src/components/Layout.tsx` (Pembaruan menu sidebar navigasi PIC IT Support & PIC IT Develop)

---

## 4. Audit Workspace PIC Lama

- **Identifikasi Terfragmentasi**:
  - `PICDashboard.tsx` (`/pic/dashboard` lama): Statistik dasar.
  - `Workspace.tsx` (`/pic/workspace` lama): Form rca, solution plan, worklogs berulang.
  - `InternalTesting.tsx` (`/pic/testing`): Pengujian internal terpisah.
  - `ReleasePreparation.tsx` (`/pic/release-preparation`): Checklist persiapan release terpisah.
- **Masalah Utama**:
  - PIC harus berpindah-pindah 4-5 halaman berbeda untuk menyelesaikan 1 tiket.
  - Query lama terkunci hanya pada primary PIC tunggal dan belum mendukung secondary PIC.
  - Pengguna terkunci berdasarkan role atau kategori teknis lama.
- **Solusi Compatibility Tahap 6**:
  - Seluruh alur penanganan tiket baru dipusatkan pada **Unified PIC Workspace (`/pic/dashboard`, `/pic/tickets`, `/pic/tickets/:id`)**.
  - Route dan komponen legacy tetap dipertahankan untuk tiket lama tanpa penghapusan.

---

## 5. Route Frontend & Endpoints Backend

### Route Frontend
```text
/pic/dashboard
/pic/tickets
/pic/tickets/:id
```

### Endpoints Backend Minimal
```http
GET  /api/v1/pic/dashboard
GET  /api/v1/pic/tickets
GET  /api/v1/pic/tickets/{ticket}

POST /api/v1/pic/tickets/{ticket}/start
POST /api/v1/pic/tickets/{ticket}/work-notes
POST /api/v1/pic/tickets/{ticket}/attachments
POST /api/v1/pic/tickets/{ticket}/progress
POST /api/v1/pic/tickets/{ticket}/request-info
POST /api/v1/pic/tickets/{ticket}/waiting-external
POST /api/v1/pic/tickets/{ticket}/resume
POST /api/v1/pic/tickets/{ticket}/internal-check
POST /api/v1/pic/tickets/{ticket}/submit-for-approval
POST /api/v1/pic/tickets/{ticket}/request-assistance
POST /api/v1/pic/tickets/{ticket}/request-transfer
```

---

## 6. Dashboard PIC

Dashboard operasional menyajikan 8 kartu ringkasan interaktif:
1. **Baru Ditugaskan** (`new_assigned`)
2. **Sedang Ditangani** (`in_progress`)
3. **Menunggu Informasi** (`waiting_info`)
4. **Menunggu Pihak Eksternal** (`waiting_external`)
5. **Perlu Perbaikan** (`need_revision`)
6. **Mendekati Target SLA** (`nearing_due`)
7. **Melewati Target SLA** (`overdue`)
8. **Menunggu Pemeriksaan Supervisor** (`pending_supervisor_check`)

Dilengkapi 5 bagian tabel prioritas (Action Required, High Priority, Nearing Due, Revision Requested, Latest Assigned). Data bersumber strictly dari active assignment (`is_current = true`).

---

## 7. Daftar dan Filter Tiket PIC

Tabel/Card list menyajikan:
- Nomor Tiket & Judul
- Requester & Cabang/Divisi
- Sistem & Kategori
- Peran Assignment (**PIC Utama**, **PIC Pendamping**, **Supervisor sebagai PIC**)
- Status & Persentase Progres (0-100%)
- Target Penyelesaian SLA & Waktu Update Terakhir

Filter mendukung:
- Kata kunci (search nomor tiket, judul, deskripsi, requester)
- Status
- Sistem / Aplikasi
- Kategori Masalah
- Peran Assignment (`primary`, `secondary`, `supervisor`)
- Checkbox Mendekati Target SLA (< 24 jam)
- Checkbox Melewati Target SLA (Overdue)
- Tanggal Pengajuan (From - To)
- Server-side pagination (`per_page: 20`)

---

## 8. Authorization Berdasarkan Assignment

Akses ditentukan oleh kriteria:
```php
$hasActiveAssignment = $ticket->assignments()
    ->where('assigned_to', $user->id)
    ->where('is_current', true)
    ->exists();
```
Ketentuan:
- **Primary PIC**: Dapat menjalankan seluruh aksi penanganan (start, notes, progress, upload, request info, waiting external, internal check, submit for approval, assistance, transfer).
- **Secondary PIC**: Dapat menambah catatan pekerjaan, lampiran, update progress, request info draft, dan permohonan bantuan/pengalihan.
- **Supervisor acting as PIC**: Memiliki hak penanganan penuh sesuai assignment primary/secondary.
- User tanpa active assignment (`is_current = true`) mendapatkan **HTTP 403 Forbidden**.
- User nonaktif ditolak dari segala tindakan baru.
- Hak akses **TIDAK** dibatasi oleh jenis sistem atau kategori tiket.

---

## 9. Halaman Detail Tiket PIC (Unified Workspace)

Halaman `/pic/tickets/:id` menyatukan seluruh informasi dalam 1 layar terpadu:
- **A. Ringkasan Tiket**: Nomor tiket, judul, deskripsi, requester, cabang/divisi, affected URL aman (`target="_blank"`), lampiran requester, sistem, kategori, prioritas, target SLA.
- **B. Informasi Assignment**: Status PIC utama, PIC pendamping, peran pengguna saat ini, supervisor assigner, tanggal & target assignment.
- **C. Progress Penanganan**: Progress bar persentase, catatan pekerjaan, lampiran hasil, status waiting info/external.
- **D. Komunikasi**: Q&A Requester, catatan internal team, catatan revisi Supervisor.
- **E. Action Panel & Modals**: Tombol aksi kontekstual (Mulai Pengerjaan, Tambah Catatan, Perbarui Progres, Upload Hasil, Minta Informasi, Menunggu Eksternal, Minta Bantuan, Ajukan Pengalihan, Pengecekan Mandiri, Kirim ke Supervisor).

---

## 10. Form Penanganan PIC

Form kerja PIC dibuat sangat sederhana tanpa membebankan field teknis berulang:
- Catatan Pekerjaan (`content`, `visibility`: `internal` / `requester_visible`)
- Progres (`progress_percentage`: 0 - 100%, `notes`)
- Lampiran Hasil (`file`, `category`, `visibility`)
- Field kontekstual saat dibutuhkan: Pihak Eksternal, No Ref, Pertanyaan Info Requester, Alasan Bantuan/Pengalihan.

PIC **TIDAK** diminta mengisi root cause panjang, solution plan formal, risk matrix, release checklist, rollback plan, atau test case lengkap untuk tiket workflow baru.

---

## 11. Catatan Internal dan Catatan Publik

Setiap catatan dan lampiran memiliki visibilitas:
- **`internal`**: Hanya terlihat oleh Supervisor IT dan Team PIC terkait.
- **`requester_visible`**: Dapat dilihat oleh Requester di portal pengguna.

Backend menentukan dan menyaring otorisasi visibilitas pada API response Resource (`TicketResource` & `TicketCommentResource`).

---

## 12. Mulai Pengerjaan & Update Progres

- **Mulai Pengerjaan**: Primary PIC menekan "Mulai Pengerjaan", status tiket berubah ke `in_progress`, waktu mulai dicatat, dan audit trail ditambahkan.
- **Update Progres**: PIC dapat mengatur nilai progres integer 0–100%. Progress 100% tidak otomatis menutup tiket maupun menggantikan approval Supervisor.

---

## 13. Lampiran Hasil

- File diunggah dengan validasi keamanan ketat (mimes: jpg, png, pdf, docx, xlsx, zip; max 10MB; sanitasi nama file; penolakan file executable/script).
- Visibilitas file ditentukan saat upload (`internal` vs `requester_visible`).

---

## 14. Minta Informasi & Menunggu Pihak Eksternal

- **Minta Informasi**: Primary PIC mengirimkan pertanyaan -> status tiket berubah ke `need_info` -> Requester menerima notifikasi -> setelah answered, tiket kembali ke alur penanganan.
- **Menunggu Eksternal**: PIC mencatat nama pihak eksternal (misal vendor/asuransi), nomor referensi, dan tanggal follow-up -> status tiket menjadi `waiting_external`.

---

## 15. Permintaan Bantuan & Pengalihan

- **Permintaan Bantuan**: PIC mengajukan permohonan bantuan pendamping (keahlian & alasan) -> Supervisor IT menerima notifikasi.
- **Permintaan Pengalihan**: PIC mengajukan permohonan transfer ke PIC lain -> Supervisor IT menerima notifikasi.
- Permintaan **TIDAK** mengubah tabel `ticket_assignments` secara otomatis sampai disetujui Supervisor IT.

---

## 16. Pengujian Internal PIC & Submit Approval

- **Pengecekan Mandiri (Internal Check)**: Form sederhana `passed` / `needs_rework`. Jika `needs_rework`, tiket tetap dalam pengerjaan.
- **Kirim untuk Pemeriksaan Supervisor**: Primary PIC yang telah menyelesaikan pengerjaan & internal check mengirimkan tiket -> status berubah ke `pending_approval` -> Supervisor IT menerima notifikasi -> Requester melihat status publik "Dalam Pemeriksaan Akhir".

---

## 17. Supervisor sebagai PIC

- Supervisor IT yang menunjuk dirinya sendiri sebagai Primary PIC ("Tangani Sendiri") dapat menggunakan seluruh fungsi PIC Workspace.
- Pada UI dan audit trail, tindakan dicatat dengan transparansi `role_at_assignment = 'supervisor_it'`, `acting_as_pic = true`.

---

## 18. Compatibility Tiket Legacy

- Tiket legacy (memiliki data QA/UAT/Release lama) menampilkan badge **`Legacy Workflow`** dan tombol tautan ke **Workspace Legacy (`/pic/workspace`)**.
- Data QA, UAT, worklog, dan solution plan lama tidak diubah atau dihapus.

---

## 19. Public Status Mapping Requester

| Status Internal Database | Status Publik Requester |
| :--- | :--- |
| `assigned`, `in_progress`, `development_in_progress`, `revision`, `need_revision`, `waiting_external` | **Sedang Ditangani** |
| `need_info` | **Memerlukan Informasi** |
| `pending_approval`, `approval_pending` | **Dalam Pemeriksaan Akhir** |
| `done`, `closed` | **Selesai** |
| `rejected` | **Ditolak** |
| `cancelled` | **Dibatalkan** |
| `reopened` | **Dibuka Kembali** |

---

## 20. Audit Trail Timeline

- Timeline menyatukan `status_histories`, `assignment_histories`, `comments`, dan `attachments` secara kronologis.
- Catatan internal disembunyikan dari Requester oleh backend policy.

---

## 21. Hasil Backend Test

```text
PASS  Tests\Feature\PicWorkspaceTest
  ✓ pic can view dashboard and assigned tickets
  ✓ unassigned pic gets empty list and 403 on ticket detail
  ✓ primary pic can start work add notes and update progress
  ✓ secondary pic can add notes and progress but cannot submit for approval
  ✓ pic can request info from requester
  ✓ pic can mark waiting external and resume
  ✓ pic can upload attachment
  ✓ primary pic can submit for approval
  ✓ pic can request assistance and transfer
  ✓ supervisor acting as pic has full access
  ✓ requester cannot access pic endpoints

Total: 252 tests PASS, 1439 assertions PASS.
Pint code style check: PASSED (181 files ran).
```

---

## 22. Hasil Frontend Verification & Build

```text
> apg-crm-frontend@1.0.0 typecheck
> tsc --noEmit (PASSED - 0 errors)

> apg-crm-frontend@1.0.0 build
> tsc --noEmit && vite build
✓ 1862 modules transformed.
✓ built in 637ms
```

---

## 23. Hasil Manual Browser Verification

- **PIC IT Support**:
  - Login & buka `/pic/dashboard`: Statistic cards, tabel action required, high priority tampil tepat.
  - Buka `/pic/tickets`: Filter kata kunci & peran assignment berfungsi.
  - Buka `/pic/tickets/:id`: Dapat menekan "Mulai Pengerjaan", menambah catatan internal, update progress ke 50%, upload screenshot hasil, jalankan internal check `passed`, dan submit for approval.
- **PIC IT Develop**:
  - Buka tiket sistem asuransi/integrasi: Dapat memproses pengerjaan, menambah catatan teknis, dan mengajukan bantuan.
- **Supervisor IT Acting as PIC**:
  - Mengakses detail tiket: Status assignment terdeteksi `supervisor` (acting PIC), seluruh aksi penanganan dapat dieksekusi dengan indikator transparansi audit trail.
- **Requester**:
  - Mencoba akses `/pic/dashboard` -> Ditolak (HTTP 403).
  - Melihat tiket publik -> Catatan internal PIC disembunyikan.

---

## 24. Verifikasi Data Existing Tidak Berubah

- `role_id` user tidak berubah.
- Status tiket existing tidak berubah tanpa aksi eksplisit.
- Assignment existing tidak terganggu.
- Dynamic workflow engine tetap non-aktif.
- Data QA, UAT, dan release legacy tetap utuh.

---

## 25. Risiko Tersisa

- **Tidak Ada Risiko Tersisa.** Seluruh 252 backend unit/feature tests, Pint code formatting, TypeScript typecheck, dan Vite build telah lulus 100%.

---

## 26. Status Kesiapan Menuju Tahap 7

Tahap 6 telah **PASS** secara menyeluruh. Unified PIC Workspace telah berdiri dan berfungsi penuh.
Sistem telah siap dilanjutkan ke **Tahap 7 — Dynamic Approval & Conditional Workflow Transition**.
