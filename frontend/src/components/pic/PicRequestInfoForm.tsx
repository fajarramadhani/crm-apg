import React, { useState } from 'react'
import { HelpCircle, AlertCircle } from 'lucide-react'

interface PicRequestInfoFormProps {
  onSubmit: (data: { question: string }) => Promise<void>
  onClose: () => void
}

export const PicRequestInfoForm: React.FC<PicRequestInfoFormProps> = ({ onSubmit, onClose }) => {
  const [question, setQuestion] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!question.trim()) {
      setError('Pertanyaan atau penjelasan informasi tidak boleh kosong.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onSubmit({ question: question.trim() })
      onClose()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal mengirim permintaan informasi.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex items-center justify-between border-b pb-3 border-slate-200 dark:border-slate-800">
        <h3 className="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <HelpCircle className="w-5 h-5 text-amber-500" /> Minta Informasi kepada Requester
        </h3>
      </div>

      {error && (
        <div className="p-3 text-xs bg-red-50 text-red-600 rounded-lg border border-red-200 flex items-center gap-2">
          <AlertCircle className="w-4 h-4 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <div>
        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
          Pertanyaan / Informasi yang Dibutuhkan <span className="text-red-500">*</span>
        </label>
        <textarea
          rows={4}
          value={question}
          onChange={(e) => setQuestion(e.target.value)}
          placeholder="Jelaskan secara singkat data, langkah reproduksi, atau tangkapan layar yang dibutuhkan dari Requester..."
          className="w-full p-3 text-sm border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500 focus:outline-none"
          required
        />
      </div>

      <div className="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-800 dark:text-amber-300">
        <p className="font-semibold">Efek Tindakan ini:</p>
        <ul className="list-disc list-inside mt-1 space-y-0.5 text-[11px] text-amber-700 dark:text-amber-400">
          <li>
            Status tiket berubah menjadi <strong>Memerlukan Informasi</strong>.
          </li>
          <li>Requester akan menerima notifikasi email & portal.</li>
          <li>Setelah Requester menjawab, tiket akan otomatis kembali ke antrean penanganan PIC.</li>
        </ul>
      </div>

      <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
        <button
          type="button"
          onClick={onClose}
          disabled={submitting}
          className="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition"
        >
          Batal
        </button>
        <button
          type="submit"
          disabled={submitting}
          className="px-4 py-2 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition disabled:opacity-50"
        >
          {submitting ? 'Mengirim...' : 'Kirim Permintaan'}
        </button>
      </div>
    </form>
  )
}
