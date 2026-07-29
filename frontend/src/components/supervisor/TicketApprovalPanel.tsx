import React, { useState } from 'react'
import type { TicketRecord } from '../../services/ticketService'

interface TicketApprovalPanelProps {
  ticket: TicketRecord
  currentUserId: number | null
  onRequestInfo: (notes: string) => Promise<void>
  onRequestRevision: (notes: string) => Promise<void>
  onApprove: (notes?: string, summaryForRequester?: string) => Promise<void>
  onReject: (reason: string, summaryForRequester?: string) => Promise<void>
  onCancel: (reason: string) => Promise<void>
  onReopen: (reason: string, primaryUserId?: number) => Promise<void>
  onClose: (notes?: string) => Promise<void>
  loading?: boolean
}

type ActiveActionModal = 'info' | 'revision' | 'approve' | 'reject' | 'cancel' | 'reopen' | 'close' | null

export const TicketApprovalPanel: React.FC<TicketApprovalPanelProps> = ({
  ticket,
  currentUserId,
  onRequestInfo,
  onRequestRevision,
  onApprove,
  onReject,
  onCancel,
  onReopen,
  onClose,
  loading = false,
}) => {
  const [activeModal, setActiveModal] = useState<ActiveActionModal>(null)
  const [inputText, setInputText] = useState<string>('')
  const [summaryText, setSummaryText] = useState<string>('')
  const [errorMsg, setErrorMsg] = useState<string | null>(null)
  const activePrimaryAssignment = ticket.assignments?.find(
    (assignment) => assignment.assignment_type === 'primary' && assignment.is_current,
  )
  const activePrimaryPicId = activePrimaryAssignment?.assigned_to ?? ticket.assignee?.id
  const isSelfApproval = currentUserId !== null && activePrimaryPicId === currentUserId

  const handleOpenModal = (modal: ActiveActionModal) => {
    setActiveModal(modal)
    setInputText('')
    setSummaryText('')
    setErrorMsg(null)
  }

  const handleCloseModal = () => {
    setActiveModal(null)
    setInputText('')
    setSummaryText('')
    setErrorMsg(null)
  }

  const handleSubmitAction = async () => {
    if (!activeModal) return
    setErrorMsg(null)

    try {
      if (activeModal === 'info') {
        if (!inputText.trim()) throw new Error('Catatan permintaan informasi wajib diisi.')
        await onRequestInfo(inputText)
      } else if (activeModal === 'revision') {
        if (!inputText.trim()) throw new Error('Catatan revisi wajib diisi.')
        await onRequestRevision(inputText)
      } else if (activeModal === 'approve') {
        if (isSelfApproval && !inputText.trim()) throw new Error('Catatan wajib diisi untuk self-approval.')
        await onApprove(inputText.trim() || undefined, summaryText.trim() || undefined)
      } else if (activeModal === 'reject') {
        if (!inputText.trim()) throw new Error('Alasan penolakan wajib diisi.')
        await onReject(inputText, summaryText || undefined)
      } else if (activeModal === 'cancel') {
        if (!inputText.trim()) throw new Error('Alasan pembatalan wajib diisi.')
        await onCancel(inputText)
      } else if (activeModal === 'reopen') {
        if (!inputText.trim()) throw new Error('Alasan membuka kembali tiket wajib diisi.')
        await onReopen(inputText)
      } else if (activeModal === 'close') {
        await onClose(inputText || undefined)
      }
      handleCloseModal()
    } catch (err: unknown) {
      setErrorMsg(err instanceof Error ? err.message : 'Gagal memproses aksi.')
    }
  }

  const isCompleted = ['done', 'closed', 'rejected', 'cancelled'].includes(ticket.status)

  return (
    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
      <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
        <span className="h-2.5 w-2.5 rounded-full bg-emerald-600" />
        Action Panel & Keputusan Supervisor IT
      </h2>

      <div className="flex flex-wrap items-center gap-3">
        {!isCompleted && (
          <>
            <button
              type="button"
              disabled={loading}
              onClick={() => handleOpenModal('approve')}
              className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition-colors shadow-sm"
            >
              ✓ Setujui & Selesaikan
            </button>

            <button
              type="button"
              disabled={loading}
              onClick={() => handleOpenModal('revision')}
              className="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-xs font-bold text-white hover:bg-amber-600 transition-colors shadow-sm"
            >
              ↩ Kirim untuk Perbaikan (Revisi)
            </button>

            <button
              type="button"
              disabled={loading}
              onClick={() => handleOpenModal('info')}
              className="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 transition-colors shadow-sm"
            >
              ❓ Minta Informasi Requester
            </button>

            <button
              type="button"
              disabled={loading}
              onClick={() => handleOpenModal('reject')}
              className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-100 transition-colors"
            >
              ✕ Tolak Tiket
            </button>

            <button
              type="button"
              disabled={loading}
              onClick={() => handleOpenModal('cancel')}
              className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-gray-50 px-3.5 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition-colors"
            >
              🚫 Batalkan Tiket
            </button>
          </>
        )}

        {isCompleted && (
          <>
            <button
              type="button"
              disabled={loading}
              onClick={() => handleOpenModal('reopen')}
              className="inline-flex items-center gap-1.5 rounded-lg bg-purple-600 px-4 py-2 text-xs font-bold text-white hover:bg-purple-700 transition-colors shadow-sm"
            >
              🔄 Buka Kembali Tiket (Reopen)
            </button>

            {ticket.status !== 'closed' && (
              <button
                type="button"
                disabled={loading}
                onClick={() => handleOpenModal('close')}
                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-gray-100 px-4 py-2 text-xs font-bold text-gray-800 hover:bg-gray-200 transition-colors"
              >
                🔒 Tutup Tiket Eksplisit
              </button>
            )}
          </>
        )}
      </div>

      {/* Modal Dialog for Actions */}
      {activeModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="supervisor-action-title"
            aria-describedby={activeModal === 'approve' && isSelfApproval ? 'self-approval-warning' : undefined}
            className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl space-y-4"
          >
            <h3 id="supervisor-action-title" className="text-base font-bold text-gray-900 capitalize">
              {activeModal === 'approve' && 'Setujui & Selesaikan Tiket'}
              {activeModal === 'revision' && 'Kembalikan untuk Revisi'}
              {activeModal === 'info' && 'Minta Informasi Tambahan kepada Requester'}
              {activeModal === 'reject' && 'Tolak Tiket Ini'}
              {activeModal === 'cancel' && 'Batalkan Tiket Ini'}
              {activeModal === 'reopen' && 'Buka Kembali Tiket'}
              {activeModal === 'close' && 'Tutup Tiket Eksplisit'}
            </h3>

            {errorMsg && (
              <div
                role="alert"
                className="rounded-lg bg-red-50 p-3 text-xs font-semibold text-red-700 border border-red-200"
              >
                {errorMsg}
              </div>
            )}

            {activeModal === 'approve' && isSelfApproval && (
              <div
                id="self-approval-warning"
                role="alert"
                className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
              >
                <p className="font-bold">Peringatan self-approval</p>
                <p>
                  Anda juga tercatat sebagai PIC utama tiket ini. Approval akan dicatat sebagai self-approval pada audit
                  trail.
                </p>
              </div>
            )}

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">
                {['reject', 'cancel', 'reopen'].includes(activeModal) || (activeModal === 'approve' && isSelfApproval)
                  ? 'Catatan / Alasan (Wajib)'
                  : 'Catatan / Deskripsi'}
              </label>
              <textarea
                rows={4}
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                required={activeModal === 'approve' && isSelfApproval}
                aria-required={activeModal === 'approve' && isSelfApproval}
                placeholder="Tuliskan catatan atau alasan tindakan secara jelas..."
                className="w-full rounded-lg border border-gray-300 p-3 text-sm focus:border-blue-500 focus:outline-none"
              />
            </div>

            {['approve', 'reject'].includes(activeModal) && (
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Ringkasan Hasil untuk Requester{' '}
                  <span className="text-gray-400 font-normal">(Dapat dilihat Requester)</span>
                </label>
                <textarea
                  rows={2}
                  value={summaryText}
                  onChange={(e) => setSummaryText(e.target.value)}
                  placeholder="Ringkasan penjelasan yang aman dan ramah untuk dibaca Requester..."
                  className="w-full rounded-lg border border-gray-300 p-3 text-sm focus:border-blue-500 focus:outline-none"
                />
              </div>
            )}

            <div className="flex items-center justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={handleCloseModal}
                className="rounded-lg border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50"
              >
                Batal
              </button>

              <button
                type="button"
                disabled={loading || (activeModal === 'approve' && isSelfApproval && !inputText.trim())}
                onClick={handleSubmitAction}
                className="rounded-lg bg-blue-700 px-5 py-2 text-xs font-bold text-white hover:bg-blue-800 transition-colors shadow-sm"
              >
                {loading ? 'Memproses...' : 'Konfirmasi Aksi'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
