import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import { masterDataService } from '../../services/masterDataService'
import { TicketRequesterSummary } from '../../components/supervisor/TicketRequesterSummary'
import { TicketAnalysisPanel } from '../../components/supervisor/TicketAnalysisPanel'
import { TicketAssignmentPanel } from '../../components/supervisor/TicketAssignmentPanel'
import { TicketApprovalPanel } from '../../components/supervisor/TicketApprovalPanel'
import { LegacyTicketActions } from '../../components/supervisor/LegacyTicketActions'
import { TicketAuditTimeline } from '../../components/supervisor/TicketAuditTimeline'
import type { EligibleAssignee, AuditTimelineItem } from '../../types'

export default function SupervisorTicketDetail() {
  const { id } = useParams<{ id: string }>()
  const { user } = useAuth()

  const [loading, setLoading] = useState<boolean>(true)
  const [actionLoading, setActionLoading] = useState<boolean>(false)
  const [error, setError] = useState<string | null>(null)
  const [ticket, setTicket] = useState<TicketRecord | null>(null)

  // Master data
  const [applications, setApplications] = useState<Array<{ id: number; name: string }>>([])
  const [modules, setModules] = useState<Array<{ id: number; name: string }>>([])
  const [categories, setCategories] = useState<Array<{ id: number; name: string }>>([])
  const [priorities, setPriorities] = useState<Array<{ id: number; name: string }>>([])
  const [assignees, setAssignees] = useState<EligibleAssignee[]>([])

  useEffect(() => {
    if (id) {
      fetchTicketDetail(id)
      fetchMasterData()
    }
  }, [id])

  const fetchTicketDetail = async (ticketId: string) => {
    setLoading(true)
    setError(null)
    try {
      const data = await ticketService.supervisorItTicketDetail(ticketId)
      setTicket(data)
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gagal memuat detail tiket.')
    } finally {
      setLoading(false)
    }
  }

  const fetchMasterData = async () => {
    try {
      const [appsRes, catsRes, prioRes, assigneesRes] = await Promise.all([
        masterDataService.getApplications(),
        masterDataService.getTicketCategories(),
        masterDataService.getTicketPriorities(),
        ticketService.supervisorItAssignees(),
      ])

      setApplications(appsRes.map((a: { id: number; name: string }) => ({ id: a.id, name: a.name })))
      setCategories(catsRes.map((c: { id: number; name: string }) => ({ id: c.id, name: c.name })))
      setPriorities(prioRes.map((p: { id: number; name: string }) => ({ id: p.id, name: p.name })))
      setAssignees(assigneesRes)

      if (ticket?.application?.id) {
        const mods = await masterDataService.getApplicationModules(ticket.application.id)
        setModules(mods.map((m: { id: number; name: string }) => ({ id: m.id, name: m.name })))
      }
    } catch {
      // Non-blocking
    }
  }

  const reload = () => {
    if (id) fetchTicketDetail(id)
  }

  // Action Handlers
  const handleSaveAnalysis = async (payload: Record<string, unknown>) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.analyzeTicket(id, payload)
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleAssignPrimary = async (userId: number, notes?: string, reason?: string) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.assignPrimaryPic(id, { user_id: userId, notes, reason })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleAddSecondary = async (userId: number, notes?: string, reason?: string) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.addSecondaryPic(id, { user_id: userId, notes, reason })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleRemoveSecondary = async (userId: number) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.removeSecondaryPic(id, userId)
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleReassign = async (newPrimaryUserId: number, notes?: string, reason?: string) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.reassignPic(id, { user_id: newPrimaryUserId, notes, reason })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleTakeover = async (notes?: string, reason?: string) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.takeoverPic(id, { notes, reason })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleRequestInfo = async (notes: string) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.requestTicketInfo(id, { notes })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleRequestRevision = async (notes: string) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.requestTicketRevision(id, { notes })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleApprove = async (notes?: string, summaryForRequester?: string) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.approveTicket(id, { notes, summary_for_requester: summaryForRequester })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleReject = async (reason: string, summaryForRequester?: string) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.rejectTicket(id, { reason, summary_for_requester: summaryForRequester })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleCancel = async (reason: string) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.cancelTicket(id, { reason })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleReopen = async (reason: string, primaryUserId?: number) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.reopenTicket(id, { reason, primary_user_id: primaryUserId })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  const handleClose = async (notes?: string) => {
    if (!id) return
    setActionLoading(true)
    try {
      await ticketService.supervisorCloseTicket(id, { notes })
      reload()
    } finally {
      setActionLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <div className="flex items-center gap-3 text-sm font-medium text-gray-600">
          <span className="h-6 w-6 animate-spin rounded-full border-2 border-blue-600 border-t-transparent" />
          Memuat detail tiket...
        </div>
      </div>
    )
  }

  if (error || !ticket) {
    return (
      <div className="rounded-xl bg-red-50 p-6 text-center shadow-sm border border-red-200 space-y-4">
        <p className="text-base font-bold text-red-800">{error || 'Tiket tidak ditemukan.'}</p>
        <Link
          to="/supervisor-it/tickets"
          className="inline-flex items-center rounded-lg bg-red-700 px-4 py-2 text-xs font-bold text-white hover:bg-red-800"
        >
          ← Kembali ke Daftar Tiket
        </Link>
      </div>
    )
  }

  const auditTimelineItems: AuditTimelineItem[] =
    ((ticket as unknown as Record<string, unknown>).audit_timeline as AuditTimelineItem[]) || []

  return (
    <div className="space-y-6 pb-16">
      <div className="flex items-center justify-between">
        <Link
          to="/supervisor-it/tickets"
          className="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 hover:text-blue-700 transition-colors"
        >
          ← Kembali ke Daftar Tiket
        </Link>

        <span className="text-xs text-gray-400 font-mono">
          Last Updated: {ticket.updated_at ? new Date(ticket.updated_at).toLocaleString('id-ID') : '-'}
        </span>
      </div>

      <LegacyTicketActions ticket={ticket} />

      <TicketRequesterSummary ticket={ticket} />

      <TicketAnalysisPanel
        ticket={ticket}
        applications={applications}
        modules={modules}
        categories={categories}
        priorities={priorities}
        assignees={assignees}
        onSaveAnalysis={handleSaveAnalysis}
        loading={actionLoading}
      />

      <TicketAssignmentPanel
        ticket={ticket}
        currentUserId={user?.id ? Number(user.id) : 0}
        assignees={assignees}
        onAssignPrimary={handleAssignPrimary}
        onAddSecondary={handleAddSecondary}
        onRemoveSecondary={handleRemoveSecondary}
        onReassign={handleReassign}
        onTakeover={handleTakeover}
        loading={actionLoading}
      />

      <TicketApprovalPanel
        ticket={ticket}
        currentUserId={user ? Number(user.id) : null}
        onRequestInfo={handleRequestInfo}
        onRequestRevision={handleRequestRevision}
        onApprove={handleApprove}
        onReject={handleReject}
        onCancel={handleCancel}
        onReopen={handleReopen}
        onClose={handleClose}
        loading={actionLoading}
      />

      <TicketAuditTimeline items={auditTimelineItems} />
    </div>
  )
}
