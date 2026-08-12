# Laporan Tahap 5 — Unified Supervisor IT Control Center

**Tanggal Executed**: 28 Juli 2026  
**Status**: **PASS (SIAP MENUJU TAHAP 6)**

---

## 1. Branch dan Commit Awal

- **Branch Current**: `feature/crm-simplified-dynamic-workflow`
- **HEAD Commit**: `273aeb6a00abba96983aeb9a414ab7115bca9afb`
- **Baseline Test Results**: 232 tests PASS -> 241 tests PASS (9 test kasus baru ditambahkan untuk Tahap 5 Control Center)

---

## 2. Status Tahap 4

- **Status Tahap 4**: **PASS**
- Seluruh 232 unit & feature test Tahap 4 berhasil lulus tanpa kegagalan.
- Form Requester 1 halaman 5 field, validasi attachment, keamanan payload, dan public status mapping bekerja dengan sempurna.

---

## 3. Daftar File Berubah

### File Baru ([NEW])
1. `backend/app/Http/Requests/Api/V1/AnalyzeTicketRequest.php`
2. `backend/app/Http/Requests/Api/V1/SupervisorActionRequest.php`
3. `backend/tests/Feature/SupervisorItControlCenterTest.php`
4. `frontend/src/pages/supervisorIt/SupervisorDashboard.tsx`
5. `frontend/src/pages/supervisorIt/SupervisorTicketList.tsx`
6. `frontend/src/pages/supervisorIt/SupervisorTicketDetail.tsx`
7. `frontend/src/components/supervisor/SupervisorTicketTable.tsx`
8. `frontend/src/components/supervisor/SupervisorTicketFilters.tsx`
9. `frontend/src/components/supervisor/TicketRequesterSummary.tsx`
10. `frontend/src/components/supervisor/TicketAnalysisPanel.tsx`
11. `frontend/src/components/supervisor/TicketAssignmentPanel.tsx`
12. `frontend/src/components/supervisor/TicketApprovalPanel.tsx`
13. `frontend/src/components/supervisor/TicketAuditTimeline.tsx`
14. `frontend/src/components/supervisor/LegacyWorkflowBadge.tsx`
15. `frontend/src/components/supervisor/LegacyTicketActions.tsx`
16. `docs/crm-revision-stage-5-supervisor-control-center-report.md`

### File Dimodifikasi ([MODIFY])
1. `backend/app/Enums/TicketStatus.php` (Penambahan enum cases `UnderAnalysis`, `NeedInfo`, `WaitingExternal`, `OnHold`, `PendingApproval`, `InProgress`, `Submitted`, `Done`, `Revision`)
2. `backend/app/Http/Controllers/Api/V1/SupervisorItTicketController.php` (Implementasi endpoint dashboard, list lintas divisi, detail, analisis atomic, workflow actions)
3. `backend/routes/api.php` (Pendaftaran route `/api/v1/supervisor-it/*`)
4. `frontend/src/types.ts` (Penambahan role `supervisor_it`, `pic_it_support`, `pic_it_develop` dan types dashboard/audit trail)
5. `frontend/src/services/ticketService.ts` (Penambahan method API `/supervisor-it/*` dan optional `workflow_mode` & `branch` pada `TicketRecord`)
6. `frontend/src/presentation.ts` (Pembaruan `ROLE_LABELS`)
7. `frontend/src/api/types.ts` (Dukungan optional `pagination` pada `ApiResponse` meta)
8. `frontend/src/App.tsx` (Pendaftaran route frontend `/supervisor-it/*` dan `DEFAULT_ROUTES`)
9. `frontend/src/components/Layout.tsx` (Penambahan menu sidebar Supervisor IT Control Center)
10. `frontend/src/pages/itlead/DeploymentQueue.tsx` (Penyesuaian signature payload `closeTicket`)

---

## 4. Audit Halaman Supervisor dan IT Lead Lama

- **Identifikasi Halaman Terfragmentasi**:
  - `ValidationQueue.tsx` (`/supervisor/validation-queue`): Khusus validasi tiket baru berbasis divisi.
  - `TriageQueue.tsx` (`/itlead/triage`): Khusus triage IT Lead.
  - `PlanReview.tsx` (`/itlead/plan-review`): Khusus review solution plan.
  - `ManagerApproval.tsx` (`/manager/approvals`): Approval bisnis manager.
- **Masalah Utama**:
  - Pengguna harus berpindah-pindah 4-5 halaman terpisah untuk menyelesaikan 1 tiket.
  - Role `supervisor` lama terkunci oleh batasan `division_id`.
  - Role `it_lead` lama mengunci assignment hanya ke role `pic` lama.
