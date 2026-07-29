import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import type { SupervisorDashboardStats } from '../../types'
import { SupervisorTicketTable } from '../../components/supervisor/SupervisorTicketTable'

export default function SupervisorDashboard() {
  const navigate = useNavigate()
  const [loading, setLoading] = useState<boolean>(true)
  const [error, setError] = useState<string | null>(null)

  const [stats, setStats] = useState<SupervisorDashboardStats | null>(null)
  const [actionRequiredTickets, setActionRequiredTickets] = useState<TicketRecord[]>([])
  const [highPriorityTickets, setHighPriorityTickets] = useState<TicketRecord[]>([])
  const [unassignedTickets, setUnassignedTickets] = useState<TicketRecord[]>([])
  const [overdueTickets, setOverdueTickets] = useState<TicketRecord[]>([])
  const [pendingApprovalTickets, setPendingApprovalTickets] = useState<TicketRecord[]>([])

  useEffect(() => {
    fetchDashboardData()
  }, [])

  const fetchDashboardData = async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await ticketService.supervisorItDashboard()
      setStats(res.stats)
      setActionRequiredTickets(res.action_required_tickets || [])
      setHighPriorityTickets(res.high_priority_tickets || [])
      setUnassignedTickets(res.unassigned_tickets || [])
      setOverdueTickets(res.overdue_tickets || [])
      setPendingApprovalTickets(res.pending_approval_tickets || [])
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gagal memuat data dashboard Supervisor IT.')
    } finally {
      setLoading(false)
    }
  }

  const handleStatCardClick = (filterKey: string, filterValue: string) => {
    navigate(`/supervisor-it/tickets?${filterKey}=${filterValue}`)
  }

  return (
    <div className="space-y-6 pb-12">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Unified Supervisor IT Control Center</h1>
          <p className="text-sm text-gray-500 mt-1">Pusat kendali operasional tiket lintas cabang & divisi</p>
        </div>
        <Link
          to="/supervisor-it/tickets"
          className="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-800 transition-colors shadow-sm"
        >
          Lihat Seluruh Tiket →
        </Link>
      </div>

      {error && (
        <div className="rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700 border border-red-200">
          {error}
        </div>
      )}

      {/* 9 Summary Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-9 gap-3">
        <div
          onClick={() => handleStatCardClick('status', 'pending_validation')}
          className="cursor-pointer rounded-xl border border-blue-200 bg-blue-50/70 p-3 text-center transition-all hover:shadow-md hover:border-blue-400"
        >
          <p className="text-[11px] font-semibold uppercase text-blue-700">Tiket Baru</p>
          <p className="mt-1 text-2xl font-black text-blue-900">{stats?.new_tickets ?? 0}</p>
        </div>

        <div
          onClick={() => handleStatCardClick('status', 'under_analysis')}
          className="cursor-pointer rounded-xl border border-purple-200 bg-purple-50/70 p-3 text-center transition-all hover:shadow-md hover:border-purple-400"
        >
          <p className="text-[11px] font-semibold uppercase text-purple-700">Dianalisis</p>
          <p className="mt-1 text-2xl font-black text-purple-900">{stats?.under_analysis ?? 0}</p>
        </div>

        <div
          onClick={() => handleStatCardClick('unassigned', 'true')}
          className="cursor-pointer rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-center transition-all hover:shadow-md hover:border-amber-400"
        >
          <p className="text-[11px] font-semibold uppercase text-amber-700">Tanpa PIC</p>
          <p className="mt-1 text-2xl font-black text-amber-900">{stats?.unassigned ?? 0}</p>
        </div>

        <div
          onClick={() => handleStatCardClick('status', 'in_progress')}
          className="cursor-pointer rounded-xl border border-indigo-200 bg-indigo-50/70 p-3 text-center transition-all hover:shadow-md hover:border-indigo-400"
        >
          <p className="text-[11px] font-semibold uppercase text-indigo-700">Ditangani</p>
          <p className="mt-1 text-2xl font-black text-indigo-900">{stats?.in_progress ?? 0}</p>
        </div>

        <div
          onClick={() => handleStatCardClick('status', 'need_info')}
          className="cursor-pointer rounded-xl border border-cyan-200 bg-cyan-50/70 p-3 text-center transition-all hover:shadow-md hover:border-cyan-400"
        >
          <p className="text-[11px] font-semibold uppercase text-cyan-700">Butuh Info</p>
          <p className="mt-1 text-2xl font-black text-cyan-900">{stats?.waiting_info ?? 0}</p>
        </div>

        <div
          onClick={() => handleStatCardClick('status', 'waiting_external')}
          className="cursor-pointer rounded-xl border border-gray-200 bg-gray-50 p-3 text-center transition-all hover:shadow-md hover:border-gray-400"
        >
          <p className="text-[11px] font-semibold uppercase text-gray-600">Eksternal</p>
          <p className="mt-1 text-2xl font-black text-gray-800">{stats?.waiting_external ?? 0}</p>
        </div>

        <div
          onClick={() => handleStatCardClick('status', 'pending_approval')}
          className="cursor-pointer rounded-xl border border-orange-200 bg-orange-50/70 p-3 text-center transition-all hover:shadow-md hover:border-orange-400"
        >
          <p className="text-[11px] font-semibold uppercase text-orange-700">Exam Akhir</p>
          <p className="mt-1 text-2xl font-black text-orange-900">{stats?.pending_final_review ?? 0}</p>
        </div>

        <div
          onClick={() => handleStatCardClick('overdue', 'true')}
          className="cursor-pointer rounded-xl border border-rose-200 bg-rose-50/70 p-3 text-center transition-all hover:shadow-md hover:border-rose-400"
        >
          <p className="text-[11px] font-semibold uppercase text-rose-700">Overdue</p>
          <p className="mt-1 text-2xl font-black text-rose-900">{stats?.overdue ?? 0}</p>
        </div>

        <div
          onClick={() => handleStatCardClick('status', 'done')}
          className="cursor-pointer rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-center transition-all hover:shadow-md hover:border-emerald-400"
        >
          <p className="text-[11px] font-semibold uppercase text-emerald-700">Selesai Hari Ini</p>
          <p className="mt-1 text-2xl font-black text-emerald-900">{stats?.completed_today ?? 0}</p>
        </div>
      </div>

      {/* Action Bucket Sections */}
      <div className="space-y-6 pt-4">
        <div>
          <h2 className="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
            <span className="h-2.5 w-2.5 rounded-full bg-blue-600" />
            Tiket Terbaru Butuh Tindakan Supervisor
          </h2>
          <SupervisorTicketTable tickets={actionRequiredTickets} loading={loading} />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <h2 className="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
              <span className="h-2.5 w-2.5 rounded-full bg-red-600" />
              Tiket Prioritas Tinggi / Kritis
            </h2>
            <SupervisorTicketTable tickets={highPriorityTickets} loading={loading} />
          </div>

          <div>
            <h2 className="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
              <span className="h-2.5 w-2.5 rounded-full bg-amber-600" />
              Tiket Tanpa PIC Utama
            </h2>
            <SupervisorTicketTable tickets={unassignedTickets} loading={loading} />
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <h2 className="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
              <span className="h-2.5 w-2.5 rounded-full bg-rose-600" />
              Tiket Melewati Target (Overdue)
            </h2>
            <SupervisorTicketTable tickets={overdueTickets} loading={loading} />
          </div>

          <div>
            <h2 className="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
              <span className="h-2.5 w-2.5 rounded-full bg-orange-600" />
              Tiket Menunggu Approval / Pemeriksaan Akhir
            </h2>
            <SupervisorTicketTable tickets={pendingApprovalTickets} loading={loading} />
          </div>
        </div>
      </div>
    </div>
  )
}
