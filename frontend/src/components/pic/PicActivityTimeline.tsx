import React from 'react'
import type { PicTicketDetailData } from '../../services/ticketService'
import { getPublicStatusLabel } from '../../presentation'
import { Clock, MessageSquare, Paperclip, HelpCircle, ExternalLink, Users, ArrowRightLeft, Lock } from 'lucide-react'

interface PicActivityTimelineProps {
  ticket: PicTicketDetailData
}

export const PicActivityTimeline: React.FC<PicActivityTimelineProps> = ({ ticket }) => {
  const histories = ticket.history ?? []
  const comments = ticket.comments ?? []
  const attachments = ticket.attachments ?? []

  // Combine into single sorted timeline items
  const timelineItems = [
    ...histories.map((h) => ({
      id: `h-${h.id}`,
      type: 'history' as const,
      timestamp: h.created_at,
      title: h.action ? `Aksi: ${h.action}` : `Status: ${getPublicStatusLabel(h.to_status)}`,
      actor: h.actor?.name ?? 'Sistem',
      comment: h.notes ?? null,
      from_status: h.from_status,
      to_status: h.to_status,
    })),
    ...comments.map((c) => ({
      id: `c-${c.id}`,
      type: 'comment' as const,
      timestamp: c.created_at,
      title: c.type ? `Catatan [Catatan Internal PIC]` : 'Komentar',
      actor: c.user?.name ?? 'User',
      comment: c.comment,
      is_internal: c.is_internal,
    })),
    ...attachments.map((a) => ({
      id: `a-${a.id}`,
      type: 'attachment' as const,
      timestamp: a.created_at,
      title: `Lampiran: ${a.original_name}`,
      actor: a.uploader?.name ?? 'User',
      comment: `Ukuran: ${Math.round(a.size / 1024)} KB`,
      visibility: a.visibility,
    })),
  ].sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime())

  const getIcon = (item: (typeof timelineItems)[0]) => {
    if (item.type === 'attachment') return <Paperclip className="w-3.5 h-3.5 text-purple-500" />
    if (item.type === 'comment') {
      if (item.title.includes('INFO_REQUEST')) return <HelpCircle className="w-3.5 h-3.5 text-amber-500" />
      if (item.title.includes('WAITING_EXTERNAL')) return <ExternalLink className="w-3.5 h-3.5 text-purple-500" />
      if (item.title.includes('ASSISTANCE')) return <Users className="w-3.5 h-3.5 text-blue-500" />
      if (item.title.includes('TRANSFER')) return <ArrowRightLeft className="w-3.5 h-3.5 text-amber-500" />
      return <MessageSquare className="w-3.5 h-3.5 text-blue-500" />
    }
    return <Clock className="w-3.5 h-3.5 text-slate-400" />
  }

  return (
    <div className="bg-white border border-slate-200 rounded-2xl p-5 space-y-4 shadow-sm">
      <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2 border-b pb-3 border-slate-100">
        <Clock className="w-4 h-4 text-[#1E3A8A]" /> Timeline Aktivitas
      </h3>

      {timelineItems.length === 0 ? (
        <div className="text-xs text-slate-400 italic py-4 text-center">
          Belum ada aktivitas tercatat pada tiket ini.
        </div>
      ) : (
        <div className="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
          {timelineItems.map((item) => (
            <div key={item.id} className="relative group">
              <div className="absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-white border border-slate-300 flex items-center justify-center">
                {getIcon(item)}
              </div>

              <div className="space-y-1">
                <div className="flex items-center justify-between text-xs">
                  <span className="font-semibold text-slate-900 flex items-center gap-1.5">
                    {item.title}
                    {'is_internal' in item && item.is_internal && (
                      <span className="inline-flex items-center gap-0.5 text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-700">
                        <Lock className="w-2.5 h-2.5" /> Catatan Internal PIC
                      </span>
                    )}
                  </span>
                  <span className="text-[10px] text-slate-400">
                    {new Date(item.timestamp).toLocaleString('id-ID', {
                      day: '2-digit',
                      month: 'short',
                      year: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </span>
                </div>

                <div className="text-xs text-slate-500">
                  Oleh: <span className="font-medium text-slate-700">{item.actor}</span>
                </div>

                {item.comment && (
                  <p className="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-100 mt-1 whitespace-pre-line leading-relaxed">
                    {item.comment}
                  </p>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
