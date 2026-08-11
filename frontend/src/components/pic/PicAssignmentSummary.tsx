import React from 'react'
import type { PicTicketDetailData } from '../../services/ticketService'
import { UserCheck, Users, Calendar, Clock, ShieldAlert } from 'lucide-react'
import { getPriorityColor, getSlaLabel, PRIORITY_LABELS } from '../../presentation'

interface PicAssignmentSummaryProps {
  ticket: PicTicketDetailData
}

function formatDate(value?: string | null) {
  if (!value) return 'Belum tersedia'
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

export const PicAssignmentSummary: React.FC<PicAssignmentSummaryProps> = ({ ticket }) => {
  const primaryPic = ticket.active_primary_pic ?? ticket.assignee
  const secondaryPics = ticket.active_secondary_pics ?? []
  const userRole = ticket.user_assignment_role

  const now = new Date()
  const isOverdue =
    ticket.resolution_due_at &&
    new Date(ticket.resolution_due_at) < now &&
    !['done', 'closed', 'rejected', 'cancelled'].includes(ticket.status)

  const slaRemainingHours = ticket.resolution_due_at
    ? Math.round((new Date(ticket.resolution_due_at).getTime() - now.getTime()) / (1000 * 60 * 60))
    : 0

  return (
    <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-4 shadow-sm">
      <div className="flex items-center justify-between border-b pb-3 border-slate-100">
        <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2">
          <UserCheck className="w-4 h-4 text-[#1E3A8A]" /> Tim Penanganan
        </h3>
        {userRole && (
          <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 uppercase tracking-wide">
            Peran Anda:{' '}
            {userRole === 'primary' ? 'PIC Utama' : userRole === 'secondary' ? 'PIC Pendamping' : 'Supervisor'}
          </span>
        )}
      </div>

      <div className="space-y-3">
        {/* Primary PIC */}
        <div className="p-3 bg-blue-50/50 border border-blue-100 rounded-xl flex items-center gap-2.5">
          <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700 shrink-0">
            <UserCheck className="h-4 w-4" />
          </span>
          <div className="min-w-0">
            <span className="block text-[10px] font-semibold text-blue-600 uppercase tracking-wider">PIC Utama</span>
            <span className="block text-sm font-semibold text-slate-900 truncate">
              {primaryPic?.name ?? 'Belum Ditunjuk'}
            </span>
          </div>
        </div>

        {/* Secondary PICs */}
        <div className="p-3 bg-purple-50/50 border border-purple-100 rounded-xl flex items-start gap-2.5">
          <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 text-purple-700 shrink-0 mt-0.5">
            <Users className="h-4 w-4" />
          </span>
          <div className="min-w-0 flex-1">
            <span className="block text-[10px] font-semibold text-purple-600 uppercase tracking-wider mb-1">
              PIC Pendamping
            </span>
            {secondaryPics.length > 0 ? (
              <div className="flex flex-wrap gap-1">
                {secondaryPics.map((pic) => (
                  <span
                    key={pic.id}
                    className="px-2 py-0.5 text-xs font-semibold bg-purple-100/80 text-purple-800 rounded-md"
                  >
                    {pic.name}
                  </span>
                ))}
              </div>
            ) : (
              <span className="text-xs text-slate-400 italic">Belum ada PIC pendamping.</span>
            )}
          </div>
        </div>
      </div>

      <div className="space-y-2.5 text-xs text-slate-600 pt-2 border-t border-slate-100">
        <div className="flex items-center justify-between">
          <span className="flex items-center gap-1.5 text-slate-400">
            <Calendar className="w-4 h-4 shrink-0" /> Ditugaskan
          </span>
          <span className="font-semibold text-slate-800">{formatDate(ticket.assigned_at)}</span>
        </div>

        <div className="flex items-center justify-between">
          <span className="flex items-center gap-1.5 text-slate-400">
            <Clock className="w-4 h-4 shrink-0" /> Target Penyelesaian
          </span>
          <div className="text-right">
            <span className={`font-semibold block ${isOverdue ? 'text-red-600' : 'text-slate-800'}`}>
              {formatDate(ticket.resolution_due_at)}
            </span>
            {ticket.resolution_due_at && (
              <span
                className={`text-[10px] font-semibold block mt-0.5 ${isOverdue ? 'text-red-500' : 'text-slate-500'}`}
              >
                {getSlaLabel(slaRemainingHours, Boolean(isOverdue))}
              </span>
            )}
          </div>
        </div>

        <div className="flex items-center justify-between">
          <span className="flex items-center gap-1.5 text-slate-400">
            <ShieldAlert className="w-4 h-4 shrink-0" /> Urgency
          </span>
          <span
            className={`px-2 py-0.5 rounded text-[10px] font-bold ${getPriorityColor(ticket.final_priority?.key ?? 'medium')}`}
          >
            {ticket.final_priority?.key ? PRIORITY_LABELS[ticket.final_priority.key]?.toUpperCase() : 'MEDIUM'}
          </span>
        </div>
      </div>
    </div>
  )
}
