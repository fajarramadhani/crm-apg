import React, { useState } from 'react'
import type { TicketRecord } from '../../services/ticketService'

interface TicketAnalysisPanelProps {
  ticket: TicketRecord
  onSaveAnalysis: (payload: Record<string, unknown>) => Promise<void>
  loading?: boolean
}

export const TicketAnalysisPanel: React.FC<TicketAnalysisPanelProps> = ({
  ticket,
  onSaveAnalysis,
  loading = false,
}) => {
  const [targetDate, setTargetDate] = useState<string>(
    ticket.resolution_due_at ? ticket.resolution_due_at.substring(0, 10) : '',
  )
  const [analysisSummary, setAnalysisSummary] = useState<string>('')
  const [handlingNote, setHandlingNote] = useState<string>('')
  const [validationError, setValidationError] = useState<string | null>(null)

  const handleSubmit = async () => {
    setValidationError(null)

    if (!analysisSummary.trim()) {
      setValidationError('Ringkasan analisis wajib diisi.')
      return
    }

    const payload: Record<string, unknown> = {
      analysis_summary: analysisSummary.trim(),
      handling_note: handlingNote.trim() || null,
      target_completion_date: targetDate || null,
    }

    try {
      await onSaveAnalysis(payload)
      setAnalysisSummary('')
      setHandlingNote('')
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menyimpan hasil analisis.'
      setValidationError(msg)
    }
  }

  return (
    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-6">
      <div className="flex items-center justify-between border-b border-gray-100 pb-3">
        <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
          <span className="h-2.5 w-2.5 rounded-full bg-blue-600" />
          Analisis Supervisor IT
        </h2>
        <span className="text-xs text-gray-500 font-medium">Klasifikasi pengajuan tidak dapat diubah</span>
      </div>

      {validationError && (
        <div className="rounded-lg bg-red-50 p-3 text-xs font-semibold text-red-700 border border-red-200">
          {validationError}
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Ringkasan Analisis <span className="text-red-500">*</span>
          </label>
          <textarea
            rows={4}
            placeholder="Tuliskan hasil identifikasi dan kesimpulan awal Supervisor IT..."
            value={analysisSummary}
            onChange={(e) => setAnalysisSummary(e.target.value)}
            className="w-full rounded-lg border border-gray-300 p-3 text-sm focus:border-blue-500 focus:outline-none"
          />
        </div>

        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">Arahan Penanganan</label>
          <textarea
            rows={4}
            placeholder="Tuliskan arahan teknis, perhatian khusus, atau konteks untuk PIC..."
            value={handlingNote}
            onChange={(e) => setHandlingNote(e.target.value)}
            className="w-full rounded-lg border border-gray-300 p-3 text-sm focus:border-blue-500 focus:outline-none"
          />
        </div>

        <div className="md:col-span-2">
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Target Penyelesaian Supervisor <span className="text-gray-400 font-normal">(Opsional)</span>
          </label>
          <input
            type="date"
            value={targetDate}
            onChange={(e) => setTargetDate(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          />
          <p className="mt-1 text-[11px] text-gray-400">Target ini tidak mengubah target kebutuhan yang diajukan Requester.</p>
        </div>
      </div>

      <div className="flex flex-wrap items-center justify-end gap-3 border-t border-gray-100 pt-4">
        <button
          type="button"
          disabled={loading}
          onClick={handleSubmit}
          className="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-5 py-2 text-xs font-bold text-white hover:bg-blue-800 transition-colors shadow-sm"
        >
          {loading && (
            <span className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent" />
          )}
          Simpan Analisis
        </button>
      </div>
    </div>
  )
}
