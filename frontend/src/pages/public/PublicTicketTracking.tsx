import { useEffect, useRef, useState, type ChangeEvent, type FormEvent, type ReactNode } from 'react'
import {
  AlertCircle,
  AlertTriangle,
  CalendarDays,
  CheckCircle2,
  Clock3,
  FileCheck2,
  Loader2,
  Mail,
  MapPin,
  MessageSquareText,
  Paperclip,
  Printer,
  RefreshCw,
  ShieldCheck,
  Tag,
  Trash2,
  X,
} from 'lucide-react'
import { useParams } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import { PublicLoadingScreen } from '../../components/public/PublicLoadingScreen'
import { PublicShell } from '../../components/public/PublicShell'
import {
  publicTicketService,
  type PublicTicketActionChallenge,
  type PublicTicketActionDescriptor,
  type PublicTicketTracking,
} from '../../services/publicTicketService'

type ActionPhase = 'email' | 'otp' | 'form' | 'confirm' | 'success' | 'expired'
type ActionResult = 'accepted' | 'rejected'

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const SAFE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv']
const MAX_FILE_BYTES = 10 * 1024 * 1024
const GENERIC_ACTION_ERROR = 'Permintaan tidak dapat diproses. Periksa data Anda atau coba lagi nanti.'
const GENERIC_OTP_ERROR = 'Kode tidak valid, kedaluwarsa, atau tidak dapat digunakan lagi.'

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
    : date.toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' })
}

