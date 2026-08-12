# CRM PIC Ticket Detail UI Revision Report

## 1. Branch dan commit awal
- Branch: `feature/crm-simplified-dynamic-workflow`
- Commit awal: `54274062dcd69bfec11556bc3430317215c37667`

## 2. Working tree awal
- Banyak perubahan backend/frontend yang sudah ada sebelumnya.
- Revisi ini hanya menyentuh komponen detail tiket PIC di frontend.

## 3. Analisis screenshot
- Card seluruh section menggunakan background dark mode navy yang tidak selaras dengan background halaman.
- Header terlalu padat: semua aksi dalam satu baris tanpa hierarki.
- Status masih memakai raw `STATUS_LABELS` yang menampilkan `reopened` bukan `Dibuka Kembali`.
- Assignment label mencampur Inggris/Indonesia: `PIC Utama (Primary)`.
- Timeline menggunakan `STATUS_LABELS` untuk status history yang seharusnya label publik.
- Action strip berisi 5 link dengan warna berbeda-beda.

## 4. Masalah tampilan sebelumnya
- Background halaman kurang bersih (kuning/pucat dari layout global).
- Setiap card dan modal mengandung `dark:` class yang bercampur.
- Info submission dan assignment belum terstruktur.
- Progress bar tanpa informasi update terakhir.
- Tidak ada section Analisis Supervisor, Informasi Requester, atau Lampiran pekerjaan.
- Timeline gelap dan berat secara visual.

## 5. Perubahan header
- Header baru berisi: nomor tiket, status publik, assignment role, urgency, judul tiket, created/updated date.
- `Tahap Saat Ini` section ditambahkan dengan pesan `nextStepMessage`.
- Back link dan refresh button dipisah rapi di atas header.

## 6. Perubahan status
- Semua status menggunakan `getPublicStatusLabel` bukan `STATUS_LABELS`.
- `reopened` → `Dibuka Kembali`.
- Legenda status raw tidak muncul lagi.

## 7. Perubahan action hierarchy
- **Primary action**: Mulai Pengerjaan / Lanjutkan Pengerjaan / Kirim ke Supervisor (navy/emerald).
- **General actions row**: Tambah Catatan, Perbarui Progress, Upload Hasil (outline button).
- **Conditional actions dropdown**: Minta Info, Menunggu Eksternal, Pengecekan Mandiri, Minta Bantuan, Ajukan Pengalihan.
- Aksi kondisional dikelompokkan dalam dropdown "Aksi Lainnya".

## 8. Detail pengajuan
- Section dinamai `Detail Pengajuan`.
- Tampil: Deskripsi, URL Terdampak, Referensi, Kategori Pengajuan, Sistem, Urgency.
- Fallback `Belum tersedia` bukan `-`.

## 9. Informasi Requester
- Section terpisah di sidebar kanan.
- Tampil: Nama, Kantor/Cabang/Divisi, Tanggal Pengajuan.
- Format tanggal `Belum tersedia` bukan `-`.

## 10. Analisis Supervisor
- Section `Analisis Supervisor` di konten utama.
- Tampil business_impact (ringkasan analisis) dan expected_result.
- Empty state: `Analisis Supervisor belum tersedia.`

## 11. Tim penanganan
- Section dinamai `Tim Penanganan`.
- PIC Utama: badge biru + icon.
- PIC Pendamping: badge ungu + icon, fallback `Belum ada PIC pendamping.`.
- Peran Anda: badge kecil di header.
- Label raw `PRIMARY` / `SECONDARY` tidak tampil, diganti `PIC Utama` / `PIC Pendamping`.

## 12. Progress
- Progress bar putih dengan info kapan terakhir diperbarui (jika ada).
- Tombol `Perbarui Progress` hanya jika diizinkan workflow.

## 13. Catatan
- Catatan pekerjaan tetap menggunakan modal yang sama.
- Internal notes tetap dilabeli dengan `Catatan Internal PIC`.

## 14. Attachment
- Section `Lampiran Pekerjaan & Hasil` ditambahkan.
- List attachment dengan nama, ukuran, tanggal, dan tombol Unduh.
- Empty state: `Belum ada lampiran hasil pengerjaan.`

## 15. Timeline
- Dinamai `Timeline Aktivitas`.
- Menggunakan `getPublicStatusLabel` untuk status history.
- Warna putih, border tipis, icon kecil, garis vertikal ringan.
- Tidak ada dark mode palette yang bercampur.

## 16. Reopened state
- Tidak ada section khusus, namun label status `Dibuka Kembali` dan pesan `nextStepMessage` menangani reopened.

## 17. Loading
- Skeleton putih terang untuk tiap card, bukan spinner saja.
- `aria-busy` untuk aksesibilitas.

## 18. Empty state
- Analisis Supervisor: `Analisis Supervisor belum tersedia.`
- Lampiran: `Belum ada lampiran hasil pengerjaan.`
- Timeline: `Belum ada aktivitas tercatat pada tiket ini.`
- Tidak ada `-` sebagai satunya informasi.

## 19. Error state
- Error API: card merah dengan pesan bersih + tombol back.
- Conflict message: banner amber + tombol `Muat Ulang Data`.

## 20. Responsive
- Grid 2/3 + 1/3 untuk konten dan sidebar.
- Mobile: semua jadi 1 kolom.
- Action buttons wrap normal.
- Dropdown aksi lintas lebar penuh di mobile.

## 21. Accessibility
- Heading h3 untuk tiap section.
- Button memiliki accessible name.
- Icon semua pakai `aria-hidden`.
- Modal dengan backdrop, focus di handle native.

## 22. Permission
- Semua aksi tetap divalidasi dengan `ticket.allowed_actions.includes(action)`. 
- Primary PIC dapat submit approval.
- Secondary PIC tidak melihat Kirim ke Supervisor.
- Backend masih memvalidasi permission (tidak diubah).

## 23. API optimization
- Tidak ada perubahan request endpoint.
- Data yang tidak digunakan (workflow snapshot penuh, permissions list berulang) tidak ditambahkan.

## 24. Backend tests
- `php artisan test`: 379 passed, 2,235 assertions.
- `vendor/bin/pint --test`: passed.

## 25. Frontend tests
- Tidak ada automated frontend test suite yang terdeteksi.
- `npm run typecheck`: passed.
- `npm run build`: passed.

## 26. File yang berubah
- `frontend/src/pages/pic/PicTicketDetail.tsx`
- `frontend/src/components/pic/PicAssignmentSummary.tsx`
- `frontend/src/components/pic/PicActivityTimeline.tsx`

## 27. Error ditemukan
- Kelebihan `</div>` setelah header menyebabkan typecheck error; sudah diperbaiki.
- Unused import `Lock` pada `PicTicketDetail.tsx`; sudah dihapus.

## 28. Pending
- Manual visual check langsung di browser dengan berbagai viewport.
- Jika backend response belum menyertakan `business_impact`/`expected_result` untuk tiket tertentu, section Analisis Supervisor akan menampilkan empty state.

## 29. Risiko
- Jika backend mengubah shape response `ticket.attachments` atau field lainnya, section ini perlu disesuaikan.
- Perubahan ini tidak menyentuh modal/action form lainnya (work note, progress, upload, dll.).

## 30. Status
- PASS
