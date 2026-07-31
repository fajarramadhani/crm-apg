import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { ticketService, type PicDashboardSummaryData, type TicketRecord } from '../../services/ticketService'
import { PicDashboardSummary } from '../../components/pic/PicDashboardSummary'
import { PicTicketTable } from '../../components/pic/PicTicketTable'
import { ArrowRight, BadgeCheck, CalendarClock, Inbox, RefreshCw, TriangleAlert } from 'lucide-react'

function DashboardSection({
  title,
  description,
  icon,
  children,
  action,
}: {
  title: string
  description: string
  icon: React.ReactNode
  children: React.ReactNode
  action?: React.ReactNode
}) {
  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700">{icon}</span>
            <div>
              <h2 className="text-base font-semibold text-slate-900">{title}</h2>
              <p className="mt-0.5 text-sm text-slate-500">{description}</p>
            </div>
          </div>
        </div>
        {action}
      </div>
      {children}
    </section>
  )
}

function DashboardPanelEmptyState({ title, message, linkLabel }: { title: string; message: string; linkLabel: string }) {
  return (
    <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center">
      <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-500 shadow-sm">
        <Inbox className="h-6 w-6" aria-hidden="true" />
      </div>
      <p className="text-sm font-semibold text-slate-900">{title}</p>
      <p className="mx-auto mt-1 max-w-md text-sm text-slate-500">{message}</p>
      <Link
        to="/pic/tickets"
        className="mt-4 inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
      >
        {linkLabel}
        <ArrowRight className="h-4 w-4" aria-hidden="true" />
      </Link>
    </div>
  )
}

function TicketTableOrEmpty({
  tickets,
  loading,
  currentUserId,
  emptyTitle,
  emptyMessage,
}: {
  tickets: TicketRecord[]
  loading: boolean
  currentUserId: number
  emptyTitle: string
  emptyMessage: string
}) {
  if (!loading && tickets.length === 0) {
    return <DashboardPanelEmptyState title={emptyTitle} message={emptyMessage} linkLabel="Lihat Semua Tiket" />
  }

  return <PicTicketTable tickets={tickets} loading={loading} currentUserId={currentUserId} />
}

