import React, { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { ticketService, type PicTicketDetailData } from '../../services/ticketService'
import { PicAssignmentSummary } from '../../components/pic/PicAssignmentSummary'
import { PicActivityTimeline } from '../../components/pic/PicActivityTimeline'
import { PicWorkNoteForm } from '../../components/pic/PicWorkNoteForm'
import { PicProgressForm } from '../../components/pic/PicProgressForm'
import { PicAttachmentUploader } from '../../components/pic/PicAttachmentUploader'
import { PicRequestInfoForm } from '../../components/pic/PicRequestInfoForm'
import { PicWaitingExternalForm } from '../../components/pic/PicWaitingExternalForm'
import { PicAssistanceRequestForm } from '../../components/pic/PicAssistanceRequestForm'
import { PicTransferRequestForm } from '../../components/pic/PicTransferRequestForm'
import { PicInternalCheckForm } from '../../components/pic/PicInternalCheckForm'
import { PicSubmitApprovalForm } from '../../components/pic/PicSubmitApprovalForm'
import { LegacyPicWorkspaceNotice } from '../../components/pic/LegacyPicWorkspaceNotice'
import { STATUS_LABELS } from '../../presentation'
import {
  ArrowLeft,
  Play,
  MessageSquare,
  Sliders,
  Paperclip,
  HelpCircle,
  ExternalLink,
  Users,
  ArrowRightLeft,
  ShieldCheck,
  Send,
  RefreshCw,
  AlertTriangle,
  FileText,
  Globe,
  RotateCcw,
} from 'lucide-react'

export const PicTicketDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const [ticket, setTicket] = useState<PicTicketDetailData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  // Active modal action state
  const [activeModal, setActiveModal] = useState<
    | 'work_note'
    | 'progress'
    | 'attachment'
    | 'request_info'
    | 'waiting_external'
    | 'request_assistance'
    | 'request_transfer'
    | 'internal_check'
    | 'submit_approval'
    | null
  >(null)

  const loadTicket = async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const data = await ticketService.getPicTicketDetail(id)
      setTicket(data)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal memuat detail tiket.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadTicket()
  }, [id])

  const flashSuccess = (msg: string) => {
    setSuccessMessage(msg)
    setTimeout(() => setSuccessMessage(null), 4000)
  }

  // Handle Start Work
  const handleStartWork = async () => {
    if (!id) return
    try {
      await ticketService.startPicTicket(id)
      flashSuccess('Pengerjaan tiket berhasil dimulai.')
      loadTicket()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal memulai pengerjaan.')
    }
  }

  // Handle Resume
  const handleResume = async () => {
    if (!id) return
    try {
      await ticketService.resumePicTicket(id)
      flashSuccess('Pengerjaan tiket dilanjutkan.')
      loadTicket()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal melanjutkan pengerjaan.')
    }
  }

  if (loading) {
    return (
      <div className="p-12 text-center text-slate-500">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-300 border-t-primary mb-2"></div>
        <p>Memuat workspace tiket...</p>
      </div>
    )
  }

  if (error || !ticket) {
    return (
      <div className="p-8 max-w-4xl mx-auto space-y-4">
        <a href="/pic/tickets" className="inline-flex items-center gap-1 text-xs font-semibold text-primary">
          <ArrowLeft className="w-4 h-4" /> Kembali ke Daftar Tiket
        </a>
        <div className="p-6 bg-red-50 text-red-600 border border-red-200 rounded-xl flex items-center gap-3">
          <AlertTriangle className="w-6 h-6 shrink-0" />
          <div>
            <h4 className="font-bold text-base">Akses Ditolak / Tiket Tidak Ditemukan</h4>
            <p className="text-xs mt-1">{error || 'Anda tidak memiliki hak akses pada tiket ini.'}</p>
          </div>
        </div>
      </div>
    )
  }

  const isPrimary = ticket.user_assignment_role === 'primary'
  const isLegacy = Boolean(ticket.solution_plan_summary?.status === 'approved' || (ticket.allowed_actions && ticket.allowed_actions.includes('start_qa')))

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Top Breadcrumb & Flash Message */}
      <div className="flex items-center justify-between">
        <a
          href="/pic/tickets"
          className="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-primary transition"
        >
          <ArrowLeft className="w-4 h-4" /> Kembali ke Daftar Tiket PIC
        </a>

        <button
          onClick={loadTicket}
          className="p-1.5 text-xs text-slate-500 hover:text-slate-900 border rounded-lg flex items-center gap-1"
        >
          <RefreshCw className="w-3.5 h-3.5" /> Refresh
        </button>
      </div>

      {successMessage && (
        <div className="p-4 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-semibold">
          {successMessage}
        </div>
      )}

      {/* Legacy Notice if applicable */}
      {isLegacy && <LegacyPicWorkspaceNotice ticketId={ticket.id} />}

      {/* Ticket Header Banner */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b pb-4 border-slate-100 dark:border-slate-800">
          <div>
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                #{ticket.ticket_number}
              </span>
              <span className="text-xs font-semibold px-2.5 py-1 rounded-md bg-primary/10 text-primary">
                {STATUS_LABELS[ticket.status] ?? ticket.status}
              </span>
            </div>
            <h1 className="text-xl font-bold text-slate-900 dark:text-slate-100 mt-2">
              {ticket.title}
            </h1>
          </div>

          {/* Quick Action Panel */}
          <div className="flex flex-wrap items-center gap-2">
            {(ticket.status === 'assigned' || (ticket.status as string) === 'under_analysis') && (
              <button
                onClick={handleStartWork}
                className="px-4 py-2 text-xs font-bold text-white bg-primary hover:bg-primary/90 rounded-lg shadow-sm transition flex items-center gap-1.5"
              >
                <Play className="w-3.5 h-3.5" /> Mulai Pengerjaan
              </button>
            )}

            {((ticket.status as string) === 'need_info' || (ticket.status as string) === 'waiting_external') && (
              <button
                onClick={handleResume}
                className="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition flex items-center gap-1.5"
              >
                <RotateCcw className="w-3.5 h-3.5" /> Lanjutkan Pengerjaan
              </button>
            )}

            <button
              onClick={() => setActiveModal('work_note')}
              className="px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 rounded-lg transition flex items-center gap-1"
            >
              <MessageSquare className="w-3.5 h-3.5 text-blue-500" /> Catatan
            </button>

            <button
              onClick={() => setActiveModal('progress')}
              className="px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 rounded-lg transition flex items-center gap-1"
            >
              <Sliders className="w-3.5 h-3.5 text-indigo-500" /> Progres ({ticket.progress_percentage ?? 0}%)
            </button>

            <button
              onClick={() => setActiveModal('attachment')}
              className="px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 rounded-lg transition flex items-center gap-1"
            >
              <Paperclip className="w-3.5 h-3.5 text-purple-500" /> Upload Hasil
            </button>

            {isPrimary && (
              <button
                onClick={() => setActiveModal('submit_approval')}
                className="px-4 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition shadow-sm flex items-center gap-1"
              >
                <Send className="w-3.5 h-3.5" /> Kirim ke Supervisor
              </button>
            )}
          </div>
        </div>

        {/* Secondary Action Strip */}
        <div className="flex flex-wrap items-center justify-between gap-3 text-xs pt-1 text-slate-500">
          <div className="flex flex-wrap items-center gap-2">
            {isPrimary && (
              <button
                onClick={() => setActiveModal('request_info')}
                className="text-amber-600 dark:text-amber-400 font-semibold hover:underline flex items-center gap-1"
              >
                <HelpCircle className="w-3.5 h-3.5" /> Minta Info Requester
              </button>
            )}

            <button
              onClick={() => setActiveModal('waiting_external')}
              className="text-purple-600 dark:text-purple-400 font-semibold hover:underline flex items-center gap-1"
            >
              <ExternalLink className="w-3.5 h-3.5" /> Menunggu Eksternal
            </button>

            <button
              onClick={() => setActiveModal('internal_check')}
              className="text-emerald-600 dark:text-emerald-400 font-semibold hover:underline flex items-center gap-1"
            >
              <ShieldCheck className="w-3.5 h-3.5" /> Pengecekan Mandiri
            </button>

            <button
              onClick={() => setActiveModal('request_assistance')}
              className="text-blue-600 dark:text-blue-400 font-semibold hover:underline flex items-center gap-1"
            >
              <Users className="w-3.5 h-3.5" /> Minta Bantuan
            </button>

            <button
              onClick={() => setActiveModal('request_transfer')}
              className="text-orange-600 dark:text-orange-400 font-semibold hover:underline flex items-center gap-1"
            >
              <ArrowRightLeft className="w-3.5 h-3.5" /> Ajukan Pengalihan
            </button>
          </div>

          <div className="text-[11px] text-slate-400">
            Dibuat: {new Date(ticket.created_at).toLocaleDateString('id-ID')}
          </div>
        </div>
      </div>

      {/* Main Grid Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left Column (2 Cols): Ticket Summary & Description */}
        <div className="lg:col-span-2 space-y-6">
          {/* Section A: Detail Ringkasan Tiket */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 space-y-4 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2 border-b pb-3 border-slate-100 dark:border-slate-800">
              <FileText className="w-4 h-4 text-primary" /> Ringkasan Informasi Tiket
            </h3>

            <div className="space-y-3">
              <div>
                <span className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                  Deskripsi Permasalahan
                </span>
                <p className="text-sm text-slate-800 dark:text-slate-200 whitespace-pre-line bg-slate-50 dark:bg-slate-800/50 p-3.5 rounded-lg border border-slate-100 dark:border-slate-800">
                  {ticket.description}
                </p>
              </div>

              {ticket.affected_url && (
                <div>
                  <span className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                    URL Terdampak
                  </span>
                  <a
                    href={ticket.affected_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-xs text-primary font-medium hover:underline inline-flex items-center gap-1 bg-primary/5 px-2.5 py-1 rounded"
                  >
                    <Globe className="w-3.5 h-3.5" /> {ticket.affected_url}
                  </a>
                </div>
              )}

              {ticket.reference && (
                <div>
                  <span className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                    Referensi / Catatan Tambahan
                  </span>
                  <div className="text-xs text-slate-700 dark:text-slate-300">
                    {ticket.reference}
                  </div>
                </div>
              )}
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
              <div>
                <span className="block text-slate-400">Requester:</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">
                  {ticket.requester?.name ?? '-'}
                </span>
              </div>
              <div>
                <span className="block text-slate-400">Cabang / Divisi:</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">
                  {ticket.branch?.name ?? ticket.division?.name ?? '-'}
                </span>
              </div>
              <div>
                <span className="block text-slate-400">Sistem / Aplikasi:</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">
                  {ticket.application?.name ?? '-'}
                </span>
              </div>
              <div>
                <span className="block text-slate-400">Kategori:</span>
                <span className="font-semibold text-slate-800 dark:text-slate-200">
                  {ticket.category?.name ?? '-'}
                </span>
              </div>
            </div>
          </div>

          {/* Timeline & Audit Trail */}
          <PicActivityTimeline ticket={ticket} />
        </div>

        {/* Right Column (1 Col): Assignment Info & Side Cards */}
        <div className="space-y-6">
          {/* Section B: Assignment Summary */}
          <PicAssignmentSummary ticket={ticket} />

          {/* Progress Card */}
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 space-y-3 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800">
              <span>Progres Pengerjaan</span>
              <span className="text-sm font-bold text-primary">{ticket.progress_percentage ?? 0}%</span>
            </h3>

            <div className="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 overflow-hidden">
              <div
                className="bg-primary h-3 rounded-full transition-all"
                style={{ width: `${Math.min(100, Math.max(0, ticket.progress_percentage ?? 0))}%` }}
              ></div>
            </div>

            <button
              onClick={() => setActiveModal('progress')}
              className="w-full py-2 text-xs font-semibold text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition text-center"
            >
              Perbarui Progres (%)
            </button>
          </div>
        </div>
      </div>

      {/* Modal Actions */}
      {activeModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 max-w-lg w-full shadow-2xl max-h-[90vh] overflow-y-auto">
            {activeModal === 'work_note' && (
              <PicWorkNoteForm
                onSubmit={async (data) => {
                  await ticketService.addPicWorkNote(ticket.id, data)
                  flashSuccess('Catatan pekerjaan berhasil disimpan.')
                  loadTicket()
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'progress' && (
              <PicProgressForm
                currentProgress={ticket.progress_percentage ?? 0}
                onSubmit={async (data) => {
                  await ticketService.updatePicProgress(ticket.id, data)
                  flashSuccess('Progres pekerjaan diperbarui.')
                  loadTicket()
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'attachment' && (
              <PicAttachmentUploader
                onUpload={async (formData) => {
                  await ticketService.uploadPicAttachment(ticket.id, formData)
                  flashSuccess('Lampiran berhasil diunggah.')
                  loadTicket()
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'request_info' && (
              <PicRequestInfoForm
                onSubmit={async (data) => {
                  await ticketService.requestPicInfo(ticket.id, data)
                  flashSuccess('Permintaan informasi dikirim ke Requester.')
                  loadTicket()
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'waiting_external' && (
              <PicWaitingExternalForm
                onSubmit={async (data) => {
                  await ticketService.markPicWaitingExternal(ticket.id, data)
                  flashSuccess('Tiket ditandai menunggu pihak eksternal.')
                  loadTicket()
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'request_assistance' && (
              <PicAssistanceRequestForm
                onSubmit={async (data) => {
                  await ticketService.requestPicAssistance(ticket.id, data)
                  flashSuccess('Permintaan bantuan dikirim ke Supervisor IT.')
                  loadTicket()
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'request_transfer' && (
              <PicTransferRequestForm
                onSubmit={async (data) => {
                  await ticketService.requestPicTransfer(ticket.id, data)
                  flashSuccess('Permintaan pengalihan dikirim ke Supervisor IT.')
                  loadTicket()
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'internal_check' && (
              <PicInternalCheckForm
                onSubmit={async (data) => {
                  await ticketService.submitPicInternalCheck(ticket.id, data)
                  flashSuccess('Hasil pengecekan mandiri dicatat.')
                  loadTicket()
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'submit_approval' && (
              <PicSubmitApprovalForm
                onSubmit={async (data) => {
                  await ticketService.submitPicForApproval(ticket.id, data)
                  flashSuccess('Tiket berhasil dikirim untuk pemeriksaan Supervisor IT.')
                  loadTicket()
                }}
                onClose={() => setActiveModal(null)}
              />
            )}
          </div>
        </div>
      )}
    </div>
  )
}
