import React from 'react'
import type { PicTicketDetailData } from '../../services/ticketService'
import { UserCheck, Users, ShieldAlert, Calendar, Clock } from 'lucide-react'

interface PicAssignmentSummaryProps {
  ticket: PicTicketDetailData
}

export const PicAssignmentSummary: React.FC<PicAssignmentSummaryProps> = ({ ticket }) => {
  const primaryPic = ticket.active_primary_pic ?? ticket.assignee
  const secondaryPics = ticket.active_secondary_pics ?? []
  const userRole = ticket.user_assignment_role

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 space-y-4 shadow-sm">
      <div className="flex items-center justify-between border-b pb-3 border-slate-100 dark:border-slate-800">
        <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <UserCheck className="w-4 h-4 text-primary" /> Informasi Penugasan (Assignment)
        </h3>
        {userRole && (
          <span className="text-xs font-semibold px-2.5 py-1 rounded-md bg-primary/10 text-primary uppercase tracking-wider">
            Peran Anda: {userRole}
          </span>
        )}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Primary PIC */}
        <div className="p-3 bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/40 rounded-lg">
          <div className="text-xs text-blue-600 dark:text-blue-400 font-semibold uppercase tracking-wider mb-1 flex items-center gap-1">
            <UserCheck className="w-3.5 h-3.5" /> PIC Utama (Primary)
          </div>
          <div className="text-sm font-bold text-slate-900 dark:text-slate-100">
            {primaryPic?.name ?? 'Belum Ditunjuk'}
          </div>
        </div>

        {/* Secondary PICs */}
        <div className="p-3 bg-purple-50/50 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/40 rounded-lg">
          <div className="text-xs text-purple-600 dark:text-purple-400 font-semibold uppercase tracking-wider mb-1 flex items-center gap-1">
            <Users className="w-3.5 h-3.5" /> PIC Pendamping (Secondary)
          </div>
          {secondaryPics.length > 0 ? (
            <div className="flex flex-wrap gap-1.5 mt-1">
              {secondaryPics.map((pic) => (
                <span
                  key={pic.id}
                  className="px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-200 rounded"
                >
                  {pic.name}
                </span>
              ))}
            </div>
          ) : (
            <div className="text-xs text-slate-400 italic">Tidak ada PIC pendamping</div>
          )}
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-3 gap-3 text-xs text-slate-600 dark:text-slate-400 pt-2">
        <div className="flex items-center gap-1.5">
          <Calendar className="w-3.5 h-3.5 text-slate-400" />
          <span>
            Ditugaskan:{' '}
            <strong>{ticket.assigned_at ? new Date(ticket.assigned_at).toLocaleDateString('id-ID') : '-'}</strong>
          </span>
        </div>
        <div className="flex items-center gap-1.5">
          <Clock className="w-3.5 h-3.5 text-slate-400" />
          <span>
            Target SLA:{' '}
            <strong>
              {ticket.resolution_due_at ? new Date(ticket.resolution_due_at).toLocaleDateString('id-ID') : '-'}
            </strong>
          </span>
        </div>
        <div className="flex items-center gap-1.5">
          <ShieldAlert className="w-3.5 h-3.5 text-slate-400" />
          <span>
            Prioritas:{' '}
            <strong className="uppercase">
              {ticket.final_priority?.name ?? ticket.requested_priority?.name ?? 'Normal'}
            </strong>
          </span>
        </div>
      </div>
    </div>
  )
}
