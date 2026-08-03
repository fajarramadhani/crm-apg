import { useEffect, useState } from 'react'
import { AlertTriangle, CalendarDays, CheckCircle2, Clock3, MapPin, MessageSquareText, Tag } from 'lucide-react'
import { useParams } from 'react-router-dom'
import { PublicShell } from '../../components/public/PublicShell'
import { publicTicketService, type PublicTicketTracking } from '../../services/publicTicketService'

function formatDate(value: string) {
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? 'Waktu tidak tersedia'
    : date.toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' })
}

function TrackingSkeleton() {
  return (
    <div className="animate-pulse space-y-6" role="status" aria-label="Memuat status tiket">
      <div className="h-40 rounded-3xl bg-slate-200" />
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div className="h-96 rounded-3xl bg-slate-200" />
        <div className="h-72 rounded-3xl bg-slate-200" />
      </div>
    </div>
  )
}

export default function PublicTicketTrackingPage() {
  const { token = '' } = useParams()
  const [ticket, setTicket] = useState<PublicTicketTracking | null>(null)
  const [loading, setLoading] = useState(true)
  const [failed, setFailed] = useState(false)
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    const existing = document.head.querySelector<HTMLMetaElement>('meta[name="referrer"]')
    const previous = existing?.content
    const meta = existing ?? document.createElement('meta')
    meta.name = 'referrer'
    meta.content = 'no-referrer'
    if (!existing) document.head.appendChild(meta)

    return () => {
      if (existing && previous !== undefined) existing.content = previous
      else meta.remove()
    }
  }, [])

  useEffect(() => {
    let active = true
    setLoading(true)
    setFailed(false)
    setTicket(null)

    if (!token) {
      setLoading(false)
      setFailed(true)
      return () => {
        active = false
      }
    }

    publicTicketService
      .track(token)
      .then((result) => {
        if (active) setTicket(result)
      })
      .catch(() => {
        if (active) setFailed(true)
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [attempt, token])

  return (
    <PublicShell
      securityLabel="Pelacakan tiket terlindungi"
      footerText="Tic Hub APG · Informasi status ditampilkan khusus melalui link pelacakan Anda."
    >
      <main className="min-h-[calc(100vh-145px)]">
        <section className="relative overflow-hidden bg-[#0b1f48] px-4 pb-32 pt-12 text-white sm:px-6 sm:pt-16">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_15%,rgba(34,211,238,0.18),transparent_30%),radial-gradient(circle_at_10%_90%,rgba(59,130,246,0.22),transparent_35%)]" />
          <div className="relative mx-auto max-w-6xl">
            <p className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-300">Status Pengajuan</p>
            <h1 className="mt-3 text-3xl font-black tracking-tight sm:text-5xl">Pantau perkembangan tiket Anda</h1>
            <p className="mt-4 max-w-2xl text-sm leading-7 text-blue-100 sm:text-base">
              Informasi berikut diperbarui oleh tim Tic Hub selama proses penanganan.
            </p>
          </div>
        </section>

        <div className="relative mx-auto -mt-20 max-w-6xl px-4 pb-14 sm:px-6 lg:px-8">
          {loading && <TrackingSkeleton />}

          {!loading && failed && (
            <section
              className="mx-auto max-w-2xl rounded-3xl border border-slate-200 bg-white p-7 text-center shadow-xl shadow-slate-300/30 sm:p-10"
              role="alert"
            >
              <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                <AlertTriangle className="h-7 w-7" />
              </div>
              <h2 className="mt-5 text-2xl font-bold text-slate-950">Status tiket tidak dapat ditampilkan</h2>
              <p className="mx-auto mt-3 max-w-lg text-sm leading-6 text-slate-600">
                Link pelacakan mungkin tidak valid, sudah kedaluwarsa, atau telah dinonaktifkan. Periksa kembali link
                yang Anda terima.
              </p>
              <button
                type="button"
                onClick={() => setAttempt((current) => current + 1)}
                className="mt-6 rounded-xl bg-[#12367a] px-5 py-3 text-sm font-bold text-white hover:bg-[#0d2a63]"
              >
                Coba Lagi
              </button>
            </section>
          )}

          {!loading && ticket && (
            <div className="space-y-6">
              <section className="overflow-hidden rounded-3xl border border-blue-200 bg-white shadow-xl shadow-slate-300/30">
                <div className="grid gap-6 bg-gradient-to-br from-[#12367a] to-[#0b1f48] p-6 text-white sm:p-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-center">
                  <div className="min-w-0">
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-blue-200">{ticket.ticket_number}</p>
                    <h2 className="mt-3 break-words text-2xl font-black sm:text-3xl">{ticket.title}</h2>
                  </div>
                  <div className="rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-blue-200">Status saat ini</p>
                    <div className="mt-2 flex items-start gap-3">
                      <CheckCircle2 className="mt-0.5 h-6 w-6 shrink-0 text-cyan-300" />
                      <div>
                        <p className="text-lg font-bold">{ticket.status.label || 'Status sedang diproses'}</p>
                        {ticket.status.description && (
                          <p className="mt-1 text-sm leading-6 text-blue-100">{ticket.status.description}</p>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
                <dl className="grid gap-5 p-6 sm:grid-cols-3 sm:p-8">
                  {[
                    { Icon: MapPin, label: 'Cabang', value: ticket.branch },
                    { Icon: Tag, label: 'Kategori', value: ticket.category },
                    { Icon: CalendarDays, label: 'Diajukan', value: formatDate(ticket.submitted_at) },
                  ].map(({ Icon, label, value }) => (
                    <div key={label} className="flex min-w-0 gap-3">
                      <Icon className="mt-0.5 h-5 w-5 shrink-0 text-blue-700" />
                      <div className="min-w-0">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
                        <dd className="mt-1 break-words text-sm font-semibold text-slate-900">{value}</dd>
                      </div>
                    </div>
                  ))}
                </dl>
              </section>

              <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                  <h2 className="text-xl font-bold text-slate-950">Riwayat status</h2>
                  <p className="mt-1 text-sm text-slate-500">Tahapan penanganan yang dapat dilihat oleh pemohon.</p>
                  {ticket.timeline.length ? (
                    <ol className="mt-7 space-y-0">
                      {ticket.timeline.map((item, index) => (
                        <li
                          key={`${item.code}-${item.occurred_at}-${index}`}
                          className="relative flex gap-4 pb-7 last:pb-0"
                        >
                          {index < ticket.timeline.length - 1 && (
                            <span className="absolute left-[11px] top-6 h-[calc(100%-8px)] w-px bg-slate-200" />
                          )}
                          <span
                            className={`relative mt-1 h-6 w-6 shrink-0 rounded-full border-4 ${item.is_current ? 'border-blue-200 bg-blue-700' : 'border-slate-100 bg-slate-400'}`}
                          />
                          <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                              <h3 className="font-bold text-slate-900">{item.label || 'Pembaruan status'}</h3>
                              {item.is_current && (
                                <span className="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-blue-800">
                                  Saat ini
                                </span>
                              )}
                            </div>
                            {item.description && (
                              <p className="mt-1 text-sm leading-6 text-slate-600">{item.description}</p>
                            )}
                            <p className="mt-2 text-xs font-medium text-slate-500">{formatDate(item.occurred_at)}</p>
                          </div>
                        </li>
                      ))}
                    </ol>
                  ) : (
                    <p className="mt-6 rounded-2xl bg-slate-50 p-4 text-sm text-slate-600">
                      Riwayat status belum tersedia.
                    </p>
                  )}
                </section>

                <aside className="space-y-6">
                  <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center gap-3">
                      <MessageSquareText className="h-6 w-6 text-blue-700" />
                      <h2 className="text-lg font-bold text-slate-950">Pembaruan untuk pemohon</h2>
                    </div>
                    {ticket.requester_updates.length ? (
                      <ul className="mt-5 space-y-4">
                        {ticket.requester_updates.map((update, index) => (
                          <li key={`${update.occurred_at}-${index}`} className="rounded-2xl bg-blue-50 p-4">
                            <p className="whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">
                              {update.message}
                            </p>
                            <p className="mt-3 text-xs font-medium text-blue-800">{formatDate(update.occurred_at)}</p>
                          </li>
                        ))}
                      </ul>
                    ) : (
                      <p className="mt-5 text-sm leading-6 text-slate-600">Belum ada pembaruan tambahan untuk Anda.</p>
                    )}
                  </section>
                  <div className="flex gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                    <Clock3 className="mt-0.5 h-5 w-5 shrink-0 text-slate-500" />
                    <p>
                      Terakhir diperbarui
                      <strong className="mt-1 block text-slate-900">{formatDate(ticket.last_updated_at)}</strong>
                    </p>
                  </div>
                </aside>
              </div>
            </div>
          )}
        </div>
      </main>
    </PublicShell>
  )
}
