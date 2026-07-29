import React, { useState } from 'react'
import { Users, AlertCircle } from 'lucide-react'

interface PicAssistanceRequestFormProps {
  onSubmit: (data: { reason: string; required_expertise?: string }) => Promise<void>
  onClose: () => void
}

export const PicAssistanceRequestForm: React.FC<PicAssistanceRequestFormProps> = ({ onSubmit, onClose }) => {
  const [reason, setReason] = useState('')
  const [expertise, setExpertise] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!reason.trim()) {
      setError('Alasan membutuhkan bantuan wajib diisi.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onSubmit({
        reason: reason.trim(),
        required_expertise: expertise.trim() || undefined,
      })
      onClose()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal mengirim permintaan bantuan.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex items-center justify-between border-b pb-3 border-slate-200 dark:border-slate-800">
        <h3 className="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <Users className="w-5 h-5 text-blue-600" /> Minta Bantuan PIC Pendamping
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
          Keahlian yang Dibutuhkan (Opsional)
        </label>
        <input
          type="text"
          value={expertise}
          onChange={(e) => setExpertise(e.target.value)}
          placeholder="Contoh: Database Specialist / Network Engineer / API Integration"
          className="w-full p-2 text-xs border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
        />
      </div>

      <div>
        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
          Alasan Membutuhkan Bantuan <span className="text-red-500">*</span>
        </label>
        <textarea
          rows={4}
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          placeholder="Jelaskan bagian kendala teknis atau skala pekerjaan yang membutuhkan PIC pendamping..."
          className="w-full p-3 text-sm border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
          required
        />
      </div>

      <div className="p-3 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-lg text-xs text-blue-800 dark:text-blue-300">
        Permintaan bantuan ini akan diteruskan ke Supervisor IT untuk penambahan PIC pendamping. Assignment tidak akan
        berubah secara otomatis.
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
          className="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition disabled:opacity-50"
        >
          {submitting ? 'Mengirim...' : 'Kirim Permintaan Bantuan'}
        </button>
      </div>
    </form>
  )
}
