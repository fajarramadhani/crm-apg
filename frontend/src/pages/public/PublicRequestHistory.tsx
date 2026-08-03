import { useEffect, useState, type FormEvent } from 'react'
import {
  AlertCircle,
  ArrowLeft,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  Clock3,
  FileSearch,
  Loader2,
  Mail,
  MapPin,
  RefreshCw,
  ShieldCheck,
  Tag,
} from 'lucide-react'
import { ApiRequestError } from '../../api/client'
import { PublicShell } from '../../components/public/PublicShell'
import {
  publicTicketService,
  type PublicFormOption,
  type PublicTicketHistoryChallenge,
  type PublicTicketHistoryItem,
  type PublicTicketHistoryPagination,
} from '../../services/publicTicketService'

type Phase = 'identity' | 'otp' | 'loading' | 'history' | 'expired'

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const GENERIC_CHALLENGE_ERROR = 'Verifikasi tidak dapat diproses. Periksa data Anda atau coba lagi nanti.'
const GENERIC_CODE_ERROR = 'Kode tidak valid, kedaluwarsa, atau tidak dapat digunakan lagi. Silakan coba kembali.'
const RATE_LIMIT_ERROR = 'Terlalu banyak percobaan. Tunggu beberapa saat sebelum mencoba kembali.'

function secondsUntil(value?: string) {
  if (!value) return 0
  const time = new Date(value).getTime()
  return Number.isNaN(time) ? 0 : Math.max(0, Math.ceil((time - Date.now()) / 1000))
}

function formatCountdown(seconds: number) {
  const minutes = Math.floor(seconds / 60)
  return `${String(minutes).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`
}

function formatDate(value: string) {
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? 'Waktu tidak tersedia'
    : date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
}

function isRateLimited(error: unknown) {
  return error instanceof ApiRequestError && error.status === 429
}

