# Phase 1.1 — Login Page Revision

## Arahan revisi

- Menghapus seluruh bagian Demo Account dari halaman Login.
- Menghapus statistik Total Tiket Bulan Ini, SLA Compliance Rate, dan Rata-rata Resolusi.
- Mengganti branding APG Enterprise menjadi Tic Hub hanya pada halaman Login.
- Mempertahankan tampilan, warna, tipografi, background, serta simulasi login yang sudah ada.

## File yang diubah

- `src/pages/Login.tsx`
- `docs/phase-1-1-login-revision.md`

## Penyesuaian layout

Panel informasi desktop dipusatkan secara vertikal setelah kartu statistik dihapus. Form login tetap berada di tengah panel kanan dan menjadi fokus utama. Pada mobile, kartu login tetap menggunakan lebar responsif yang sudah ada dan branding diperbarui menjadi Tic Hub.

## Ukuran layar yang diuji

- Desktop: 1440 × 900
- Mobile: 390 × 844

## Hasil verifikasi

- Type-check (`corepack pnpm typecheck`): lulus.
- Format check (`corepack pnpm format:check`): lulus.
- Production build (`corepack pnpm build`): lulus; terdapat peringatan ukuran chunk Vite yang sudah ada dan tidak memblokir build.
- Git whitespace check (`git diff --check`): lulus.
- Desktop 1440 × 900: lulus; layout seimbang, form terpusat, dan tidak ada horizontal overflow.
- Mobile 390 × 844: lulus; card login tampil utuh, mudah dibaca, dan tidak ada horizontal overflow.
- Pemeriksaan konten: Tic Hub tampil; Demo Account dan ketiga statistik tidak tampil.
- Browser console: tidak ditemukan error aplikasi utama.
