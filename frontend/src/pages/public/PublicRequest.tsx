import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react'
import {
  AlertCircle,
  Building2,
  Check,
  CheckCircle2,
  Clipboard,
  FileText,
  Headphones,
  Loader2,
  LockKeyhole,
  Send,
  Trash2,
  Upload,
} from 'lucide-react'
import { ApiRequestError } from '../../api/client'
import { TicketDescriptionEditor, ticketDescriptionText } from '../../components/TicketDescriptionEditor'
import { PublicShell } from '../../components/public/PublicShell'
import {
  publicTicketService,
  type PublicTicketFormOptions,
  type PublicTicketReceipt,
} from '../../services/publicTicketService'

const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx']
const MAX_FILES = 10
const MAX_FILE_SIZE = 10 * 1024 * 1024

type FormValues = {
  requester_name: string
  branch_id: string
  division_id: string
  requester_phone: string
  requester_email: string
  ticket_category_id: string
  application_id: string
  title: string
  description: string
  affected_url: string
  reference: string
  urgency: string
  website: string
}

const EMPTY_FORM: FormValues = {
  requester_name: '',
  branch_id: '',
  division_id: '',
  requester_phone: '',
  requester_email: '',
  ticket_category_id: '',
  application_id: '',
  title: '',
  description: '',
  affected_url: '',
  reference: '',
  urgency: '',
  website: '',
}

function fieldClass(error?: string) {
  return `mt-1.5 block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:ring-4 disabled:cursor-not-allowed disabled:bg-slate-100 ${
    error
      ? 'border-red-400 focus:border-red-500 focus:ring-red-100'
      : 'border-slate-300 focus:border-blue-700 focus:ring-blue-100'
  }`
}

function ErrorText({ id, children }: { id: string; children?: string }) {
  return children ? (
    <p id={id} className="mt-1.5 text-xs font-medium text-red-600">
      {children}
    </p>
  ) : null
}

