import React, { useState } from 'react'
import { CheckCircle, AlertTriangle, ShieldCheck } from 'lucide-react'

interface PicInternalCheckFormProps {
  onSubmit: (data: { result: 'passed' | 'needs_rework'; notes: string }) => Promise<void>
  onClose: () => void
}

export const PicInternalCheckForm: React.FC<PicInternalCheckFormProps> = ({ onSubmit, onClose }) => {
  const [result, setResult] = useState<'passed' | 'needs_rework'>('passed')
  const [notes, setNotes] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!notes.trim()) {
      setError('Catatan hasil pengecekan internal wajib diisi.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onSubmit({ result, notes: notes.trim() })
      onClose()
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Gagal menyimpan hasil pengecekan.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex items-center justify-between border-b pb-3 border-slate-200">
        <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
          <ShieldCheck className="w-5 h-5 text-emerald-600" /> Pengecekan Mandiri PIC (Internal Check)
        </h3>
      </div>

      {error && <div className="p-3 text-xs bg-red-50 text-red-600 rounded-lg border border-red-200">{error}</div>}

      <div>
        <label className="block text-xs font-semibold text-slate-700 mb-2">
          Hasil Pengecekan Mandiri <span className="text-red-500">*</span>
        </label>
        <div className="grid grid-cols-2 gap-3">
          <label
            className={`flex items-center gap-2 p-3 rounded-lg border cursor-pointer text-xs font-medium transition ${
              result === 'passed'
                ? 'border-emerald-500 bg-emerald-50 text-emerald-800'
                : 'border-slate-200 text-slate-600'
            }`}
          >
            <input
              type="radio"
              name="checkResult"
              value="passed"
              checked={result === 'passed'}
              onChange={() => setResult('passed')}
              className="sr-only"
            />
            <CheckCircle className="w-5 h-5 text-emerald-500" />
            <div>
              <div className="font-bold">Berhasil</div>
              <div className="text-[10px] text-slate-500">Perbaikan & pengecekan sesuai kriteria</div>
            </div>
          </label>

          <label
            className={`flex items-center gap-2 p-3 rounded-lg border cursor-pointer text-xs font-medium transition ${
              result === 'needs_rework'
                ? 'border-orange-500 bg-orange-50 text-orange-800'
                : 'border-slate-200 text-slate-600'
            }`}
          >
            <input
              type="radio"
              name="checkResult"
              value="needs_rework"
              checked={result === 'needs_rework'}
              onChange={() => setResult('needs_rework')}
              className="sr-only"
            />
            <AlertTriangle className="w-5 h-5 text-orange-500" />
            <div>
              <div className="font-bold">Masih Perlu Perbaikan</div>
              <div className="text-[10px] text-slate-500">Pekerjaan dilanjutkan oleh PIC</div>
            </div>
          </label>
        </div>
      </div>

      <div>
        <label className="block text-xs font-semibold text-slate-700 mb-1">
          Catatan & Bukti Pengecekan <span className="text-red-500">*</span>
        </label>
        <textarea
          rows={3}
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          placeholder="Tuliskan skenario pengujian mandiri yang dijalankan dan hasil pemeriksaannya..."
          className="w-full p-3 text-sm border border-slate-300 rounded-lg bg-slate-50 text-slate-900 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
          required
        />
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
          disabled={submitting}
          className="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition disabled:opacity-50"
        >
          {submitting ? 'Menyimpan...' : 'Simpan Hasil Check'}
        </button>
      </div>
    </form>
  )
}
