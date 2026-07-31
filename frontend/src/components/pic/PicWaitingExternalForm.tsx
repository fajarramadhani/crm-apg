import React, { useState } from 'react'
import { ExternalLink, AlertCircle } from 'lucide-react'

interface PicWaitingExternalFormProps {
  onSubmit: (data: {
    external_party_name: string
    reference_number?: string
    follow_up_date?: string
    notes: string
  }) => Promise<void>
  onClose: () => void
}

export const PicWaitingExternalForm: React.FC<PicWaitingExternalFormProps> = ({ onSubmit, onClose }) => {
  const [externalPartyName, setExternalPartyName] = useState('')
  const [referenceNumber, setReferenceNumber] = useState('')
  const [followUpDate, setFollowUpDate] = useState('')
  const [notes, setNotes] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!externalPartyName.trim()) {
      setError('Nama pihak eksternal wajib diisi.')
      return
    }
    if (!notes.trim()) {
      setError('Catatan penjelasan wajib diisi.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onSubmit({
        external_party_name: externalPartyName.trim(),
        reference_number: referenceNumber.trim() || undefined,
        follow_up_date: followUpDate || undefined,
        notes: notes.trim(),
      })
      onClose()
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Gagal menandai pihak eksternal.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="flex items-center justify-between border-b pb-3 border-slate-200">
        <h3 className="text-base font-bold text-slate-900 flex items-center gap-2">
          <ExternalLink className="w-5 h-5 text-purple-600" /> Menunggu Pihak Eksternal
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
          Nama Pihak Eksternal <span className="text-red-500">*</span>
        </label>
        <input
          type="text"
          value={externalPartyName}
          onChange={(e) => setExternalPartyName(e.target.value)}
          placeholder="Contoh: Perusahaan Asuransi XYZ / Vendor Cloud / Penyedia Jaringan"
          className="w-full p-2.5 text-sm border border-slate-300 rounded-lg bg-slate-50 text-slate-900 focus:ring-2 focus:ring-purple-500 focus:outline-none"
          required
        />
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <label className="block text-xs font-semibold text-slate-700 mb-1">
            Nomor Referensi Eksternal
          </label>
          <input
            type="text"
            value={referenceNumber}
            onChange={(e) => setReferenceNumber(e.target.value)}
            placeholder="No Tiket / Ref Vendor"
            className="w-full p-2 text-xs border border-slate-300 rounded-lg bg-slate-50 text-slate-900"
          />
        </div>

        <div>
          <label className="block text-xs font-semibold text-slate-700 mb-1">
            Rencana Tanggal Follow-Up
          </label>
          <input
            type="date"
            value={followUpDate}
            onChange={(e) => setFollowUpDate(e.target.value)}
            className="w-full p-2 text-xs border border-slate-300 rounded-lg bg-slate-50 text-slate-900"
          />
        </div>
      </div>

      <div>
        <label className="block text-xs font-semibold text-slate-700 mb-1">
          Catatan / Alasan Menunggu <span className="text-red-500">*</span>
        </label>
        <textarea
          rows={3}
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          placeholder="Detail tanggapan atau konfirmasi yang sedang ditunggu dari pihak eksternal..."
          className="w-full p-3 text-sm border border-slate-300 rounded-lg bg-slate-50 text-slate-900 focus:ring-2 focus:ring-purple-500 focus:outline-none"
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
          className="px-4 py-2 text-xs font-semibold text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition disabled:opacity-50"
        >
          {submitting ? 'Menyimpan...' : 'Tandai Menunggu Eksternal'}
        </button>
      </div>
    </form>
  )
}
