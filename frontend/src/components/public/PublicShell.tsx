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
    <div className="min-h-screen overflow-x-hidden bg-[#f4f7fb] text-slate-900">
      <header className="border-b border-white/10 bg-[#0b1f48] text-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
          <div className="flex min-w-0 items-center gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white font-black text-[#12367a] shadow-lg">
              A
            </div>
            <div className="min-w-0">
              <p className="truncate text-base font-bold tracking-tight">Tic Hub</p>
              <p className="truncate text-xs text-blue-200">Layanan Dukungan Sistem APG</p>
            </div>
          </div>
          <div className="hidden items-center gap-2 text-sm text-blue-100 sm:flex">
            <ShieldCheck className="h-4 w-4 text-cyan-300" />
            {securityLabel}
          </div>
        </div>
      </header>
      {children}
      <footer className="border-t border-slate-200 bg-white px-4 py-6 text-center text-xs text-slate-500">
        {footerText}
      </footer>
    </div>
  )
}
