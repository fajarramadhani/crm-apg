import React from 'react'
import type { TicketRecord } from '../../services/ticketService'
import { LegacyWorkflowBadge } from './LegacyWorkflowBadge'

interface TicketRequesterSummaryProps {
  ticket: TicketRecord
}

export const TicketRequesterSummary: React.FC<TicketRequesterSummaryProps> = ({ ticket }) => {
  const isLegacy = ticket.workflow_mode !== 'simplified' &&
    ['triage', 'plan_review', 'ready_for_qa', 'qa_in_progress', 'ready_for_uat', 'uat_in_progress', 'approval_pending', 'release_preparation'].includes(ticket.status)

  return (
    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-4">
        <div>
          <div className="flex items-center gap-3">
            <span className="font-mono text-sm font-semibold text-blue-900 bg-blue-50 px-2.5 py-1 rounded-md border border-blue-100">
              #{ticket.ticket_number}
            </span>
            <LegacyWorkflowBadge isLegacy={isLegacy} />
          </div>
          <h1 className="mt-2 text-xl font-bold text-gray-900">{ticket.title}</h1>
        </div>
        <div className="text-right text-xs text-gray-500">
          <div>Diajukan pada</div>
          <div className="font-semibold text-gray-700">
            {ticket.submitted_at ? new Date(ticket.submitted_at).toLocaleString('id-ID') : new Date(ticket.created_at).toLocaleString('id-ID')}
          </div>
        </div>
      </div>

      <div>
        <h3 className="text-xs font-semibold uppercase text-gray-400">Deskripsi Kendala</h3>
        <p className="mt-2 whitespace-pre-wrap text-sm text-gray-800 bg-gray-50/70 p-4 rounded-lg border border-gray-100 leading-relaxed">
          {ticket.description}
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div>
          <span className="font-semibold text-gray-500">Requester:</span>{' '}
          <span className="text-gray-900 font-medium">{ticket.requester?.name || '-'}</span>
        </div>

        <div>
          <span className="font-semibold text-gray-500">Cabang / Divisi:</span>{' '}
          <span className="text-gray-900 font-medium">
            {ticket.division?.name || '-'} {ticket.branch ? `(${ticket.branch.name})` : ''}
          </span>
        </div>

        {ticket.affected_url && (
          <div className="md:col-span-2">
            <span className="font-semibold text-gray-500">Link Submission Error:</span>{' '}
            <a
              href={ticket.affected_url}
              target="_blank"
              rel="noopener noreferrer"
              className="font-mono text-blue-600 hover:underline break-all"
            >
              {ticket.affected_url}
            </a>
          </div>
        )}

        {ticket.reference && (
          <div className="md:col-span-2">
            <span className="font-semibold text-gray-500">Referensi:</span>{' '}
            <span className="text-gray-800">{ticket.reference}</span>
          </div>
        )}
      </div>

      {ticket.attachments && ticket.attachments.length > 0 && (
        <div className="border-t border-gray-100 pt-4">
          <h4 className="text-xs font-semibold uppercase text-gray-400 mb-2">Lampiran Dokumen / Bukti Screenshot</h4>
          <div className="flex flex-wrap gap-2">
            {ticket.attachments.map((att) => (
              <a
                key={att.id}
                href={`/api/v1/tickets/${ticket.id}/attachments/${att.id}/download`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 transition-colors"
              >
                <svg className="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
                <span>{att.original_name}</span>
                <span className="text-gray-400 text-[10px]">({Math.round(att.size / 1024)} KB)</span>
              </a>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
