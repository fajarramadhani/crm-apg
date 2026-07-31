import React, { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
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
import { TicketDescriptionContent } from '../../components/TicketDescriptionContent'
import { getPublicStatusLabel, PRIORITY_LABELS } from '../../presentation'
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
  ChevronDown,
  CalendarClock,
  UserRound,
} from 'lucide-react'

function formatDate(value?: string | null, withTime = false) {
  if (!value) return 'Belum tersedia'
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  })
}

function assignmentLabel(role?: string | null) {
  if (role === 'primary') return 'PIC Utama'
  if (role === 'secondary') return 'PIC Pendamping'
  if (role === 'supervisor') return 'Supervisor sebagai PIC'
  return 'Belum tersedia'
}

function nextStepMessage(status: string) {
  const messages: Record<string, string> = {
    reopened: 'Tiket ini dibuka kembali oleh Supervisor karena masih memerlukan penanganan.',
    assigned: 'Mulai pengerjaan tiket sesuai penugasan yang diberikan.',
    in_progress: 'Lengkapi pekerjaan dan perbarui progres secara berkala.',
    need_info: 'Tunggu informasi dari Requester sebelum melanjutkan pengerjaan.',
    waiting_external: 'Pantau tindak lanjut pihak eksternal dan lanjutkan saat informasi tersedia.',
    need_revision: 'Perbaiki hasil pekerjaan sesuai catatan Supervisor.',
    pending_approval: 'Hasil pekerjaan sedang menunggu pemeriksaan Supervisor.',
  }
  return messages[status] ?? 'Tinjau detail tiket dan lanjutkan tindakan yang tersedia.'
}