export default function PublicRequestHistory() {
  const [phase, setPhase] = useState<Phase>('identity')
  const [branches, setBranches] = useState<PublicFormOption[]>([])
  const [optionsLoading, setOptionsLoading] = useState(true)
  const [branchId, setBranchId] = useState('')
  const [email, setEmail] = useState('')
  const [challenge, setChallenge] = useState<PublicTicketHistoryChallenge | null>(null)
  const [code, setCode] = useState('')
  const [accessToken, setAccessToken] = useState<string | null>(null)
  const [tickets, setTickets] = useState<PublicTicketHistoryItem[]>([])
  const [pagination, setPagination] = useState<PublicTicketHistoryPagination>()
  const [submitting, setSubmitting] = useState(false)
  const [loadingTicket, setLoadingTicket] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [now, setNow] = useState(Date.now())

  const resendSeconds = secondsUntil(challenge?.resend_available_at)
  const expirySeconds = secondsUntil(challenge?.expires_at)

  useEffect(() => {
    let active = true
    publicTicketService
      .options()
      .then((options) => {
        if (active) setBranches(options.branches)
      })
      .catch(() => {
        if (active) setError('Daftar cabang tidak dapat dimuat. Silakan muat ulang halaman.')
      })
      .finally(() => {
        if (active) setOptionsLoading(false)
      })
    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    if (phase !== 'otp') return
    const timer = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [phase])

  const clearVerification = () => {
    setBranchId('')
    setEmail('')
    setChallenge(null)
    setCode('')
    setAccessToken(null)
    setTickets([])
    setPagination(undefined)
    setLoadingTicket(null)
  }

  const expireSession = () => {
    clearVerification()
    setError('')
    setNotice('')
    setPhase('expired')
  }

  const loadHistory = async (token: string, page = 1) => {
    setPhase('loading')
    setError('')
    try {
      const result = await publicTicketService.history(token, page)
      setTickets(result.tickets)
      setPagination(result.pagination)
      setPhase('history')
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (caught) {
      if (caught instanceof ApiRequestError && caught.status === 401) {
        expireSession()
        return
      }
      setError(isRateLimited(caught) ? RATE_LIMIT_ERROR : 'Riwayat pengajuan tidak dapat dimuat. Silakan coba lagi.')
      setPhase('history')
    }
  }

  const requestChallenge = async () => {
    const selectedBranch = Number(branchId)
    if (!selectedBranch || !EMAIL_PATTERN.test(email.trim())) {
      setError('Pilih cabang dan masukkan alamat email yang valid.')
      return
    }

    setSubmitting(true)
    setError('')
    setNotice('')
    try {
      const result = await publicTicketService.requestHistoryChallenge(selectedBranch, email.trim().toLowerCase())
      setChallenge(result)
      setCode('')
      setNow(Date.now())
      setNotice('Jika data sesuai, petunjuk verifikasi telah diproses untuk tujuan yang ditampilkan.')
      setPhase('otp')
    } catch (caught) {
      setError(isRateLimited(caught) ? RATE_LIMIT_ERROR : GENERIC_CHALLENGE_ERROR)
    } finally {
      setSubmitting(false)
    }
  }

  const submitIdentity = (event: FormEvent) => {
    event.preventDefault()
    void requestChallenge()
  }

  const submitCode = async (event: FormEvent) => {
    event.preventDefault()
    if (!challenge || !/^\d{6}$/.test(code)) {
      setError('Masukkan 6 digit kode verifikasi.')
      return
    }

    setSubmitting(true)
    setError('')
    try {
      const result = await publicTicketService.verifyHistoryChallenge(challenge.challenge_token, code)
      setAccessToken(result.access_token)
      setChallenge(null)
      setCode('')
      setNotice('')
      await loadHistory(result.access_token)
    } catch (caught) {
      setError(isRateLimited(caught) ? RATE_LIMIT_ERROR : GENERIC_CODE_ERROR)
    } finally {
      setSubmitting(false)
    }
  }

  const resend = async () => {
    if (resendSeconds > 0 || submitting) return
    await requestChallenge()
  }

  const changeIdentity = () => {
    setChallenge(null)
    setCode('')
    setError('')
    setNotice('')
    setPhase('identity')
  }

  const openTicket = async (ticketNumber: string) => {
    if (!accessToken) {
      expireSession()
      return
    }
    setLoadingTicket(ticketNumber)
    setError('')
    try {
      const result = await publicTicketService.createHistoryTrackingLink(accessToken, ticketNumber)
      if (!result.tracking_path.startsWith('/track/')) throw new Error('Invalid tracking path')
      const destination = new URL(result.tracking_path, window.location.origin)
      if (destination.origin !== window.location.origin) throw new Error('Invalid tracking origin')
      window.location.assign(`${destination.pathname}${destination.search}${destination.hash}`)
    } catch (caught) {
      if (caught instanceof ApiRequestError && caught.status === 401) {
        expireSession()
        return
      }
      setError(isRateLimited(caught) ? RATE_LIMIT_ERROR : 'Detail tiket tidak dapat dibuka. Silakan coba lagi.')
    } finally {
      setLoadingTicket(null)
    }
  }

  const currentPage = pagination?.current_page ?? 1
  const lastPage = pagination?.last_page ?? 1

  return (
    <PublicShell
      securityLabel="Riwayat dilindungi verifikasi email"
      footerText="Tic Hub APG · Verifikasi hanya berlaku selama halaman ini tetap terbuka."
    >
      <main className="min-h-[calc(100vh-145px)]">
        <section className="relative overflow-hidden bg-[#0b1f48] px-4 pb-28 pt-10 text-white sm:px-6 sm:pb-32 sm:pt-14">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_85%_10%,rgba(34,211,238,0.18),transparent_30%),radial-gradient(circle_at_15%_90%,rgba(59,130,246,0.2),transparent_35%)]" />
          <div className="relative mx-auto max-w-5xl">
            <a
              href="/request"
              className="inline-flex items-center gap-2 text-sm font-semibold text-blue-100 hover:text-white"
            >
              <ArrowLeft className="h-4 w-4" /> Kembali ke formulir pengajuan
            </a>
            <p className="mt-8 text-xs font-bold uppercase tracking-[0.22em] text-cyan-300">Riwayat Publik</p>
            <h1 className="mt-3 max-w-3xl text-3xl font-black tracking-tight sm:text-5xl">Temukan pengajuan Anda</h1>
            <p className="mt-4 max-w-2xl text-sm leading-7 text-blue-100 sm:text-base">
              Verifikasi cabang dan email untuk melihat daftar pengajuan tanpa masuk ke akun Tic Hub.
            </p>
          </div>
        </section>

        <div className="relative mx-auto -mt-20 max-w-5xl px-4 pb-14 sm:px-6 lg:px-8">
          {(phase === 'identity' || phase === 'otp') && (
            <section className="mx-auto max-w-2xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-300/30">
              <div className="border-b border-slate-100 px-6 py-6 sm:px-9">
                <div className="flex items-center gap-3">
                  <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-800">
                    {phase === 'identity' ? <Mail className="h-6 w-6" /> : <ShieldCheck className="h-6 w-6" />}
                  </div>
                  <div>
                    <p className="text-xs font-bold uppercase tracking-wide text-blue-700">
                      Langkah {phase === 'identity' ? '1 dari 2' : '2 dari 2'}
                    </p>
                    <h2 className="text-xl font-bold text-slate-950">
                      {phase === 'identity' ? 'Verifikasi identitas' : 'Masukkan kode verifikasi'}
                    </h2>
                  </div>
                </div>
              </div>

              {phase === 'identity' ? (
                <form onSubmit={submitIdentity} className="space-y-5 p-6 sm:p-9" noValidate>
                  <div>
                    <label htmlFor="history-branch" className="text-sm font-bold text-slate-800">
                      Cabang
                    </label>
                    <select
                      id="history-branch"
                      value={branchId}
                      onChange={(event) => setBranchId(event.target.value)}
                      disabled={optionsLoading || submitting}
                      required
                      className="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 outline-none focus:border-blue-700 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100"
                    >
                      <option value="">{optionsLoading ? 'Memuat cabang...' : 'Pilih cabang'}</option>
                      {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                          {branch.name}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label htmlFor="history-email" className="text-sm font-bold text-slate-800">
                      Email yang digunakan saat mengajukan
                    </label>
                    <input
                      id="history-email"
                      type="email"
                      inputMode="email"
                      autoComplete="email"
                      value={email}
                      onChange={(event) => setEmail(event.target.value)}
                      disabled={submitting}
                      required
                      placeholder="nama@perusahaan.com"
                      className="mt-2 block w-full rounded-xl border border-slate-300 px-3 py-3 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:border-blue-700 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100"
                    />
                  </div>
                  <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                    Riwayat melalui WhatsApp belum tersedia. Jika pengajuan Anda hanya memakai nomor telepon, gunakan
                    link tracking yang diterima saat pengajuan dibuat.
                  </div>
                  {error && <ErrorMessage message={error} />}
                  <button
                    type="submit"
                    disabled={submitting || optionsLoading || !branches.length}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#12367a] px-5 py-3 text-sm font-bold text-white hover:bg-[#0d2a63] disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                    Lanjutkan Verifikasi
                  </button>
                </form>
              ) : (
                <form onSubmit={submitCode} className="space-y-5 p-6 sm:p-9" noValidate>
                  <div className="rounded-2xl bg-blue-50 p-4 text-sm leading-6 text-slate-700">
                    {notice && <p>{notice}</p>}
                    <p className={notice ? 'mt-2' : ''}>
                      Tujuan: <strong className="text-slate-950">{challenge?.masked_destination}</strong>
                    </p>
                  </div>
                  <div>
                    <label htmlFor="history-code" className="text-sm font-bold text-slate-800">
                      Kode 6 digit
                    </label>
                    <input
                      id="history-code"
                      value={code}
                      onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
                      inputMode="numeric"
                      autoComplete="one-time-code"
                      pattern="[0-9]{6}"
                      maxLength={6}
                      autoFocus
                      disabled={submitting}
                      className="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-center font-mono text-2xl font-bold tracking-[0.35em] text-slate-950 outline-none focus:border-blue-700 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100"
                    />
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs font-medium text-slate-500">
                      <span>Kode berakhir dalam {formatCountdown(expirySeconds)}</span>
                      <span>{now ? `Kirim ulang dalam ${formatCountdown(resendSeconds)}` : ''}</span>
                    </div>
                  </div>
                  {expirySeconds === 0 && (
                    <p className="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                      Kode telah kedaluwarsa. Kirim ulang untuk memperoleh kode baru.
                    </p>
                  )}
                  {error && <ErrorMessage message={error} />}
                  <button
                    type="submit"
                    disabled={submitting || code.length !== 6 || expirySeconds === 0}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#12367a] px-5 py-3 text-sm font-bold text-white hover:bg-[#0d2a63] disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                    Verifikasi dan Lihat Riwayat
                  </button>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <button
                      type="button"
                      onClick={() => void resend()}
                      disabled={submitting || resendSeconds > 0}
                      className="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-bold text-blue-900 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      <RefreshCw className="h-4 w-4" /> Kirim Ulang Kode
                    </button>
                    <button
                      type="button"
                      onClick={changeIdentity}
                      disabled={submitting}
                      className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                    >
                      Ubah Identitas
                    </button>
                  </div>
                </form>
              )}
            </section>
          )}

          {phase === 'loading' && (
            <section
              className="rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-xl shadow-slate-300/30"
              role="status"
            >
              <Loader2 className="mx-auto h-9 w-9 animate-spin text-blue-700" />
              <h2 className="mt-4 text-lg font-bold text-slate-950">Memuat riwayat pengajuan</h2>
              <p className="mt-2 text-sm text-slate-500">Mohon tunggu sebentar.</p>
            </section>
          )}

          {phase === 'expired' && (
            <section className="mx-auto max-w-2xl rounded-3xl border border-slate-200 bg-white p-7 text-center shadow-xl shadow-slate-300/30 sm:p-10">
              <AlertCircle className="mx-auto h-12 w-12 text-amber-600" />
              <h2 className="mt-5 text-2xl font-bold text-slate-950">Sesi verifikasi telah berakhir</h2>
              <p className="mt-3 text-sm leading-6 text-slate-600">
                Verifikasi ulang untuk melihat riwayat pengajuan Anda.
              </p>
              <button
                type="button"
                onClick={() => setPhase('identity')}
                className="mt-6 rounded-xl bg-[#12367a] px-5 py-3 text-sm font-bold text-white hover:bg-[#0d2a63]"
              >
                Verifikasi Ulang
              </button>
            </section>
          )}

          {phase === 'history' && (
            <div className="space-y-5">
              <section className="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-300/30 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div>
                  <p className="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Hasil Verifikasi</p>
                  <h2 className="mt-2 text-2xl font-black text-slate-950">Riwayat pengajuan Anda</h2>
                  {pagination && <p className="mt-1 text-sm text-slate-500">{pagination.total} pengajuan ditemukan</p>}
                </div>
                <button
                  type="button"
                  onClick={expireSession}
                  className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"
                >
                  Akhiri Sesi
                </button>
              </section>

              {error && (
                <div className="rounded-2xl border border-red-200 bg-red-50 p-4">
                  <ErrorMessage message={error} />
                  {accessToken && (
                    <button
                      type="button"
                      onClick={() => void loadHistory(accessToken, currentPage)}
                      className="mt-3 text-sm font-bold text-red-800 underline"
                    >
                      Coba muat kembali
                    </button>
                  )}
                </div>
              )}

              {!error && tickets.length === 0 && (
                <section className="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm sm:p-12">
                  <FileSearch className="mx-auto h-12 w-12 text-slate-400" />
                  <h3 className="mt-4 text-xl font-bold text-slate-950">Belum ada pengajuan</h3>
                  <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">
                    Tidak ada pengajuan yang dapat ditampilkan untuk identitas terverifikasi ini.
                  </p>
                </section>
              )}

              {tickets.map((ticket) => (
                <HistoryCard
                  key={ticket.ticket_number}
                  ticket={ticket}
                  loading={loadingTicket === ticket.ticket_number}
                  disabled={loadingTicket !== null}
                  onOpen={() => void openTicket(ticket.ticket_number)}
                />
              ))}

              {pagination && lastPage > 1 && (
                <nav
                  className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-3"
                  aria-label="Navigasi halaman riwayat"
                >
                  <button
                    type="button"
                    onClick={() => accessToken && void loadHistory(accessToken, currentPage - 1)}
                    disabled={currentPage <= 1}
                    className="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-bold text-blue-900 hover:bg-blue-50 disabled:cursor-not-allowed disabled:text-slate-300"
                  >
                    <ChevronLeft className="h-4 w-4" /> Sebelumnya
                  </button>
                  <span className="text-sm font-semibold text-slate-600">
                    Halaman {currentPage} dari {lastPage}
                  </span>
                  <button
                    type="button"
                    onClick={() => accessToken && void loadHistory(accessToken, currentPage + 1)}
                    disabled={currentPage >= lastPage}
                    className="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-bold text-blue-900 hover:bg-blue-50 disabled:cursor-not-allowed disabled:text-slate-300"
                  >
                    Berikutnya <ChevronRight className="h-4 w-4" />
                  </button>
                </nav>
              )}
            </div>
          )}
        </div>
      </main>
    </PublicShell>
  )
}

function ErrorMessage({ message }: { message: string }) {
  return (
    <p role="alert" className="flex items-start gap-2 text-sm font-semibold text-red-700">
      <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" /> {message}
    </p>
  )
}

function HistoryCard({
  ticket,
  loading,
  disabled,
  onOpen,
}: {
  ticket: PublicTicketHistoryItem
  loading: boolean
  disabled: boolean
  onOpen: () => void
}) {
  return (
    <article className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
      <div className="grid gap-6 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_260px] lg:items-center">
        <div className="min-w-0">
          <p className="font-mono text-xs font-bold uppercase tracking-wide text-blue-700">{ticket.ticket_number}</p>
          <h3 className="mt-2 break-words text-xl font-black text-slate-950 sm:text-2xl">{ticket.title}</h3>
          <dl className="mt-5 grid gap-4 text-sm sm:grid-cols-3">
            <div className="flex min-w-0 gap-2">
              <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
              <div>
                <dt className="text-xs text-slate-500">Cabang</dt>
                <dd className="break-words font-semibold text-slate-800">{ticket.branch}</dd>
              </div>
            </div>
            <div className="flex min-w-0 gap-2">
              <Tag className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
              <div>
                <dt className="text-xs text-slate-500">Kategori</dt>
                <dd className="break-words font-semibold text-slate-800">{ticket.category}</dd>
              </div>
            </div>
            <div className="flex min-w-0 gap-2">
              <CalendarDays className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
              <div>
                <dt className="text-xs text-slate-500">Diajukan</dt>
                <dd className="font-semibold text-slate-800">{formatDate(ticket.submitted_at)}</dd>
              </div>
            </div>
          </dl>
        </div>
        <div className="rounded-2xl border border-blue-100 bg-blue-50 p-5">
          <p className="text-xs font-bold uppercase tracking-wide text-blue-700">Status saat ini</p>
          <p className="mt-2 font-bold text-slate-950">{ticket.status.label}</p>
          {ticket.status.description && (
            <p className="mt-1 text-sm leading-6 text-slate-600">{ticket.status.description}</p>
          )}
          <p className="mt-3 flex items-center gap-1.5 text-xs text-slate-500">
            <Clock3 className="h-3.5 w-3.5" /> Diperbarui {formatDate(ticket.last_updated_at)}
          </p>
          <button
            type="button"
            onClick={onOpen}
            disabled={disabled}
            className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-[#12367a] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#0d2a63] disabled:cursor-not-allowed disabled:opacity-60"
          >
            {loading && <Loader2 className="h-4 w-4 animate-spin" />} Lihat Detail
          </button>
        </div>
      </div>
    </article>
  )
}
