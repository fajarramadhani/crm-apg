import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import { Button, Card, Input, PageHeader, Select, Toast } from '../../components/ui'
import { TicketDescriptionEditor, ticketDescriptionText } from '../../components/TicketDescriptionEditor'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import { masterDataService, type Application } from '../../services/masterDataService'
import { Upload, Trash2, FileText, CheckCircle2, AlertCircle, ExternalLink } from 'lucide-react'

const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx']
const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024 // 10MB

export default function CreateTicket() {
  const navigate = useNavigate()
  const [requestCategory, setRequestCategory] = useState<'request' | 'error_bug' | 'other' | ''>('')
  const [applicationId, setApplicationId] = useState('')
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [affectedUrl, setAffectedUrl] = useState('')
  const [reference, setReference] = useState('')
  const [urgency, setUrgency] = useState<'low' | 'medium' | 'high' | ''>('')
  const [files, setFiles] = useState<File[]>([])
  const [applications, setApplications] = useState<Application[]>([])
  const [loadingApplications, setLoadingApplications] = useState(true)

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [createdTicket, setCreatedTicket] = useState<TicketRecord | null>(null)
  const [toastMessage, setToastMessage] = useState('')

  useEffect(() => {
    masterDataService
      .getApplications()
      .then(setApplications)
      .catch(() => setError('Daftar sistem tidak dapat dimuat. Muat ulang halaman untuk mencoba kembali.'))
      .finally(() => setLoadingApplications(false))
  }, [])

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (!e.target.files) return
    const selectedFiles = Array.from(e.target.files)
    setError('')
    setFieldErrors((prev) => ({ ...prev, attachments: '' }))

    const invalidFiles: string[] = []
    const oversizedFiles: string[] = []
    const validFiles: File[] = []

    selectedFiles.forEach((file) => {
      const ext = file.name.split('.').pop()?.toLowerCase() || ''
      if (!ALLOWED_EXTENSIONS.includes(ext)) {
        invalidFiles.push(file.name)
      } else if (file.size > MAX_FILE_SIZE_BYTES) {
        oversizedFiles.push(file.name)
      } else {
        validFiles.push(file)
      }
    })

    if (invalidFiles.length > 0) {
      setError(
        `Format file tidak diperbolehkan: ${invalidFiles.join(', ')}. Format yang didukung: ${ALLOWED_EXTENSIONS.join(', ')}`,
      )
      return
    }

    if (oversizedFiles.length > 0) {
      setError(`Ukuran file melebihi 10MB: ${oversizedFiles.join(', ')}`)
      return
    }

    setFiles((prev) => [...prev, ...validFiles])
    e.target.value = ''
  }

  const removeFile = (index: number) => {
    setFiles((prev) => prev.filter((_, i) => i !== index))
  }

  const validate = (): boolean => {
    const errors: Record<string, string> = {}

    if (!requestCategory) errors.request_category = 'Kategori pengajuan wajib dipilih.'
    if (!applicationId) errors.application_id = 'Nama sistem wajib dipilih.'

    if (!title.trim()) {
      errors.title = 'Judul pengajuan tiket wajib diisi.'
    } else if (title.length > 200) {
      errors.title = 'Judul maksimal 200 karakter.'
    }

    if (!ticketDescriptionText(description)) {
      errors.description = 'Deskripsi pengajuan tiket wajib diisi.'
    } else if (description.length > 10000) {
      errors.description = 'Deskripsi maksimal 10.000 karakter.'
    }

    if (requestCategory === 'error_bug' && !affectedUrl.trim()) {
      errors.affected_url = 'Link submission yang error wajib diisi.'
    } else if (affectedUrl.trim()) {
      const lower = affectedUrl.trim().toLowerCase()
      if (!lower.startsWith('http://') && !lower.startsWith('https://')) {
        errors.affected_url = 'Link submission harus diawali dengan http:// atau https://'
      }
    }

    if (files.length === 0) {
      errors.attachments = 'Minimal 1 lampiran dokumen atau screenshot wajib diunggah.'
    }

    if (!urgency) errors.urgency = 'Status Urgent wajib dipilih.'

    setFieldErrors(errors)
    return Object.keys(errors).length === 0
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!validate()) return

    setSubmitting(true)
    setError('')
    setToastMessage('')

    const formData = new FormData()
    formData.append('request_category', requestCategory)
    formData.append('application_id', applicationId)
    formData.append('title', title.trim())
    formData.append('description', description.trim())
    if (affectedUrl.trim()) formData.append('affected_url', affectedUrl.trim())
    if (reference.trim()) {
      formData.append('reference', reference.trim())
    }

    files.forEach((file) => {
      formData.append('attachments[]', file)
    })
    formData.append('urgency', urgency)

    try {
      const ticket = await ticketService.createRequesterTicket(formData)
      setCreatedTicket(ticket)
      setToastMessage(`Tiket #${ticket.ticket_number} berhasil dibuat!`)
    } catch (cause) {
      const apiError = cause as ApiRequestError
      if (apiError.errors) {
        const mappedErrors: Record<string, string> = {}
        Object.entries(apiError.errors).forEach(([key, messages]) => {
          mappedErrors[key] = Array.isArray(messages) ? messages[0] : messages
        })
        setFieldErrors(mappedErrors)
        setError(Object.values(mappedErrors)[0] || 'Gagal mengajukan tiket.')
      } else {
        setError(apiError.message || 'Terjadi kesalahan saat mengajukan tiket.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  if (createdTicket) {
    return (
      <div className="space-y-6 max-w-3xl mx-auto">
        <PageHeader
          title="Tiket Berhasil Diajukan"
          subtitle="Tiket Anda telah tercatat dan akan diproses oleh Supervisor IT"
        />
        <Card className="p-8 text-center space-y-6 bg-white border border-emerald-100 shadow-lg rounded-xl">
          <div className="mx-auto w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600">
            <CheckCircle2 className="w-10 h-10" />
          </div>
          <div>
            <h3 className="text-2xl font-bold text-gray-900">Nomor Tiket: {createdTicket.ticket_number}</h3>
            <p className="text-gray-600 mt-2 max-w-lg mx-auto">
              Terima kasih. Pengajuan Anda telah berhasil diterima dengan status{' '}
              <span className="font-semibold text-emerald-700">Diajukan</span>. Supervisor IT akan menganalisis dan
              memproses tiket Anda.
            </p>
          </div>
          <div className="p-4 bg-gray-50 rounded-lg text-left text-sm space-y-2 border border-gray-100 max-w-md mx-auto">
            <div>
              <span className="font-semibold text-gray-700">Judul:</span> {createdTicket.title}
            </div>
            <div>
              <span className="font-semibold text-gray-700">Link Error:</span>{' '}
              <a
                href={createdTicket.affected_url || '#'}
                target="_blank"
                rel="noreferrer"
                className="text-blue-600 hover:underline"
              >
                {createdTicket.affected_url}
              </a>
            </div>
            {createdTicket.reference && (
              <div>
                <span className="font-semibold text-gray-700">Referensi:</span> {createdTicket.reference}
              </div>
            )}
          </div>
          <div className="flex justify-center gap-4 pt-4">
            <Button variant="secondary" onClick={() => navigate('/user/tickets')}>
              Lihat Daftar Tiket
            </Button>
            <Button onClick={() => navigate(`/user/tickets/${createdTicket.id}`)}>
              Detail Tiket <ExternalLink className="w-4 h-4 ml-2 inline" />
            </Button>
          </div>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6 max-w-4xl mx-auto pb-12">
      {toastMessage && <Toast message={toastMessage} onClose={() => setToastMessage('')} />}
      <PageHeader
        title="Form Pengajuan Tiket"
        subtitle="Sampaikan kendala atau permasalahan sistem Anda kepada tim IT"
      />

      {error && (
        <div className="p-4 bg-red-50 border-l-4 border-red-500 rounded-r-md flex items-start gap-3">
          <AlertCircle className="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
          <div className="text-sm text-red-700">{error}</div>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        <Card className="p-4 sm:p-6 bg-white shadow-sm border border-gray-200 rounded-xl space-y-6">
          <fieldset>
            <legend className="text-sm font-semibold text-gray-800">
              Kategori Pengajuan <span className="text-red-500">*</span>
            </legend>
            <p className="mt-1 text-xs leading-relaxed text-gray-500">
              Request — fitur baru atau perubahan fitur. Error / Bug — kesalahan atau kendala sistem. Lainnya —
              pengajuan di luar dua kategori tersebut.
            </p>
            <div className="mt-3 grid gap-2 sm:grid-cols-3">
              {(
                [
                  ['request', 'Request'],
                  ['error_bug', 'Error / Bug'],
                  ['other', 'Lainnya'],
                ] as const
              ).map(([value, label]) => (
                <label
                  key={value}
                  className={`cursor-pointer rounded-lg border p-3 text-center text-sm font-semibold transition ${requestCategory === value ? 'border-blue-700 bg-blue-50 text-blue-900' : 'border-gray-300 text-gray-700 hover:border-blue-300'}`}
                >
                  <input
                    className="sr-only"
                    type="radio"
                    name="request_category"
                    value={value}
                    checked={requestCategory === value}
                    disabled={submitting}
                    onChange={() => {
                      setRequestCategory(value)
                      setFieldErrors((prev) => ({ ...prev, request_category: '', affected_url: '' }))
                    }}
                  />
                  {label}
                </label>
              ))}
            </div>
            {fieldErrors.request_category && (
              <p className="mt-1 text-xs text-red-600">{fieldErrors.request_category}</p>
            )}
          </fieldset>

          <Select
            label="Nama Sistem *"
            value={applicationId}
            disabled={submitting || loadingApplications}
            error={fieldErrors.application_id}
            onChange={(event) => {
              setApplicationId(event.target.value)
              setFieldErrors((prev) => ({ ...prev, application_id: '' }))
            }}
            options={[
              {
                value: '',
                label: loadingApplications
                  ? 'Memuat sistem...'
                  : applications.length
                    ? 'Pilih sistem'
                    : 'Belum ada sistem aktif',
              },
              ...applications.map((application) => ({ value: String(application.id), label: application.name })),
            ]}
          />

          <div className="space-y-1">
            <Input
              label="Judul Pengajuan *"
              error={fieldErrors.title}
              value={title}
              onChange={(e) => {
                setTitle(e.target.value)
                setFieldErrors((prev) => ({ ...prev, title: '' }))
              }}
              placeholder="Contoh: Portal asuransi gagal memproses submission"
              disabled={submitting}
              maxLength={200}
            />
            <p className="text-xs text-gray-500">
              Tuliskan judul singkat yang mengabarkan masalah utama (maks 200 karakter).
            </p>
          </div>

          {/* Field 2: Deskripsi */}
          <div className="space-y-1">
            <TicketDescriptionEditor
              error={fieldErrors.description}
              value={description}
              onChange={(value) => {
                setDescription(value)
                setFieldErrors((prev) => ({ ...prev, description: '' }))
              }}
              disabled={submitting}
            />
            <p className="text-xs text-gray-500">
              Jelaskan secara rinci kendala yang dialami dan dampaknya bagi pengguna (maks 10.000 karakter).
            </p>
          </div>

          {/* Field 3: Link Submission Error */}
          <div className="space-y-1">
            <Input
              label={`Link Submission${requestCategory === 'error_bug' ? ' *' : ' (Opsional)'}`}
              error={fieldErrors.affected_url}
              type="url"
              value={affectedUrl}
              onChange={(e) => {
                setAffectedUrl(e.target.value)
                setFieldErrors((prev) => ({ ...prev, affected_url: '' }))
              }}
              placeholder="https://portal-asuransi.example/submission/123"
              disabled={submitting}
            />
            <p className="text-xs text-gray-500">
              Masukkan tautan/URL halaman web di mana error atau kendala terjadi (diawali http:// atau https://).
            </p>
          </div>

          {/* Field 4: Referensi */}
          <div className="space-y-1">
            <Input
              label="Referensi (Opsional)"
              value={reference}
              onChange={(e) => setReference(e.target.value)}
              placeholder="Nomor submission, nomor polis, nomor transaksi, atau referensi lain"
              disabled={submitting}
              maxLength={255}
            />
            <p className="text-xs text-gray-500">
              Dapat diisi nomor dokumen terkait seperti nomor polis, nomor klaim, atau nomor transaksi untuk mempermudah
              pencarian.
            </p>
          </div>

          {/* Field 5: Lampiran Dokumen atau Screenshot */}
          <div className="space-y-2">
            <label className="block text-sm font-semibold text-gray-800">
              Lampiran Dokumen atau Screenshot <span className="text-red-500">*</span>
            </label>

            <div className="border-2 border-dashed border-gray-300 hover:border-blue-500 rounded-lg p-6 text-center transition-colors bg-gray-50/50">
              <Upload className="w-10 h-10 mx-auto text-gray-400 mb-2" />
              <p className="text-sm font-medium text-gray-700">Klik atau tarik file ke sini untuk mengunggah</p>
              <p className="text-xs text-gray-500 mt-1">
                Format yang didukung: JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX (Maks 10MB per file)
              </p>
              <input
                type="file"
                multiple
                accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx"
                onChange={handleFileChange}
                disabled={submitting}
                className="mt-4 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
              />
            </div>
            {fieldErrors.attachments && <p className="text-xs text-red-600 mt-1">{fieldErrors.attachments}</p>}

            {/* Preview files */}
            {files.length > 0 && (
              <div className="mt-4 space-y-2">
                <p className="text-xs font-semibold text-gray-700">File Terpilih ({files.length}):</p>
                <div className="divide-y divide-gray-100 border border-gray-200 rounded-md bg-white">
                  {files.map((file, idx) => (
                    <div key={idx} className="p-3 flex items-center justify-between hover:bg-gray-50">
                      <div className="flex items-center gap-3 overflow-hidden">
                        <FileText className="w-5 h-5 text-blue-600 shrink-0" />
                        <div className="truncate">
                          <p className="text-sm font-medium text-gray-800 truncate">{file.name}</p>
                          <p className="text-xs text-gray-400">{(file.size / 1024).toFixed(1)} KB</p>
                        </div>
                      </div>
                      <button
                        type="button"
                        onClick={() => removeFile(idx)}
                        disabled={submitting}
                        className="text-red-500 hover:text-red-700 p-1.5 rounded-md hover:bg-red-50 transition-colors"
                        title="Hapus file"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          <fieldset>
            <legend className="text-sm font-semibold text-gray-800">
              Status Urgent <span className="text-red-500">*</span>
            </legend>
            <div className="mt-3 grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Status Urgent">
              {(
                [
                  ['low', 'LOW', 'Tidak menghambat pekerjaan utama.'],
                  ['medium', 'MEDIUM', 'Mengganggu sebagian proses pekerjaan.'],
                  ['high', 'HIGH', 'Menghambat pekerjaan utama atau layanan penting.'],
                ] as const
              ).map(([value, label, description]) => (
                <button
                  key={value}
                  type="button"
                  role="radio"
                  aria-checked={urgency === value}
                  disabled={submitting}
                  className={`rounded-lg border p-3 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${urgency === value ? 'border-blue-700 bg-blue-50 text-blue-900' : 'border-gray-300 text-gray-700 hover:border-blue-300 hover:bg-blue-50/40'}`}
                  onClick={() => {
                    setUrgency(value)
                    setFieldErrors((prev) => ({ ...prev, urgency: '' }))
                  }}
                >
                  <span className="block text-sm font-bold">{label}</span>
                  <span className="mt-1 block text-xs font-normal leading-relaxed">{description}</span>
                </button>
              ))}
            </div>
            {fieldErrors.urgency && <p className="mt-1 text-xs text-red-600">{fieldErrors.urgency}</p>}
          </fieldset>
        </Card>

        <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
          <Button type="button" variant="secondary" onClick={() => navigate('/user/tickets')} disabled={submitting}>
            Batal
          </Button>
          <Button
            type="submit"
            disabled={submitting || loadingApplications || applications.length === 0}
            className="min-w-[160px] justify-center"
          >
            {submitting ? 'Mengirim...' : 'Kirim Pengajuan'}
          </Button>
        </div>
      </form>
    </div>
  )
}
