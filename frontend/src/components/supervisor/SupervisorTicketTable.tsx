import React from 'react'
import { Link } from 'react-router-dom'
import type { TicketRecord } from '../../services/ticketService'
import { LegacyWorkflowBadge } from './LegacyWorkflowBadge'

interface SupervisorTicketTableProps {
  tickets: TicketRecord[]
  loading?: boolean
  onSelectTicket?: (id: number) => void
}

export const SupervisorTicketTable: React.FC<SupervisorTicketTableProps> = ({
  tickets,
  loading = false,
}) => {
  if (loading) {
    return (
      <div className="flex h-48 items-center justify-center rounded-xl bg-white p-6 shadow-sm border border-gray-100">
        <div className="flex items-center gap-3 text-sm font-medium text-gray-500">
          <span className="h-5 w-5 animate-spin rounded-full border-2 border-blue-600 border-t-transparent" />
          Memuat daftar tiket...
        </div>
      </div>
    )
  }

  if (!tickets || tickets.length === 0) {
    return (
      <div className="flex h-48 flex-col items-center justify-center rounded-xl bg-white p-6 text-center shadow-sm border border-gray-100">
        <p className="text-base font-semibold text-gray-700">Tidak ada tiket ditemukan</p>
        <p className="mt-1 text-sm text-gray-500">Sesuaikan filter pencarian atau belum ada tiket yang diajukan.</p>
      </div>
    )
  }

  const getStatusBadgeClass = (status: string) => {
    switch (status) {
      case 'pending_validation':
      case 'submitted':
      case 'draft':
        return 'bg-blue-50 text-blue-700 border-blue-200'
      case 'under_analysis':
      case 'triage':
      case 'analysis':
        return 'bg-purple-50 text-purple-700 border-purple-200'
      case 'assigned':
      case 'in_progress':
      case 'development_in_progress':
        return 'bg-indigo-50 text-indigo-700 border-indigo-200'
      case 'need_info':
      case 'need_revision':
        return 'bg-amber-50 text-amber-700 border-amber-200'
      case 'pending_approval':
      case 'awaiting_requester_confirmation':
        return 'bg-orange-50 text-orange-700 border-orange-200'
      case 'done':
      case 'closed':
        return 'bg-emerald-50 text-emerald-700 border-emerald-200'
      case 'rejected':
      case 'cancelled':
        return 'bg-rose-50 text-rose-700 border-rose-200'
      default:
        return 'bg-gray-50 text-gray-700 border-gray-200'
    }
  }

  const getPriorityBadgeClass = (priorityKey?: string) => {
    switch (priorityKey) {
      case 'critical':
        return 'bg-red-100 text-red-800'
      case 'high':
        return 'bg-orange-100 text-orange-800'
      case 'medium':
        return 'bg-yellow-100 text-yellow-800'
      case 'low':
        return 'bg-green-100 text-green-800'
      default:
        return 'bg-gray-100 text-gray-700'
    }
  }

  return (
    <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
      <table className="w-full text-left text-sm text-gray-600">
        <thead className="bg-gray-50/75 text-xs uppercase font-semibold text-gray-500 border-b border-gray-200">
          <tr>
            <th scope="col" className="px-4 py-3.5">No. Tiket</th>
            <th scope="col" className="px-4 py-3.5">Judul</th>
            <th scope="col" className="px-4 py-3.5">Requester</th>
            <th scope="col" className="px-4 py-3.5">Cabang / Divisi</th>
            <th scope="col" className="px-4 py-3.5">Sistem / Kategori</th>
            <th scope="col" className="px-4 py-3.5">Prioritas</th>
            <th scope="col" className="px-4 py-3.5">PIC Utama</th>
            <th scope="col" className="px-4 py-3.5">Status</th>
            <th scope="col" className="px-4 py-3.5">Target</th>
            <th scope="col" className="px-4 py-3.5 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {tickets.map((t) => {
            const isLegacy = t.workflow_mode !== 'simplified' &&
              ['triage', 'plan_review', 'ready_for_qa', 'qa_in_progress', 'ready_for_uat', 'uat_in_progress', 'approval_pending', 'release_preparation'].includes(t.status)

            return (
              <tr key={t.id} className="hover:bg-blue-50/30 transition-colors">
                <td className="whitespace-nowrap px-4 py-3.5 font-medium text-blue-900">
                  {t.ticket_number}
                </td>
                <td className="px-4 py-3.5 max-w-xs truncate font-medium text-gray-900" title={t.title}>
                  <div>{t.title}</div>
                  {isLegacy && <div className="mt-1"><LegacyWorkflowBadge isLegacy /></div>}
                </td>
                <td className="whitespace-nowrap px-4 py-3.5 text-gray-700">
                  {t.requester?.name || '-'}
                </td>
                <td className="whitespace-nowrap px-4 py-3.5 text-gray-600 text-xs">
                  <div>{t.division?.name || '-'}</div>
                  <div className="text-gray-400">{t.branch?.name || '-'}</div>
                </td>
                <td className="whitespace-nowrap px-4 py-3.5 text-xs">
                  <div className="font-semibold text-gray-800">{t.application?.name || 'Belum Ditentukan'}</div>
                  <div className="text-gray-500">{t.category?.name || '-'}</div>
                </td>
                <td className="whitespace-nowrap px-4 py-3.5">
                  <span className={`inline-flex rounded-md px-2 py-0.5 text-xs font-semibold ${getPriorityBadgeClass(t.final_priority?.key)}`}>
                    {t.final_priority?.name || 'Belum Set'}
                  </span>
                </td>
                <td className="whitespace-nowrap px-4 py-3.5 font-medium text-gray-800">
                  {t.assignee?.name ? (
                    <span className="inline-flex items-center gap-1.5 text-xs font-medium text-gray-900">
                      <span className="h-2 w-2 rounded-full bg-blue-600" />
                      {t.assignee.name}
                    </span>
                  ) : (
                    <span className="text-xs italic text-gray-400">Belum ada PIC</span>
                  )}
                </td>
                <td className="whitespace-nowrap px-4 py-3.5">
                  <span className={`inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize ${getStatusBadgeClass(t.status)}`}>
                    {t.status.replace(/_/g, ' ')}
                  </span>
                </td>
                <td className="whitespace-nowrap px-4 py-3.5 text-xs text-gray-600">
                  {t.resolution_due_at ? new Date(t.resolution_due_at).toLocaleDateString('id-ID') : '-'}
                </td>
                <td className="whitespace-nowrap px-4 py-3.5 text-right text-xs font-medium">
                  <Link
                    to={`/supervisor-it/tickets/${t.id}`}
                    className="inline-flex items-center rounded-lg bg-blue-50 px-3 py-1.5 text-blue-700 hover:bg-blue-100 font-semibold transition-colors"
                  >
                    Detail & Action
                  </Link>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