export default function PublicRequest() {
  const [values, setValues] = useState<FormValues>(EMPTY_FORM)
  const [files, setFiles] = useState<File[]>([])
  const [options, setOptions] = useState<PublicTicketFormOptions | null>(null)
  const [optionsError, setOptionsError] = useState('')
  const [optionsAttempt, setOptionsAttempt] = useState(0)
  const [loadingOptions, setLoadingOptions] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [receipt, setReceipt] = useState<PublicTicketReceipt | null>(null)
  const [copied, setCopied] = useState<'number' | 'link' | null>(null)
  const [copyError, setCopyError] = useState('')
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())

  useEffect(() => {
    let active = true
    setLoadingOptions(true)
    setOptionsError('')
    publicTicketService
      .options()
      .then((result) => {
        if (active) setOptions(result)
      })
      .catch(() => {
        if (active) setOptionsError('Pilihan formulir tidak dapat dimuat. Periksa koneksi lalu coba kembali.')
      })
      .finally(() => {
        if (active) setLoadingOptions(false)
      })
    return () => {
      active = false
    }
  }, [optionsAttempt])

  const update = (field: keyof FormValues, value: string) => {
    setValues((current) => ({ ...current, [field]: value }))
    setErrors((current) => ({ ...current, [field]: '' }))
  }

  const validate = () => {
    const next: Record<string, string> = {}
    const name = values.requester_name.trim()
    const phone = values.requester_phone.trim()
    const email = values.requester_email.trim()
    const normalizedPhone = phone
      .replace(/[^0-9+]/g, '')
      .replace(/^\+62/, '62')
      .replace(/^0/, '62')

    if (!name) next.requester_name = 'Nama pemohon wajib diisi.'
    else if (name.length > 150) next.requester_name = 'Nama pemohon maksimal 150 karakter.'
    if (!values.branch_id) next.branch_id = 'Cabang wajib dipilih.'
    if (!phone && !email) {
      const message = 'Isi minimal satu kontak: WhatsApp atau email kantor.'
      next.requester_phone = message
      next.requester_email = message
    }
    if (phone && !/^628\d{8,11}$/.test(normalizedPhone)) {
      next.requester_phone = 'Gunakan nomor Indonesia, contoh 081234567890 atau +6281234567890.'
    }
    if (email && (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || email.length > 255)) {
      next.requester_email = 'Alamat email tidak valid atau melebihi 255 karakter.'
    }
    if (!values.ticket_category_id) next.ticket_category_id = 'Kategori tiket wajib dipilih.'
    if (!values.application_id) next.application_id = 'Sistem/aplikasi wajib dipilih.'
    if (!values.title.trim()) next.title = 'Judul pengajuan wajib diisi.'
    else if (values.title.trim().length > 200) next.title = 'Judul maksimal 200 karakter.'
    if (!ticketDescriptionText(values.description)) next.description = 'Deskripsi pengajuan wajib diisi.'
    else if (values.description.length > 10000) next.description = 'Deskripsi maksimal 10.000 karakter.'
    if (values.affected_url.trim()) {
      if (values.affected_url.trim().length > 2048) next.affected_url = 'URL maksimal 2.048 karakter.'
      try {
        const url = new URL(values.affected_url.trim())
        if (!['http:', 'https:'].includes(url.protocol)) throw new Error('invalid protocol')
      } catch {
        next.affected_url = 'Masukkan URL lengkap yang diawali http:// atau https://.'
      }
    }
    if (values.reference.trim().length > 255) next.reference = 'Referensi maksimal 255 karakter.'
    if (!values.urgency) next.urgency = 'Tingkat urgensi wajib dipilih.'
    if (files.length > MAX_FILES) next.attachments = `Maksimal ${MAX_FILES} lampiran.`
    const invalidFile = files.find(
      (file) => !ALLOWED_EXTENSIONS.includes(file.name.split('.').pop()?.toLowerCase() ?? ''),
    )
    const oversizedFile = files.find((file) => file.size > MAX_FILE_SIZE)
    if (invalidFile) next.attachments = `Format ${invalidFile.name} tidak didukung.`
    else if (oversizedFile) next.attachments = `${oversizedFile.name} melebihi batas 10 MB.`

    setErrors(next)
    if (Object.keys(next).length) {
      setFormError('Periksa kembali kolom yang ditandai sebelum mengirim pengajuan.')
      window.scrollTo({ top: 0, behavior: 'smooth' })
      return false
    }
    return true
  }

  const selectFiles = (event: ChangeEvent<HTMLInputElement>) => {
    const selected = Array.from(event.target.files ?? [])
    event.target.value = ''
    if (!selected.length) return

    if (files.length + selected.length > MAX_FILES) {
      setErrors((current) => ({ ...current, attachments: `Maksimal ${MAX_FILES} lampiran.` }))
      return
    }
    const invalid = selected.find(
      (file) => !ALLOWED_EXTENSIONS.includes(file.name.split('.').pop()?.toLowerCase() ?? ''),
    )
    if (invalid) {
      setErrors((current) => ({ ...current, attachments: `Format ${invalid.name} tidak didukung.` }))
      return
    }
    const oversized = selected.find((file) => file.size > MAX_FILE_SIZE)
    if (oversized) {
      setErrors((current) => ({ ...current, attachments: `${oversized.name} melebihi batas 10 MB.` }))
      return
    }
    setFiles((current) => [...current, ...selected])
    setErrors((current) => ({ ...current, attachments: '' }))
  }

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    if (submitting || !validate()) return
    setSubmitting(true)
    setFormError('')

    const body = new FormData()
    body.append('requester_name', values.requester_name.trim())
    body.append('branch_id', values.branch_id)
    if (values.division_id) body.append('division_id', values.division_id)
    if (values.requester_phone.trim()) {
      body.append('requester_phone', values.requester_phone.trim().replace(/[\s().-]/g, ''))
    }
    if (values.requester_email.trim()) body.append('requester_email', values.requester_email.trim().toLowerCase())
    body.append('ticket_category_id', values.ticket_category_id)
    body.append('application_id', values.application_id)
    body.append('title', values.title.trim())
    body.append('description', values.description.trim())
    if (values.affected_url.trim()) body.append('affected_url', values.affected_url.trim())
    if (values.reference.trim()) body.append('reference', values.reference.trim())
    body.append('urgency', values.urgency)
    body.append('website', values.website)
    files.forEach((file) => body.append('attachments[]', file))

    try {
      setReceipt(await publicTicketService.submit(body, idempotencyKey))
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (cause) {
      const error = cause as ApiRequestError
      if (error.errors) {
        const mapped: Record<string, string> = {}
        Object.entries(error.errors).forEach(([field, messages]) => {
          const target = field === 'attachments' || field.startsWith('attachments.') ? 'attachments' : field
          if (!mapped[target]) mapped[target] = Array.isArray(messages) ? messages[0] : String(messages)
        })
        setErrors(mapped)
      }
      setFormError(error.message || 'Pengajuan belum dapat dikirim. Silakan coba kembali.')
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setSubmitting(false)
    }
  }

  const newSubmission = () => {
    setValues(EMPTY_FORM)
    setFiles([])
    setErrors({})
    setFormError('')
    setReceipt(null)
    setCopied(null)
    setCopyError('')
    setIdempotencyKey(crypto.randomUUID())
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  if (receipt) {
    const trackingUrl =
      receipt.tracking_url || `${window.location.origin}/track/${encodeURIComponent(receipt.tracking_token)}`

    const copy = async (value: string, target: 'number' | 'link') => {
      setCopyError('')
      try {
        await navigator.clipboard.writeText(value)
        setCopied(target)
      } catch {
        setCopied(null)
        setCopyError('Tidak dapat menyalin otomatis. Silakan salin secara manual dari perangkat Anda.')
      }
    }

    return (
      <PublicShell>
        <main className="mx-auto flex min-h-[calc(100vh-145px)] max-w-3xl items-center px-4 py-10 sm:px-6 sm:py-16">
          <section className="w-full overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-xl shadow-slate-200/70">
            <div className="bg-gradient-to-br from-emerald-600 to-teal-700 px-6 py-9 text-center text-white sm:px-10">
              <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30">
                <CheckCircle2 className="h-9 w-9" />
              </div>
              <h1 className="mt-5 text-2xl font-bold sm:text-3xl">Pengajuan berhasil diterima</h1>
              <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-emerald-50">
                Simpan nomor tiket berikut sebagai bukti penerimaan pengajuan Anda.
              </p>
            </div>
            <div className="space-y-6 p-5 sm:p-9">
              <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-5 text-center">
                <p className="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Nomor tiket</p>
                <p className="mt-2 break-all font-mono text-2xl font-black text-slate-950 sm:text-3xl">
                  {receipt.ticket_number}
                </p>
                <button
                  type="button"
                  onClick={() => void copy(receipt.ticket_number, 'number')}
                  className="mt-4 inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100"
                >
                  {copied === 'number' ? <Check className="h-4 w-4" /> : <Clipboard className="h-4 w-4" />}
                  {copied === 'number' ? 'Nomor tersalin' : 'Salin Nomor Tiket'}
                </button>
              </div>

              <dl className="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:grid-cols-2">
                {[
                  ['Nama pemohon', receipt.requester_name],
                  ['Cabang', receipt.branch_name],
                  ['Judul', receipt.title],
                  ['Waktu pengajuan', new Date(receipt.submitted_at).toLocaleString('id-ID')],
                ].map(([label, value]) => (
                  <div key={label} className={label === 'Judul' ? 'sm:col-span-2' : ''}>
                    <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
                    <dd className="mt-1 break-words text-sm font-semibold text-slate-900">{value}</dd>
                  </div>
                ))}
              </dl>

              <div className="grid gap-3 sm:grid-cols-2">
                <a
                  href={trackingUrl}
                  className="flex items-center justify-center rounded-xl bg-[#12367a] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-900/15 hover:bg-[#0d2a63]"
                >
                  Lihat Status Tiket
                </a>
                <button
                  type="button"
                  onClick={() => void copy(trackingUrl, 'link')}
                  className="flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-5 py-3 text-sm font-bold text-blue-900 hover:bg-blue-100"
                >
                  {copied === 'link' ? <Check className="h-4 w-4" /> : <Clipboard className="h-4 w-4" />}
                  {copied === 'link' ? 'Link tersalin' : 'Salin Link Tracking'}
                </button>
              </div>
              <div>
                <label htmlFor="tracking-link" className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Link tracking
                </label>
                <input
                  id="tracking-link"
                  readOnly
                  value={trackingUrl}
                  onFocus={(event) => event.currentTarget.select()}
                  className="mt-1.5 block w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 font-mono text-xs text-slate-700 outline-none focus:border-blue-700 focus:ring-2 focus:ring-blue-100"
                />
                {receipt.tracking_expires_at && (
                  <p className="mt-1.5 text-xs text-slate-500">
                    Link berlaku sampai {new Date(receipt.tracking_expires_at).toLocaleString('id-ID')}.
                  </p>
                )}
              </div>
              {copyError && (
                <p role="alert" className="text-center text-sm font-medium text-red-700">
                  {copyError}
                </p>
              )}
              <div className="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                <LockKeyhole className="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                <p>Jangan bagikan link tracking. Siapa pun yang memiliki link dapat melihat perkembangan tiket ini.</p>
              </div>
              <button
                type="button"
                onClick={newSubmission}
                className="flex w-full items-center justify-center rounded-xl bg-[#12367a] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-900/15 hover:bg-[#0d2a63]"
              >
                Buat Pengajuan Baru
              </button>
              <a
                href="/request/history"
                className="flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-800 hover:bg-slate-50"
              >
                Lihat Riwayat Pengajuan Saya
              </a>
            </div>
          </section>
        </main>
      </PublicShell>
    )
  }

  const noOptions = Boolean(
    options && (!options.branches.length || !options.categories.length || !options.applications.length),
  )

  return (
    <PublicShell>
      <main>
        <section className="relative overflow-hidden bg-[#0b1f48] px-4 pb-28 pt-10 text-white sm:px-6 sm:pb-32 sm:pt-14">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_85%_10%,rgba(34,211,238,0.18),transparent_30%),radial-gradient(circle_at_15%_90%,rgba(59,130,246,0.2),transparent_35%)]" />
          <div className="relative mx-auto max-w-6xl">
            <div className="inline-flex items-center gap-2 rounded-full border border-blue-300/20 bg-blue-100/10 px-3 py-1.5 text-xs font-semibold text-blue-100">
              <Headphones className="h-4 w-4 text-cyan-300" /> Dukungan sistem untuk karyawan APG
            </div>
            <h1 className="mt-5 max-w-3xl text-3xl font-black tracking-tight sm:text-5xl">
              Sampaikan kebutuhan Anda kepada tim IT
            </h1>
            <p className="mt-4 max-w-2xl text-sm leading-7 text-blue-100 sm:text-base">
              Laporkan kendala atau ajukan kebutuhan sistem tanpa harus masuk. Berikan informasi lengkap agar tim kami
              dapat melakukan verifikasi dengan tepat.
            </p>
          </div>
        </section>

        <div className="relative mx-auto -mt-20 max-w-6xl px-4 pb-14 sm:px-6 lg:px-8">
          <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
            <section className="min-w-0 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-300/30">
              <div className="border-b border-slate-200 px-5 py-5 sm:px-8">
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-blue-700">Formulir pengajuan publik</p>
                <h2 className="mt-1 text-xl font-bold text-slate-950">Informasi pengajuan</h2>
                <p className="mt-1 text-sm text-slate-500">Kolom bertanda * wajib diisi.</p>
              </div>

              {formError && (
                <div
                  className="mx-5 mt-5 flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 sm:mx-8"
                  role="alert"
                >
                  <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                  <p>{formError}</p>
                </div>
              )}
              {optionsError && (
                <div
                  className="mx-5 mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:mx-8"
                  role="alert"
                >
                  <p>{optionsError}</p>
                  <button
                    type="button"
                    className="mt-2 font-bold underline"
                    onClick={() => setOptionsAttempt((n) => n + 1)}
                  >
                    Coba muat kembali
                  </button>
                </div>
              )}
              {noOptions && (
                <div
                  className="mx-5 mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:mx-8"
                  role="alert"
                >
                  Formulir belum dapat digunakan karena pilihan aktif belum lengkap.
                </div>
              )}

              <form onSubmit={submit} noValidate className="space-y-8 p-5 sm:p-8">
                <fieldset disabled={submitting} className="space-y-5">
                  <legend className="mb-4 flex items-center gap-2 text-base font-bold text-slate-950">
                    <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 text-xs text-blue-800">
                      1
                    </span>
                    Data pemohon
                  </legend>
                  <div>
                    <label htmlFor="requester-name" className="text-sm font-semibold text-slate-700">
                      Nama lengkap *
                    </label>
                    <input
                      id="requester-name"
                      value={values.requester_name}
                      maxLength={150}
                      onChange={(e) => update('requester_name', e.target.value)}
                      className={fieldClass(errors.requester_name)}
                      aria-invalid={Boolean(errors.requester_name)}
                      aria-describedby={errors.requester_name ? 'requester-name-error' : undefined}
                      autoComplete="name"
                      placeholder="Nama pemohon"
                    />
                    <ErrorText id="requester-name-error">{errors.requester_name}</ErrorText>
                  </div>
                  <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                      <label htmlFor="branch" className="text-sm font-semibold text-slate-700">
                        Cabang *
                      </label>
                      <select
                        id="branch"
                        value={values.branch_id}
                        onChange={(e) => update('branch_id', e.target.value)}
                        className={fieldClass(errors.branch_id)}
                        aria-invalid={Boolean(errors.branch_id)}
                        aria-describedby={errors.branch_id ? 'branch-error' : undefined}
                      >
                        <option value="">{loadingOptions ? 'Memuat cabang...' : 'Pilih cabang'}</option>
                        {options?.branches.map((item) => (
                          <option key={item.id} value={item.id}>
                            {item.name}
                          </option>
                        ))}
                      </select>
                      <ErrorText id="branch-error">{errors.branch_id}</ErrorText>
                    </div>
                    <div>
                      <label htmlFor="division" className="text-sm font-semibold text-slate-700">
                        Divisi <span className="font-normal text-slate-400">(opsional)</span>
                      </label>
                      <select
                        id="division"
                        value={values.division_id}
                        onChange={(e) => update('division_id', e.target.value)}
                        className={fieldClass(errors.division_id)}
                      >
                        <option value="">Pilih divisi bila relevan</option>
                        {options?.divisions.map((item) => (
                          <option key={item.id} value={item.id}>
                            {item.name}
                          </option>
                        ))}
                      </select>
                      <ErrorText id="division-error">{errors.division_id}</ErrorText>
                    </div>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-slate-700">Kontak yang dapat dihubungi *</p>
                    <p id="contact-hint" className="mt-1 text-xs leading-5 text-slate-500">
                      Isi minimal satu: nomor WhatsApp atau email kantor.
                    </p>
                    <div className="mt-2 grid gap-5 sm:grid-cols-2">
                      <div>
                        <label htmlFor="phone" className="text-xs font-semibold text-slate-600">
                          WhatsApp
                        </label>
                        <input
                          id="phone"
                          type="tel"
                          value={values.requester_phone}
                          onChange={(e) => update('requester_phone', e.target.value)}
                          className={fieldClass(errors.requester_phone)}
                          aria-describedby={errors.requester_phone ? 'phone-error' : 'contact-hint'}
                          autoComplete="tel"
                          placeholder="Contoh: 0812 3456 7890"
                        />
                        <ErrorText id="phone-error">{errors.requester_phone}</ErrorText>
                      </div>
                      <div>
                        <label htmlFor="email" className="text-xs font-semibold text-slate-600">
                          Email kantor
                        </label>
                        <input
                          id="email"
                          type="email"
                          value={values.requester_email}
                          maxLength={255}
                          onChange={(e) => update('requester_email', e.target.value)}
                          className={fieldClass(errors.requester_email)}
                          aria-describedby={errors.requester_email ? 'email-error' : 'contact-hint'}
                          autoComplete="email"
                          placeholder="nama@perusahaan.co.id"
                        />
                        <ErrorText id="email-error">{errors.requester_email}</ErrorText>
                      </div>
                    </div>
                  </div>
                </fieldset>

                <div className="h-px bg-slate-200" />

                <fieldset disabled={submitting} className="space-y-5">
                  <legend className="mb-4 flex items-center gap-2 text-base font-bold text-slate-950">
                    <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 text-xs text-blue-800">
                      2
                    </span>
                    Detail kebutuhan
                  </legend>
                  <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                      <label htmlFor="category" className="text-sm font-semibold text-slate-700">
                        Kategori tiket *
                      </label>
                      <select
                        id="category"
                        value={values.ticket_category_id}
                        onChange={(e) => update('ticket_category_id', e.target.value)}
                        className={fieldClass(errors.ticket_category_id)}
                        aria-invalid={Boolean(errors.ticket_category_id)}
                        aria-describedby={errors.ticket_category_id ? 'category-error' : undefined}
                      >
                        <option value="">{loadingOptions ? 'Memuat kategori...' : 'Pilih kategori'}</option>
                        {options?.categories.map((item) => (
                          <option key={item.id} value={item.id}>
                            {item.name}
                          </option>
                        ))}
                      </select>
                      <ErrorText id="category-error">{errors.ticket_category_id}</ErrorText>
                    </div>
                    <div>
                      <label htmlFor="application" className="text-sm font-semibold text-slate-700">
                        Sistem / aplikasi *
                      </label>
                      <select
                        id="application"
                        value={values.application_id}
                        onChange={(e) => update('application_id', e.target.value)}
                        className={fieldClass(errors.application_id)}
                        aria-invalid={Boolean(errors.application_id)}
                        aria-describedby={errors.application_id ? 'application-error' : undefined}
                      >
                        <option value="">{loadingOptions ? 'Memuat sistem...' : 'Pilih sistem / aplikasi'}</option>
                        {options?.applications.map((item) => (
                          <option key={item.id} value={item.id}>
                            {item.name}
                          </option>
                        ))}
                      </select>
                      <ErrorText id="application-error">{errors.application_id}</ErrorText>
                    </div>
                  </div>
                  <div>
                    <label htmlFor="title" className="text-sm font-semibold text-slate-700">
                      Judul pengajuan *
                    </label>
                    <input
                      id="title"
                      value={values.title}
                      maxLength={200}
                      onChange={(e) => update('title', e.target.value)}
                      className={fieldClass(errors.title)}
                      aria-describedby={errors.title ? 'title-error' : 'title-hint'}
                      placeholder="Ringkas kebutuhan atau kendala utama"
                    />
                    <div className="mt-1.5 flex justify-between gap-3 text-xs text-slate-500">
                      <span id="title-hint">Maksimal 200 karakter.</span>
                      <span>{values.title.length}/200</span>
                    </div>
                    <ErrorText id="title-error">{errors.title}</ErrorText>
                  </div>
                  <div>
                    <TicketDescriptionEditor
                      id="public-ticket-description"
                      mode="public"
                      value={values.description}
                      error={errors.description}
                      disabled={submitting}
                      onChange={(value) => update('description', value)}
                    />
                    <p className="mt-1.5 text-xs leading-5 text-slate-500">
                      Jelaskan kejadian, langkah yang sudah dicoba, hasil yang diharapkan, dan dampaknya.
                    </p>
                  </div>
                  <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                      <label htmlFor="affected-url" className="text-sm font-semibold text-slate-700">
                        URL terdampak <span className="font-normal text-slate-400">(opsional)</span>
                      </label>
                      <input
                        id="affected-url"
                        type="url"
                        value={values.affected_url}
                        maxLength={2048}
                        onChange={(e) => update('affected_url', e.target.value)}
                        className={fieldClass(errors.affected_url)}
                        aria-describedby={errors.affected_url ? 'affected-url-error' : undefined}
                        placeholder="https://..."
                      />
                      <ErrorText id="affected-url-error">{errors.affected_url}</ErrorText>
                    </div>
                    <div>
                      <label htmlFor="reference" className="text-sm font-semibold text-slate-700">
                        Nomor referensi <span className="font-normal text-slate-400">(opsional)</span>
                      </label>
                      <input
                        id="reference"
                        value={values.reference}
                        maxLength={255}
                        onChange={(e) => update('reference', e.target.value)}
                        className={fieldClass(errors.reference)}
                        aria-describedby={errors.reference ? 'reference-error' : undefined}
                        placeholder="Nomor polis, transaksi, atau dokumen"
                      />
                      <ErrorText id="reference-error">{errors.reference}</ErrorText>
                    </div>
                  </div>
                  <fieldset aria-describedby={errors.urgency ? 'urgency-error' : undefined}>
                    <legend className="text-sm font-semibold text-slate-700">Tingkat urgensi *</legend>
                    <div className="mt-2 grid gap-2 sm:grid-cols-3">
                      {[
                        ['low', 'Rendah', 'Tidak menghambat pekerjaan utama'],
                        ['medium', 'Sedang', 'Mengganggu sebagian pekerjaan'],
                        ['high', 'Tinggi', 'Menghambat pekerjaan utama'],
                      ].map(([value, label, detail]) => (
                        <label
                          key={value}
                          className={`cursor-pointer rounded-xl border p-3 transition focus-within:ring-2 focus-within:ring-blue-700 focus-within:ring-offset-2 ${values.urgency === value ? 'border-blue-700 bg-blue-50 ring-2 ring-blue-100' : 'border-slate-300 hover:border-blue-400'}`}
                        >
                          <input
                            type="radio"
                            name="urgency"
                            value={value}
                            checked={values.urgency === value}
                            onChange={() => update('urgency', value)}
                            className="sr-only"
                          />
                          <span className="block text-sm font-bold text-slate-900">{label}</span>
                          <span className="mt-1 block text-xs leading-5 text-slate-500">{detail}</span>
                        </label>
                      ))}
                    </div>
                    <ErrorText id="urgency-error">{errors.urgency}</ErrorText>
                  </fieldset>
                </fieldset>

                <div className="h-px bg-slate-200" />

                <fieldset disabled={submitting}>
                  <legend className="flex items-center gap-2 text-base font-bold text-slate-950">
                    <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 text-xs text-blue-800">
                      3
                    </span>
                    Lampiran <span className="text-sm font-normal text-slate-400">(opsional)</span>
                  </legend>
                  <div
                    className={`mt-4 rounded-2xl border border-dashed p-5 ${errors.attachments ? 'border-red-400 bg-red-50/40' : 'border-slate-300 bg-slate-50'}`}
                  >
                    <Upload className="h-7 w-7 text-blue-700" />
                    <p className="mt-2 text-sm font-semibold text-slate-800">Pilih dokumen atau tangkapan layar</p>
                    <p className="mt-1 text-xs leading-5 text-slate-500">
                      JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, atau XLSX. Maksimal 10 file, 10 MB per file.
                    </p>
                    <input
                      id="attachments"
                      type="file"
                      multiple
                      accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx"
                      onChange={selectFiles}
                      aria-describedby={errors.attachments ? 'attachments-error' : undefined}
                      className="mt-4 block w-full min-w-0 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-100 file:px-3 file:py-2 file:font-semibold file:text-blue-800 hover:file:bg-blue-200"
                    />
                  </div>
                  <ErrorText id="attachments-error">{errors.attachments}</ErrorText>
                  {files.length > 0 && (
                    <ul className="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
                      {files.map((file, index) => (
                        <li key={`${file.name}-${file.lastModified}`} className="flex min-w-0 items-center gap-3 p-3">
                          <FileText className="h-5 w-5 shrink-0 text-blue-700" />
                          <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-slate-800">{file.name}</p>
                            <p className="text-xs text-slate-500">{(file.size / 1024 / 1024).toFixed(2)} MB</p>
                          </div>
                          <button
                            type="button"
                            onClick={() => setFiles((current) => current.filter((_, itemIndex) => itemIndex !== index))}
                            className="rounded-lg p-2 text-red-600 hover:bg-red-50"
                            aria-label={`Hapus ${file.name}`}
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </fieldset>

                <div className="absolute left-[-10000px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                  <label htmlFor="website">Website</label>
                  <input
                    id="website"
                    name="website"
                    value={values.website}
                    onChange={(e) => update('website', e.target.value)}
                    tabIndex={-1}
                    autoComplete="off"
                  />
                </div>

                <div className="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-950">
                  Dengan mengirim formulir ini, Anda memastikan informasi yang diberikan benar dan dapat digunakan untuk
                  verifikasi pengajuan.
                </div>
                <button
                  type="submit"
                  disabled={submitting || loadingOptions || Boolean(optionsError) || noOptions}
                  className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#12367a] px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-900/20 transition hover:bg-[#0d2a63] disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {submitting ? <Loader2 className="h-5 w-5 animate-spin" /> : <Send className="h-5 w-5" />}
                  {submitting ? 'Mengirim pengajuan...' : 'Kirim pengajuan'}
                </button>
              </form>
            </section>

            <aside className="space-y-4 lg:sticky lg:top-6">
              <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <Building2 className="h-6 w-6 text-blue-700" />
                <h2 className="mt-3 font-bold text-slate-950">Sebelum mengirim</h2>
                <ul className="mt-3 space-y-3 text-sm leading-6 text-slate-600">
                  <li className="flex gap-2">
                    <Check className="mt-1 h-4 w-4 shrink-0 text-emerald-600" />
                    Pilih cabang dan sistem yang sesuai.
                  </li>
                  <li className="flex gap-2">
                    <Check className="mt-1 h-4 w-4 shrink-0 text-emerald-600" />
                    Jelaskan kendala beserta dampaknya.
                  </li>
                  <li className="flex gap-2">
                    <Check className="mt-1 h-4 w-4 shrink-0 text-emerald-600" />
                    Pastikan kontak dapat dihubungi.
                  </li>
                </ul>
              </div>
              <div className="rounded-2xl bg-[#102d64] p-5 text-white shadow-lg">
                <LockKeyhole className="h-6 w-6 text-cyan-300" />
                <h2 className="mt-3 font-bold">Data pengajuan terlindungi</h2>
                <p className="mt-2 text-xs leading-5 text-blue-100">
                  Lampiran dan rincian tiket hanya digunakan oleh tim berwenang untuk proses penanganan.
                </p>
              </div>
            </aside>
          </div>
        </div>
      </main>
    </PublicShell>
  )
}