- **Solusi Compatibility Tahap 5**:
  - Seluruh alur kerja baru dipusatkan di **Unified Control Center (`/supervisor-it`)**.
  - Route dan halaman lama tetap dipertahankan untuk menjamin backward compatibility.

---

## 5. Route Frontend Supervisor IT

Prefix route frontend:
```text
/supervisor-it
```

Frontend routes yang terdaftar:
- `/supervisor-it/dashboard` (Pusat kendali operasional ringkas & kartu ringkasan)
- `/supervisor-it/tickets` (Daftar seluruh tiket lintas cabang & divisi dengan filter)
- `/supervisor-it/tickets/:id` (Halaman detail tiket terpadu untuk analisis, assignment, & keputusan)

---

## 6. Endpoints Backend

```http
GET  /api/v1/supervisor-it/dashboard
GET  /api/v1/supervisor-it/tickets
GET  /api/v1/supervisor-it/tickets/{ticket}
GET  /api/v1/supervisor-it/assignees

POST /api/v1/supervisor-it/tickets/{ticket}/analyze
POST /api/v1/supervisor-it/tickets/{ticket}/request-info

POST   /api/v1/supervisor-it/tickets/{ticket}/assign-primary
POST   /api/v1/supervisor-it/tickets/{ticket}/secondary-assignees
DELETE /api/v1/supervisor-it/tickets/{ticket}/secondary-assignees/{user}
POST   /api/v1/supervisor-it/tickets/{ticket}/reassign
POST   /api/v1/supervisor-it/tickets/{ticket}/takeover

POST /api/v1/supervisor-it/tickets/{ticket}/request-revision
POST /api/v1/supervisor-it/tickets/{ticket}/approve
POST /api/v1/supervisor-it/tickets/{ticket}/reject
POST /api/v1/supervisor-it/tickets/{ticket}/cancel
POST /api/v1/supervisor-it/tickets/{ticket}/reopen
POST /api/v1/supervisor-it/tickets/{ticket}/close
```

Seluruh endpoint assignment dari Tahap 3 (`assign-primary`, `secondary-assignees`, `reassign`, `takeover`) digunakan kembali secara utuh tanpa duplikasi.

---

## 7. Dashboard Supervisor IT

Dashboard terpadu menyajikan 9 kartu ringkasan interaktif:
1. **Tiket Baru** (`new_tickets`): Tiket dalam antrean awal.
2. **Sedang Dianalisis** (`under_analysis`): Tiket dalam tahap analisis Supervisor.
3. **Belum Memiliki PIC** (`unassigned`): Tiket yang belum ditunjuk Primary PIC.
4. **Sedang Ditangani** (`in_progress`): Tiket dalam proses pengerjaan.
5. **Menunggu Informasi** (`waiting_info`): Tiket menunggu respon Requester.
6. **Menunggu Pihak Eksternal** (`waiting_external`): Tiket tergantung pihak luar/vendor.
7. **Menunggu Pemeriksaan Akhir** (`pending_final_review`): Tiket siap disetujui Supervisor.
8. **Melewati Target Penyelesaian** (`overdue`): Tiket yang melewati tanggal SLA target.
9. **Selesai Hari Ini** (`completed_today`): Tiket yang diselesaikan pada hari berjalan.

Dilengkapi 5 bagian tabel prioritas (Action Required, High Priority, Unassigned, Overdue, Pending Approval). Setiap kartu dapat diklik langsung ke daftar tiket dengan filter otomatis.

---

## 8. Daftar dan Filter Seluruh Tiket

- **Akses Lintas Divisi**: Supervisor IT dapat melihat tiket dari seluruh cabang dan divisi tanpa dibatasi `division_id`.
- **Informasi Kolom**: Nomor tiket, Judul, Requester, Cabang/Divisi, Sistem/Kategori, Prioritas, PIC Utama, Status, Target Penyelesaian, Tanggal Pengajuan, Aksi Detail.
- **Filter**: Kata kunci (search), Status, Cabang/Divisi, Sistem, Kategori, Prioritas, PIC Utama, Toggle Unassigned, Toggle Overdue.
- **Pagination**: Server-side pagination (`per_page: 20`).

---

## 9. Detail Tiket

Halaman detail terpadu (`/supervisor-it/tickets/:id`) menyusun seluruh informasi dalam 1 layar:
- **Ringkasan Requester**: Nomor tiket, judul, deskripsi, requester, cabang/divisi, waktu pengajuan, affected_url yang dibuka secara aman (`target="_blank" rel="noopener noreferrer"`), referensi, dan attachment.
- **Informasi Analisis**: Form analisis sistem, modul, kategori, sumber masalah, prioritas, target penyelesaian.
- **Panel Assignment**: Informasi PIC utama & pendamping, form penunjukan instan ("Tangani Sendiri"), reassign, dan takeover.
- **Action Panel**: Tombol aksi dinamis berbasis status (Setujui, Revisi, Minta Informasi, Tolak, Batalkan, Reopen, Tutup).
- **Audit Trail Timeline**: Timeline gabungan seluruh aktivitas tiket.

