# CRM Public Portal UX Improvement Implementation Report

## 1. Informasi Umum

- Tanggal: 2026-08-28
- Branch: `development`
- Status akhir: **PASS** (typecheck, build lulus; format:check hanya file user lain yang pre-existing belum ter-format)
- Cakupan: Perbaikan UI/UX prioritas tinggi dan menengah pada formulir pengajuan publik, halaman pelacakan tiket, dan halaman riwayat publik — tanpa perubahan backend.

## 2. Ringkasan

Analisa UI/UX sebelumnya mengidentifikasi empat temuan prioritas tinggi dan beberapa prioritas menengah pada bagian public portal (`/request`, `/track/:token`, `/request/history`). Seluruhnya diimplementasikan pada sisi frontend dalam dua iterasi:

Iterasi 1 — prioritas tinggi:
1. **Draft autosave** pada formulir pengajuan publik (mencegah kehilangan data saat refresh/back navigation).
2. **Focus otomatis ke field invalid pertama** saat validasi gagal (aksesibilitas keyboard & screen reader).
3. **Karakter counter** pada deskripsi pengajuan (paritas dengan counter judul).
4. **Perbarui Status + auto-poll 60 detik** pada halaman pelacakan tiket + timestamp "Terakhir diperiksa".
5. **Tombol "Coba muat kembali"** saat gagal memuat daftar cabang pada halaman riwayat publik.

Iterasi 2 — prioritas menengah:
6. **Stepper 3 langkah** (Data pemohon → Detail kebutuhan → Lampiran) dengan status selesai/aktif/tunda.
7. **Sticky submit bar** di layar kecil — muncul hanya bila tombol kirim utama di luar viewport.
8. **Validasi inline (on blur)** — error muncul per field saat user pindah dari field, bukan menunggu submit.
9. **CTA aksi (UAT/Konfirmasi) tampil di atas timeline** pada mobile, tetap di sidebar pada layar besar.
10. **Cetak / Simpan Bukti** pada halaman pelacakan + print stylesheet (header/footer/hero/aside disembunyikan saat print).

Iterasi 3 — polish:
11. **Konsistensi placeholder email** `nama@perusahaan.co.id` di seluruh halaman public (form, tracking, history).
12. **Kontras helper text** dinaikkan (`text-slate-400→500`, `text-slate-500→600`) untuk keterbacaan WCAG pada hint, countdown, dan teks bantu.
13. **Panduan deskripsi interaktif** — 4 chip klik (Kejadian / Langkah dicoba / Hasil diharapkan / Dampak) yang menyisipkan bagian terstruktur ke deskripsi.
14. **Feedback sukses kontekstual** pada dialog aksi — menjelaskan dampak keputusan (UAT diterima/ditolak, konfirmasi diterima/ditolak).
15. **Aksesibilitas honeypot** — atribut `inert` + `aria-hidden` pada wrapper dan input, label redundan dihapus.

## 3. Keputusan Desain

### 3.1 Draft Autosave (localStorage)

- Key: `tic-hub:public-request:draft:v1`, disimpan dengan debounce 500 ms.
- Nilai yang disimpan: seluruh `FormValues` (honeypot `website` sengaja dikosongkan agar yang tersimpan hanya data nyata) + metadata lampiran (`name`, `size`, `lastModified`).
- **Konten file tidak disimpan** (membatasi risiko penyalahgunaan localStorage); saat draft dipulihkan, nama lampiran yang hilang ditampilkan dan diminta dipilih ulang.
- Draft dibersihkan saat submit sukses dan saat "Buat Pengajuan Baru".
- Indikator status kecil ("Menyimpan draft…" / "Draft tersimpan · HH:mm") ditampilkan di atas tombol kirim untuk transparansi.
- Guard `skipNextSave` mencegah autosave menimpa draft (termasuk metadata lampiran) pada render pertama setelah restore.
- Data hanya tersimpan lokal di perangkat pemohon; tidak pernah dikirim sebagai bagian payload.

### 3.2 Focus ke Field Invalid Pertama

- `validate()` kini mengembalikan nama field error pertama (dari urutan DOM `FIELD_ORDER`), bukan boolean.
- `focusField()` mencari elemen target via `FIELD_SELECTORS`; untuk TinyMCE diprioritaskan ke `.tox-tinymce` agar fokus masuk ke editor.
- Fokus elemen (browser otomatis scroll ke elemen); fallback `scrollTo(top)` bila target tidak ditemukan.

### 3.3 Karakter Counter Deskripsi

- Menampilkan jumlah karakter teks terlihat (hasil `ticketDescriptionText`, HTML di-strip) dibanding limit 10.000, konsisten secara visual dengan counter judul.
- Validasi keberlanjutan batas kuantitas tetap mengikuti aturan backend (panjang HTML), sehingga tidak mengubah kontrak API.

### 3.4 Perbarui Status pada Pelacakan Tiket

- Tombol "Perbarui Status" manual yang memanggil `refreshTracking()` yang sudah ada.
- Auto-poll setiap 60 detik, hanya saat tab terlihat (`visibilityState`) dan punya fokus, serta tidak sedang loading/refreshing — aman terhadap rate limit endpoint tracking (30/min per IP).
- Ditambahkan baris "Terakhir diperiksa HH:mm" pada kartu informasi waktu.

### 3.5 Retry pada Halaman Riwayat

