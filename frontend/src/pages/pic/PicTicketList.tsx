import React, { useEffect, useState } from 'react'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import { masterDataService } from '../../services/masterDataService'
import { PicTicketFilters, type PicFilterValues } from '../../components/pic/PicTicketFilters'
import { PicTicketTable } from '../../components/pic/PicTicketTable'
import { ListFilter, ChevronLeft, ChevronRight, RefreshCw } from 'lucide-react'

export const PicTicketList: React.FC = () => {
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [totalItems, setTotalItems] = useState(0)

  // Master data for filters
  const [applications, setApplications] = useState<Array<{ id: number; name: string }>>([])
  const [categories, setCategories] = useState<Array<{ id: number; name: string }>>([])

  useEffect(() => {
    masterDataService
      .getApplications()
      .then(setApplications)
      .catch(() => {})
    masterDataService
      .getTicketCategories()
      .then(setCategories)
      .catch(() => {})
  }, [])

  const initialFilters: PicFilterValues = {
    keyword: '',
    status: '',
    application_id: '',
    ticket_category_id: '',
    priority: '',
    assignment_role: '',
    near_due: false,
    overdue: false,
    date_from: '',
    date_to: '',
  }

  // Parse initial status from URL query params
  const queryParams = new URLSearchParams(window.location.search)
  const urlStatus = queryParams.get('status') ?? ''

  const [filters, setFilters] = useState<PicFilterValues>({
    ...initialFilters,
    status: urlStatus,
  })

  const loadTickets = async () => {
    setLoading(true)
    try {
      const params: Record<string, unknown> = {
        page,
        per_page: 20,
      }

      if (filters.keyword) params.keyword = filters.keyword
      if (filters.status) params.status = filters.status
      if (filters.application_id) params.application_id = filters.application_id
      if (filters.ticket_category_id) params.ticket_category_id = filters.ticket_category_id
      if (filters.priority) params.priority = filters.priority
      if (filters.assignment_role) params.assignment_role = filters.assignment_role
      if (filters.near_due) params.near_due = true
      if (filters.overdue) params.overdue = true
      if (filters.date_from) params.date_from = filters.date_from
      if (filters.date_to) params.date_to = filters.date_to

      const res = await ticketService.getPicTickets(params)
      setTickets(res.data)

      if (res.meta?.pagination) {
        setPage(res.meta.pagination.current_page)
        setTotalPages(res.meta.pagination.last_page)
        setTotalItems(res.meta.pagination.total)
      }
    } catch (err) {
      console.error('Failed to load PIC tickets:', err)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadTickets()
  }, [page, filters])

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Page Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <ListFilter className="w-7 h-7 text-primary" /> Daftar Tiket Penanganan PIC
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Seluruh tiket aktif yang ditugaskan kepada Anda sebagai PIC Utama atau PIC Pendamping.
          </p>
        </div>

        <button
          onClick={loadTickets}
          disabled={loading}
          className="p-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 border border-slate-200 dark:border-slate-800 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition flex items-center gap-1.5 text-xs font-semibold self-start"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} /> Refresh
        </button>
      </div>

      {/* Filters */}
      <PicTicketFilters
        filters={filters}
        onChange={(newFilters) => {
          setFilters(newFilters)
          setPage(1)
        }}
        onReset={() => {
          setFilters(initialFilters)
          setPage(1)
        }}
        applications={applications}
        categories={categories}
      />

      {/* Ticket Table */}
      <PicTicketTable tickets={tickets} loading={loading} />

      {/* Server-Side Pagination Controls */}
      <div className="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500">
        <div>
          Menampilkan total <strong>{totalItems}</strong> tiket (Halaman {page} dari {totalPages})
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page <= 1 || loading}
            className="p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 transition"
          >
            <ChevronLeft className="w-4 h-4" />
          </button>

          <span className="font-semibold text-slate-800 dark:text-slate-200">
            {page} / {totalPages || 1}
          </span>

          <button
            onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
            disabled={page >= totalPages || loading}
            className="p-2 rounded-lg border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 transition"
          >
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  )
}
