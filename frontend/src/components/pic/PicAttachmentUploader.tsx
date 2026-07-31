import React, { useState } from 'react'
import { Paperclip, Upload, AlertCircle } from 'lucide-react'

interface PicAttachmentUploaderProps {
  onUpload: (formData: FormData) => Promise<void>
  onClose: () => void
}

export const PicAttachmentUploader: React.FC<PicAttachmentUploaderProps> = ({ onUpload, onClose }) => {
  const [file, setFile] = useState<File | null>(null)
  const [visibility, setVisibility] = useState<'internal' | 'requester_visible'>('internal')
  const [category, setCategory] = useState('result')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const selected = e.target.files[0]
      // Max size check 10MB
      if (selected.size > 10 * 1024 * 1024) {
        setError('Ukuran file tidak boleh melebihi 10MB.')
        setFile(null)
        return
      }

      // Safe extension check (no exe, sh, bat, php, etc.)
      const ext = selected.name.split('.').pop()?.toLowerCase() ?? ''
      const forbiddenExts = ['exe', 'sh', 'bat', 'cmd', 'vbs', 'php', 'js', 'jar', 'py', 'pl']
      if (forbiddenExts.includes(ext)) {
        setError('Jenis file ini dilarang demi keamanan sistem.')
        setFile(null)
        return
      }

      setError(null)
      setFile(selected)
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!file) {
      setError('Silakan pilih file yang akan diunggah.')
      return
    }

    const formData = new FormData()
    formData.append('file', file)
    formData.append('visibility', visibility)
    formData.append('category', category)

    setSubmitting(true)
    setError(null)
    try {
      await onUpload(formData)
      onClose()
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Gagal mengunggah file.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex items-center justify-between border-b pb-3 border-slate-200">
        <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
          <Paperclip className="w-5 h-5 text-primary" /> Unggah Lampiran Hasil Pekerjaan
        </h3>
      </div>

      {error && (
        <div className="p-3 text-xs bg-red-50 text-red-600 rounded-lg border border-red-200 flex items-center gap-2">
          <AlertCircle className="w-4 h-4 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <div>
        <label className="block text-xs font-semibold text-slate-700 mb-1">
          Pilih File Lampiran <span className="text-red-500">*</span>
        </label>
        <div className="border-2 border-dashed border-slate-300 rounded-xl p-4 text-center hover:border-primary transition">
          <input
            type="file"
            onChange={handleFileChange}
            className="hidden"
            id="pic-file-input"
            accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
          />
          <label htmlFor="pic-file-input" className="cursor-pointer flex flex-col items-center gap-1">
            <Upload className="w-8 h-8 text-slate-400" />
            <span className="text-xs font-semibold text-primary">{file ? file.name : 'Klik untuk memilih file'}</span>
            <span className="text-[10px] text-slate-400">
              Format didukung: PDF, PNG, JPG, DOCX, XLSX, ZIP (Maks 10MB)
            </span>
          </label>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <label className="block text-xs font-semibold text-slate-700 mb-1">
            Kategori Lampiran
          </label>
          <select
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            className="w-full p-2 text-xs border border-slate-300 rounded-lg bg-slate-50 text-slate-900"
          >
            <option value="result">Bukti Perbaikan / Hasil</option>
            <option value="screenshot">Screenshot Layar</option>
            <option value="test_report">Laporan Pengecekan</option>
            <option value="communication">Bukti Komunikasi</option>
            <option value="documentation">Dokumentasi Teknis</option>
          </select>
        </div>

        <div>
          <label className="block text-xs font-semibold text-slate-700 mb-1">
            Visibilitas File
          </label>
          <select
            value={visibility}
            onChange={(e) => setVisibility(e.target.value as 'internal' | 'requester_visible')}
            className="w-full p-2 text-xs border border-slate-300 rounded-lg bg-slate-50 text-slate-900"
          >
            <option value="internal">Internal (Supervisor & IT Team)</option>
            <option value="requester_visible">Publik (Terlihat oleh Requester)</option>
          </select>
        </div>
      </div>

      <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button
          type="button"
          onClick={onClose}
          disabled={submitting}
          className="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition"
        >
          Batal
        </button>
        <button
          type="submit"
          disabled={submitting || !file}
          className="px-4 py-2 text-xs font-semibold text-white bg-primary hover:bg-primary/90 rounded-lg transition disabled:opacity-50"
        >
          {submitting ? 'Mengunggah...' : 'Unggah File'}
        </button>
      </div>
    </form>
  )
}
