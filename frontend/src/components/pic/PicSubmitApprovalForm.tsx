import React, { useState } from 'react'
import { Send, CheckSquare, AlertCircle } from 'lucide-react'

interface PicSubmitApprovalFormProps {
  onSubmit: (data: { result_summary: string; internal_notes?: string; requester_summary?: string }) => Promise<void>
  onClose: () => void
}

export const PicSubmitApprovalForm: React.FC<PicSubmitApprovalFormProps> = ({ onSubmit, onClose }) => {
  const [resultSummary, setResultSummary] = useState('')
  const [internalNotes, setInternalNotes] = useState('')
  const [requesterSummary, setRequesterSummary] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!resultSummary.trim()) {
      setError('Ringkasan hasil perbaikan wajib diisi.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onSubmit({
        result_summary: resultSummary.trim(),
        internal_notes: internalNotes.trim() || undefined,
        requester_summary: requesterSummary.trim() || undefined,
      })
      onClose()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal mengirim untuk approval Supervisor.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex items-center justify-between border-b pb-3 border-slate-200 dark:border-slate-800">
        <h3 className="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <Send className="w-5 h-5 text-emerald-600" /> Kirim untuk Pemeriksaan Supervisor IT
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
          Ringkasan Hasil Perbaikan / Solusi <span className="text-red-500">*</span>
        </label>
        <textarea
          rows={3}
          value={resultSummary}
          onChange={(e) => setResultSummary(e.target.value)}
          placeholder="Ringkas tindakan perbaikan teknis yang telah selesai dikerjakan..."
          className="w-full p-3 text-sm border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          required
        />
      </div>

      <div>
        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
          Ringkasan untuk Requester (Dapat Dilihat Publik, Opsional)
        </label>
        <textarea
          rows={2}
          value={requesterSummary}
          onChange={(e) => setRequesterSummary(e.target.value)}
          placeholder="Pesan ringkas bahasa non-teknis yang dapat dibaca oleh pemohon tiket..."
          className="w-full p-2.5 text-xs border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
        />
      </div>

      <div>
        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
          Catatan Internal Tambahan (Opsional)
        </label>
        <textarea
          rows={2}
          value={internalNotes}
          onChange={(e) => setInternalNotes(e.target.value)}
          placeholder="Catatan tambahan khusus untuk Supervisor IT..."
          className="w-full p-2.5 text-xs border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
        />
      </div>

      <div className="p-3 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 rounded-lg text-xs text-emerald-800 dark:text-emerald-300 flex items-start gap-2">
        <CheckSquare className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
        <span>
          Tiket akan masuk ke status <strong>Menunggu Pemeriksaan Akhir</strong>. Supervisor IT akan meninjau hasil
          perbaikan Anda sebelum menyetujui tiket secara final.
        </span>
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
          className="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition disabled:opacity-50"
        >
          {submitting ? 'Mengirim...' : 'Kirim ke Supervisor'}
        </button>
      </div>
    </form>
  )
}