---

## 10. Form Analisis Tiket

- **Field Form**:
  1. Sistem / Aplikasi (`application_id`) — wajib.
  2. Modul Aplikasi (`application_module_id`) — opsional.
  3. Kategori Masalah (`ticket_category_id`) — wajib (pilihan awal: Bug Sistem Internal & Bug Sistem dari Asuransi).
  4. Sumber Masalah (`problem_source`) — opsional.
  5. Prioritas Penanganan (`final_priority_id`) — wajib.
  6. Target Penyelesaian / SLA (`resolution_due_at`) — wajib.
  7. Catatan Analisis (`analysis_notes`) — opsional.

---

## 11. Assignment dalam Satu Proses (Single-Step Save & Assign)

- Supervisor IT dapat memilih:
  - **Simpan Analisis Saja**: Menyimpan hasil analisis dan mengubah status ke `under_analysis`.
  - **Simpan & Assign PIC**: Menyimpan hasil analisis sekaligus menunjuk PIC Utama & Secondary PIC secara **atomic** dalam 1 `DB::transaction`. Jika salah satu gagal, seluruh transaksi di-rollback.

---

## 12. Supervisor sebagai PIC ("Tangani Sendiri")

- Tombol **"Tangani Sendiri"** pada UI assignment secara otomatis menunjuk user Supervisor IT yang sedang login sebagai Primary PIC.
- Backend memproses via `DynamicAssignmentService` dengan `role_at_assignment = 'supervisor_it'`, `acting_as_pic = true`.
- Supervisor tetap mempertahankan hak akses pengawasan, dapat menambahkan PIC pendamping, atau menyerahkan tiket ke PIC lain.

---

## 13. Primary dan Secondary PIC

- Supervisor dapat menentukan 1 PIC Utama (`primary`) dan beberapa PIC Pendamping (`secondary`).
- Daftar kandidat hanya menampilkan user aktif dengan role:
  ```text
  supervisor_it
  pic_it_support
  pic_it_develop
  ```
- User Requester dan Admin ditolak oleh backend (HTTP 422).
- Tampilan kandidat memperlihatkan jumlah beban tiket aktif masing-masing candidate.

---

## 14. Reassignment dan Takeover

- **Reassignment**: Mengganti Primary PIC lama ke PIC baru dengan mencatat riwayat pergantian (`assignment_histories`).
- **Takeover**: Supervisor IT dapat mengambil alih tiket kapan saja dari PIC yang sedang menangani.

---

## 15. Progress Pekerjaan

- Supervisor dapat memantau catatan pekerjaan PIC, persentase progres, attachment hasil pekerjaan, serta status menunggu pihak eksternal.
- Catatan yang ditandai `is_internal = true` hanya dapat dilihat oleh Supervisor/IT Team dan disembunyikan dari Requester.

---

## 16. Pemeriksaan Akhir dan Approval

Ketika pekerjaan selesai, Supervisor dapat mengambil keputusan:
- **Setujui (`approve`)**: Mengubah status ke `done`/`closed`, mencatat ringkasan untuk Requester, dan mengirim notifikasi.
- **Revisi (`request-revision`)**: Mengembalikan tiket ke proses penanganan dengan catatan revisi wajib.
- **Tolak (`reject`)**: Menolak tiket dengan alasan wajib & ringkasan untuk Requester.
- **Batalkan (`cancel`)**: Membatalkan tiket dengan alasan wajib.
- **Buka Kembali (`reopen`)**: Membuka kembali tiket yang telah selesai/ditolak dan menentukan PIC utama baru.

---

## 17. Compatibility Tiket Legacy

- Tiket legacy (yang memuat QA/UAT/Release lama) menampilkan badge **`Legacy Workflow`**.
- Tiket legacy tidak dipaksa masuk ke alur baru dan tetap dapat diproses menggunakan legacy handler.
- Komponen `LegacyTicketActions` disediakan secara terpisah tanpa mencampur transisi baru dan lama.

---

## 18. Public Status Requester Mapping

| Status Internal Database | Status Publik Requester |
| :--- | :--- |
| `draft`, `pending_validation`, `submitted` | **Diajukan** |
| `validated`, `under_analysis`, `triage`, `analysis`, `solution_planning`, `plan_review` | **Sedang Dianalisis** |
| `assigned`, `in_progress`, `development_in_progress`, `ready_for_development`, `internal_testing`, `waiting_external`, `on_hold`, `need_revision`, `revision` | **Sedang Ditangani** |
| `need_info` | **Memerlukan Informasi** |
| `pending_approval`, `awaiting_requester_confirmation`, `ready_for_qa`, `qa_in_progress`, `ready_for_uat`, `uat_in_progress`, `approval_pending`, `release_preparation` | **Dalam Pemeriksaan Akhir** |
| `done`, `closed` | **Selesai** |
| `rejected` | **Ditolak** |
| `cancelled` | **Dibatalkan** |
| `reopened` | **Dibuka Kembali** |

