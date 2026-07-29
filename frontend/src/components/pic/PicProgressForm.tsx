import React, { useState } from 'react'
import { Sliders, Info } from 'lucide-react'

interface PicProgressFormProps {
  currentProgress: number
  onSubmit: (data: { progress_percentage: number; notes?: string }) => Promise<void>
  onClose: () => void
}

export const PicProgressForm: React.FC<PicProgressFormProps> = ({ currentProgress, onSubmit, onClose }) => {
  const [progress, setProgress] = useState(currentProgress ?? 0)
  const [notes, setNotes] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (progress < 0 || progress > 100) {
      setError('Persentase progres harus antara 0% sampai 100%.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onSubmit({
        progress_percentage: Number(progress),
        notes: notes.trim() || undefined,
      })
      onClose()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal memperbarui progres.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex items-center justify-between border-b pb-3 border-slate-200 dark:border-slate-800">
        <h3 className="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <Sliders className="w-5 h-5 text-primary" /> Perbarui Progres Pekerjaan
        </h3>
      </div>

      {error && <div className="p-3 text-xs bg-red-50 text-red-600 rounded-lg border border-red-200">{error}</div>}

      <div>
        <div className="flex justify-between items-center mb-2">
          <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">
            Persentase Progres (0 - 100%)
          </label>
          <span className="text-sm font-bold text-primary">{progress}%</span>
        </div>

        <input
          type="range"
          min="0"
          max="100"
          step="5"
          value={progress}
          onChange={(e) => setProgress(Number(e.target.value))}
          className="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-primary"
        />
        <div className="flex justify-between text-[10px] text-slate-400 mt-1">
          <span>0% (Belum Mulai)</span>
          <span>50% (Pengerjaan)</span>
          <span>100% (Selesai PIC)</span>
        </div>
      </div>

      <div>
        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
          Catatan Perubahan Progres (Opsional)
        </label>
        <textarea
          rows={3}
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          placeholder="Ringkasan milestone atau tugas yang telah diselesaikan..."
          className="w-full p-3 text-sm border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary focus:outline-none"
        />
      </div>

      <div className="p-3 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-lg text-xs text-blue-700 dark:text-blue-300 flex items-start gap-2">
        <Info className="w-4 h-4 text-blue-500 shrink-0 mt-0.5" />
        <span>
          <strong>Catatan:</strong> Pengisian 100% tidak otomatis menutup tiket. Tiket baru akan dianggap selesai
          setelah mendapat approval resmi dari Supervisor IT.
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
          className="px-4 py-2 text-xs font-semibold text-white bg-primary hover:bg-primary/90 rounded-lg transition disabled:opacity-50"
        >
          {submitting ? 'Menyimpan...' : 'Simpan Progres'}
        </button>
      </div>
    </form>
  )
}
