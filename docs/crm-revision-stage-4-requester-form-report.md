# Laporan Tahap 4 — Simplifikasi Form Requester dan Ticket Intake

**Tanggal Executed**: 28 Juli 2026  
**Status**: **PASS (SIAP MENUJU TAHAP 5)**

---

## 1. Branch dan Commit Awal

- **Branch Current**: `feature/crm-simplified-dynamic-workflow`
- **HEAD Commit**: `273aeb6a00abba96983aeb9a414ab7115bca9afb`
- **Baseline Test Results**: 214 tests PASS -> 232 tests PASS (18 test kasus baru ditambahkan untuk Tahap 4)

---

## 2. Status Tahap 3

- **Status Tahap 3**: **PASS**
- Seluruh 214 unit & feature test Tahap 3 berhasil lulus tanpa kegagalan.
- Fitur dynamic multi-PIC assignment (Primary & Secondary PIC), permission scoping `supervisor_it`, `pic_it_support`, `pic_it_develop`, dan eligibility logic bekerja sempurna.

---

## 3. Daftar File Berubah

### File Baru ([NEW])
1. `backend/database/migrations/2026_07_28_000005_add_reference_and_nullable_category_to_tickets_table.php`
2. `backend/app/Services/RequesterTicketService.php`
3. `backend/app/Http/Requests/Api/V1/StoreRequesterTicketRequest.php`
4. `backend/app/Http/Controllers/Api/V1/RequesterTicketController.php`
5. `backend/tests/Feature/RequesterTicketCreationTest.php`
6. `backend/tests/Feature/RequesterSecurityPayloadTest.php`
7. `backend/tests/Feature/RequesterAuthorizationTest.php`
8. `backend/tests/Feature/RequesterTransactionAndNotificationTest.php`
9. `docs/crm-revision-stage-4-requester-form-report.md`

### File Dimodifikasi ([MODIFY])
1. `backend/app/Models/Ticket.php` (Penambahan attribute `reference` pada `$fillable`)
2. `backend/app/Http/Resources/Api/V1/TicketResource.php` (Penambahan field `reference` pada output array)
3. `backend/app/Services/TicketNotificationRecipientResolver.php` (Notifikasi supervisor menggunakan role key `supervisor_it`/`supervisor` dengan fallback ke `it_lead`)
4. `backend/routes/api.php` (Pendaftaran endpoint `POST /api/v1/requester/tickets`)
5. `frontend/src/presentation.ts` (Penambahan `PUBLIC_STATUS_LABELS` dan helper `getPublicStatusLabel`)
6. `frontend/src/services/ticketService.ts` (Penambahan `affected_url` & `reference` pada `TicketRecord`, serta method `createRequesterTicket`)
7. `frontend/src/types.ts` (Penambahan `affectedUrl` & `reference` pada interface `Ticket`)
8. `frontend/src/pages/user/CreateTicket.tsx` (Redesign penuh menjadi form 1 halaman 5 field)
9. `frontend/src/pages/user/TicketDetail.tsx` (Penyesuaian rendering opsional category & penambahan field `Link Error` serta `Referensi`)
10. `frontend/src/pages/user/TicketHistory.tsx` (Penyesuaian safe navigation property `ticket.category?.name`)

---

## 4. Kondisi Form Requester Lama

- Form requester lama menggunakan 3-step wizard yang rumit.
- Meminta requester menentukan informasi teknis seperti:
  - Kategori tiket (`ticket_category_id`) - diwajibkan oleh backend.
  - Aplikasi / Sistem (`application_id`) & Modul (`application_module_id`).
  - Prioritas usulan (`requested_priority_id`).
  - Urgensi, tujuan pengajuan, dampak bisnis, alasan perubahan, atau penanda masalah berulang.
- Risiko: Requester salah memilih kategori/sistem atau kebingungan mengisi istilah teknis internal.

---

## 5. Form Requester Baru

- Form requester baru disederhanakan menjadi **satu halaman tunggal (single page form)** tanpa wizard.
- Hanya berisi 5 field utama:
  1. **Judul pengajuan tiket** (`title`) — wajib.
  2. **Deskripsi kendala** (`description`) — wajib.
  3. **Link submission yang error** (`affected_url`) — wajib.
  4. **Referensi** (`reference`) — opsional.
  5. **Lampiran dokumen atau screenshot** (`attachments`) — wajib (minimal 1 file).
- Tampilan UI bersih, indikator wajib (`*`) jelas, disertai preview file terpilih dan tombol hapus file sebelum submit.

---

## 6. Endpoint yang Digunakan

- Endpoint khusus Requester yang terpisah secara tegas dari endpoint Supervisor/Admin:
  ```http
  POST /api/v1/requester/tickets
  ```
- Menggunakan `multipart/form-data` untuk mendukung pembuatan tiket dan upload file secara atomic dalam 1 HTTP request.

---

## 7. Validasi Backend

