import React from 'react'
import type { TicketRecord } from '../../services/ticketService'
import { STATUS_LABELS } from '../../presentation'
import { UserCheck, Users, ShieldAlert, ArrowRight, Clock, AlertTriangle } from 'lucide-react'

interface PicTicketTableProps {
  tickets: TicketRecord[]
  loading?: boolean
  currentUserId?: number
}

export const PicTicketTable: React.FC<PicTicketTableProps> = ({ tickets, loading, currentUserId }) => {
  if (loading) {
    return (
      <div className="p-8 text-center text-slate-500">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-300 border-t-primary mb-2"></div>
        <p>Memuat daftar tiket PIC...</p>
      </div>
    )
  }

  if (!tickets || tickets.length === 0) {
    return (
      <div className="p-12 text-center text-slate-500 border border-dashed rounded-xl bg-slate-50 dark:bg-slate-900/50">
        <p className="text-base font-medium">Tidak ada tiket yang ditugaskan.</p>
        <p className="text-xs text-slate-400 mt-1">
          Tiket yang di-assign oleh Supervisor IT akan tampil di sini.
        </p>
      </div>
    )
  }

  const renderAssignmentRoleBadge = (ticket: TicketRecord) => {
    // Check if primary or secondary or supervisor
    const isPrimary = ticket.assignee?.id === currentUserId
    const roleType = ticket.user_assignment_role ?? (isPrimary ? 'primary' : 'secondary')

    if (roleType === 'primary' || isPrimary) {
      return (
        <span className="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
          <UserCheck className="w-3 h-3" /> PIC Utama
        </span>
      )
    }

    if (roleType === 'secondary') {
      return (
        <span className="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-md bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
          <Users className="w-3 h-3" /> PIC Pendamping
        </span>
      )
    }

    if (roleType === 'supervisor') {
      return (
        <span className="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
          <ShieldAlert className="w-3 h-3" /> Supervisor (Acting PIC)
        </span>
      )
    }

    return (
      <span className="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded bg-slate-100 text-slate-700">
        Assigned
      </span>
    )
  }

  return (
    <div className="w-full overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
      {/* Desktop Table View */}
      <table className="w-full text-left text-sm hidden md:table">
        <thead className="bg-slate-50 dark:bg-slate-900/80 text-xs font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-800">
          <tr>
            <th className="px-4 py-3">No Tiket & Judul</th>
            <th className="px-4 py-3">Requester / Divisi</th>
            <th className="px-4 py-3">Sistem & Kategori</th>
            <th className="px-4 py-3">Peran PIC</th>
            <th className="px-4 py-3">Status</th>
            <th className="px-4 py-3">Progres</th>
            <th className="px-4 py-3">Target SLA</th>
            <th className="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
          {tickets.map((ticket) => {
            const isOverdue =
              ticket.resolution_due_at &&
              new Date(ticket.resolution_due_at) < new Date() &&
              !['done', 'closed', 'rejected', 'cancelled'].includes(ticket.status)

            return (
              <tr key={ticket.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-900/40 transition">
                <td className="px-4 py-3">
                  <div className="font-semibold text-slate-900 dark:text-slate-100">
                    {ticket.ticket_number}
                  </div>
                  <div className="text-xs text-slate-600 dark:text-slate-400 line-clamp-1 max-w-xs">
                    {ticket.title}
                  </div>
                </td>
                <td className="px-4 py-3">
                  <div className="font-medium text-slate-800 dark:text-slate-200">
                    {ticket.requester?.name ?? '-'}
                  </div>
                  <div className="text-xs text-slate-400">
                    {ticket.branch?.name ?? ticket.division?.name ?? '-'}
                  </div>
                </td>
                <td className="px-4 py-3">
                  <div className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                    {ticket.application?.name ?? '-'}
                  </div>
                  <div className="text-xs text-slate-400">
                    {ticket.category?.name ?? '-'}
                  </div>
                </td>
                <td className="px-4 py-3">{renderAssignmentRoleBadge(ticket)}</td>
                <td className="px-4 py-3">
                  <span className="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300">
                    {STATUS_LABELS[ticket.status] ?? ticket.status}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-2">
                    <div className="w-16 bg-slate-200 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                      <div
                        className="bg-primary h-2 rounded-full transition-all"
                        style={{ width: `${Math.min(100, Math.max(0, ticket.progress_percentage ?? 0))}%` }}
                      ></div>
                    </div>
                    <span className="text-xs font-medium text-slate-600 dark:text-slate-400">
                      {ticket.progress_percentage ?? 0}%
                    </span>
                  </div>
                </td>
                <td className="px-4 py-3">
                  <div className={`text-xs font-medium ${isOverdue ? 'text-red-600 font-semibold flex items-center gap-1' : 'text-slate-600 dark:text-slate-400'}`}>
                    {isOverdue && <AlertTriangle className="w-3 h-3 text-red-500" />}
                    {ticket.resolution_due_at
                      ? new Date(ticket.resolution_due_at).toLocaleDateString('id-ID', {
                          day: '2-digit',
                          month: 'short',
                          year: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                        })
                      : '-'}
                  </div>
                </td>
                <td className="px-4 py-3 text-right">
                  <a
                    href={`/pic/tickets/${ticket.id}`}
                    className="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition"
                  >
                    Detail <ArrowRight className="w-3 h-3" />
                  </a>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>

      {/* Mobile Card List View */}
      <div className="md:hidden divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-950">
        {tickets.map((ticket) => (
          <div key={ticket.id} className="p-4 space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-900 dark:text-slate-100">
                {ticket.ticket_number}
              </span>
              {renderAssignmentRoleBadge(ticket)}
            </div>
            <h4 className="text-sm font-semibold text-slate-800 dark:text-slate-200">
              {ticket.title}
            </h4>
            <div className="grid grid-cols-2 gap-2 text-xs text-slate-500">
              <div>
                <span className="block text-slate-400">Requester:</span>
                <span className="font-medium text-slate-700 dark:text-slate-300">
                  {ticket.requester?.name ?? '-'}
                </span>
              </div>
              <div>
                <span className="block text-slate-400">Sistem:</span>
                <span className="font-medium text-slate-700 dark:text-slate-300">
                  {ticket.application?.name ?? '-'}
                </span>
              </div>
              <div>
                <span className="block text-slate-400">Status:</span>
                <span className="font-medium text-slate-700 dark:text-slate-300">
                  {STATUS_LABELS[ticket.status] ?? ticket.status}
                </span>
              </div>
              <div>
                <span className="block text-slate-400">Progres:</span>
                <span className="font-medium text-slate-700 dark:text-slate-300">
                  {ticket.progress_percentage ?? 0}%
                </span>
              </div>
            </div>
            <div className="flex items-center justify-between pt-2">
              <span className="text-xs text-slate-400 flex items-center gap-1">
                <Clock className="w-3 h-3" />
                {ticket.resolution_due_at
                  ? new Date(ticket.resolution_due_at).toLocaleDateString('id-ID', {
                      day: '2-digit',
                      month: 'short',
                    })
                  : '-'}
              </span>
              <a
                href={`/pic/tickets/${ticket.id}`}
                className="px-3 py-1.5 text-xs font-semibold text-white bg-primary hover:bg-primary/90 rounded-lg transition"
              >
                Buka Workspace
              </a>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
