import React from 'react'
import type { AuditTimelineItem } from '../../types'

interface TicketAuditTimelineProps {
  items: AuditTimelineItem[]
}

export const TicketAuditTimeline: React.FC<TicketAuditTimelineProps> = ({ items = [] }) => {
  if (!items || items.length === 0) {
    return (
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm text-center text-sm text-gray-500">
        Belum ada riwayat audit trail recorded.
      </div>
    )
  }

  const getIcon = (type: string) => {
    switch (type) {
      case 'status_change':
        return '🔄'
      case 'assignment':
        return '👤'
      case 'comment':
        return '💬'
      default:
        return '📌'
    }
  }

  return (
    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
      <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
        <span className="h-2.5 w-2.5 rounded-full bg-gray-600" />
        Riwayat Tiket & Assignment (Audit Trail Terpadu)
      </h2>

      <div className="relative border-l-2 border-gray-200 ml-3 space-y-6 pt-2">
        {items.map((item) => (
          <div key={item.id} className="relative pl-6">
            <span className="absolute -left-[9px] top-1 flex h-4 w-4 items-center justify-center rounded-full bg-white text-xs border border-gray-300 shadow-xs">
              {getIcon(item.type)}
            </span>

            <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
              <div className="flex items-center gap-2 font-semibold text-gray-800">
                <span>{item.actor?.name || 'Sistem'}</span>
                {item.actor_role && (
                  <span className="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] uppercase font-bold text-gray-600">
                    {item.actor_role}
                  </span>
                )}
                {item.is_internal && (
                  <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">
                    Internal Note
                  </span>
                )}
              </div>

              <span className="text-gray-400 font-mono text-[11px]">
                {new Date(item.timestamp).toLocaleString('id-ID')}
              </span>
            </div>

            <div className="mt-1.5 text-xs text-gray-700 space-y-1">
              {item.type === 'status_change' && (
                <p>
                  Perubahan status dari <span className="font-semibold">{item.from_status || 'Draft'}</span> menjadi{' '}
                  <span className="font-semibold text-blue-700">{item.to_status}</span>
                </p>
              )}

              {item.type === 'assignment' && (
                <p>
                  Assignment {item.assignment_type || 'PIC'}: {item.action} — Pengalihan ke{' '}
                  <span className="font-bold text-indigo-700">{item.to_user?.name || '-'}</span>
                </p>
              )}

              {item.comment && (
                <p className="bg-gray-50/80 p-2.5 rounded-lg border border-gray-100 italic text-gray-800 whitespace-pre-wrap">
                  "{item.comment}"
                </p>
              )}

              {item.notes && !item.comment && (
                <p className="text-gray-600 bg-gray-50/50 p-2 rounded border border-gray-100">{item.notes}</p>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