Validasi dipusatkan pada `StoreRequesterTicketRequest.php`:
- `title`: Wajib, string, tidak boleh hanya whitespace, maksimal 200 karakter.
- `description`: Wajib, string, tidak boleh hanya whitespace, maksimal 10.000 karakter.
- `affected_url`: Wajib, string, format URL valid, maksimal 2.048 karakter.
- `reference`: Opsional, nullable string, maksimal 255 karakter.
- `attachments`: Wajib, array minimal 1 file, maksimal 10 file.

---

## 8. Validasi File

- **File Whitelist Extension & MIME**: `jpg`, `jpeg`, `png`, `webp`, `pdf`, `doc`, `docx`, `xls`, `xlsx`.
- **Ekstensi Terlarang**: `.exe`, `.sh`, `.php`, `.html`, script/executable ditolak ketat (HTTP 422).
- **Ukuran Maksimal**: 10 MB per file.
- **Penyimpanan Aman**: Disimpan menggunakan UUID sebagai filename di storage (`tickets/{ticket_id}/{stored_uuid}`). File original name tetap disimpan di database untuk kebutuhan download.
- **Kontrol Akses**: Mengunduh lampiran dilindungi oleh `Gate::authorize('view', $ticket)` & pemeriksaan `uploaded_by` / `visibility` sehingga Requester tidak dapat mengakses file milik Requester lain.

---

## 9. Validasi URL

- Protokol URL dibatasi secara ketat hanya pada `http://` dan `https://`.
- Ditolak dengan pesan validasi jelas jika mengandung `javascript:`, `file:`, `data:`, `ftp:`, dll.
- Server **tidak** melakukan request HTTP/cURL otomatis ke URL tersebut untuk menghindari risiko SSRF.

---

## 10. Payload yang Dilarang (Security Payload Rejection)

Jika Requester mengirimkan field teknis atau sensitif pada payload `POST /api/v1/requester/tickets`, backend langsung menolak dengan **HTTP 422 Unprocessable Entity**:
- `requester_id`
- `division_id`
- `branch_id`
- `application_id`
- `application_module_id`
- `ticket_category_id`
- `requested_priority_id`
- `final_priority_id`
- `priority_id`
- `assigned_to`
- `pic_user_id`
- `workflow_id`
- `workflow_version`
- `workflow_mode`
- `status`
- `approver_id`

---

## 11. Data yang Dibuat Otomatis

Backend melalui `RequesterTicketService` secara otomatis menetapkan:
- `ticket_number`: Dihasilkan oleh `TicketNumberGenerator`.
- `requester_id`: Diambil dari `auth()->user()->id`.
- `division_id`: Diambil dari `auth()->user()->division_id`.
- `branch_id`: Diambil dari `auth()->user()->branch_id`.
- `current_division_id`: Diambil dari `auth()->user()->division_id`.
- `submitted_at`: Diisi `now()`.
- `status`: Set ke `pending_validation`.
- `histories`: Dibuat otomatis record `created` dan `submitted`.

---

## 12. Compatibility Field Sistem dan Kategori

- Schema database pada migration `2026_07_28_000005_add_reference_and_nullable_category_to_tickets_table.php` membuat `ticket_category_id` dan `application_id` bernilai `nullable()`.
- Tiket baru yang dibuat oleh Requester disimpan dengan `ticket_category_id = null` dan `application_id = null`.
- Tidak ada data palsu yang diisikan secara diam-diam.
- Penentuan Sistem (`application_id`), Modul (`application_module_id`), Kategori (`ticket_category_id`), dan Prioritas akan dilakukan secara tepat oleh Supervisor IT pada tahap berikutnya.

---

## 13. Status Internal Tiket Baru

- Status internal di database untuk tiket baru menggunakan `pending_validation` (kompatibel 100% dengan backend & flow existing).
- Tidak ada perubahan destruktif pada `TicketStatus` enum.

---

## 14. Public Status Mapping

Presentation layer (`presentation.ts`) memetakan status internal ke label status publik untuk Requester:

| Status Internal | Label Status Publik |
| :--- | :--- |
| `draft`, `pending_validation`, `submitted` | **Diajukan** |
| `validated`, `triage`, `assigned`, `analysis`, `solution_planning`, `plan_review` | **Sedang Dianalisis** |
| `ready_for_development`, `development_in_progress`, `internal_testing` | **Sedang Ditangani** |
| `need_revision`, `revision` | **Memerlukan Informasi** |
| `ready_for_qa`, `qa_assignment`, `qa_in_progress`, `qa_failed`, `qa_retest`, `ready_for_uat`, `uat_assignment`, `uat_in_progress`, `uat_failed`, `uat_retest`, `uat_approved`, `approval_pending`, `approval_revision`, `release_preparation`, `release_ready`, `deployment_scheduled`, `deployment_in_progress`, `deployed`, `monitoring`, `awaiting_requester_confirmation` | **Dalam Pemeriksaan Akhir** |
| `done`, `closed` | **Selesai** |
| `rejected` | **Ditolak** |
| `cancelled` | **Dibatalkan** |
| `reopened` | **Dibuka Kembali** |

Detail teknis internal seperti QA, UAT cycle, Solution Plan Review, dan Deployment Step disembunyikan dari tampilan Requester.

---

## 15. Notifikasi dan Fallback

