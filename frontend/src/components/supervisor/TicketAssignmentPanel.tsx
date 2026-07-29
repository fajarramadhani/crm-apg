import React, { useState } from 'react'
import type { TicketRecord } from '../../services/ticketService'
import type { EligibleAssignee } from '../../types'

interface TicketAssignmentPanelProps {
  ticket: TicketRecord
  currentUserId: number
  assignees: EligibleAssignee[]
  onAssignPrimary: (userId: number, notes?: string, reason?: string) => Promise<void>
  onAddSecondary: (userId: number, notes?: string, reason?: string) => Promise<void>
  onRemoveSecondary: (userId: number, notes?: string, reason?: string) => Promise<void>
  onReassign: (newPrimaryUserId: number, notes?: string, reason?: string) => Promise<void>
  onTakeover: (notes?: string, reason?: string) => Promise<void>
  loading?: boolean
}

export const TicketAssignmentPanel: React.FC<TicketAssignmentPanelProps> = ({
  ticket,
  currentUserId,
  assignees,
  onAssignPrimary,
  onAddSecondary,
  onRemoveSecondary,
  onReassign,
  onTakeover,
  loading = false,
}) => {
  const [selectedPrimaryId, setSelectedPrimaryId] = useState<string>('')
  const [selectedSecondaryId, setSelectedSecondaryId] = useState<string>('')
  const [assignmentNotes, setAssignmentNotes] = useState<string>('')
  const [assignmentReason, setAssignmentReason] = useState<string>('')
  const [isReassigning, setIsReassigning] = useState<boolean>(false)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  const currentPrimary = ticket.assignee
  const isSelfPrimary = currentPrimary?.id === currentUserId

  const handleAssignSelf = async () => {
    setErrorMsg(null)
    try {
      if (currentPrimary) {
        await onReassign(currentUserId, 'Pengambilalihan oleh supervisor', 'Supervisor menangani sendiri tiket ini')
      } else {
        await onAssignPrimary(
          currentUserId,
          'Penunjukan supervisor sebagai PIC utama',
          'Supervisor menangani sendiri tiket ini',
        )
      }
    } catch (err: unknown) {
      setErrorMsg(err instanceof Error ? err.message : 'Gagal assign diri sendiri')
    }
  }

  const handleAssignPrimarySubmit = async () => {
    if (!selectedPrimaryId) return
    setErrorMsg(null)
    try {
      if (currentPrimary) {
        await onReassign(Number(selectedPrimaryId), assignmentNotes, assignmentReason)
      } else {
        await onAssignPrimary(Number(selectedPrimaryId), assignmentNotes, assignmentReason)
      }
      setSelectedPrimaryId('')
      setAssignmentNotes('')
      setAssignmentReason('')
      setIsReassigning(false)
    } catch (err: unknown) {
      setErrorMsg(err instanceof Error ? err.message : 'Gagal assign PIC utama')
    }
  }

  const handleAddSecondarySubmit = async () => {
    if (!selectedSecondaryId) return
    setErrorMsg(null)
    try {
      await onAddSecondary(Number(selectedSecondaryId), assignmentNotes, assignmentReason)
      setSelectedSecondaryId('')
      setAssignmentNotes('')
      setAssignmentReason('')
    } catch (err: unknown) {
      setErrorMsg(err instanceof Error ? err.message : 'Gagal menambakan PIC pendamping')
    }
  }

  const handleTakeoverSubmit = async () => {
    setErrorMsg(null)
    try {
      await onTakeover(assignmentNotes || 'Takeover oleh Supervisor', assignmentReason || 'Mempercepat penanganan')
      setAssignmentNotes('')
      setAssignmentReason('')
    } catch (err: unknown) {
      setErrorMsg(err instanceof Error ? err.message : 'Gagal melakukan takeover tiket')
    }
  }

  const handleRemoveSecondarySubmit = async (userId: number) => {
    setErrorMsg(null)
    try {
      await onRemoveSecondary(userId)
    } catch (err: unknown) {
      setErrorMsg(err instanceof Error ? err.message : 'Gagal menghapus PIC pendamping')
    }
  }

  return (
    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-3">
        <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
          <span className="h-2.5 w-2.5 rounded-full bg-indigo-600" />
          Status Assignment PIC
        </h2>

        {!isSelfPrimary && (
          <button
            type="button"
            disabled={loading}
            onClick={handleAssignSelf}
            className="inline-flex items-center gap-2 rounded-lg bg-indigo-50 border border-indigo-200 px-3.5 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition-colors shadow-sm"
          >
            <span>👤 Tangani Sendiri</span>
          </button>
        )}
      </div>

      {errorMsg && (
        <div className="rounded-lg bg-red-50 p-3 text-xs font-semibold text-red-700 border border-red-200">
          {errorMsg}
        </div>
      )}

      {/* Current Assignments Display */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="rounded-lg bg-indigo-50/50 p-4 border border-indigo-100 space-y-2">
          <span className="text-xs font-semibold text-indigo-900 uppercase">PIC Utama (Primary)</span>
          {currentPrimary ? (
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-bold text-gray-900">{currentPrimary.name}</p>
                {isSelfPrimary && (
                  <span className="text-[10px] font-semibold text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded-full">
                    Supervisor (Anda)
                  </span>
                )}
              </div>
              <button
                type="button"
                onClick={() => setIsReassigning(!isReassigning)}
                className="text-xs font-semibold text-indigo-600 hover:underline"
              >
                Ganti PIC Utama
              </button>
            </div>
          ) : (
            <p className="text-xs italic text-gray-500">Belum ada PIC Utama</p>
          )}
        </div>

        <div className="rounded-lg bg-gray-50 p-4 border border-gray-100 space-y-2">
          <span className="text-xs font-semibold text-gray-700 uppercase">PIC Pendamping (Secondary)</span>
          <div className="flex flex-wrap gap-2 pt-1">
            {ticket.assignments &&
            ticket.assignments.filter((a: any) => a.assignment_type === 'secondary' && a.is_current).length > 0 ? (
              ticket.assignments
                .filter((a: any) => a.assignment_type === 'secondary' && a.is_current)
                .map((sec: any) => (
                  <span
                    key={sec.id}
                    className="inline-flex items-center gap-1.5 rounded-md bg-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-800 border border-gray-300"
                  >
                    <span>{sec.assignee?.name || `User #${sec.assigned_to}`}</span>
                    <button
                      type="button"
                      onClick={() => handleRemoveSecondarySubmit(sec.assigned_to)}
                      className="ml-1 text-red-600 hover:text-red-800 font-bold"
                      title="Hapus Secondary PIC"
                    >
                      ×
                    </button>
                  </span>
                ))
            ) : (
              <p className="text-xs text-gray-500">Gunakan form di bawah untuk menambah PIC pendamping.</p>
            )}
          </div>
        </div>
      </div>

      {/* Assignment / Reassignment Controls */}
      {(!currentPrimary || isReassigning) && (
        <div className="rounded-xl border border-indigo-200 bg-indigo-50/30 p-4 space-y-4">
          <h3 className="text-xs font-bold text-indigo-900 uppercase">
            {currentPrimary ? 'Ganti / Reassign PIC Utama' : 'Penunjukan PIC Utama Baru'}
          </h3>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">Pilih Kandidat PIC</label>
              <select
                value={selectedPrimaryId}
                onChange={(e) => setSelectedPrimaryId(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none bg-white"
              >
                <option value="">-- Pilih PIC Support / Develop / Supervisor --</option>
                {assignees.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name} ({u.role.name}) — Beban Aktif: {u.active_ticket_count} tiket
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">Alasan Reassignment / Catatan</label>
              <input
                type="text"
                placeholder="Alasan penunjukan atau catatan tugas..."
                value={assignmentReason}
                onChange={(e) => setAssignmentReason(e.target.value)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
              />
            </div>
          </div>

          <div className="flex items-center justify-end gap-2">
            {isReassigning && (
              <button
                type="button"
                onClick={() => setIsReassigning(false)}
                className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100"
              >
                Batal
              </button>
            )}
            <button
              type="button"
              disabled={loading || !selectedPrimaryId}
              onClick={handleAssignPrimarySubmit}
              className="rounded-lg bg-indigo-700 px-4 py-1.5 text-xs font-bold text-white hover:bg-indigo-800 disabled:opacity-50"
            >
              Confirm PIC Utama
            </button>
          </div>
        </div>
      )}

      {/* Add Secondary & Takeover controls */}
      {currentPrimary && (
        <div className="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-gray-100">
          <div className="flex items-center gap-3">
            <select
              value={selectedSecondaryId}
              onChange={(e) => setSelectedSecondaryId(e.target.value)}
              className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-indigo-500 focus:outline-none"
            >
              <option value="">+ Tambah PIC Pendamping</option>
              {assignees
                .filter((u) => u.id !== currentPrimary.id)
                .map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name} ({u.role.name})
                  </option>
                ))}
            </select>
            {selectedSecondaryId && (
              <button
                type="button"
                disabled={loading}
                onClick={handleAddSecondarySubmit}
                className="rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-bold text-white hover:bg-gray-900"
              >
                Simpan Pendamping
              </button>
            )}
          </div>

          {!isSelfPrimary && (
            <button
              type="button"
              disabled={loading}
              onClick={handleTakeoverSubmit}
              className="rounded-lg border border-amber-300 bg-amber-50 px-3.5 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-100 transition-colors"
            >
              Takeover Tiket Ini
            </button>
          )}
        </div>
      )}
    </div>
  )
}
