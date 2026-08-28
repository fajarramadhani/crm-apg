import { useEffect, useRef, useState, type ChangeEvent, type FormEvent } from 'react'
import {
  AlertCircle,
  Building2,
  Check,
  CheckCircle2,
  ChevronDown,
  Clipboard,
  File,
  FileSpreadsheet,
  FileText,
  Headphones,
  Image as ImageIcon,
  Loader2,
  LockKeyhole,
  Plus,
  Send,
  Trash2,
  Upload,
  User,
  Wrench,
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

const DRAFT_STORAGE_KEY = 'tic-hub:public-request:draft:v1'
const DRAFT_SAVE_DELAY_MS = 500

type DraftFileMeta = { name: string; size: number; lastModified: number }

function restoreDraft() {
  try {
    const raw = window.localStorage.getItem(DRAFT_STORAGE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as {
      values?: Partial<FormValues>
      filesMeta?: DraftFileMeta[]
      savedAt?: string
    } | null
    if (!parsed || typeof parsed !== 'object' || typeof parsed.values !== 'object') return null
    return {
      values: { ...EMPTY_FORM, ...parsed.values } as FormValues,
      filesMeta: Array.isArray(parsed.filesMeta) ? (parsed.filesMeta as DraftFileMeta[]) : [],
      savedAt: typeof parsed.savedAt === 'string' ? parsed.savedAt : undefined,
    }
  } catch {
    return null
  }
}

const FIELD_ORDER = [
  'requester_name',
  'branch_id',
  'requester_phone',
  'requester_email',
  'ticket_category_id',
  'application_id',
  'title',
  'description',
  'affected_url',
  'reference',
  'urgency',
  'attachments',
]

const DESCRIPTION_TEMPLATES = [
  { key: 'event', label: 'Kejadian', prompt: 'Kejadian' },
  { key: 'steps', label: 'Langkah dicoba', prompt: 'Langkah yang sudah dicoba' },
  { key: 'expected', label: 'Hasil diharapkan', prompt: 'Hasil yang diharapkan' },
  { key: 'impact', label: 'Dampak', prompt: 'Dampak' },
] as const

const FIELD_SELECTORS: Record<string, string[]> = {
  requester_name: ['#requester-name'],
  branch_id: ['#branch'],
  division_id: ['#division'],
  requester_phone: ['#phone'],
  requester_email: ['#email'],
  ticket_category_id: ['#category'],
  application_id: ['#application'],
  title: ['#title'],
  description: ['.tox-tinymce', '#public-ticket-description'],
  affected_url: ['#affected-url'],
  reference: ['#reference'],
  urgency: ['#urgency input[type="radio"]'],
  attachments: ['#attachments'],
}

function fieldClass(error?: string) {
  return `mt-1.5 block w-full rounded-2xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:ring-4 disabled:cursor-not-allowed disabled:bg-slate-100 ${
    error
      ? 'border-red-400 focus:border-red-500 focus:ring-red-100'
      : 'border-slate-300 focus:border-blue-700 focus:ring-blue-100'
  }`
}

function fileDetails(name: string) {
  const ext = name.split('.').pop()?.toLowerCase() ?? ''
  if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) return { Icon: ImageIcon, tile: 'bg-violet-100 text-violet-700' }
  if (ext === 'pdf') return { Icon: FileText, tile: 'bg-rose-100 text-rose-700' }
  if (['doc', 'docx'].includes(ext)) return { Icon: FileText, tile: 'bg-blue-100 text-blue-700' }
  if (['xls', 'xlsx'].includes(ext)) return { Icon: FileSpreadsheet, tile: 'bg-emerald-100 text-emerald-700' }
  return { Icon: File, tile: 'bg-slate-100 text-slate-600' }
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
  const [draftRestored, setDraftRestored] = useState(false)
  const [draftRestoredAt, setDraftRestoredAt] = useState('')
  const [draftSavedAt, setDraftSavedAt] = useState('')
  const [draftStatus, setDraftStatus] = useState<'none' | 'saving' | 'saved'>('none')
  const [restoredFileNames, setRestoredFileNames] = useState<string[]>([])
  const [showStickySubmit, setShowStickySubmit] = useState(false)
  const saveTimer = useRef<number | null>(null)
  const skipNextSave = useRef(false)
  const formRef = useRef<HTMLFormElement>(null)
  const submitButtonRef = useRef<HTMLButtonElement>(null)

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

  useEffect(() => {
    const draft = restoreDraft()
    if (!draft) return
    skipNextSave.current = true
    setValues((current) => ({ ...current, ...draft.values }))
    if (draft.savedAt) {
      const parsed = new Date(draft.savedAt)
      if (!Number.isNaN(parsed.getTime())) {
        setDraftRestoredAt(parsed.toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }))
      }
    }
    if (draft.filesMeta.length) {
      setRestoredFileNames(draft.filesMeta.map((file) => file.name))
    }
    setDraftRestored(true)
  }, [])

  useEffect(() => {
    if (skipNextSave.current) {
      skipNextSave.current = false
      return
    }
    if (receipt) return
    const hasContent =
      values.requester_name.trim() ||
      values.requester_phone.trim() ||
      values.requester_email.trim() ||
      values.branch_id ||
      values.division_id ||
      values.ticket_category_id ||
      values.application_id ||
      values.title.trim() ||
      values.description.trim() ||
      values.affected_url.trim() ||
      values.reference.trim() ||
      values.urgency ||
      files.length > 0
    if (!hasContent) {
      if (saveTimer.current) window.clearTimeout(saveTimer.current)
      window.localStorage.removeItem(DRAFT_STORAGE_KEY)
      setDraftStatus('none')
      return
    }
    setDraftStatus('saving')
    if (saveTimer.current) window.clearTimeout(saveTimer.current)
    saveTimer.current = window.setTimeout(() => {
      const meta: DraftFileMeta[] = files.map((file) => ({
        name: file.name,
        size: file.size,
        lastModified: file.lastModified,
      }))
      const clean: FormValues = { ...values, website: '' }
      window.localStorage.setItem(
        DRAFT_STORAGE_KEY,
        JSON.stringify({ values: clean, filesMeta: meta, savedAt: new Date().toISOString() }),
      )
      setDraftSavedAt(new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }))
      setDraftStatus('saved')
    }, DRAFT_SAVE_DELAY_MS)
    return () => {
      if (saveTimer.current) window.clearTimeout(saveTimer.current)
    }
  }, [values, files, receipt])

  useEffect(() => {
    const target = submitButtonRef.current
    if (!target) return
    const observer = new IntersectionObserver(([entry]) => setShowStickySubmit(!entry.isIntersecting), {
      rootMargin: '0px 0px 96px 0px',
    })
    observer.observe(target)
    return () => observer.disconnect()
  }, [receipt])

  const update = (field: keyof FormValues, value: string) => {
    setValues((current) => ({ ...current, [field]: value }))
    setErrors((current) => ({ ...current, [field]: '' }))
  }

  const getFieldError = (field: string): string | undefined => {
    const name = values.requester_name.trim()
    const phone = values.requester_phone.trim()
    const email = values.requester_email.trim()
    const normalizedPhone = phone
      .replace(/[^0-9+]/g, '')
      .replace(/^\+62/, '62')
      .replace(/^0/, '62')

    switch (field) {
      case 'requester_name':
        if (!name) return 'Nama pemohon wajib diisi.'
        if (name.length > 150) return 'Nama pemohon maksimal 150 karakter.'
        return undefined
      case 'branch_id':
        return values.branch_id ? undefined : 'Cabang wajib dipilih.'
      case 'requester_phone':
        if (!phone && !email) return 'Isi minimal satu kontak: WhatsApp atau email kantor.'
        if (phone && !/^628\d{8,11}$/.test(normalizedPhone)) {
          return 'Gunakan nomor Indonesia, contoh 081234567890 atau +6281234567890.'
        }
        return undefined
      case 'requester_email':
        if (!phone && !email) return 'Isi minimal satu kontak: WhatsApp atau email kantor.'
        if (email && (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || email.length > 255)) {
          return 'Alamat email tidak valid atau melebihi 255 karakter.'
        }
        return undefined
      case 'ticket_category_id':
        return values.ticket_category_id ? undefined : 'Kategori tiket wajib dipilih.'
      case 'application_id':
        return values.application_id ? undefined : 'Sistem/aplikasi wajib dipilih.'
      case 'title':
        if (!values.title.trim()) return 'Judul pengajuan wajib diisi.'
        if (values.title.trim().length > 200) return 'Judul maksimal 200 karakter.'
        return undefined
      case 'description':
        if (!ticketDescriptionText(values.description)) return 'Deskripsi pengajuan wajib diisi.'
        if (values.description.length > 10000) return 'Deskripsi maksimal 10.000 karakter.'
        return undefined
      case 'affected_url':
        if (values.affected_url.trim()) {
          if (values.affected_url.trim().length > 2048) return 'URL maksimal 2.048 karakter.'
          try {
            const url = new URL(values.affected_url.trim())
            if (!['http:', 'https:'].includes(url.protocol)) throw new Error('invalid protocol')
          } catch {
            return 'Masukkan URL lengkap yang diawali http:// atau https://.'
          }
        }
        return undefined
      case 'reference':
        if (values.reference.trim().length > 255) return 'Referensi maksimal 255 karakter.'
        return undefined
      case 'urgency':
        return values.urgency ? undefined : 'Tingkat urgensi wajib dipilih.'
      case 'attachments':
        if (files.length > MAX_FILES) return `Maksimal ${MAX_FILES} lampiran.`
        const invalidFile = files.find(
          (file) => !ALLOWED_EXTENSIONS.includes(file.name.split('.').pop()?.toLowerCase() ?? ''),
        )
        if (invalidFile) return `Format ${invalidFile.name} tidak didukung.`
        const oversizedFile = files.find((file) => file.size > MAX_FILE_SIZE)
        if (oversizedFile) return `${oversizedFile.name} melebihi batas 10 MB.`
        return undefined
      default:
        return undefined
    }
  }

  const validate = () => {
    const next: Record<string, string> = {}
    for (const field of FIELD_ORDER) {
      const message = getFieldError(field)
      if (message) next[field] = message
    }
    const firstInvalid = FIELD_ORDER.find((field) => field in next) ?? null
    setErrors(next)
    if (!firstInvalid) return null
    setFormError('Periksa kembali kolom yang ditandai sebelum mengirim pengajuan.')
    return firstInvalid
  }

  const handleBlur = (field: string) => {
    const message = getFieldError(field)
    if (!message) {
      setErrors((current) => ({ ...current, [field]: '' }))
      return
    }
    const raw = values[field as keyof FormValues]
    const hasContent = typeof raw === 'string' && raw.trim().length > 0
    if (field === 'attachments' || hasContent) {
      setErrors((current) => ({ ...current, [field]: message }))
    }
  }

  const insertDescriptionPrompt = (prompt: string) => {
    setValues((current) => {
      if (current.description.includes(prompt)) return current
      const suffix = `<p>\u2022 ${prompt}: </p>`
      const next = current.description.trim() ? `${current.description.trimEnd()}${suffix}` : suffix
      return { ...current, description: next }
    })
    setErrors((current) => ({ ...current, description: '' }))
  }

  const focusField = (field: string) => {
    for (const selector of FIELD_SELECTORS[field] ?? []) {
      const target = Array.from(document.querySelectorAll<HTMLElement>(selector)).find((el) => {
        if (el.hasAttribute('disabled')) return false
        return (
          ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'A'].includes(el.tagName) ||
          el.getAttribute('tabindex') !== null ||
          el.classList.contains('tox-tinymce')
        )
      })
      if (target) {
        target.focus()
        return
      }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const clearDraft = () => {
    if (saveTimer.current) window.clearTimeout(saveTimer.current)
    window.localStorage.removeItem(DRAFT_STORAGE_KEY)
    setDraftRestored(false)
    setDraftRestoredAt('')
    setRestoredFileNames([])
    setDraftStatus('none')
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
    if (submitting) return
    const firstInvalid = validate()
    if (firstInvalid) {
      focusField(firstInvalid)
      return
    }
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
      clearDraft()
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
    clearDraft()
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const sectionDone1 = Boolean(
    values.requester_name.trim() &&
    values.branch_id &&
    (values.requester_phone.trim() || values.requester_email.trim()),
  )
  const sectionDone2 =
    Boolean(values.ticket_category_id) &&
    Boolean(values.application_id) &&
    Boolean(values.title.trim()) &&
    Boolean(ticketDescriptionText(values.description)) &&
    Boolean(values.urgency)
  const steps: Array<{ num: string; label: string; done: boolean }> = [
    { num: '1', label: 'Data pemohon', done: sectionDone1 },
    { num: '2', label: 'Detail kebutuhan', done: sectionDone2 },
    { num: '3', label: 'Lampiran', done: true },
  ]

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
          <section className="w-full animate-in overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-xl shadow-slate-200/70">
            <div className="relative overflow-hidden bg-gradient-to-br from-emerald-600 to-teal-700 px-6 py-9 text-center text-white sm:px-10">
              <div
                className="absolute inset-0 opacity-20 [background-image:radial-gradient(circle_at_18%_15%,rgba(255,255,255,0.5),transparent_45%),radial-gradient(circle_at_85%_85%,rgba(255,255,255,0.35),transparent_40%)]"
                aria-hidden="true"
              />
              <div className="relative">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
                  <CheckCircle2 className="h-9 w-9" />
                </div>
                <h1 className="mt-5 text-2xl font-bold sm:text-3xl">Pengajuan berhasil diterima</h1>
                <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-emerald-50">
                  Simpan nomor tiket berikut sebagai bukti penerimaan pengajuan Anda.
                </p>
              </div>
            </div>
            <div className="space-y-6 p-5 sm:p-9">
              <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-5 text-center ring-1 ring-emerald-100">
                <p className="text-xs font-bold uppercase tracking-[0.2em] text-emerald-700">Nomor tiket</p>
                <p className="mt-2 break-all font-mono text-2xl font-black tabular-nums text-slate-950 sm:text-3xl">
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
                  className="flex items-center justify-center rounded-xl bg-gradient-to-r from-[#12367a] to-blue-800 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-900/15 hover:from-[#0d2a63] hover:to-blue-900"
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
                  <p className="mt-1.5 text-xs text-slate-600">
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
                className="flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-[#12367a] to-blue-800 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-900/15 hover:from-[#0d2a63] hover:to-blue-900"
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
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_85%_10%,rgba(34,211,238,0.2),transparent_32%),radial-gradient(circle_at_15%_90%,rgba(59,130,246,0.22),transparent_38%)]" />
          <div
            className="absolute inset-0 opacity-[0.06] [background-image:linear-gradient(rgba(255,255,255,0.55)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.55)_1px,transparent_1px)] [background-size:28px_28px]"
            aria-hidden="true"
          />
          <div className="relative mx-auto max-w-6xl">
            <div className="inline-flex items-center gap-2.5 rounded-full border border-blue-300/20 bg-blue-100/10 px-3.5 py-1.5 text-xs font-semibold text-blue-100">
              <span className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-cyan-300 opacity-75" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-cyan-300" />
              </span>
              <Headphones className="h-3.5 w-3.5 text-cyan-300" /> Dukungan sistem untuk karyawan APG
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
              <div className="h-1.5 bg-gradient-to-r from-[#0b1f48] via-[#12367a] to-cyan-400" aria-hidden="true" />
              <div className="border-b border-slate-200 px-5 py-6 sm:px-8">
                <div className="flex items-center gap-3.5">
                  <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#12367a] to-blue-700 text-white shadow-lg shadow-blue-900/15">
                    <FileText className="h-6 w-6" />
                  </div>
                  <div className="min-w-0">
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-blue-700">
                      Formulir pengajuan publik
                    </p>
                    <h2 className="mt-0.5 text-xl font-bold text-slate-950">Informasi pengajuan</h2>
                    <p className="mt-1 text-sm text-slate-600">Kolom bertanda * wajib diisi.</p>
                  </div>
                </div>
              </div>

              <ol
                className="flex items-center gap-1 border-b border-slate-100 px-5 py-4 sm:gap-2 sm:px-8"
                aria-label="Tahapan formulir"
              >
                {steps.map((step, index) => {
                  const isCurrent =
                    !step.done && (index === 0 || steps.slice(0, index).every((previous) => previous.done))
                  return (
                    <li key={step.num} className="flex min-w-0 flex-1 items-center gap-1 sm:gap-2">
                      {index > 0 && (
                        <span
                          className={`h-px w-3 shrink-0 ${index === 1 ? (sectionDone1 ? 'bg-emerald-400' : 'bg-slate-200') : sectionDone1 && sectionDone2 ? 'bg-emerald-400' : 'bg-slate-200'}`}
                          aria-hidden="true"
                        />
                      )}
                      <span
                        className={`inline-flex min-w-0 items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-bold sm:px-3 ${
                          step.done
                            ? 'bg-emerald-50 text-emerald-800'
                            : isCurrent
                              ? 'bg-blue-700 text-white'
                              : 'bg-slate-100 text-slate-500'
                        }`}
                      >
                        <span
                          className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full ${
                            step.done
                              ? 'bg-emerald-600 text-white'
                              : isCurrent
                                ? 'bg-white/20'
                                : 'bg-slate-200 text-slate-500'
                          }`}
                        >
                          {step.done ? <Check className="h-3.5 w-3.5" /> : step.num}
                        </span>
                        <span className="truncate">{step.label}</span>
                      </span>
                    </li>
                  )
                })}
              </ol>

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
              {draftRestored && (
                <div className="mx-5 mt-5 flex flex-col gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 sm:mx-8 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0">
                    <p>Draft pengajuan dipulihkan{draftRestoredAt ? ` (disimpan ${draftRestoredAt})` : ''}.</p>
                    {restoredFileNames.length > 0 && (
                      <p className="mt-1">
                        Lampiran yang dipilih sebelumnya perlu dipilih ulang:{' '}
                        <span className="font-semibold">{restoredFileNames.join(', ')}</span>.
                      </p>
                    )}
                  </div>
                  <button
                    type="button"
                    onClick={clearDraft}
                    className="shrink-0 font-bold underline hover:text-blue-700"
                  >
                    Buang Draft
                  </button>
                </div>
              )}

              <form ref={formRef} onSubmit={submit} noValidate className="space-y-8 p-5 sm:p-8">
                <fieldset disabled={submitting} className="space-y-5">
                  <legend className="mb-4 flex items-center justify-between gap-3">
                    <span className="flex items-center gap-2.5 text-base font-bold text-slate-950">
                      <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#12367a] to-blue-700 text-white shadow-sm">
                        <User className="h-5 w-5" />
                      </span>
                      Data pemohon
                    </span>
                    <span
                      className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full ${sectionDone1 ? 'bg-emerald-100 text-emerald-700' : 'border border-slate-300 text-transparent'}`}
                      title={sectionDone1 ? 'Seksi lengkap' : 'Belum lengkap'}
                    >
                      {sectionDone1 && <Check className="h-3.5 w-3.5" />}
                    </span>
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
                      onBlur={() => handleBlur('requester_name')}
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
                      <div className="relative">
                        <select
                          id="branch"
                          value={values.branch_id}
                          onChange={(e) => update('branch_id', e.target.value)}
                          onBlur={() => handleBlur('branch_id')}
                          className={`${fieldClass(errors.branch_id)} appearance-none pr-10`}
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
                        <ChevronDown
                          className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                          aria-hidden="true"
                        />
                      </div>
                      <ErrorText id="branch-error">{errors.branch_id}</ErrorText>
                    </div>
                    <div>
                      <label htmlFor="division" className="text-sm font-semibold text-slate-700">
                        Divisi <span className="font-normal text-slate-500">(opsional)</span>
                      </label>
                      <div className="relative">
                        <select
                          id="division"
                          value={values.division_id}
                          onChange={(e) => update('division_id', e.target.value)}
                          className={`${fieldClass(errors.division_id)} appearance-none pr-10`}
                        >
                          <option value="">Pilih divisi bila relevan</option>
                          {options?.divisions.map((item) => (
                            <option key={item.id} value={item.id}>
                              {item.name}
                            </option>
                          ))}
                        </select>
                        <ChevronDown
                          className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                          aria-hidden="true"
                        />
                      </div>
                      <ErrorText id="division-error">{errors.division_id}</ErrorText>
                    </div>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-slate-700">Kontak yang dapat dihubungi *</p>
                    <p id="contact-hint" className="mt-1 text-xs leading-5 text-slate-600">
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
                          onBlur={() => handleBlur('requester_phone')}
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
                          onBlur={() => handleBlur('requester_email')}
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

                <div className="h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent" />

                <fieldset disabled={submitting} className="space-y-5">
                  <legend className="mb-4 flex items-center justify-between gap-3">
                    <span className="flex items-center gap-2.5 text-base font-bold text-slate-950">
                      <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#12367a] to-blue-700 text-white shadow-sm">
                        <Wrench className="h-5 w-5" />
                      </span>
                      Detail kebutuhan
                    </span>
                    <span
                      className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full ${sectionDone2 ? 'bg-emerald-100 text-emerald-700' : 'border border-slate-300 text-transparent'}`}
                      title={sectionDone2 ? 'Seksi lengkap' : 'Belum lengkap'}
                    >
                      {sectionDone2 && <Check className="h-3.5 w-3.5" />}
                    </span>
                  </legend>
                  <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                      <label htmlFor="category" className="text-sm font-semibold text-slate-700">
                        Kategori tiket *
                      </label>
                      <div className="relative">
                        <select
                          id="category"
                          value={values.ticket_category_id}
                          onChange={(e) => update('ticket_category_id', e.target.value)}
                          onBlur={() => handleBlur('ticket_category_id')}
                          className={`${fieldClass(errors.ticket_category_id)} appearance-none pr-10`}
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
                        <ChevronDown
                          className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                          aria-hidden="true"
                        />
                      </div>
                      <ErrorText id="category-error">{errors.ticket_category_id}</ErrorText>
                    </div>
                    <div>
                      <label htmlFor="application" className="text-sm font-semibold text-slate-700">
                        Sistem / aplikasi *
                      </label>
                      <div className="relative">
                        <select
                          id="application"
                          value={values.application_id}
                          onChange={(e) => update('application_id', e.target.value)}
                          onBlur={() => handleBlur('application_id')}
                          className={`${fieldClass(errors.application_id)} appearance-none pr-10`}
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
                        <ChevronDown
                          className="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                          aria-hidden="true"
                        />
                      </div>
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
                      onBlur={() => handleBlur('title')}
                      className={fieldClass(errors.title)}
                      aria-describedby={errors.title ? 'title-error' : 'title-hint'}
                      placeholder="Ringkas kebutuhan atau kendala utama"
                    />
                    <div className="mt-1.5 flex justify-between gap-3 text-xs text-slate-600">
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
                    <div className="mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                      <p className="text-xs font-semibold text-slate-600">
                        Panduan menulis — klik untuk menambahkan bagian ke deskripsi:
                      </p>
                      <div className="mt-2 flex flex-wrap gap-2">
                        {DESCRIPTION_TEMPLATES.map((item) => (
                          <button
                            key={item.key}
                            type="button"
                            onClick={() => insertDescriptionPrompt(item.prompt)}
                            disabled={submitting}
                            className="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-white px-3 py-1.5 text-xs font-bold text-blue-900 hover:bg-blue-100 disabled:opacity-50"
                          >
                            <Plus className="h-3.5 w-3.5" />
                            {item.label}
                          </button>
                        ))}
                      </div>
                    </div>
                    <div className="mt-3 flex items-start justify-between gap-3">
                      <p className="text-xs leading-5 text-slate-600">
                        Lengkapi kejadian, langkah yang sudah dicoba, hasil yang diharapkan, dan dampaknya.
                      </p>
                      <span
                        className={`shrink-0 text-xs font-medium ${values.description.length > 10000 ? 'text-red-600' : 'text-slate-500'}`}
                      >
                        {ticketDescriptionText(values.description).length}/10.000
                      </span>
                    </div>
                  </div>
                  <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                      <label htmlFor="affected-url" className="text-sm font-semibold text-slate-700">
                        URL terdampak <span className="font-normal text-slate-500">(opsional)</span>
                      </label>
                      <input
                        id="affected-url"
                        type="url"
                        value={values.affected_url}
                        maxLength={2048}
                        onChange={(e) => update('affected_url', e.target.value)}
                        onBlur={() => handleBlur('affected_url')}
                        className={fieldClass(errors.affected_url)}
                        aria-describedby={errors.affected_url ? 'affected-url-error' : undefined}
                        placeholder="https://..."
                      />
                      <ErrorText id="affected-url-error">{errors.affected_url}</ErrorText>
                    </div>
                    <div>
                      <label htmlFor="reference" className="text-sm font-semibold text-slate-700">
                        Nomor referensi <span className="font-normal text-slate-500">(opsional)</span>
                      </label>
                      <input
                        id="reference"
                        value={values.reference}
                        maxLength={255}
                        onChange={(e) => update('reference', e.target.value)}
                        onBlur={() => handleBlur('reference')}
                        className={fieldClass(errors.reference)}
                        aria-describedby={errors.reference ? 'reference-error' : undefined}
                        placeholder="Nomor polis, transaksi, atau dokumen"
                      />
                      <ErrorText id="reference-error">{errors.reference}</ErrorText>
                    </div>
                  </div>
                  <fieldset id="urgency" aria-describedby={errors.urgency ? 'urgency-error' : undefined}>
                    <legend className="text-sm font-semibold text-slate-700">Tingkat urgensi *</legend>
                    <div className="mt-2 grid gap-2 sm:grid-cols-3">
                      {[
                        ['low', 'Rendah', 'Tidak menghambat pekerjaan utama'],
                        ['medium', 'Sedang', 'Mengganggu sebagian pekerjaan'],
                        ['high', 'Tinggi', 'Menghambat pekerjaan utama'],
                      ].map(([value, label, detail]) => (
                        <label
                          key={value}
                          className={`cursor-pointer rounded-2xl border p-3 transition focus-within:ring-2 focus-within:ring-blue-700 focus-within:ring-offset-2 ${
                            values.urgency === value
                              ? 'border-blue-700 bg-gradient-to-br from-blue-50 to-white ring-2 ring-blue-100'
                              : 'border-slate-300 hover:border-blue-400'
                          }`}
                        >
                          <input
                            type="radio"
                            name="urgency"
                            value={value}
                            checked={values.urgency === value}
                            onChange={() => update('urgency', value)}
                            className="sr-only"
                          />
                          <span className="flex items-center gap-1.5">
                            <span
                              className={`h-2 w-2 shrink-0 rounded-full ${value === 'low' ? 'bg-emerald-500' : value === 'medium' ? 'bg-amber-500' : 'bg-rose-500'}`}
                              aria-hidden="true"
                            />
                            <span className="text-sm font-bold text-slate-900">{label}</span>
                          </span>
                          <span className="mt-1 block text-xs leading-5 text-slate-600">{detail}</span>
                        </label>
                      ))}
                    </div>
                    <ErrorText id="urgency-error">{errors.urgency}</ErrorText>
                  </fieldset>
                </fieldset>

                <div className="h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent" />

                <fieldset disabled={submitting}>
                  <legend className="flex items-center gap-2.5 text-base font-bold text-slate-950">
                    <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-[#12367a] to-blue-700 text-white shadow-sm">
                      <Paperclip className="h-5 w-5" />
                    </span>
                    Lampiran <span className="text-sm font-normal text-slate-500">(opsional)</span>
                  </legend>
                  <div
                    className={`mt-4 rounded-2xl border-2 border-dashed p-6 transition ${
                      errors.attachments
                        ? 'border-red-300 bg-red-50/40'
                        : 'border-slate-300 bg-slate-50 hover:border-blue-400 hover:bg-blue-50/40'
                    }`}
                  >
                    <div className="flex flex-col items-center justify-center text-center">
                      <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-100 to-cyan-100 text-blue-700 shadow-sm">
                        <Upload className="h-6 w-6" />
                      </div>
                      <p className="mt-3 text-sm font-semibold text-slate-800">Pilih dokumen atau tangkapan layar</p>
                      <p className="mt-1 text-xs leading-5 text-slate-600">
                        JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, atau XLSX. Maksimal 10 file, 10 MB per file.
                      </p>
                      <input
                        id="attachments"
                        type="file"
                        multiple
                        accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx"
                        onChange={selectFiles}
                        aria-describedby={errors.attachments ? 'attachments-error' : undefined}
                        className="mt-4 block w-full max-w-xs min-w-0 text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-blue-700 file:px-4 file:py-2.5 file:font-semibold file:text-white hover:file:bg-blue-800"
                      />
                    </div>
                  </div>
                  <ErrorText id="attachments-error">{errors.attachments}</ErrorText>
                  {files.length > 0 && (
                    <ul className="mt-3 space-y-2">
                      {files.map((file, index) => {
                        const detail = fileDetails(file.name)
                        return (
                          <li
                            key={`${file.name}-${file.lastModified}`}
                            className="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm"
                          >
                            <div
                              className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${detail.tile}`}
                            >
                              <detail.Icon className="h-5 w-5" />
                            </div>
                            <div className="min-w-0 flex-1">
                              <p className="truncate text-sm font-medium text-slate-800">{file.name}</p>
                              <p className="text-xs text-slate-500">{(file.size / 1024 / 1024).toFixed(2)} MB</p>
                            </div>
                            <button
                              type="button"
                              onClick={() =>
                                setFiles((current) => current.filter((_, itemIndex) => itemIndex !== index))
                              }
                              className="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                              aria-label={`Hapus ${file.name}`}
                            >
                              <Trash2 className="h-4 w-4" />
                            </button>
                          </li>
                        )
                      })}
                    </ul>
                  )}
                </fieldset>

                <div className="flex items-center justify-end gap-3 text-xs text-slate-500">
                  {draftStatus === 'saving' && (
                    <span className="inline-flex items-center gap-1.5">
                      <Loader2 className="h-3.5 w-3.5 animate-spin" /> Menyimpan draft&hellip;
                    </span>
                  )}
                  {draftStatus === 'saved' && draftSavedAt && (
                    <span className="inline-flex items-center gap-1.5 text-emerald-700">
                      <Check className="h-3.5 w-3.5" /> Draft tersimpan &middot; {draftSavedAt}
                    </span>
                  )}
                  {draftStatus === 'none' && null}
                </div>

                <div className="absolute left-[-10000px] top-auto h-px w-px overflow-hidden" inert aria-hidden="true">
                  <input
                    id="website"
                    name="website"
                    type="text"
                    value={values.website}
                    onChange={(e) => update('website', e.target.value)}
                    tabIndex={-1}
                    autoComplete="off"
                    aria-hidden="true"
                  />
                </div>

                <div className="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-950">
                  Dengan mengirim formulir ini, Anda memastikan informasi yang diberikan benar dan dapat digunakan untuk
                  verifikasi pengajuan.
                </div>
                <button
                  ref={submitButtonRef}
                  type="submit"
                  disabled={submitting || loadingOptions || Boolean(optionsError) || noOptions}
                  className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#12367a] px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-900/20 transition hover:bg-[#0d2a63] disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {submitting ? <Loader2 className="h-5 w-5 animate-spin" /> : <Send className="h-5 w-5" />}
                  {submitting ? 'Mengirim pengajuan...' : 'Kirim pengajuan'}
                </button>
              </form>
            </section>

            {showStickySubmit && !submitting && (
              <div className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-xl shadow-slate-900/10 backdrop-blur print:hidden lg:hidden">
                <div className="mx-auto flex max-w-6xl items-center gap-3">
                  <p className="min-w-0 flex-1 truncate text-xs font-semibold text-slate-500">
                    Formulir pengajuan publik
                  </p>
                  <button
                    type="button"
                    onClick={() => formRef.current?.requestSubmit()}
                    disabled={submitting || loadingOptions || Boolean(optionsError) || noOptions}
                    className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#12367a] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-900/20 hover:bg-[#0d2a63] disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    <Send className="h-4 w-4" />
                    Kirim pengajuan
                  </button>
                </div>
              </div>
            )}

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
