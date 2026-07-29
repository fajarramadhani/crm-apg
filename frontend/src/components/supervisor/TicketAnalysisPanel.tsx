import React, { useState, useEffect } from 'react'
import type { TicketRecord } from '../../services/ticketService'
import type { EligibleAssignee } from '../../types'

interface TicketAnalysisPanelProps {
  ticket: TicketRecord
  applications: Array<{ id: number; name: string }>
  modules: Array<{ id: number; name: string }>
  categories: Array<{ id: number; name: string }>
  priorities: Array<{ id: number; name: string }>
  assignees: EligibleAssignee[]
  onSaveAnalysis: (payload: Record<string, unknown>) => Promise<void>
  loading?: boolean
}

export const TicketAnalysisPanel: React.FC<TicketAnalysisPanelProps> = ({
  ticket,
  applications,
  modules,
  categories,
  priorities,
  assignees,
  onSaveAnalysis,
  loading = false,
}) => {
  const [applicationId, setApplicationId] = useState<string>(ticket.application?.id?.toString() || '')
  const [moduleId, setModuleId] = useState<string>(ticket.application_module?.id?.toString() || '')
  const [categoryId, setCategoryId] = useState<string>(ticket.category?.id?.toString() || '')
  const [problemSource, setProblemSource] = useState<string>('')
  const [priorityId, setPriorityId] = useState<string>(ticket.final_priority?.id?.toString() || '')
  const [targetDate, setTargetDate] = useState<string>(
    ticket.resolution_due_at
      ? ticket.resolution_due_at.substring(0, 10)
      : new Date(Date.now() + 3 * 86400000).toISOString().substring(0, 10),
  )
  const [notes, setNotes] = useState<string>('')

  // Single step assign fields
  const [primaryUserId, setPrimaryUserId] = useState<string>('')
  const [secondaryUserIds, setSecondaryUserIds] = useState<number[]>([])

  const [validationError, setValidationError] = useState<string | null>(null)

  useEffect(() => {
    if (ticket.application?.id) setApplicationId(ticket.application.id.toString())
    if (ticket.category?.id) setCategoryId(ticket.category.id.toString())
    if (ticket.final_priority?.id) setPriorityId(ticket.final_priority.id.toString())
  }, [ticket])

  const handleSubmit = async (assignDirectly: boolean) => {
    setValidationError(null)

    if (!applicationId) {
      setValidationError('Sistem / Aplikasi wajib dipilih.')
      return
    }
    if (!categoryId) {
      setValidationError('Kategori masalah wajib dipilih.')
      return
    }
    if (!priorityId) {
      setValidationError('Prioritas wajib ditentukan.')
      return
    }
    if (!targetDate) {
      setValidationError('Target penyelesaian wajib diisi.')
      return
    }
    if (assignDirectly && !primaryUserId) {
      setValidationError('Silakan pilih PIC utama jika ingin Simpan & Assign.')
      return
    }

    const payload: Record<string, unknown> = {
      application_id: Number(applicationId),
      application_module_id: moduleId ? Number(moduleId) : null,
      ticket_category_id: Number(categoryId),
      problem_source: problemSource || null,
      priority_id: Number(priorityId),
      target_completion_date: targetDate,
      analysis_notes: notes || null,
    }

    if (assignDirectly && primaryUserId) {
      payload.assign_primary_user_id = Number(primaryUserId)
      if (secondaryUserIds.length > 0) {
        payload.secondary_user_ids = secondaryUserIds
      }
    }

    try {
      await onSaveAnalysis(payload)
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal menyimpan hasil analisis.'
      setValidationError(msg)
    }
  }

  const handleToggleSecondary = (id: number) => {
    if (secondaryUserIds.includes(id)) {
      setSecondaryUserIds(secondaryUserIds.filter((x) => x !== id))
    } else {
      setSecondaryUserIds([...secondaryUserIds, id])
    }
  }

  return (
    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-6">
      <div className="flex items-center justify-between border-b border-gray-100 pb-3">
        <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
          <span className="h-2.5 w-2.5 rounded-full bg-blue-600" />
          Analisis Tiket & Penentuan PIC (Supervisor IT)
        </h2>
        <span className="text-xs text-gray-500 font-medium">* Wajib diisi sebelum assignment</span>
      </div>

      {validationError && (
        <div className="rounded-lg bg-red-50 p-3 text-xs font-semibold text-red-700 border border-red-200">
          {validationError}
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Sistem / Aplikasi <span className="text-red-500">*</span>
          </label>
          <select
            value={applicationId}
            onChange={(e) => setApplicationId(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
            <option value="">-- Pilih Sistem / Aplikasi --</option>
            {applications.map((app) => (
              <option key={app.id} value={app.id}>
                {app.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Modul Aplikasi <span className="text-gray-400 font-normal">(Opsional)</span>
          </label>
          <select
            value={moduleId}
            onChange={(e) => setModuleId(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
            <option value="">-- Pilih Modul (Opsional) --</option>
            {modules.map((m) => (
              <option key={m.id} value={m.id}>
                {m.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Kategori Masalah <span className="text-red-500">*</span>
          </label>
          <select
            value={categoryId}
            onChange={(e) => setCategoryId(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
            <option value="">-- Pilih Kategori --</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
          <p className="mt-1 text-[11px] text-gray-400">Pilihan awal: Bug Sistem Internal / Bug Sistem dari Asuransi</p>
        </div>

        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Sumber Masalah <span className="text-gray-400 font-normal">(Opsional)</span>
          </label>
          <input
            type="text"
            placeholder="Contoh: Core Insurance API, Database Lock, Server Outage"
            value={problemSource}
            onChange={(e) => setProblemSource(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          />
        </div>

        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Prioritas Penanganan <span className="text-red-500">*</span>
          </label>
          <select
            value={priorityId}
            onChange={(e) => setPriorityId(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
            <option value="">-- Pilih Prioritas --</option>
            {priorities.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">
            Target Penyelesaian / SLA <span className="text-red-500">*</span>
          </label>
          <input
            type="date"
            value={targetDate}
            onChange={(e) => setTargetDate(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          />
        </div>
      </div>

      <div>
        <label className="block text-xs font-semibold text-gray-700 mb-1">Catatan Analisis</label>
        <textarea
          rows={3}
          placeholder="Tuliskan temuan analisis awal, petunjuk penanganan, atau informasi teknis awal untuk PIC..."
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          className="w-full rounded-lg border border-gray-300 p-3 text-sm focus:border-blue-500 focus:outline-none"
        />
      </div>

      {/* Embedded Assignment Options */}
      <div className="border-t border-gray-100 pt-4 bg-gray-50/50 p-4 rounded-xl space-y-4">
        <h3 className="text-xs font-bold uppercase text-gray-600">Penunjukan PIC (Satu Langkah)</h3>

        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">Pilih PIC Utama</label>
          <select
            value={primaryUserId}
            onChange={(e) => setPrimaryUserId(e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none bg-white"
          >
            <option value="">-- Belum Assign PIC (Atau pilih nanti) --</option>
            {assignees.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name} ({u.role.name}) — Tiket Aktif: {u.active_ticket_count}
              </option>
            ))}
          </select>
        </div>

        {primaryUserId && (
          <div>
            <label className="block text-xs font-semibold text-gray-700 mb-2">PIC Pendamping (Secondary PIC)</label>
            <div className="flex flex-wrap gap-2">
              {assignees
                .filter((u) => u.id.toString() !== primaryUserId)
                .map((u) => {
                  const isSelected = secondaryUserIds.includes(u.id)
                  return (
                    <button
                      key={u.id}
                      type="button"
                      onClick={() => handleToggleSecondary(u.id)}
                      className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-all ${
                        isSelected
                          ? 'border-blue-600 bg-blue-100 text-blue-900 font-semibold'
                          : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100'
                      }`}
                    >
                      <span>{isSelected ? '✓' : '+'}</span>
                      <span>{u.name}</span>
                      <span className="text-[10px] text-gray-500">({u.role.name})</span>
                    </button>
                  )
                })}
            </div>
          </div>
        )}
      </div>

      <div className="flex flex-wrap items-center justify-end gap-3 border-t border-gray-100 pt-4">
        <button
          type="button"
          disabled={loading}
          onClick={() => handleSubmit(false)}
          className="rounded-lg border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
        >
          Simpan Analisis Saja
        </button>

        <button
          type="button"
          disabled={loading}
          onClick={() => handleSubmit(true)}
          className="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-5 py-2 text-xs font-bold text-white hover:bg-blue-800 transition-colors shadow-sm"
        >
          {loading && (
            <span className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent" />
          )}
          Simpan & Assign PIC
        </button>
      </div>
    </div>
  )
}
