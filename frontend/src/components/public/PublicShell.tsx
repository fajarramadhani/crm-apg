import type { ReactNode } from 'react'
import { ShieldCheck } from 'lucide-react'

interface PublicShellProps {
  children: ReactNode
  securityLabel?: string
  footerText?: string
}

export function PublicShell({
  children,
  securityLabel = 'Formulir aman dan terverifikasi',
  footerText = 'Tic Hub APG · Informasi pada formulir ini digunakan untuk penanganan pengajuan Anda.',
}: PublicShellProps) {
  return (
    <div className="min-h-screen overflow-x-hidden bg-[radial-gradient(80%_60%_at_50%_-10%,rgba(34,211,238,0.12),transparent_70%),radial-gradient(55%_45%_at_100%_35%,rgba(59,130,246,0.06),transparent_70%),radial-gradient(45%_40%_at_0%_70%,rgba(34,211,238,0.05),transparent_70%),#f4f7fb] text-slate-900">
      <header className="border-b border-white/10 bg-[#0b1f48] text-white shadow-lg shadow-blue-950/10 print:hidden">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
          <div className="flex min-w-0 items-center gap-3">
            <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1 text-[#12367a] shadow-md ring-1 ring-white/20">
              <img src="/logo/apg-logo.png" alt="APG Logo" className="h-full w-full object-contain" />
            </div>
            <div className="min-w-0">
              <p className="truncate text-base font-bold tracking-tight">Tic Hub</p>
              <p className="truncate text-xs text-blue-200">Layanan Dukungan Sistem APG</p>
            </div>
          </div>
          <div className="hidden items-center gap-2 rounded-full bg-white/5 px-3 py-1.5 text-sm text-blue-100 ring-1 ring-white/10 sm:flex">
            <ShieldCheck className="h-4 w-4 text-cyan-300" />
            {securityLabel}
          </div>
        </div>
      </header>
      {children}
      <footer className="border-t border-slate-200 bg-gradient-to-t from-slate-100 to-white px-4 py-6 text-center text-xs text-slate-500 print:hidden">
        {footerText}
      </footer>
    </div>
  )
}