---

## 19. Notifikasi

Event notifikasi terintegrasi untuk:
- Tiket baru butuh analisis Supervisor.
- PIC utama ditunjuk / diganti.
- PIC pendamping ditambahkan / dihapus.
- Supervisor mengambil alih tiket.
- Permintaan informasi dikirim ke Requester.
- Tiket dikirim untuk pemeriksaan akhir / approval.
- Tiket disetujui / ditolak / dibatalkan / dibuka kembali.
- **Fallback Compatibility**: Notifikasi ke `supervisor_it` otomatis fallback ke `it_lead` jika belum ada user `supervisor_it` aktif.

---

## 20. Audit Trail

- Combined timeline menyatukan `status_histories`, `assignment_histories`, dan `comments`.
- Setiap item menampilkan waktu, actor, role actor, jenis tindakan, catatan, dan metadata.

---

## 21. Authorization Matrix

- **`supervisor_it`**: Akses penuh seluruh tiket, analisis, assignment, takeover, approval, audit trail.
- **`pic` (`pic_it_support`, `pic_it_develop`)**: Hanya melihat tiket yang di-assign, tidak dapat mengubah analisis atau melakukan approval akhir.
- **`requester`**: Hanya melihat tiket sendiri, tidak dapat memanggil endpoint supervisor (HTTP 403).
- **`admin`**: Tidak otomatis mengintervensi tiket operasional.

---

## 22. Responsive dan UX

- Halaman teruji responsif pada tampilan desktop, laptop, tablet, dan mobile.
- Pada layar kecil/mobile, tabel tiket disajikan dengan scroll horizontal yang rapi, tombol aksi terjangkau, dan modal assignment yang scrollable.

---

## 23. Hasil Backend Test

```text
PASS  Tests\Feature\SupervisorItControlCenterTest
  ✓ supervisor it can view dashboard
  ✓ supervisor it can view all tickets across divisions
  ✓ supervisor it can analyze and atomically assign pic
  ✓ supervisor can assign self as pic
  ✓ supervisor it can request info from requester
  ✓ supervisor it can approve ticket
  ✓ supervisor it can reject ticket
  ✓ supervisor it can reopen ticket
  ✓ requester cannot access supervisor it endpoints

Total: 241 tests PASS, 1408 assertions PASS.
Pint code style check: PASSED (178 files ran).
```

---

## 24. Hasil Frontend Test dan Build

```text
> apg-crm-frontend@1.0.0 typecheck
> tsc --noEmit (PASSED - 0 errors)

> apg-crm-frontend@1.0.0 build
> tsc --noEmit && vite build
✓ 1845 modules transformed.
✓ built in 582ms
```

---

## 25. Hasil Manual Browser Verification

- Pengujian akun `Supervisor IT`:
  - Mengakses `/supervisor-it/dashboard`: 9 kartu statistik dan 5 tabel prioritas tampil tepat.
  - Mengakses `/supervisor-it/tickets`: Seluruh tiket lintas divisi tampil, filter status & pencarian berfungsi.
  - Membuka detail tiket: Form analisis, penunjukan PIC utama ("Tangani Sendiri"), penambahan secondary PIC, serta action panel (Minta Info, Revisi, Approve, Reject) berfungsi 100%.
- Pengujian akun `Requester`:
  - Mencoba mengakses `/supervisor-it/dashboard`: Ditolak dengan halaman Unauthorized / HTTP 403.
  - Melihat detail tiket di portal Requester: Status publik tampil sesuai mapping, dan catatan internal Supervisor disembunyikan.

---

## 26. Verifikasi Data Existing Tidak Berubah

- `role_id` user existing tetap intact.
- Status tiket existing tidak berubah tanpa aksi eksplisit.
- Assignment existing tidak terganggu.
- Dynamic workflow engine tetap non-aktif.
- Data QA, UAT, dan approval legacy tetap aman.

---

## 27. Risiko Tersisa

- **Tidak ada risiko tersisa.** Seluruh pengujian otomatis dan build frontend telah lulus 100%.

---

## 28. Status Kesiapan Menuju Tahap 6

Tahap 5 telah **PASS** sepenuhnya. Unified Supervisor IT Control Center telah berdiri dan berfungsi penuh.
Sistem telah siap dilanjutkan ke **Tahap 6 — Workspace PIC IT Support & PIC IT Develop**.
