import React from 'react'
import { Link } from 'react-router-dom'
import type { TicketRecord } from '../../services/ticketService'
import { STATUS_LABELS } from '../../presentation'
import { AlertTriangle, ArrowRight, Clock, ShieldAlert, UserCheck, Users } from 'lucide-react'

interface PicTicketTableProps {
  tickets: TicketRecord[]
  loading?: boolean
  currentUserId?: number
}

function formatDateTime(value?: string | null) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export const PicTicketTable: React.FC<PicTicketTableProps> = ({ tickets, loading, currentUserId }) => {
  if (loading) {
    return (
      <div className="rounded-2xl border border-slate-200 bg-white" aria-live="polite" aria-busy="true">
        <div className="hidden md:block">
          <div className="grid grid-cols-[1.25fr_1fr_1fr_0.9fr_0.8fr_0.9fr_1fr_0.8fr] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
            {Array.from({ length: 8 }).map((_, index) => (
              <div key={index} className="h-4 animate-pulse rounded bg-slate-200" />
            ))}
          </div>
          {Array.from({ length: 3 }).map((_, rowIndex) => (
            <div key={rowIndex} className="grid grid-cols-[1.25fr_1fr_1fr_0.9fr_0.8fr_0.9fr_1fr_0.8fr] gap-3 px-4 py-4">
              {Array.from({ length: 8 }).map((_, cellIndex) => (
                <div key={cellIndex} className="h-4 animate-pulse rounded bg-slate-100" />
              ))}
            </div>
          ))}
        </div>
        <div className="space-y-3 p-4 md:hidden">
          {Array.from({ length: 3 }).map((_, index) => (
            <div key={index} className="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div className="h-4 w-1/3 animate-pulse rounded bg-slate-200" />
              <div className="h-5 w-2/3 animate-pulse rounded bg-slate-200" />
              <div className="grid grid-cols-2 gap-2">
                {Array.from({ length: 4 }).map((__, itemIndex) => (
                  <div key={itemIndex} className="h-4 animate-pulse rounded bg-slate-100" />
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    )
  }

  if (!tickets || tickets.length === 0) {
    return null
  }

  const renderAssignmentRoleBadge = (ticket: TicketRecord) => {
    const isPrimary = ticket.assignee?.id === currentUserId
    const roleType = ticket.user_assignment_role ?? (isPrimary ? 'primary' : 'secondary')

    if (roleType === 'primary' || isPrimary) {
      return (
        <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
          <UserCheck className="h-3.5 w-3.5" aria-hidden="true" /> PIC Utama
        </span>
      )
    }

    if (roleType === 'secondary') {
      return (
        <span className="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">
          <Users className="h-3.5 w-3.5" aria-hidden="true" /> PIC Pendamping
        </span>
      )
    }

    if (roleType === 'supervisor') {
      return (
        <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
          <ShieldAlert className="h-3.5 w-3.5" aria-hidden="true" /> Supervisor sebagai PIC
        </span>
      )
    }

    return (
      <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
        Assigned
      </span>
    )
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div className="hidden overflow-x-auto md:block">
        <table className="min-w-full text-left text-sm">
          <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th className="px-4 py-3">Nomor Tiket</th>
              <th className="px-4 py-3">Judul</th>
              <th className="px-4 py-3">Kategori</th>
              <th className="px-4 py-3">Sistem</th>
              <th className="px-4 py-3">Urgency</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Peran Assignment</th>
              <th className="px-4 py-3">Target Penyelesaian</th>
              <th className="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-200">
            {tickets.map((ticket) => {
              const isOverdue =
                ticket.resolution_due_at &&
                new Date(ticket.resolution_due_at) < new Date() &&
                !['done', 'closed', 'rejected', 'cancelled'].includes(ticket.status)

              return (
                <tr key={ticket.id} className="transition hover:bg-slate-50/80">
                  <td className="px-4 py-4 align-top">
                    <div className="font-semibold text-slate-900">{ticket.ticket_number}</div>
                    <div className="mt-1 text-xs text-slate-500">{ticket.requester?.name ?? '-'}</div>
                    {ticket.submission_source === 'public_form' && (
                      <span className="mt-1 inline-flex rounded-full bg-cyan-50 px-2 py-0.5 text-[11px] font-semibold text-cyan-800">
                        Public Form
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-4 align-top">
                    <div className="font-medium text-slate-900">{ticket.title}</div>
                    <div className="mt-1 text-xs text-slate-500">
                      {ticket.branch?.name ?? ticket.division?.name ?? '-'}
                    </div>
                  </td>
                  <td className="px-4 py-4 align-top text-sm text-slate-700">{ticket.category?.name ?? '-'}</td>
                  <td className="px-4 py-4 align-top text-sm text-slate-700">{ticket.application?.name ?? '-'}</td>
                  <td className="px-4 py-4 align-top">
                    <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                      {ticket.final_priority?.name ?? '-'}
                    </span>
                  </td>
                  <td className="px-4 py-4 align-top">
                    <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                      {STATUS_LABELS[ticket.status] ?? ticket.status}
                    </span>
                  </td>
                  <td className="px-4 py-4 align-top">{renderAssignmentRoleBadge(ticket)}</td>
                  <td className="px-4 py-4 align-top">
                    <div
                      className={`inline-flex items-center gap-1.5 text-sm ${isOverdue ? 'font-semibold text-red-600' : 'text-slate-700'}`}
                    >
                      {isOverdue && <AlertTriangle className="h-4 w-4" aria-hidden="true" />}
                      {formatDateTime(ticket.resolution_due_at)}
                    </div>
                  </td>
                  <td className="px-4 py-4 text-right align-top">
                    <Link
                      to={`/pic/tickets/${ticket.id}`}
                      className="inline-flex items-center gap-1 rounded-lg bg-[#1E3A8A] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#1e40af] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30"
                    >
                      Lihat Detail
                      <ArrowRight className="h-3.5 w-3.5" aria-hidden="true" />
                    </Link>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      <div className="space-y-3 p-4 md:hidden">
        {tickets.map((ticket) => {
          const isOverdue =
            ticket.resolution_due_at &&
            new Date(ticket.resolution_due_at) < new Date() &&
            !['done', 'closed', 'rejected', 'cancelled'].includes(ticket.status)

          return (
            <article key={ticket.id} className="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{ticket.ticket_number}</p>
                  <h3 className="mt-1 text-sm font-semibold text-slate-900">{ticket.title}</h3>
                </div>
                {renderAssignmentRoleBadge(ticket)}
              </div>
              <div className="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div>
                  <p className="text-xs font-medium text-slate-500">Kategori</p>
                  <p className="mt-1 text-slate-800">{ticket.category?.name ?? '-'}</p>
                </div>
                <div>
                  <p className="text-xs font-medium text-slate-500">Sistem</p>
                  <p className="mt-1 text-slate-800">{ticket.application?.name ?? '-'}</p>
                </div>
                <div>
                  <p className="text-xs font-medium text-slate-500">Urgency</p>
                  <p className="mt-1 text-slate-800">{ticket.final_priority?.name ?? '-'}</p>
                </div>
                <div>
                  <p className="text-xs font-medium text-slate-500">Status</p>
                  <p className="mt-1 text-slate-800">{STATUS_LABELS[ticket.status] ?? ticket.status}</p>
                </div>
              </div>
              <div className="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div
                  className={`inline-flex items-center gap-2 text-sm ${isOverdue ? 'font-semibold text-red-600' : 'text-slate-600'}`}
                >
                  <Clock className="h-4 w-4" aria-hidden="true" />
                  {formatDateTime(ticket.resolution_due_at)}
                </div>
                <Link
                  to={`/pic/tickets/${ticket.id}`}
                  className="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1E3A8A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1e40af] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30"
                >
                  Lihat Detail
                  <ArrowRight className="h-4 w-4" aria-hidden="true" />
                </Link>
              </div>
            </article>
          )
        })}
      </div>
    </div>
  )
}