- Setelah tiket berhasil dibuat, event `TicketSubmitted` memicu notifikasi kepada Supervisor IT di divisi yang sama.
- `TicketNotificationRecipientResolver` memetakan penerima notifikasi menggunakan role key (`supervisor_it` / `supervisor`).
- **Fallback Compatibility**: Jika belum ada user dengan role supervisor di divisi terkait (karena user belum dimigrasikan), resolver secara otomatis melakukan fallback notifikasi kepada role `it_lead`.

---

## 16. Transaction dan Cleanup File

- Proses pembuatan tiket dan penyimpanan file attachment berada dalam satu **Database Transaction** (`DB::transaction`).
- Jika terjadi kegagalan pada saat upload file, penulisan ke database, atau pembuatan riwayat status:
  - Transaksi database di-rollback secara penuh.
  - Berkas fisik yang sempat tersimpan di storage disk langsung dibersihkan/dihapus (`Storage::disk($disk)->delete($path)`).
  - Tidak ada tiket atau berkas setengah jadi (orphaned files) yang tertinggal di server.

---

## 17. Hasil Authorization Test

- `test_requester_can_view_own_ticket`: **PASS**
- `test_requester_cannot_view_other_requester_ticket`: **PASS** (HTTP 403)
- `test_requester_can_download_own_attachment`: **PASS**
- `test_requester_cannot_download_other_requester_attachment`: **PASS** (HTTP 403)
- `test_supervisor_can_view_requester_ticket`: **PASS**

---

## 18. Hasil Security Test

- `test_payload_with_forbidden_requester_id_is_rejected_422`: **PASS** (HTTP 422)
- `test_payload_with_forbidden_status_is_rejected_422`: **PASS** (HTTP 422)
- `test_payload_with_forbidden_technical_fields_is_rejected_422`: **PASS** (HTTP 422 untuk seluruh 9 parameter sensitif)
- `test_creation_fails_with_invalid_or_unsafe_urls`: **PASS** (Reject `javascript:`, `file:`, `data:`, `ftp:`)
- `test_creation_fails_with_disallowed_file_types`: **PASS** (Reject `.exe`, `.sh`, `.php`, `.html`)

---

## 19. Hasil Backend Test

```text
PASS  Tests\Feature\RequesterAuthorizationTest
  ✓ requester can view own ticket
  ✓ requester cannot view other requester ticket
  ✓ requester can download own attachment
  ✓ requester cannot download other requester attachment
  ✓ supervisor can view requester ticket

PASS  Tests\Feature\RequesterSecurityPayloadTest
  ✓ payload with forbidden requester id is rejected 422
  ✓ payload with forbidden status is rejected 422
  ✓ payload with forbidden technical fields is rejected 422

PASS  Tests\Feature\RequesterTicketCreationTest
  ✓ requester can create ticket with five fields
  ✓ requester can create ticket without optional reference
  ✓ creation fails without required fields
  ✓ creation fails with invalid or unsafe urls
  ✓ creation fails with disallowed file types
  ✓ creation fails with oversized file
  ✓ creation fails if requester is inactive

PASS  Tests\Feature\RequesterTransactionAndNotificationTest
  ✓ transaction rolls back and cleans up files on failure
  ✓ notification falls back to it lead when no supervisor exists
  ✓ legacy tickets and routes remain functional

Total: 232 tests PASS, 1363 assertions PASS.
Pint code style check: PASSED.
```

---

## 20. Hasil Frontend Typecheck dan Build

```text
> apg-crm-frontend@1.0.0 typecheck
> tsc --noEmit (PASSED - 0 errors)

> apg-crm-frontend@1.0.0 build
✓ 1833 modules transformed.
✓ built in 839ms
```

---

## 21. Hasil Manual Browser Verification

- Pengujian form 1 halaman Requester pada browser:
  - Form hanya menampilkan 5 field (Judul, Deskripsi, Affected URL, Referensi, Lampiran).
  - Tanda wajib (`*`) dan pesan error validasi muncul tepat di dekat field.
  - Mengunggah file menampilkan daftar preview file beserta ukuran dan tombol hapus file.
  - Pengiriman tiket berhasil dan menampilkan layar konfirmasi nomor tiket.
  - Halaman riwayat tiket dan detail tiket Requester menampilkan status publik `Diajukan` serta menunjukkan field `Link Error` dan `Referensi`.

---

## 22. Verifikasi Data Existing Tidak Berubah

- `role_id` user existing tetap intact.
- Status tiket existing tidak berubah.
- Assignment existing tidak terganggu.
- Dynamic workflow engine tetap non-aktif.
- Tidak ada data atau file lampiran lama yang terhapus.

---

## 23. Kendala dan Risiko Tersisa

- **Tidak ada kendala kritis.** Semua pengujian unit, integrasi, keamanan, dan kompilasi frontend berhasil 100%.

---

## 24. Status Kesiapan Menuju Tahap 5

Tahap 4 telah **PASS** sepenuhnya dan memenuhi seluruh kriteria revisi CRM.
Sistem telah siap dilanjutkan ke **Tahap 5 — Form Intake dan Analisis Supervisor IT**.
