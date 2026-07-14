import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { NOTIFICATIONS, TICKETS, formatDateTime } from '../../data'
import { PageHeader, Button, SectionCard } from '../../components/ui'
import type { Role } from '../../types'

const ESCALATIONS = [
  {
    id: 'esc1',
    ticketId: 'IT-2026-000006',
    title: 'ESCALATION LEVEL 2: Notifikasi Email Tidak Terkirim',
    message: 'Tiket IT-2026-000006 telah melewati SLA 43 jam. Eskalasi otomatis ke IT Lead dan Manager. Perlu tindakan segera.',
    severity: 'critical',
    time: '2026-07-13T06:00:00Z',
  },
  {
    id: 'esc2',
    ticketId: 'IT-2026-000001',
    title: 'ESCALATION LEVEL 1: PDF Polis Over SLA',
    message: 'Tiket IT-2026-000001 telah melewati SLA 96 jam. Eskalasi ke Manager. Status UAT sedang berjalan.',
    severity: 'high',
    time: '2026-07-09T08:30:00Z',
  },
  {
    id: 'esc3',
    ticketId: 'IT-2026-000003',
    title: 'ESCALATION LEVEL 1: Dashboard Premi Over SLA',
    message: 'Tiket IT-2026-000003 melewati SLA 24 jam. Dalam proses internal testing.',
    severity: 'high',
    time: '2026-07-12T09:00:00Z',
  },
]

export default function NotificationCenter({ role }: { role: Role }) {
  const navigate = useNavigate()
  const [readIds, setReadIds] = useState<Set<string>>(new Set())
  const [activeTab, setActiveTab] = useState<'all' | 'escalation'>('all')

  const markRead = (id: string) => setReadIds(s => new Set([...s, id]))
  const markAllRead = () => setReadIds(new Set(NOTIFICATIONS.map(n => n.id)))

  const unread = NOTIFICATIONS.filter(n => !n.read && !readIds.has(n.id))

  const typeStyle: Record<string, string> = {
    info: 'bg-blue-50 border-blue-200 text-blue-700',
    warning: 'bg-amber-50 border-amber-200 text-amber-700',
    error: 'bg-red-50 border-red-200 text-red-700',
    success: 'bg-emerald-50 border-emerald-200 text-emerald-700',
  }
  const typeIcon: Record<string, string> = { info: 'ℹ️', warning: '⚠️', error: '🚨', success: '✅' }

  return (
    <div className="max-w-3xl">
      <PageHeader
        title="Notification Center"
        subtitle={`${unread.length} notifikasi belum dibaca`}
        actions={
          <div className="flex gap-2">
            <Button variant="ghost" size="sm" onClick={markAllRead}>Tandai Semua Dibaca</Button>
          </div>
        }
      />

      {/* Tabs */}
      <div className="flex gap-1 p-1 bg-gray-100 rounded-xl w-fit mb-5">
        {(['all', 'escalation'] as const).map(tab => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`px-4 py-1.5 text-sm font-medium rounded-lg transition-all ${activeTab === tab ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
          >
            {tab === 'all' ? `📬 Semua Notifikasi (${NOTIFICATIONS.length})` : `🚨 Eskalasi (${ESCALATIONS.length})`}
          </button>
        ))}
      </div>

      {activeTab === 'all' && (
        <div className="space-y-3">
          {NOTIFICATIONS.map(n => {
            const isRead = n.read || readIds.has(n.id)
            return (
              <div
                key={n.id}
                className={`border rounded-xl p-4 transition-all ${isRead ? 'opacity-60 bg-gray-50 border-gray-200' : `${typeStyle[n.type]} border`}`}
              >
                <div className="flex items-start gap-3">
                  <span className="text-xl shrink-0">{typeIcon[n.type]}</span>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2">
                      <p className="text-sm font-semibold text-gray-900">{n.title}</p>
                      {!isRead && <div className="w-2 h-2 bg-[#1E3A8A] rounded-full shrink-0 mt-1.5" />}
                    </div>
                    <p className="text-xs text-gray-600 mt-0.5 leading-relaxed">{n.message}</p>
                    <div className="flex items-center gap-3 mt-2">
                      <span className="text-xs text-gray-400">{formatDateTime(n.createdAt)}</span>
                      {n.ticketId && (
                        <button onClick={() => navigate(`/user/tickets/${n.ticketId}`)} className="text-xs text-[#1E3A8A] font-medium hover:underline">
                          Lihat Tiket →
                        </button>
                      )}
                      {!isRead && (
                        <button onClick={() => markRead(n.id)} className="text-xs text-gray-400 hover:text-gray-600">
                          Tandai dibaca
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      )}

      {activeTab === 'escalation' && (
        <div className="space-y-4">
          <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex gap-3 mb-2">
            <span className="text-red-500 text-lg">🚨</span>
            <p className="text-sm text-red-800">Eskalasi otomatis terjadi ketika tiket melewati SLA. Sistem mengirim notifikasi ke IT Lead, Manager, dan jika perlu — Direktur.</p>
          </div>
          {ESCALATIONS.map(e => (
            <div key={e.id} className={`border rounded-xl p-4 ${e.severity === 'critical' ? 'bg-red-50 border-red-300' : 'bg-orange-50 border-orange-200'}`}>
              <div className="flex items-start gap-3">
                <span className="text-xl">🚨</span>
                <div className="flex-1">
                  <p className={`text-sm font-bold ${e.severity === 'critical' ? 'text-red-800' : 'text-orange-800'}`}>{e.title}</p>
                  <p className="text-xs text-gray-700 mt-1 leading-relaxed">{e.message}</p>
                  <div className="flex items-center gap-3 mt-2">
                    <span className="text-xs text-gray-400">{formatDateTime(e.time)}</span>
                    <button onClick={() => navigate(`/user/tickets/${e.ticketId}`)} className="text-xs text-[#1E3A8A] font-medium hover:underline">
                      Lihat Tiket {e.ticketId} →
                    </button>
                  </div>
                </div>
                <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${e.severity === 'critical' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700'}`}>
                  {e.severity.toUpperCase()}
                </span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
