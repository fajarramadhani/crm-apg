# Laporan Implementasi — Penyatuan Loading Screen Halaman Publik

- **Tanggal:** 2026-08-28
- **Branch:** `development`
- **Status akhir:** Selesai — typecheck, format check, dan build lulus

## Ringkasan

Seluruh halaman publik Tic Hub (pengajuan `/request`, pelacakan tiket `/track/:token`,
dan riwayat `/request/history`) sebelumnya menampilkan pola pemuatan yang **tidak
konsisten**:

1. **`/request`** — selama opsi formulir dimuat, form dirender apa adanya dengan select
   nonaktif berlabel "Memuat cabang...", tanpa layar pemuatan khusus.
2. **`/track/:token`** — memakai `TrackingSkeleton` berupa blok abu-abu berdenyut
   (`animate-pulse`) tanpa identitas visual.
3. **`/request/history`** — memakai kartu polos dengan satu ikon spinner.
4. **Suspense fallback** (saat lazy chunk dimuat) — memakai `GlobalLoader` gelap penuh
   layar yang menabrak identitas visual halaman publik yang terang.

Semua pola tersebut kini disatukan menjadi satu komponen `PublicLoadingScreen` yang
konsisten di seluruh link publik.

## Keputusan desain dan alasannya

- **Komponen bersama baru `PublicLoadingScreen`** di `components/public/`:
  satu sumber kebenaran untuk seluruh pemuatan di area publik, sehingga perbaikan
  berikutnya cukup dilakukan di satu tempat.
- **Identitas visual publik yang seragam**: latar gradien lembut cyan/biru di atas
  `#f4f7fb`, logo APG dalam tile putih dengan dering animasi dan *ping* halus, label
  "Tic Hub", judul penuh huruf tebal, bar progres yang memakai keyframe
  `global-loader-bar` yang sudah ada, dan baris "Mohon tunggu sebentar" dengan spinner.
  Ini selaras dengan header/footer `PublicShell` dan hero biru `#0b1f48` halaman publik.
- **Props `label` dan `hint`** agar konteks setiap halaman tetap komunikatif (misalnya
  "Menyiapkan formulir pengajuan..." vs "Memuat status tiket...").
- **Dipakai di dua konteks**:
  - sebagai **fallback `Suspense`** (lazy chunk) di `App.tsx`, dibungkus `PublicShell`
    agar header/footer tetap tampil selama muat pertama;
  - sebagai **indikator pemuatan data di dalam halaman** pada ketiga halaman publik.
- **Aksesibilitas**: `role="status"` + `aria-live="polite"`; animasi dinonaktifkan
  otomatis lewat aturan `prefers-reduced-motion` global yang sudah ada di `index.css`.
- **Menghapus duplikasi**: `TrackingSkeleton` pada PublicTicketTracking dihapus dan
  digantikan komponen bersama.

## Daftar file yang berubah

- `frontend/src/components/public/PublicLoadingScreen.tsx` — **baru**; komponen loading
  bersama untuk halaman publik.
- `frontend/src/App.tsx` — ganti fallback Suspense tiga rute publik menjadi
  `PublicLoadingScreen` (dibungkus `PublicShell`).
- `frontend/src/pages/public/PublicRequest.tsx` — tampilkan `PublicLoadingScreen`
  selama `loadingOptions` aktif, menggantikan placeholder select "Memuat cabang...".
- `frontend/src/pages/public/PublicTicketTracking.tsx` — ganti `TrackingSkeleton`
  dengan `PublicLoadingScreen`; hapus fungsi `TrackingSkeleton`.
- `frontend/src/pages/public/PublicRequestHistory.tsx` — ganti kartu spinner
  "Memuat riwayat pengajuan" dengan `PublicLoadingScreen`.

## Hasil verifikasi

| Pemeriksaan | Perintah | Hasil |
| --- | --- | --- |
| TypeScript | `npm run typecheck` | ✅ lulus |
| Format | `npm run format:check` | ✅ lulus |
| Build | `npm run build` | ✅ lulus (entry chunk gabungan ~301 kB) |

## Catatan risiko

Perubahan bersifat kosmetik (UI/UX) pada area publik dan tidak memengaruhi logika
identity, otorisasi, workflow, SLA, atau audit. Tidak ada baris pada
`docs/known-issues-and-risks.md` yang perlu diperbarui.
