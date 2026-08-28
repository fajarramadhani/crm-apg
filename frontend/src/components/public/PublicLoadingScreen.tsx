import { Loader2 } from 'lucide-react'

interface PublicLoadingScreenProps {
  label?: string
  hint?: string
}

/**
 * Sablon pemuatan bersama untuk seluruh halaman publik (pengajuan, pelacakan,
 * dan riwayat). Dipakai baik sebagai Suspense fallback (lazy chunk) maupun
 * sebagai indikator pemuatan data di dalam halaman, sehingga tampilannya
 * konsisten di semua link publik.
 */
export function PublicLoadingScreen({
  label = 'Memuat halaman, sebentar ya...',
  hint = 'Mohon tunggu, Tic Hub sedang menyiapkan tampilan untuk Anda.',
}: PublicLoadingScreenProps) {
  return (
    <div
      className="relative min-h-[62vh] w-full overflow-hidden bg-[radial-gradient(70%_55%_at_50%_-5%,rgba(34,211,238,0.28),transparent_60%),radial-gradient(50%_45%_at_12%_8%,rgba(96,165,250,0.22),transparent_55%),radial-gradient(45%_50%_at_88%_92%,rgba(34,211,238,0.14),transparent_60%)]"
      role="status"
      aria-live="polite"
    >
      <div className="relative mx-auto flex min-h-[62vh] w-full max-w-md flex-col items-center justify-center px-6 py-16 text-center">
        <div className="relative mb-10 flex h-28 w-28 items-center justify-center">
          <span className="absolute h-28 w-28 rounded-full border border-blue-900/10" />
          <span className="absolute h-36 w-36 rounded-full border border-cyan-400/10" />
          <span className="absolute h-44 w-44 rounded-full border border-blue-500/5" />
          <span className="absolute h-28 w-28 animate-ping rounded-full bg-cyan-400/10" />
          <span className="absolute h-40 w-40 rounded-full bg-cyan-400/10 blur-2xl" />
          <div className="relative flex h-20 w-20 items-center justify-center overflow-hidden rounded-3xl bg-white p-2 shadow-xl shadow-blue-950/10 ring-1 ring-blue-900/5">
            <img src="/logo/apg-logo.png" alt="APG Logo" className="h-full w-full object-contain" />
          </div>
        </div>

        <p className="text-xs font-bold uppercase tracking-[0.42em] text-[#12367a]/60">Tic Hub</p>
        <h2 className="mt-3 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">{label}</h2>
        <p className="mt-3 max-w-sm text-sm leading-6 text-slate-600">{hint}</p>

        <div className="mt-8 flex w-full max-w-xs flex-col items-center gap-4">
          <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-200/80">
            <div className="global-loader-bar h-full w-1/3 rounded-full bg-gradient-to-r from-cyan-400 via-blue-600 to-[#12367a]" />
          </div>
          <div className="flex items-center gap-2 text-xs font-semibold text-slate-500">
            <Loader2 className="h-4 w-4 animate-spin text-blue-700" />
            <span>Mohon tunggu sebentar</span>
          </div>
        </div>
      </div>
    </div>
  )
}
