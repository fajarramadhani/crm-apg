import React, { useState } from 'react'
import { MessageSquare, Eye, Lock } from 'lucide-react'

interface PicWorkNoteFormProps {
  onSubmit: (data: { content: string; visibility: 'internal' | 'requester_visible' }) => Promise<void>
  onClose: () => void
}

export const PicWorkNoteForm: React.FC<PicWorkNoteFormProps> = ({ onSubmit, onClose }) => {
  const [content, setContent] = useState('')
  const [visibility, setVisibility] = useState<'internal' | 'requester_visible'>('internal')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!content.trim()) {
      setError('Catatan pekerjaan tidak boleh kosong.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onSubmit({ content: content.trim(), visibility })
      onClose()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal menyimpan catatan pekerjaan.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex items-center justify-between border-b pb-3 border-slate-200 dark:border-slate-800">
        <h3 className="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <MessageSquare className="w-5 h-5 text-primary" /> Tambah Catatan Pekerjaan
        </h3>
      </div>

      {error && <div className="p-3 text-xs bg-red-50 text-red-600 rounded-lg border border-red-200">{error}</div>}

      <div>
        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
          Isi Catatan Pekerjaan <span className="text-red-500">*</span>
        </label>
        <textarea
          rows={4}
          value={content}
          onChange={(e) => setContent(e.target.value)}
          placeholder="Tuliskan detail perbaikan, temuan teknis, atau perkembangan pengerjaan..."
          className="w-full p-3 text-sm border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-primary focus:outline-none"
          required
        />
      </div>

      <div>
        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
          Visibilitas Catatan
        </label>
        <div className="grid grid-cols-2 gap-3">
          <label
            className={`flex items-center gap-2 p-3 rounded-lg border cursor-pointer text-xs font-medium transition ${
              visibility === 'internal'
                ? 'border-primary bg-primary/5 text-primary'
                : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'
            }`}
          >
            <input
              type="radio"
              name="visibility"
              value="internal"
              checked={visibility === 'internal'}
              onChange={() => setVisibility('internal')}
              className="sr-only"
            />
            <Lock className="w-4 h-4 text-amber-500" />
            <div>
              <div className="font-semibold">Catatan Internal</div>
              <div className="text-[10px] text-slate-400">Hanya terlihat oleh Supervisor & TIM IT</div>
            </div>
          </label>

          <label
            className={`flex items-center gap-2 p-3 rounded-lg border cursor-pointer text-xs font-medium transition ${
              visibility === 'requester_visible'
                ? 'border-primary bg-primary/5 text-primary'
                : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400'
            }`}
          >
            <input
              type="radio"
              name="visibility"
              value="requester_visible"
              checked={visibility === 'requester_visible'}
              onChange={() => setVisibility('requester_visible')}
              className="sr-only"
            />
            <Eye className="w-4 h-4 text-blue-500" />
            <div>
              <div className="font-semibold">Dapat Dilihat Requester</div>
              <div className="text-[10px] text-slate-400">Ringkasan publik untuk pengguna</div>
            </div>
          </label>
        </div>
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
          {submitting ? 'Menyimpan...' : 'Simpan Catatan'}
        </button>
      </div>
    </form>
  )
}
