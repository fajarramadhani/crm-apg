# Laporan Pembaruan Logo APG CRM

- **Tanggal**: 2026-08-26
- **Status Akhir**: Selesai / Terverifikasi

## Ringkasan Perubahan
Memperbarui aset identitas visual logo APG CRM (Tic Hub) menggunakan logo resmi **APG (Ardana Perkasa Group)** yang diunggah oleh pengguna:
1. Menambahkan file logo resmi di `frontend/public/logo/apg-logo.png` dan `frontend/public/logo/logo-crm.png`.
2. Menghubungkan favicon browser di `frontend/index.html`.
3. Mengganti semua placeholder icon huruf "A" dengan gambar logo resmi APG pada:
   - Sidebar navigasi admin/internal (`Layout.tsx`)
   - Halaman login panel kiri dan header mobile (`Login.tsx`)
   - Global loading screen dan splash screen (`App.tsx`)
   - Global loading overlay (`LoadingContext.tsx`)
   - Header portal formulir publik dan tracking (`PublicShell.tsx`)

## File yang Berubah
- `frontend/public/logo/apg-logo.png` (Baru)
- `frontend/public/logo/logo-crm.png` (Diperbarui)
- `frontend/index.html`
- `frontend/src/components/Layout.tsx`
- `frontend/src/pages/Login.tsx`
- `frontend/src/App.tsx`
- `frontend/src/context/LoadingContext.tsx`
- `frontend/src/components/public/PublicShell.tsx`

## Hasil Verifikasi
- `npm run typecheck`: Berhasil tanpa error (0 errors).
- Dev server Vite: Berjalan normal di `http://localhost:5173`.