export const PicUnifiedDashboard: React.FC = () => {
  const { user } = useAuth()
  const [data, setData] = useState<PicDashboardSummaryData | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const loadDashboard = async (mode: 'initial' | 'refresh' = 'initial') => {
    if (mode === 'refresh') {
      setRefreshing(true)
    } else {
      setLoading(true)
    }

    setError(null)

    try {
      const res = await ticketService.getPicDashboard()
      setData(res)
    } catch {
      setError('Dashboard tidak dapat dimuat.')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }

  useEffect(() => {
    void loadDashboard()
  }, [])

  const latestAssigned = data?.latest_assigned ?? []
  const attentionTickets = data?.revision_requested_tickets ?? []
  const nearingDueTickets = data?.nearing_due_tickets ?? []
  const isSupervisorActingAsPic = user?.role.key === 'supervisor_it'

  return (
    <div className="space-y-6">
      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div className="min-w-0 space-y-3">
            <div className="space-y-2">
              <h1 className="text-2xl font-bold tracking-tight text-slate-950">PIC Workspace</h1>
              <p className="text-sm text-slate-600">Kelola tiket yang sedang Anda tangani sebagai PIC.</p>
            </div>
            {isSupervisorActingAsPic && (
              <div className="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <BadgeCheck className="h-3.5 w-3.5" aria-hidden="true" />
                Supervisor sebagai PIC
              </div>
            )}
          </div>

          <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <button
              type="button"
              onClick={() => void loadDashboard('refresh')}
              disabled={refreshing}
              aria-label="Refresh dashboard PIC"
              title="Refresh data dashboard"
              className="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} aria-hidden="true" />
              Refresh
            </button>
            <Link
              to="/pic/tickets"
              className="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1E3A8A] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1e40af] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30"
            >
              Lihat Semua Tiket
              <ArrowRight className="h-4 w-4" aria-hidden="true" />
            </Link>
          </div>
        </div>
      </section>

      {error && (
        <section className="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm sm:p-6" aria-live="polite">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-red-600 shadow-sm">
                <TriangleAlert className="h-5 w-5" aria-hidden="true" />
              </div>
              <div>
                <h2 className="text-sm font-semibold text-red-800">Dashboard tidak dapat dimuat.</h2>
                <p className="mt-1 text-sm text-red-700">Silakan coba kembali beberapa saat lagi.</p>
              </div>
            </div>
            <button
              type="button"
              onClick={() => void loadDashboard('refresh')}
              disabled={refreshing}
              className="inline-flex items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500/30 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} aria-hidden="true" />
              Coba Lagi
            </button>
          </div>
        </section>
      )}

      {data && (
        <PicDashboardSummary
          stats={data.summary_cards}
          loading={loading}
          onSelectFilter={(key) => {
            window.location.href = `/pic/tickets?status=${key}`
          }}
        />
      )}

      <DashboardSection
        title="Tiket Baru Ditugaskan"
        description="Tiket terbaru yang ditugaskan kepada Anda sebagai PIC utama atau PIC pendamping."
        icon={<Inbox className="h-5 w-5" aria-hidden="true" />}
        action={
          <Link
            to="/pic/tickets"
            className="inline-flex items-center gap-2 text-sm font-semibold text-[#1E3A8A] transition hover:text-[#1e40af] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30"
          >
            Lihat Semua Tiket
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        }
      >
        <TicketTableOrEmpty
          tickets={latestAssigned}
          loading={loading}
          currentUserId={user?.id ?? 0}
          emptyTitle="Belum ada tiket yang ditugaskan."
          emptyMessage="Tiket yang Anda tangani sendiri atau yang ditugaskan kepada Anda akan muncul di sini."
        />
      </DashboardSection>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <DashboardSection
          title="Tiket Memerlukan Perhatian"
          description="Sorotan tiket yang perlu ditindaklanjuti seperti revisi, kebutuhan informasi, atau kondisi khusus lainnya."
          icon={<TriangleAlert className="h-5 w-5" aria-hidden="true" />}
          action={
            <Link
              to="/pic/tickets"
              className="inline-flex items-center gap-2 text-sm font-semibold text-[#1E3A8A] transition hover:text-[#1e40af] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30"
            >
              Lihat Semua
              <ArrowRight className="h-4 w-4" aria-hidden="true" />
            </Link>
          }
        >
          <TicketTableOrEmpty
            tickets={attentionTickets}
            loading={loading}
            currentUserId={user?.id ?? 0}
            emptyTitle="Belum ada tiket yang memerlukan perhatian khusus."
            emptyMessage="Tiket revisi atau kondisi penting lainnya akan ditampilkan di bagian ini."
          />
        </DashboardSection>

        <DashboardSection
          title="Target Penyelesaian Terdekat"
          description="Tiket yang paling dekat dengan target penyelesaian agar prioritas kerja tetap terjaga."
          icon={<CalendarClock className="h-5 w-5" aria-hidden="true" />}
          action={
            <Link
              to="/pic/tickets?near_due=true"
              className="inline-flex items-center gap-2 text-sm font-semibold text-[#1E3A8A] transition hover:text-[#1e40af] focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30"
            >
              Lihat Semua
              <ArrowRight className="h-4 w-4" aria-hidden="true" />
            </Link>
          }
        >
          <TicketTableOrEmpty
            tickets={nearingDueTickets}
            loading={loading}
            currentUserId={user?.id ?? 0}
            emptyTitle="Belum ada target penyelesaian yang mendesak."
            emptyMessage="Tiket yang mendekati target penyelesaian akan muncul di bagian ini."
          />
        </DashboardSection>
      </div>
    </div>
  )
}
