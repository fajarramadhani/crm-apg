import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import { Button, Card, Input, PageHeader, Textarea, Toast } from '../../components/ui'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import { Upload, Trash2, FileText, CheckCircle2, AlertCircle, ExternalLink } from 'lucide-react'

const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx']
const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024 // 10MB

export default function CreateTicket() {
  const navigate = useNavigate()
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [affectedUrl, setAffectedUrl] = useState('')
  const [reference, setReference] = useState('')
  const [files, setFiles] = useState<File[]>([])

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [createdTicket, setCreatedTicket] = useState<TicketRecord | null>(null)
  const [toastMessage, setToastMessage] = useState('')

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

    if (!title.trim()) {
      errors.title = 'Judul pengajuan tiket wajib diisi.'
    } else if (title.length > 200) {
      errors.title = 'Judul maksimal 200 karakter.'
    }

    if (!description.trim()) {
      errors.description = 'Deskripsi pengajuan tiket wajib diisi.'
    } else if (description.length > 10000) {
      errors.description = 'Deskripsi maksimal 10.000 karakter.'
    }

    if (!affectedUrl.trim()) {
      errors.affected_url = 'Link submission yang error wajib diisi.'
    } else {
      const lower = affectedUrl.trim().toLowerCase()
      if (!lower.startsWith('http://') && !lower.startsWith('https://')) {
        errors.affected_url = 'Link submission harus diawali dengan http:// atau https://'
      }
    }

    if (files.length === 0) {
      errors.attachments = 'Minimal 1 lampiran dokumen atau screenshot wajib diunggah.'
    }

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
    formData.append('title', title.trim())
    formData.append('description', description.trim())
    formData.append('affected_url', affectedUrl.trim())
    if (reference.trim()) {
      formData.append('reference', reference.trim())
    }

    files.forEach((file) => {
      formData.append('attachments[]', file)
    })

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
            <Button variant="secondary" onClick={() => navigate('/tickets')}>
              Lihat Daftar Tiket
            </Button>
            <Button onClick={() => navigate(`/tickets/${createdTicket.id}`)}>
              Detail Tiket <ExternalLink className="w-4 h-4 ml-2 inline" />
            </Button>
          </div>
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6 max-w-4xl mx-auto pb-12">
      <Toast message={toastMessage} onClose={() => setToastMessage('')} />
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
        <Card className="p-6 bg-white shadow-sm border border-gray-200 rounded-xl space-y-6">
          {/* Field 1: Judul Pengajuan */}
          <div className="space-y-1">
            <label className="block text-sm font-semibold text-gray-800">
              Judul Pengajuan Tiket <span className="text-red-500">*</span>
            </label>
            <Input
              value={title}
              onChange={(e) => {
                setTitle(e.target.value)
                setFieldErrors((prev) => ({ ...prev, title: '' }))
              }}
              placeholder="Contoh: Portal asuransi gagal memproses submission"
              disabled={submitting}
              maxLength={200}
              className={fieldErrors.title ? 'border-red-500 focus:ring-red-500' : ''}
            />
            {fieldErrors.title && <p className="text-xs text-red-600 mt-1">{fieldErrors.title}</p>}
            <p className="text-xs text-gray-500">
              Tuliskan judul singkat yang mengabarkan masalah utama (maks 200 karakter).
            </p>
          </div>

          {/* Field 2: Deskripsi */}
          <div className="space-y-1">
            <label className="block text-sm font-semibold text-gray-800">
              Deskripsi Kendala <span className="text-red-500">*</span>
            </label>
            <Textarea
              value={description}
              onChange={(e) => {
                setDescription(e.target.value)
                setFieldErrors((prev) => ({ ...prev, description: '' }))
              }}
              placeholder="Jelaskan masalah yang terjadi dan langkah terakhir sebelum error muncul."
              rows={6}
              disabled={submitting}
              maxLength={10000}
              className={fieldErrors.description ? 'border-red-500 focus:ring-red-500' : ''}
            />
            {fieldErrors.description && <p className="text-xs text-red-600 mt-1">{fieldErrors.description}</p>}
            <p className="text-xs text-gray-500">
              Jelaskan secara rinci kendala yang dialami dan dampaknya bagi pengguna (maks 10.000 karakter).
            </p>
          </div>

          {/* Field 3: Link Submission Error */}
          <div className="space-y-1">
            <label className="block text-sm font-semibold text-gray-800">
              Link Submission yang Error <span className="text-red-500">*</span>
            </label>
            <Input
              type="url"
              value={affectedUrl}
              onChange={(e) => {
                setAffectedUrl(e.target.value)
                setFieldErrors((prev) => ({ ...prev, affected_url: '' }))
              }}
              placeholder="https://portal-asuransi.example/submission/123"
              disabled={submitting}
              className={fieldErrors.affected_url ? 'border-red-500 focus:ring-red-500' : ''}
            />
            {fieldErrors.affected_url && <p className="text-xs text-red-600 mt-1">{fieldErrors.affected_url}</p>}
            <p className="text-xs text-gray-500">
              Masukkan tautan/URL halaman web di mana error atau kendala terjadi (diawali http:// atau https://).
            </p>
          </div>

          {/* Field 4: Referensi */}
          <div className="space-y-1">
            <label className="block text-sm font-semibold text-gray-800">
              Referensi <span className="text-gray-400 font-normal">(Opsional)</span>
            </label>
            <Input
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
        </Card>

        <div className="flex justify-end gap-3">
          <Button type="button" variant="secondary" onClick={() => navigate('/tickets')} disabled={submitting}>
            Batal
          </Button>
          <Button type="submit" disabled={submitting} className="min-w-[140px]">
            {submitting ? 'Mengirim...' : 'Kirim Tiket'}
          </Button>
        </div>
      </form>
    </div>
  )
}
