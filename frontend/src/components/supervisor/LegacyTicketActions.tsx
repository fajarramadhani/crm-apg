import React from 'react'
import type { TicketRecord } from '../../services/ticketService'
import { LegacyWorkflowBadge } from './LegacyWorkflowBadge'

interface LegacyTicketActionsProps {
  ticket: TicketRecord
}

export const LegacyTicketActions: React.FC<LegacyTicketActionsProps> = ({ ticket }) => {
  const isLegacy =
    ticket.workflow_mode !== 'simplified' &&
    [
      'triage',
      'plan_review',
      'ready_for_qa',
      'qa_in_progress',
      'ready_for_uat',
      'uat_in_progress',
      'approval_pending',
      'release_preparation',
    ].includes(ticket.status)

  if (!isLegacy) return null

  return (
    <div className="rounded-xl border border-amber-200 bg-amber-50/40 p-4 space-y-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <LegacyWorkflowBadge isLegacy />
          <span className="text-xs font-bold text-amber-900">Legacy Transition Controls</span>
        </div>
        <span className="text-[11px] text-amber-700">
          Tahap Legacy: <strong className="uppercase">{ticket.status}</strong>
        </span>
      </div>

      <p className="text-xs text-amber-800 leading-relaxed">
        Tiket ini dibuat menggunakan alur kerja terdahulu. Anda dapat melihat seluruh riwayat dan data QA, UAT, serta
        Approval legacy dari audit trail di bawah.
      </p>
    </div>
  )
}