export const PicTicketDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const [ticket, setTicket] = useState<PicTicketDetailData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [conflictMessage, setConflictMessage] = useState<string | null>(null)
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
  const [dropdownOpen, setDropdownOpen] = useState(false)

  const loadTicket = async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const data = await ticketService.getPicTicketDetail(id)
      setTicket(data)
    } catch (cause: unknown) {
      setError(cause instanceof ApiRequestError ? cause.message : 'Gagal memuat detail tiket.')
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

  const handleActionError = (cause: unknown, fallback: string) => {
    if (cause instanceof ApiRequestError && cause.status === 409) {
      setConflictMessage(cause.message)
      return
    }
    setError(cause instanceof ApiRequestError ? cause.message : fallback)
  }

  const runModalAction = async (action: () => Promise<unknown>, success: string) => {
    setError(null)
    setConflictMessage(null)
    try {
      await action()
      flashSuccess(success)
      await loadTicket()
    } catch (cause: unknown) {
      handleActionError(cause, 'Aksi tidak dapat diproses.')
      throw cause
    }
  }

  // Handle Start Work
  const handleStartWork = async () => {
    if (!id) return
    try {
      await ticketService.startPicTicket(id)
      flashSuccess('Pengerjaan tiket berhasil dimulai.')
      loadTicket()
    } catch (cause: unknown) {
      handleActionError(cause, 'Gagal memulai pengerjaan.')
    }
  }

  // Handle Resume
  const handleResume = async () => {
    if (!id) return
    try {
      await ticketService.resumePicTicket(id)
      flashSuccess('Pengerjaan tiket dilanjutkan.')
      loadTicket()
    } catch (cause: unknown) {
      handleActionError(cause, 'Gagal melanjutkan pengerjaan.')
    }
  }

  if (loading) {
    return (
      <div className="mx-auto max-w-7xl p-6 space-y-6" aria-busy="true">
        <div className="h-10 w-48 animate-pulse rounded bg-slate-200" />
        <div className="h-[200px] animate-pulse rounded-2xl border border-slate-200 bg-white p-6" />
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-6">
            <div className="h-[300px] animate-pulse rounded-2xl border border-slate-200 bg-white" />
            <div className="h-[250px] animate-pulse rounded-2xl border border-slate-200 bg-white" />
          </div>
          <div className="space-y-6">
            <div className="h-[180px] animate-pulse rounded-2xl border border-slate-200 bg-white" />
            <div className="h-[120px] animate-pulse rounded-2xl border border-slate-200 bg-white" />
          </div>
        </div>
      </div>
    )
  }

  if (error || !ticket) {
    return (
      <div className="p-8 max-w-4xl mx-auto space-y-4">
        <Link to="/pic/tickets" className="inline-flex items-center gap-1 text-xs font-semibold text-[#1E3A8A] hover:underline">
          <ArrowLeft className="w-4 h-4" /> Kembali ke Daftar Tiket
        </Link>
        <div className="p-6 bg-red-50 text-red-600 border border-red-200 rounded-xl flex items-center gap-3">
          <AlertTriangle className="w-6 h-6 shrink-0" />
          <div>
            <h4 className="font-bold text-base">Akses Ditolak / Tiket Tidak Ditemukan</h4>
            <p className="text-xs mt-1">{error || 'Anda tidak memiliki hak akses pada tiket ini. Silakan hubungi Administrator jika ini merupakan kesalahan.'}</p>
          </div>
        </div>
      </div>
    )
  }

  const isLegacy = Boolean(ticket.is_legacy_workflow)
  const can = (action: string) => ticket.allowed_actions.includes(action)

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Top Breadcrumb & Flash Message */}
      <div className="flex items-center justify-between">
        <Link
          to="/pic/tickets"
          className="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-primary transition"
        >
          <ArrowLeft className="w-4 h-4" /> Kembali ke Daftar Tiket PIC
        </Link>

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

      {conflictMessage && (
        <div className="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-2 text-xs">
            <AlertTriangle className="h-4 w-4 shrink-0" />
            <span>{conflictMessage} Data tiket mungkin telah berubah.</span>
          </div>
          <button
            type="button"
            onClick={() => {
              setConflictMessage(null)
              loadTicket()
            }}
            className="rounded-lg bg-amber-900 px-3 py-2 text-xs font-bold text-white"
          >
            Muat Ulang Data
          </button>
        </div>
      )}

      {/* Legacy Notice if applicable */}
      {isLegacy && <LegacyPicWorkspaceNotice ticketId={ticket.id} />}

      {/* Ticket Header Banner */}
      <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
        <div className="flex flex-col md:flex-row md:items-start justify-between gap-4 border-b pb-4 border-slate-100">
          <div className="space-y-2 min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs font-mono font-bold px-2.5 py-1 rounded-md bg-slate-100 text-slate-800">
                #{ticket.ticket_number}
              </span>
              <span className="text-xs font-semibold px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700">
                {getPublicStatusLabel(ticket.status)}
              </span>
              {ticket.user_assignment_role && (
                <span className="text-xs font-semibold px-2.5 py-1 rounded-md bg-blue-50 text-blue-700">
                  {assignmentLabel(ticket.user_assignment_role)}
                </span>
              )}
              <span className="text-xs font-bold px-2.5 py-1 rounded-md bg-slate-100 text-slate-700">
                {ticket.final_priority?.key ? PRIORITY_LABELS[ticket.final_priority.key]?.toUpperCase() : 'MEDIUM'}
              </span>
            </div>
            <h1 className="text-xl font-bold text-slate-900 leading-snug">{ticket.title}</h1>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
              <span>Dibuat: {formatDate(ticket.created_at)}</span>
              <span className="hidden md:inline text-slate-300">•</span>
              <span>Terakhir diperbarui: {formatDate(ticket.updated_at, true)}</span>
            </div>
            <div className="mt-3 p-3 rounded-xl bg-slate-50 border border-slate-100 text-xs text-slate-700 flex items-start gap-2">
              <CalendarClock className="h-4 w-4 text-slate-400 shrink-0 mt-0.5" />
              <div>
                <span className="font-semibold block text-slate-900">Tahap Saat Ini</span>
                <span className="text-slate-600 block mt-0.5">{nextStepMessage(ticket.status)}</span>
              </div>
            </div>
          </div>

          {/* Quick Action Panel */}
          <div className="flex flex-col gap-2 shrink-0 w-full md:w-auto">
            {/* Primary & Green Submit Action Row */}
            <div className="flex flex-wrap items-center gap-2">
              {can('start') && (
                <button
                  onClick={handleStartWork}
                  className="px-4 py-2 text-xs font-bold text-white bg-[#1E3A8A] hover:bg-[#1e40af] rounded-lg shadow-sm transition flex items-center justify-center gap-1.5 flex-1 md:flex-none"
                >
                  <Play className="w-3.5 h-3.5" /> Mulai Pengerjaan
                </button>
              )}

              {can('resume') && (
                <button
                  onClick={handleResume}
                  className="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition flex items-center justify-center gap-1.5 flex-1 md:flex-none"
                >
                  <RotateCcw className="w-3.5 h-3.5" /> Lanjutkan Pengerjaan
                </button>
              )}

              {can('submit_for_approval') && (
                <button
                  onClick={() => setActiveModal('submit_approval')}
                  className="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition shadow-sm flex items-center justify-center gap-1.5 flex-1 md:flex-none"
                >
                  <Send className="w-3.5 h-3.5" /> Kirim ke Supervisor
                </button>
              )}
            </div>

            {/* General Actions Row */}
            <div className="flex flex-wrap items-center gap-2">
              {can('add_work_note') && (
                <button
                  onClick={() => setActiveModal('work_note')}
                  className="px-3 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition flex items-center justify-center gap-1.5 flex-1 md:flex-none"
                >
                  <MessageSquare className="w-3.5 h-3.5 text-blue-500" /> Tambah Catatan
                </button>
              )}

              {can('update_progress') && (
                <button
                  onClick={() => setActiveModal('progress')}
                  className="px-3 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition flex items-center justify-center gap-1.5 flex-1 md:flex-none"
                >
                  <Sliders className="w-3.5 h-3.5 text-indigo-500" /> Perbarui Progress ({ticket.progress_percentage ?? 0}%)
                </button>
              )}

              {can('upload_attachment') && (
                <button
                  onClick={() => setActiveModal('attachment')}
                  className="px-3 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition flex items-center justify-center gap-1.5 flex-1 md:flex-none"
                >
                  <Paperclip className="w-3.5 h-3.5 text-purple-500" /> Upload Hasil
                </button>
              )}
            </div>

            {/* Conditional / Other Actions Dropdown */}
            {(can('request_info') || can('mark_waiting_external') || can('internal_check') || can('request_assistance') || can('request_transfer')) && (
              <div className="relative">
                <button
                  onClick={() => setDropdownOpen(!dropdownOpen)}
                  className="w-full px-3 py-2 text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-300 hover:bg-slate-100 rounded-lg transition flex items-center justify-between gap-1.5"
                >
                  <span className="flex items-center gap-1">Aksi Lainnya</span>
                  <ChevronDown className={`w-3.5 h-3.5 transition-transform ${dropdownOpen ? 'rotate-180' : ''}`} />
                </button>

                {dropdownOpen && (
                  <>
                    <div className="fixed inset-0 z-10" onClick={() => setDropdownOpen(false)} />
                    <div className="absolute right-0 mt-1.5 w-full md:w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg z-20 space-y-0.5">
                      {can('request_info') && (
                        <button
                          onClick={() => { setDropdownOpen(false); setActiveModal('request_info'); }}
                          className="w-full text-left px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 rounded-lg flex items-center gap-2"
                        >
                          <HelpCircle className="w-3.5 h-3.5 text-amber-500" /> Minta Info Requester
                        </button>
                      )}

                      {can('mark_waiting_external') && (
                        <button
                          onClick={() => { setDropdownOpen(false); setActiveModal('waiting_external'); }}
                          className="w-full text-left px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 rounded-lg flex items-center gap-2"
                        >
                          <ExternalLink className="w-3.5 h-3.5 text-purple-500" /> Menunggu Eksternal
                        </button>
                      )}

                      {can('internal_check') && (
                        <button
                          onClick={() => { setDropdownOpen(false); setActiveModal('internal_check'); }}
                          className="w-full text-left px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 rounded-lg flex items-center gap-2"
                        >
                          <ShieldCheck className="w-3.5 h-3.5 text-emerald-500" /> Pengecekan Mandiri
                        </button>
                      )}

                      {can('request_assistance') && (
                        <button
                          onClick={() => { setDropdownOpen(false); setActiveModal('request_assistance'); }}
                          className="w-full text-left px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 rounded-lg flex items-center gap-2"
                        >
                          <Users className="w-3.5 h-3.5 text-blue-500" /> Minta Bantuan
                        </button>
                      )}

                      {can('request_transfer') && (
                        <button
                          onClick={() => { setDropdownOpen(false); setActiveModal('request_transfer'); }}
                          className="w-full text-left px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 rounded-lg flex items-center gap-2"
                        >
                          <ArrowRightLeft className="w-3.5 h-3.5 text-orange-500" /> Ajukan Pengalihan
                        </button>
                      )}
                    </div>
                  </>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Secondary Strip - Dibuat */}
        <div className="flex items-center justify-between text-xs text-slate-400">
          <span>Sistem dynamic workflow aktif</span>
          <span>Dibuat: {formatDate(ticket.created_at)}</span>
        </div>
      </div>

      {/* Main Grid Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left Column (2 Cols): Ticket Summary & Description */}
        <div className="lg:col-span-2 space-y-6">
          {/* Section A: Detail Pengajuan */}
          <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-4 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2 border-b pb-3 border-slate-100">
              <FileText className="w-4 h-4 text-[#1E3A8A]" /> Detail Pengajuan
            </h3>

            <div className="space-y-4 text-sm text-slate-700">
              <div>
                <span className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                  Deskripsi Permasalahan
                </span>
                <TicketDescriptionContent
                  html={ticket.description}
                  className="rounded-xl border border-slate-100 bg-slate-50 p-3.5"
                />
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
                    className="text-xs text-[#1E3A8A] font-semibold hover:underline inline-flex items-center gap-1 bg-blue-50 px-2.5 py-1 rounded"
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
                  <div className="text-slate-800 bg-slate-50 p-3 rounded-lg border border-slate-100">{ticket.reference}</div>
                </div>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-4 border-t border-slate-100 text-xs">
              <div>
                <span className="block text-slate-500 mb-0.5">Kategori Pengajuan:</span>
                <span className="font-semibold text-slate-800">
                  {ticket.category?.name ?? 'Belum tersedia'}
                </span>
              </div>
              <div>
                <span className="block text-slate-500 mb-0.5">Sistem / Aplikasi:</span>
                <span className="font-semibold text-slate-800">
                  {ticket.application?.name ?? 'Belum tersedia'}
                </span>
              </div>
              <div>
                <span className="block text-slate-500 mb-0.5">Urgency:</span>
                <span className="font-semibold text-slate-800">
                  {ticket.final_priority?.name ?? ticket.requested_priority?.name ?? 'Belum tersedia'}
                </span>
              </div>
            </div>
          </div>

          {/* Section: Analisis Supervisor */}
          <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-4 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2 border-b pb-3 border-slate-100">
              <ShieldCheck className="w-4 h-4 text-[#1E3A8A]" /> Analisis Supervisor
            </h3>
            {ticket.business_impact ? (
              <div className="space-y-4 text-sm">
                <div>
                  <span className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                    Ringkasan Analisis & Dampak Bisnis
                  </span>
                  <p className="text-slate-800 bg-slate-50 p-3.5 rounded-xl border border-slate-100 leading-relaxed">
                    {ticket.business_impact}
                  </p>
                </div>
                {ticket.expected_result && (
                  <div>
                    <span className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                      Target Hasil Pekerjaan
                    </span>
                    <p className="text-slate-800 bg-slate-50 p-3 rounded-lg border border-slate-100">
                      {ticket.expected_result}
                    </p>
                  </div>
                )}
              </div>
            ) : (
              <div className="text-xs text-slate-400 italic py-2">
                Analisis Supervisor belum tersedia.
              </div>
            )}
          </div>

          {/* Section: Lampiran Pekerjaan */}
          <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-4 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2 border-b pb-3 border-slate-100">
              <Paperclip className="w-4 h-4 text-[#1E3A8A]" /> Lampiran Pekerjaan & Hasil
            </h3>
            {ticket.attachments && ticket.attachments.length > 0 ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {ticket.attachments.map((file) => (
                  <div key={file.id} className="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100/50 transition">
                    <div className="min-w-0 flex-1 pr-2">
                      <p className="text-xs font-semibold text-slate-900 truncate" title={file.original_name}>
                        {file.original_name}
                      </p>
                      <p className="text-[10px] text-slate-500 mt-0.5">
                        {Math.round(file.size / 1024)} KB • {formatDate(file.created_at)}
                      </p>
                    </div>
                    <a
                      href={`/api/v1/attachments/${file.id}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-xs font-bold text-[#1E3A8A] hover:underline shrink-0"
                    >
                      Unduh
                    </a>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-xs text-slate-400 italic py-2">
                Belum ada lampiran hasil pengerjaan.
              </div>
            )}
          </div>

          {/* Timeline & Audit Trail */}
          <PicActivityTimeline ticket={ticket} />
        </div>

        {/* Right Column (1 Col): Assignment Info & Side Cards */}
        <div className="space-y-6">
          {/* Section B: Assignment Summary */}
          <PicAssignmentSummary ticket={ticket} />

          {/* Progress Card */}
          <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-3 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 flex items-center justify-between border-b pb-3 border-slate-100">
              <span>Progress Pengerjaan</span>
              <span className="text-sm font-bold text-[#1E3A8A]">{ticket.progress_percentage ?? 0}%</span>
            </h3>

            <div className="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
              <div
                className="bg-[#1E3A8A] h-3 rounded-full transition-all"
                style={{ width: `${Math.min(100, Math.max(0, ticket.progress_percentage ?? 0))}%` }}
              ></div>
            </div>

            {ticket.latest_progress_at && (
              <p className="text-[11px] text-slate-500">
                Terakhir diperbarui: {formatDate(ticket.latest_progress_at, true)}
              </p>
            )}

            {can('update_progress') && (
              <button
                onClick={() => setActiveModal('progress')}
                className="w-full py-2.5 text-xs font-semibold text-[#1E3A8A] bg-blue-50 hover:bg-blue-100 rounded-lg transition text-center focus:outline-none focus:ring-2 focus:ring-blue-500/30"
              >
                Perbarui Progress
              </button>
            )}
          </div>

          {/* Section: Informasi Requester */}
          <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-4 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2 border-b pb-3 border-slate-100">
              <UserRound className="w-4 h-4 text-[#1E3A8A]" /> Informasi Requester
            </h3>
            <div className="space-y-2.5 text-xs">
              <div>
                <span className="text-slate-500 block mb-0.5">Nama:</span>
                <span className="font-semibold text-slate-950 block">{ticket.requester?.name ?? '-'}</span>
              </div>
              <div>
                <span className="text-slate-500 block mb-0.5">Kantor / Cabang / Divisi:</span>
                <span className="font-semibold text-slate-950 block">
                  {ticket.office?.name ?? ticket.branch?.name ?? ticket.division?.name ?? 'Kantor Pusat'}
                </span>
              </div>
              <div>
                <span className="text-slate-500 block mb-0.5">Tanggal Pengajuan:</span>
                <span className="font-semibold text-slate-950 block">{formatDate(ticket.created_at)}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Modal Actions */}
      {activeModal && (
        <div className="fixed inset-0 z-50 bg-slate-200/70 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-2xl p-6 max-w-lg w-full shadow-2xl max-h-[90vh] overflow-y-auto">
            {activeModal === 'work_note' && (
              <PicWorkNoteForm
                onSubmit={async (data) => {
                  await runModalAction(
                    () => ticketService.addPicWorkNote(ticket.id, data),
                    'Catatan pekerjaan berhasil disimpan.',
                  )
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'progress' && (
              <PicProgressForm
                currentProgress={ticket.progress_percentage ?? 0}
                onSubmit={async (data) => {
                  await runModalAction(
                    () => ticketService.updatePicProgress(ticket.id, data),
                    'Progres pekerjaan diperbarui.',
                  )
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'attachment' && (
              <PicAttachmentUploader
                onUpload={async (formData) => {
                  await runModalAction(
                    () => ticketService.uploadPicAttachment(ticket.id, formData),
                    'Lampiran berhasil diunggah.',
                  )
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'request_info' && (
              <PicRequestInfoForm
                onSubmit={async (data) => {
                  await runModalAction(
                    () => ticketService.requestPicInfo(ticket.id, data),
                    'Permintaan informasi dikirim ke Requester.',
                  )
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'waiting_external' && (
              <PicWaitingExternalForm
                onSubmit={async (data) => {
                  await runModalAction(
                    () => ticketService.markPicWaitingExternal(ticket.id, data),
                    'Tiket ditandai menunggu pihak eksternal.',
                  )
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'request_assistance' && (
              <PicAssistanceRequestForm
                onSubmit={async (data) => {
                  await runModalAction(
                    () => ticketService.requestPicAssistance(ticket.id, data),
                    'Permintaan bantuan dikirim ke Supervisor IT.',
                  )
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'request_transfer' && (
              <PicTransferRequestForm
                onSubmit={async (data) => {
                  await runModalAction(
                    () => ticketService.requestPicTransfer(ticket.id, data),
                    'Permintaan pengalihan dikirim ke Supervisor IT.',
                  )
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'internal_check' && (
              <PicInternalCheckForm
                onSubmit={async (data) => {
                  await runModalAction(
                    () => ticketService.submitPicInternalCheck(ticket.id, data),
                    'Hasil pengecekan mandiri dicatat.',
                  )
                }}
                onClose={() => setActiveModal(null)}
              />
            )}

            {activeModal === 'submit_approval' && (
              <PicSubmitApprovalForm
                onSubmit={async (data) => {
                  await runModalAction(
                    () => ticketService.submitPicForApproval(ticket.id, data),
                    'Tiket berhasil dikirim untuk pemeriksaan Supervisor IT.',
                  )
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
