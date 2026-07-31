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
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Gagal memperbarui progres.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      <div className="flex items-center justify-between border-b border-slate-200 pb-3">
        <h3 className="flex items-center gap-2 text-base font-bold text-slate-900">
          <Sliders className="h-5 w-5 text-primary" /> Perbarui Progres Pekerjaan
        </h3>
      </div>

      {error && <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-600">{error}</div>}

      <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div className="mb-3 flex items-center justify-between">
          <label className="text-xs font-semibold text-slate-700">Persentase Progres (0 - 100%)</label>
          <span className="rounded-full bg-white px-2.5 py-1 text-sm font-bold text-primary shadow-sm">{progress}%</span>
        </div>

        <input
          type="range"
          min="0"
          max="100"
          step="5"
          value={progress}
          onChange={(e) => setProgress(Number(e.target.value))}
          className="h-2.5 w-full cursor-pointer appearance-none rounded-lg bg-slate-200 accent-primary"
        />
        <div className="mt-2 flex justify-between text-[10px] text-slate-500">
          <span>0% (Belum Mulai)</span>
          <span>50% (Pengerjaan)</span>
          <span>100% (Selesai PIC)</span>
        </div>
      </div>

      <div>
        <label className="mb-1.5 block text-xs font-semibold text-slate-700">Catatan Perubahan Progres (Opsional)</label>
        <textarea
          rows={4}
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          placeholder="Ringkasan milestone atau tugas yang telah diselesaikan..."
          className="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary"
        />
      </div>

      <div className="flex items-start gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-700">
        <Info className="mt-0.5 h-4 w-4 shrink-0 text-blue-500" />
        <span>
          <strong>Catatan:</strong> Pengisian 100% tidak otomatis menutup tiket. Tiket baru akan dianggap selesai
          setelah mendapat approval resmi dari Supervisor IT.
        </span>
      </div>

      <div className="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
        <button
          type="button"
          onClick={onClose}
          disabled={submitting}
          className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50"
        >
          Batal
        </button>
        <button
          type="submit"
          disabled={submitting}
          className="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary/90 disabled:opacity-50"
        >
          {submitting ? 'Menyimpan...' : 'Simpan Progres'}
        </button>
      </div>
    </form>
  )
}
