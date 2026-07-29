import React, { useState } from 'react'
import { ArrowRightLeft, AlertCircle } from 'lucide-react'

interface PicTransferRequestFormProps {
  onSubmit: (data: { reason: string }) => Promise<void>
  onClose: () => void
}

export const PicTransferRequestForm: React.FC<PicTransferRequestFormProps> = ({
  onSubmit,
  onClose,
}) => {
  const [reason, setReason] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!reason.trim()) {
      setError('Alasan pengalihan tiket wajib diisi.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onSubmit({ reason: reason.trim() })
      onClose()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal mengirim permintaan pengalihan.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex items-center justify-between border-b pb-3 border-slate-200 dark:border-slate-800">
        <h3 className="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <ArrowRightLeft className="w-5 h-5 text-amber-600" /> Ajukan Pengalihan Tiket (Transfer)
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
          Alasan Pengalihan <span className="text-red-500">*</span>
        </label>
        <textarea
          rows={4}
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          placeholder="Jelaskan secara spesifik alasan pengalihan tiket (misal: terkait sistem eksternal lain, pergantian shift, beban pengerjaan)..."
          className="w-full p-3 text-sm border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500 focus:outline-none"
          required
        />
      </div>

      <div className="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-800 dark:text-amber-300">
        Pengalihan tidak otomatis memindahkan penugasan tiket. Permintaan akan dievaluasi dan diputuskan oleh Supervisor IT.
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
          {submitting ? 'Mengirim...' : 'Ajukan Pengalihan'}
        </button>
      </div>
    </form>
  )
}