function ActionDialog({
  token,
  descriptor,
  onClose,
  onCompleted,
}: {
  token: string
  descriptor: PublicTicketActionDescriptor
  onClose: () => void
  onCompleted: () => Promise<void>
}) {
  const [phase, setPhase] = useState<ActionPhase>('email')
  const [email, setEmail] = useState('')
  const [challenge, setChallenge] = useState<PublicTicketActionChallenge | null>(null)
  const [code, setCode] = useState('')
  const [accessToken, setAccessToken] = useState<string | null>(null)
  const accessTokenRef = useRef<string | null>(null)
  const [accessExpiresAt, setAccessExpiresAt] = useState<string>()
  const [result, setResult] = useState<ActionResult>('accepted')
  const [notes, setNotes] = useState('')
  const [rejectionReason, setRejectionReason] = useState('')
  const [attachments, setAttachments] = useState<File[]>([])
  const [idempotencyKey, setIdempotencyKey] = useState<string>()
  const [submitting, setSubmitting] = useState(false)
  const submitLock = useRef(false)
  const dialogRef = useRef<HTMLElement>(null)
  const [error, setError] = useState('')
  const [now, setNow] = useState(Date.now())

  const action = descriptor.action
  const isUat = action === 'uat'
  const maxFiles = Math.min(descriptor.attachments?.max_files ?? 10, 10)
  const maxSizeMb = Math.min(descriptor.attachments?.max_size_mb ?? 10, 10)
  const maxFileBytes = Math.min(maxSizeMb * 1024 * 1024, MAX_FILE_BYTES)
  const allowedExtensions = (descriptor.attachments?.allowed_extensions ?? SAFE_EXTENSIONS)
    .map((extension) => extension.toLowerCase().replace(/^\./, ''))
    .filter((extension) => SAFE_EXTENSIONS.includes(extension))
  const attachmentsAllowed = isUat && descriptor.attachments?.allowed !== false
  const notesRequired = descriptor.notes_required[result]
  const resendSeconds = secondsUntil(challenge?.resend_available_at)
  const challengeSeconds = secondsUntil(challenge?.expires_at)
  const accessSeconds = secondsUntil(accessExpiresAt)

  useEffect(() => {
    if (phase !== 'otp' && phase !== 'form' && phase !== 'confirm') return
    const timer = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [phase])

  useEffect(() => {
    if (accessExpiresAt && accessSeconds === 0 && ['form', 'confirm'].includes(phase)) {
      setAccessToken(null)
      setPhase('expired')
      setError('')
    }
  }, [accessExpiresAt, accessSeconds, now, phase])

  useEffect(() => {
    setIdempotencyKey(undefined)
  }, [attachments, notes, rejectionReason, result])

  const revokeAndClose = async () => {
    const currentToken = accessToken
    setAccessToken(null)
    if (currentToken) {
      try {
        await publicTicketService.revokeActionAccess(token, currentToken)
      } catch {
        // The short-lived token is cleared locally even if server revocation is unavailable.
      }
    }
    onClose()
  }
  useEffect(() => {
    accessTokenRef.current = accessToken
  }, [accessToken])

  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null
    const dialog = dialogRef.current
    const focusable = () =>
      Array.from(
        dialog?.querySelectorAll<HTMLElement>(
          'button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), a[href]',
        ) ?? [],
      )
    focusable()[0]?.focus()
    const keydown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !submitLock.current) {
        event.preventDefault()
        const currentToken = accessTokenRef.current
        accessTokenRef.current = null
        setAccessToken(null)
        if (currentToken) void publicTicketService.revokeActionAccess(token, currentToken).catch(() => undefined)
        onClose()
        return
      }
      if (event.key !== 'Tab') return
      const items = focusable()
      if (!items.length) return
      const first = items[0]
      const last = items[items.length - 1]
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    }
    document.addEventListener('keydown', keydown)
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', keydown)
      document.body.style.overflow = previousOverflow
      previous?.focus()
    }
    // This effect intentionally runs once for the lifetime of the open dialog.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const requestChallenge = async () => {
    if (!action || !EMAIL_PATTERN.test(email.trim())) {
      setError('Masukkan alamat email yang valid.')
      return
    }
    setSubmitting(true)
    setError('')
    try {
      const response = await publicTicketService.requestActionChallenge(token, action, email.trim().toLowerCase())
      setChallenge(response)
      setCode('')
      setNow(Date.now())
      setPhase('otp')
    } catch {
      setError(GENERIC_ACTION_ERROR)
    } finally {
      setSubmitting(false)
    }
  }

  const submitEmail = (event: FormEvent) => {
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
      if (!action) throw new Error('Action is no longer available')
      const response = await publicTicketService.verifyActionChallenge(token, action, challenge.challenge_token, code)
      setAccessToken(response.access_token)
      setAccessExpiresAt(response.expires_at)
      setChallenge(null)
      setCode('')
      setPhase('form')
    } catch {
      setError(GENERIC_OTP_ERROR)
    } finally {
      setSubmitting(false)
    }
  }

  const selectFiles = (event: ChangeEvent<HTMLInputElement>) => {
    const selected = Array.from(event.target.files ?? [])
    event.target.value = ''
    if (attachments.length + selected.length > maxFiles) {
      setError(`Maksimal ${maxFiles} lampiran dapat diunggah.`)
      return
    }
    const invalid = selected.find((file) => {
      const extension = file.name.split('.').pop()?.toLowerCase() ?? ''
      return !allowedExtensions.includes(extension) || file.size > maxFileBytes
    })
    if (invalid) {
      setError(`Lampiran harus berformat ${allowedExtensions.join(', ')} dan maksimal ${maxSizeMb} MB per file.`)
      return
    }
    setError('')
    setAttachments((current) => [...current, ...selected])
  }

  const validateAction = () => {
    if (result === 'rejected' && isUat && !notes.trim()) {
      setError('Catatan wajib diisi untuk hasil pengujian ditolak.')
      return false
    }
    if (!isUat && result === 'rejected' && !rejectionReason.trim()) {
      setError('Alasan penolakan wajib diisi.')
      return false
    }
    if (notesRequired && !notes.trim()) {
      setError('Catatan wajib diisi untuk tindakan ini.')
      return false
    }
    return true
  }

  const reviewAction = (event: FormEvent) => {
    event.preventDefault()
    setError('')
    if (validateAction()) setPhase('confirm')
  }

  const submitAction = async () => {
    if (submitLock.current || !action || !validateAction()) return
    if (!accessToken) {
      setPhase('expired')
      return
    }
    const key = idempotencyKey ?? crypto.randomUUID()
    if (!idempotencyKey) setIdempotencyKey(key)
    submitLock.current = true
    setSubmitting(true)
    setError('')
    try {
      if (isUat) {
        await publicTicketService.submitUat(token, accessToken ?? '', key, result, notes.trim(), attachments)
      } else {
        await publicTicketService.submitConfirmation(
          token,
          accessToken ?? '',
          key,
          result,
          notes.trim(),
          rejectionReason.trim(),
        )
      }
      const currentToken = accessToken
      setAccessToken(null)
      if (currentToken) {
        try {
          await publicTicketService.revokeActionAccess(token, currentToken)
        } catch {
          // Submission succeeded; local token clearing and absolute expiry remain effective.
        }
      }
      try {
        await onCompleted()
      } catch {
        // The action succeeded; tracking can still be refreshed by reloading the page.
      }
      setPhase('success')
    } catch (caught) {
      if (caught instanceof ApiRequestError && (caught.status === 401 || caught.status === 403)) {
        setAccessToken(null)
        setPhase('expired')
      } else {
        setError(GENERIC_ACTION_ERROR)
      }
    } finally {
      submitLock.current = false
      setSubmitting(false)
    }
  }

  const title = descriptor.label || (isUat ? 'Lakukan Pengujian' : 'Konfirmasi Penyelesaian')

  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 p-0 backdrop-blur-sm sm:items-center sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-labelledby="public-action-title"
    >
      <section
        ref={dialogRef}
        className="max-h-[92vh] w-full overflow-y-auto rounded-t-3xl bg-white shadow-2xl sm:max-w-xl sm:rounded-3xl"
      >
        <header className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-100 bg-white px-5 py-5 sm:px-7">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-blue-700">Tindakan Pemohon</p>
            <h2 id="public-action-title" className="mt-1 text-xl font-black text-slate-950">
              {title}
            </h2>
          </div>
          <button
            type="button"
            onClick={() => void revokeAndClose()}
            disabled={submitting}
            aria-label="Tutup"
            className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 disabled:opacity-50"
          >
            <X className="h-5 w-5" />
          </button>
        </header>

        {phase === 'email' && (
          <form onSubmit={submitEmail} className="space-y-5 p-5 sm:p-7" noValidate>
            <StepHeading icon={<Mail className="h-5 w-5" />} step="1 dari 3" title="Verifikasi email Anda" />
            <p className="text-sm leading-6 text-slate-600">
              Kami akan mengirim kode satu kali ke email yang terdaftar pada tiket ini.
            </p>
            <label className="block text-sm font-bold text-slate-800" htmlFor="action-email">
              Email pemohon
              <input
                id="action-email"
                type="email"
                inputMode="email"
                autoComplete="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                disabled={submitting}
                placeholder="nama@perusahaan.co.id"
                className="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 font-normal outline-none focus:border-blue-700 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100"
              />
            </label>
            {error && <ActionError message={error} />}
            <PrimaryButton loading={submitting}>Kirim Kode Verifikasi</PrimaryButton>
          </form>
        )}

        {phase === 'otp' && (
          <form onSubmit={submitCode} className="space-y-5 p-5 sm:p-7" noValidate>
            <StepHeading icon={<ShieldCheck className="h-5 w-5" />} step="2 dari 3" title="Masukkan kode OTP" />
            <div className="rounded-2xl bg-blue-50 p-4 text-sm leading-6 text-slate-700">
              Kode dikirim ke <strong className="text-slate-950">{challenge?.masked_destination}</strong>.
            </div>
            <label className="block text-sm font-bold text-slate-800" htmlFor="action-code">
              Kode 6 digit
              <input
                id="action-code"
                value={code}
                onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
                inputMode="numeric"
                autoComplete="one-time-code"
                autoFocus
                disabled={submitting}
                className="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-center font-mono text-2xl tracking-[0.35em] outline-none focus:border-blue-700 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100"
              />
            </label>
            <div className="flex justify-between gap-3 text-xs font-medium text-slate-600">
              <span>Kedaluwarsa {formatCountdown(challengeSeconds)}</span>
              <span>Kirim ulang {formatCountdown(resendSeconds)}</span>
            </div>
            {challengeSeconds === 0 && <ActionError message="Kode telah kedaluwarsa. Kirim kode baru." warning />}
            {error && <ActionError message={error} />}
            <PrimaryButton loading={submitting} disabled={code.length !== 6 || challengeSeconds === 0}>
              Verifikasi Kode
            </PrimaryButton>
            <div className="grid gap-3 sm:grid-cols-2">
              <button
                type="button"
                onClick={() => void requestChallenge()}
                disabled={submitting || resendSeconds > 0}
                className="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-bold text-blue-900 disabled:opacity-50"
              >
                <RefreshCw className="h-4 w-4" /> Kirim Ulang
              </button>
              <button
                type="button"
                onClick={() => {
                  setChallenge(null)
                  setCode('')
                  setError('')
                  setPhase('email')
                }}
                disabled={submitting}
                className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 disabled:opacity-50"
              >
                Ubah Email
              </button>
            </div>
          </form>
        )}

        {phase === 'form' && (
          <form onSubmit={reviewAction} className="space-y-5 p-5 sm:p-7">
            <StepHeading icon={<FileCheck2 className="h-5 w-5" />} step="3 dari 3" title={title} />
            {accessExpiresAt && (
              <p className="rounded-xl bg-slate-50 px-4 py-3 text-xs font-medium text-slate-600">
                Sesi verifikasi berakhir dalam {formatCountdown(accessSeconds)}.
              </p>
            )}
            <fieldset>
              <legend className="text-sm font-bold text-slate-800">{isUat ? 'Hasil pengujian' : 'Keputusan'}</legend>
              <div className="mt-2 grid grid-cols-2 gap-3">
                {(['accepted', 'rejected'] as const).map((value) => (
                  <label
                    key={value}
                    className={`cursor-pointer rounded-2xl border p-4 text-center text-sm font-bold focus-within:ring-2 focus-within:ring-blue-700 focus-within:ring-offset-2 ${result === value ? (value === 'accepted' ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-red-400 bg-red-50 text-red-800') : 'border-slate-200 text-slate-600'}`}
                  >
                    <input
                      type="radio"
                      name="action-result"
                      value={value}
                      checked={result === value}
                      onChange={() => setResult(value)}
                      className="sr-only"
                    />
                    {value === 'accepted' ? 'Diterima' : 'Ditolak'}
                  </label>
                ))}
              </div>
            </fieldset>
            {!isUat && result === 'rejected' && (
              <TextArea
                id="rejection-reason"
                label="Alasan penolakan"
                value={rejectionReason}
                onChange={setRejectionReason}
                required
                placeholder="Jelaskan hal yang masih perlu diperbaiki."
              />
            )}
            <TextArea
              id="action-notes"
              label={`Catatan${notesRequired || (isUat && result === 'rejected') ? '' : ' (opsional)'}`}
              value={notes}
              onChange={setNotes}
              required={notesRequired || (isUat && result === 'rejected')}
              placeholder={isUat ? 'Tuliskan hasil dan temuan pengujian.' : 'Tambahkan catatan untuk tim penanganan.'}
            />
            {attachmentsAllowed && (
              <div>
                <label className="text-sm font-bold text-slate-800" htmlFor="action-attachments">
                  Bukti pengujian (opsional)
                </label>
                <div className="mt-2 rounded-2xl focus-within:ring-2 focus-within:ring-blue-700 focus-within:ring-offset-2">
                  <label
                    htmlFor="action-attachments"
                    className="flex cursor-pointer items-center justify-center gap-2 rounded-2xl border border-dashed border-blue-300 bg-blue-50 px-4 py-5 text-sm font-bold text-blue-900 hover:bg-blue-100"
                  >
                    <Paperclip className="h-5 w-5" /> Pilih Lampiran
                  </label>
                  <input
                    id="action-attachments"
                    type="file"
                    multiple
                    accept={allowedExtensions.map((extension) => `.${extension}`).join(',')}
                    onChange={selectFiles}
                    className="sr-only"
                  />
                </div>
                <p className="mt-2 text-xs leading-5 text-slate-600">
                  Maksimal {maxFiles} file, {maxSizeMb} MB per file. Format: {allowedExtensions.join(', ')}.
                </p>
                {!!attachments.length && (
                  <ul className="mt-3 space-y-2">
                    {attachments.map((file, index) => (
                      <li
                        key={`${file.name}-${file.size}-${index}`}
                        className="flex items-center gap-3 rounded-xl bg-slate-50 p-3"
                      >
                        <Paperclip className="h-4 w-4 shrink-0 text-blue-700" />
                        <span className="min-w-0 flex-1 truncate text-sm text-slate-700">{file.name}</span>
                        <button
                          type="button"
                          onClick={() =>
                            setAttachments((current) => current.filter((_, itemIndex) => itemIndex !== index))
                          }
                          aria-label={`Hapus ${file.name}`}
                          className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-slate-500 hover:bg-white hover:text-red-700"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            )}
            {error && <ActionError message={error} />}
            <PrimaryButton>Tinjau dan Lanjutkan</PrimaryButton>
          </form>
        )}

        {phase === 'confirm' && (
          <div className="space-y-5 p-5 sm:p-7">
            <StepHeading icon={<AlertCircle className="h-5 w-5" />} step="Konfirmasi" title="Pastikan pilihan Anda" />
            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">Keputusan</p>
              <p className={`mt-1 text-lg font-black ${result === 'accepted' ? 'text-emerald-700' : 'text-red-700'}`}>
                {result === 'accepted' ? 'Diterima' : 'Ditolak'}
              </p>
              {rejectionReason && <Summary label="Alasan penolakan" value={rejectionReason} />}
              {notes && <Summary label="Catatan" value={notes} />}
              {attachments.length > 0 && <Summary label="Lampiran" value={`${attachments.length} file`} />}
            </div>
            <p className="text-sm leading-6 text-slate-600">
              Setelah dikirim, status tiket akan diperbarui dan jawaban ini tidak dapat diubah dari halaman ini.
            </p>
            {error && <ActionError message={error} />}
            <div className="grid gap-3 sm:grid-cols-2">
              <button
                type="button"
                onClick={() => {
                  setError('')
                  setPhase('form')
                }}
                disabled={submitting}
                className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 disabled:opacity-50"
              >
                Periksa Kembali
              </button>
              <button
                type="button"
                onClick={() => void submitAction()}
                disabled={submitting}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#12367a] px-5 py-3 text-sm font-bold text-white hover:bg-[#0d2a63] disabled:cursor-not-allowed disabled:opacity-60"
              >
                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                {submitting ? 'Mengirim...' : 'Ya, Kirim Jawaban'}
              </button>
            </div>
          </div>
        )}

        {phase === 'success' && (
          <div className="p-7 text-center sm:p-10" role="status" aria-live="polite">
            <CheckCircle2 className="mx-auto h-14 w-14 text-emerald-600" />
            <h3 className="mt-5 text-2xl font-black text-slate-950">Jawaban berhasil dikirim</h3>
            <p className="mt-3 text-sm leading-6 text-slate-600">Status dan riwayat tiket telah diperbarui.</p>
            <p className="mx-auto mt-4 max-w-md rounded-2xl bg-slate-50 p-4 text-sm leading-6 text-slate-700">
              {isUat
                ? result === 'accepted'
                  ? 'Hasil pengujian diterima dan catatan Anda telah dikirim. Proses tiket akan dilanjutkan ke tahap berikutnya.'
                  : 'Penolakan tercatat dan tiket akan dikembalikan ke tim PIC untuk perbaikan.'
                : result === 'accepted'
                  ? 'Konfirmasi diterima dan tiket akan segera ditutup. Terima kasih atas konfirmasinya.'
                  : 'Penolakan tercatat dan tiket akan dibuka kembali untuk tindak lanjut.'}
            </p>
            <button
              type="button"
              onClick={onClose}
              className="mt-6 rounded-xl bg-[#12367a] px-6 py-3 text-sm font-bold text-white hover:bg-[#0d2a63]"
            >
              Kembali ke Tiket
            </button>
          </div>
        )}

        {phase === 'expired' && (
          <div className="p-7 text-center sm:p-10" role="alert">
            <Clock3 className="mx-auto h-12 w-12 text-amber-600" />
            <h3 className="mt-5 text-2xl font-black text-slate-950">Sesi verifikasi berakhir</h3>
            <p className="mt-3 text-sm leading-6 text-slate-600">Minta kode baru untuk melanjutkan tindakan ini.</p>
            <button
              type="button"
              onClick={() => {
                setChallenge(null)
                setCode('')
                setError('')
                setPhase('email')
              }}
              className="mt-6 rounded-xl bg-[#12367a] px-6 py-3 text-sm font-bold text-white hover:bg-[#0d2a63]"
            >
              Verifikasi Ulang
            </button>
          </div>
        )}
      </section>
    </div>
  )
}

function StepHeading({ icon, step, title }: { icon: ReactNode; step: string; title: string }) {
  return (
    <div className="flex items-center gap-3">
      <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#12367a] to-blue-700 text-white shadow-sm">
        {icon}
      </div>
      <div>
        <p className="text-xs font-bold uppercase tracking-wide text-blue-700">{step}</p>
        <h3 className="font-bold text-slate-950">{title}</h3>
      </div>
    </div>
  )
}

function TextArea({
  id,
  label,
  value,
  onChange,
  required,
  placeholder,
}: {
  id: string
  label: string
  value: string
  onChange: (value: string) => void
  required?: boolean
  placeholder: string
}) {
  return (
    <label className="block text-sm font-bold text-slate-800" htmlFor={id}>
      {label}
      <textarea
        id={id}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        required={required}
        rows={4}
        placeholder={placeholder}
        className="mt-2 block w-full resize-y rounded-xl border border-slate-300 px-4 py-3 font-normal outline-none placeholder:text-slate-400 focus:border-blue-700 focus:ring-2 focus:ring-blue-100"
      />
    </label>
  )
}

function PrimaryButton({
  children,
  loading = false,
  disabled = false,
}: {
  children: ReactNode
  loading?: boolean
  disabled?: boolean
}) {
  return (
    <button
      type="submit"
      disabled={loading || disabled}
      className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#12367a] px-5 py-3 text-sm font-bold text-white hover:bg-[#0d2a63] disabled:cursor-not-allowed disabled:opacity-60"
    >
      {loading && <Loader2 className="h-4 w-4 animate-spin" />}
      {children}
    </button>
  )
}

function ActionError({ message, warning = false }: { message: string; warning?: boolean }) {
  return (
    <p
      className={`rounded-xl p-3 text-sm ${warning ? 'bg-amber-50 text-amber-900' : 'bg-red-50 text-red-800'}`}
      role="alert"
    >
      {message}
    </p>
  )
}

function Summary({ label, value }: { label: string; value: string }) {
  return (
    <div className="mt-4 border-t border-slate-200 pt-4">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-1 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">{value}</p>
    </div>
  )
}

function ActionCtaCard({ action, onOpen }: { action: PublicTicketActionDescriptor; onOpen: () => void }) {
  return (
    <section className="relative overflow-hidden rounded-3xl border border-cyan-200 bg-gradient-to-br from-blue-50 to-cyan-50 p-6 shadow-sm">
      <div className="absolute -right-5 -top-5 h-20 w-20 rounded-full bg-cyan-300/10" aria-hidden="true" />
      <div className="relative">
        <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#12367a] to-blue-700 text-white shadow-lg shadow-blue-900/20">
          <FileCheck2 className="h-6 w-6" />
        </div>
        <h2 className="mt-4 text-lg font-black text-slate-950">
          {action.action === 'uat' ? 'Pengujian Anda diperlukan' : 'Konfirmasi Anda diperlukan'}
        </h2>
        <p className="mt-2 text-sm leading-6 text-slate-600">
          {action.action === 'uat'
            ? 'Uji hasil penanganan lalu sampaikan apakah solusi sudah sesuai.'
            : 'Tinjau penyelesaian tiket dan sampaikan keputusan Anda.'}
        </p>
        <button
          type="button"
          onClick={onOpen}
          className="mt-5 w-full rounded-xl bg-gradient-to-r from-[#12367a] to-blue-800 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-900/15 hover:from-[#0d2a63] hover:to-blue-900"
        >
          {action.action === 'uat' ? 'Lakukan Pengujian' : 'Konfirmasi Penyelesaian'}
        </button>
      </div>
    </section>
  )
}

export default function PublicTicketTrackingPage() {
  const { token = '' } = useParams()
  const [ticket, setTicket] = useState<PublicTicketTracking | null>(null)
  const [action, setAction] = useState<PublicTicketActionDescriptor | null>(null)
  const [actionOpen, setActionOpen] = useState(false)
  const [loading, setLoading] = useState(true)
  const [failed, setFailed] = useState(false)
  const [attempt, setAttempt] = useState(0)
  const [refreshing, setRefreshing] = useState(false)
  const [lastChecked, setLastChecked] = useState<Date | null>(null)

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
    setAction(null)

    if (!token) {
      setLoading(false)
      setFailed(true)
      return () => {
        active = false
      }
    }

    Promise.all([publicTicketService.track(token), publicTicketService.action(token).catch(() => null)])
      .then(([result, availableAction]) => {
        if (active) {
          setTicket(result)
          setAction(availableAction ?? result.action ?? null)
          setLastChecked(new Date())
        }
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

  const refreshTracking = async () => {
    setRefreshing(true)
    try {
      const [tracking, availableAction] = await Promise.all([
        publicTicketService.track(token),
        publicTicketService.action(token).catch(() => null),
      ])
      setTicket(tracking)
      setAction(availableAction ?? tracking.action ?? null)
      setLastChecked(new Date())
    } finally {
      setRefreshing(false)
    }
  }

  useEffect(() => {
    const interval = window.setInterval(() => {
      if (document.visibilityState !== 'visible' || !document.hasFocus()) return
      if (loading || refreshing || !ticket) return
      void refreshTracking()
    }, 60000)
    return () => window.clearInterval(interval)
  }, [loading, refreshing, ticket, token])

  return (
    <PublicShell
      securityLabel="Pelacakan tiket terlindungi"
      footerText="Tic Hub APG · Informasi status ditampilkan khusus melalui link pelacakan Anda."
    >
      <main className="min-h-[calc(100vh-145px)]">
        <section className="relative overflow-hidden bg-[#0b1f48] px-4 pb-32 pt-12 text-white sm:px-6 sm:pt-16 print:hidden">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_15%,rgba(34,211,238,0.2),transparent_32%),radial-gradient(circle_at_10%_90%,rgba(59,130,246,0.24),transparent_38%)]" />
          <div
            className="absolute inset-0 opacity-[0.06] [background-image:linear-gradient(rgba(255,255,255,0.55)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.55)_1px,transparent_1px)] [background-size:28px_28px]"
            aria-hidden="true"
          />
          <div className="relative mx-auto max-w-6xl">
            <div className="flex items-center gap-2">
              <span className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-cyan-300 opacity-75" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-cyan-300" />
              </span>
              <p className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-300">Status Pengajuan</p>
            </div>
            <h1 className="mt-3 text-3xl font-black tracking-tight sm:text-5xl">Pantau perkembangan tiket Anda</h1>
            <p className="mt-4 max-w-2xl text-sm leading-7 text-blue-100 sm:text-base">
              Informasi berikut diperbarui oleh tim Tic Hub selama proses penanganan.
            </p>
          </div>
        </section>

        <div className="relative mx-auto -mt-20 max-w-6xl px-4 pb-14 sm:px-6 lg:px-8 print:mt-0">
          {loading && (
            <PublicLoadingScreen
              label="Memuat status tiket..."
              hint="Kami sedang mengambil perkembangan terbaru dari tim penanganan."
            />
          )}

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
                <div className="relative grid gap-6 bg-gradient-to-br from-[#12367a] to-[#0b1f48] p-6 text-white sm:p-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-center">
                  <div
                    className="absolute inset-0 opacity-[0.07] [background-image:linear-gradient(rgba(255,255,255,0.6)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.6)_1px,transparent_1px)] [background-size:26px_26px]"
                    aria-hidden="true"
                  />
                  <div className="relative min-w-0">
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-blue-200">{ticket.ticket_number}</p>
                    <h2 className="mt-3 break-words text-2xl font-black sm:text-3xl">{ticket.title}</h2>
                  </div>
                  <div className="relative rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-blue-200">Status saat ini</p>
                    <div className="mt-2 flex items-start gap-3">
                      <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan-400/15 text-cyan-300 ring-1 ring-cyan-300/30">
                        <CheckCircle2 className="h-6 w-6" />
                      </div>
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
                    <div key={label} className="flex min-w-0 items-start gap-3">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700 ring-1 ring-blue-100">
                        <Icon className="h-5 w-5" />
                      </div>
                      <div className="min-w-0">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
                        <dd className="mt-1 break-words text-sm font-semibold text-slate-900">{value}</dd>
                      </div>
                    </div>
                  ))}
                </dl>
              </section>

              {(action?.action === 'uat' || action?.action === 'confirmation') && (
                <div className="print:hidden lg:hidden">
                  <ActionCtaCard action={action} onOpen={() => setActionOpen(true)} />
                </div>
              )}

              <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                  <h2 className="text-xl font-bold text-slate-950">Riwayat status</h2>
                  <p className="mt-1 text-sm text-slate-600">Tahapan penanganan yang dapat dilihat oleh pemohon.</p>
                  {ticket.timeline.length ? (
                    <ol className="mt-7 space-y-0">
                      {ticket.timeline.map((item, index) => (
                        <li
                          key={`${item.code}-${item.occurred_at}-${index}`}
                          className="relative flex gap-4 pb-7 last:pb-0"
                        >
                          {index < ticket.timeline.length - 1 && (
                            <span className="absolute left-[11px] top-6 h-[calc(100%-8px)] w-px bg-gradient-to-b from-blue-200 to-slate-200" />
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

                <aside className="space-y-6 print:hidden">
                  {(action?.action === 'uat' || action?.action === 'confirmation') && (
                    <div className="hidden lg:block">
                      <ActionCtaCard action={action} onOpen={() => setActionOpen(true)} />
                    </div>
                  )}
                  <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex items-center gap-3">
                      <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700 ring-1 ring-blue-100">
                        <MessageSquareText className="h-5 w-5" />
                      </div>
                      <h2 className="text-lg font-bold text-slate-950">Pembaruan untuk pemohon</h2>
                    </div>
                    {ticket.requester_updates.length ? (
                      <ul className="mt-5 space-y-4">
                        {ticket.requester_updates.map((update, index) => (
                          <li key={`${update.occurred_at}-${index}`} className="rounded-2xl border-l-4 border-l-blue-500 bg-blue-50 p-4">
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
                  <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                    <div className="flex gap-3">
                      <Clock3 className="mt-0.5 h-5 w-5 shrink-0 text-slate-500" />
                      <p>
                        Terakhir diperbarui
                        <strong className="mt-1 block text-slate-900">{formatDate(ticket.last_updated_at)}</strong>
                      </p>
                    </div>
                    <p className="mt-3 text-xs text-slate-600">
                      Terakhir diperiksa{' '}
                      {lastChecked
                        ? new Date(lastChecked).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
                        : '\u2014'}
                    </p>
                    <button
                      type="button"
                      onClick={() => void refreshTracking()}
                      disabled={refreshing}
                      className="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                    >
                      <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                      Perbarui Status
                    </button>
                    <button
                      type="button"
                      onClick={() => window.print()}
                      className="mt-2 flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-100"
                    >
                      <Printer className="h-4 w-4" />
                      Cetak / Simpan Bukti
                    </button>
                  </div>
                </aside>
              </div>
            </div>
          )}
        </div>
        {actionOpen && action && (action.action === 'uat' || action.action === 'confirmation') && (
          <ActionDialog
            token={token}
            descriptor={action}
            onClose={() => setActionOpen(false)}
            onCompleted={refreshTracking}
          />
        )}
      </main>
    </PublicShell>
  )
}
