import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { EmptyState, KPICard, PageHeader, PriorityBadge, SectionCard, StatusBadge, Toast } from '../../components/ui'
import { ticketService, type TicketRecord } from '../../services/ticketService'

export default function PICDashboard() {
  const navigate = useNavigate()
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  useEffect(() => {
    ticketService
      .picAssignments({ per_page: 10 })
      .then((r) => setTickets(r.data))
      .catch(() => setError('Dashboard PIC tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }, [])
  const overdue = tickets.filter(
    (t) => t.resolution_due_at && new Date(t.resolution_due_at).getTime() < Date.now(),
  ).length
  return (
    <div>
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}
      <PageHeader title="Dashboard PIC" subtitle="Tiket yang aktif ditugaskan kepada Anda" />
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Assignment Aktif" value={tickets.length} color="blue" />
        <KPICard
          title="Critical"
          value={tickets.filter((t) => t.final_priority?.key === 'critical').length}
          color="red"
        />
        <KPICard title="High" value={tickets.filter((t) => t.final_priority?.key === 'high').length} color="amber" />
        <KPICard title="Lewat Deadline" value={overdue} color="red" />
      </div>
      <SectionCard title="Assignment Terbaru">
        {loading ? (
          <p className="py-10 text-center text-sm text-gray-500">Memuat...</p>
        ) : tickets.length === 0 ? (
          <EmptyState title="Belum ada assignment" message="Assignment baru akan tampil otomatis di sini." />
        ) : (
          <div className="space-y-3">
            {tickets.map((t) => (
              <button
                key={t.id}
                onClick={() => navigate('/pic/workspace')}
                className="w-full text-left border rounded-xl p-4 hover:border-blue-400"
              >
                <div className="flex flex-wrap justify-between gap-2">
                  <div>
                    <span className="font-mono text-xs text-gray-500">{t.ticket_number}</span>
                    <p className="font-semibold text-sm">{t.title}</p>
                  </div>
                  <div className="flex gap-2">
                    {t.final_priority && <PriorityBadge priority={t.final_priority.key} />}
                    <StatusBadge status={t.status} />
                  </div>
                </div>
                <p className="text-xs text-gray-500 mt-2">
                  Deadline: {t.resolution_due_at ? new Date(t.resolution_due_at).toLocaleString('id-ID') : '—'}
                </p>
              </button>
            ))}
          </div>
        )}
      </SectionCard>
    </div>
  )
}