- Konsisten dengan halaman formulir: state `optionsAttempt` + tombol "Coba muat kembali" saat fetch options gagal.

### 3.6 Stepper 3 Langkah

- 3 chip status di atas form: selesai (ceklist hijau), aktif (biru terisi, = langkah pertama yang belum lengkap dalam urutan), tunda (abu-abu).
- Konektor antar langkah berubah hijau saat langkah sebelumnya lengkap.
- Lampiran dianggap selesai karena opsional; berfungsi sebagai isyarat "urutkan isian dari atas".

### 3.7 Sticky Submit Bar (Mobile)

- Bar `fixed bottom-0` hanya muncul di bawah `lg` dan hanya saat tombol submit utama tidak terlihat (IntersectionObserver, `rootMargin` 96px).
- Tombol bar memanggil `formRef.current.requestSubmit()` sehingga reuse logika submit + validasi yang sama (bukan duplikasi handler).
- Disembunyikan saat `submitting` dan saat print.

### 3.8 Validasi Inline (On Blur)

- Aturan validasi dipisah ke `getFieldError(field)` (single source of truth) yang dipakai oleh `validate()` (submit) dan `handleBlur()` (on-blur).
- Pada blur, error hanya ditampilkan bila field sudah berisi konten (untuk menangkap error format); error "wajib diisi" untuk field kosong tetap muncul saat submit agar tidak berisik saat user melewati field kosong.

### 3.9 CTA Aksi di Mobile + Cetak Bukti

- `ActionCtaCard` diekstrak menjadi komponen reusable; ditampilkan di atas timeline pada `< lg`, dan di sidebar (kanan) pada `lg+`.
- Tombol "Cetak / Simpan Bukti" memanggil `window.print()`; saat print, header/footer (`PublicShell`), hero banner, CTA, dan sidebar disembunyikan sehingga dokumen cetak berisi kartu tiket + timeline saja.

### 3.10 Konsistensi Placeholder Email

- Seluruh placeholder email disamakan menjadi `nama@perusahaan.co.id` (domain kantor Indonesia) di form, dialog aksi tracking, dan halaman riwayat.

### 3.11 Kontras Helper Text

- Hint, countdown, dan teks bantu dinaikkan satu tingkat warna (`slate-400→500` untuk label opsional, `slate-500→600` untuk paragraf bantu) tetap tanpa mengubah label utama dan ikon dekoratif.

### 3.12 Panduan Deskripsi Interaktif

- 4 chips klik di bawah editor deskripsi; klik menyisipkan `<p>• {bagian}: </p>` ke HTML deskripsi (dalam `valid_elements` editor) dan menghindari duplikasi bila label sudah ada.
- Tabel data statis `DESCRIPTION_TEMPLATES` sebagai satu sumber; `insertDescriptionPrompt()` juga membersihkan error deskripsi.

### 3.13 Feedback Sukses Kontekstual

- Fase sukses dialog aksi kini menampilkan catatan dampak keputusan sesuai kombinasi aksi × hasil (UAT/konfirmasi × diterima/ditolak), meningkatkan transparansi proses.

### 3.14 Aksesibilitas Honeypot

- Wrapper honeypot diberi `inert` (React 19) + `aria-hidden`, input diberi `aria-hidden` + `tabIndex=-1`, label sengaja dihapus — tidak fokusable dan tidak diumumkan screen reader, tetap berfungsi sebagai bot trap di sisi server (`website` max:0).

## 4. File yang Berubah

- `frontend/src/pages/public/PublicRequest.tsx` — draft autosave, fokus error, counter deskripsi, banner restore draft, stepper, sticky submit bar, validasi on-blur (`getFieldError`/`handleBlur`), chip panduan deskripsi, kontras helper, honeypot `inert`.
- `frontend/src/pages/public/PublicTicketTracking.tsx` — tombol & auto-poll refresh, timestamp "Terakhir diperiksa", `ActionCtaCard` extract + posisi mobile, tombol cetak, `print:hidden`, feedback sukses kontekstual, placeholder email, kontras helper.
- `frontend/src/pages/public/PublicRequestHistory.tsx` — retry button saat options gagal dimuat, placeholder email, kontras helper.
- `frontend/src/components/public/PublicShell.tsx` — `print:hidden` pada header & footer.

Tidak ada perubahan pada backend, API contract, atau data model.

## 5. Hasil Verifikasi

- `npm run typecheck` — PASS.
- `npm run build` (tsc + vite build) — PASS.
- `npm run format:check` — file yang diubah PASS. Catatan: satu file di working tree milik pekerjaan lain (`frontend/src/pages/qa/QADashboard.tsx`) belum ter-format dan tidak diubah pada pekerjaan ini.

## 6. Risiko & Catatan

- Draft autosave menyimpan data pribadi pemohon di localStorage perangkat; ini bersifat lokal dan standar untuk pola draft, namun perlu disadari. Tidak dimuat ke payload selain dari isi form itu sendiri.
- Auto-poll menambah 1 request per menit per pemohon pada endpoint tracking; masih jauh di bawah rate limit yang berlaku.
- Sticky submit bar memakai `requestSubmit()`; tidak memicu double-submit karena guard `submitting` tetap ada di handler `submit`.
- Manual browser viewport matrix dan uji interaksi visual belum dijalankan (tidak ada automated runner E2E di repo).