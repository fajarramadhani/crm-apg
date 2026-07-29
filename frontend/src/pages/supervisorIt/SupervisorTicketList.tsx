import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import { masterDataService } from '../../services/masterDataService'
import { SupervisorTicketTable } from '../../components/supervisor/SupervisorTicketTable'
import { SupervisorTicketFilters, type FilterState } from '../../components/supervisor/SupervisorTicketFilters'
import type { EligibleAssignee } from '../../types'

export default function SupervisorTicketList() {
  const [searchParams, setSearchParams] = useSearchParams()

  const [loading, setLoading] = useState<boolean>(true)
  const [error, setError] = useState<string | null>(null)
  const [tickets, setTickets] = useState<TicketRecord[]>([])

  const [currentPage, setCurrentPage] = useState<number>(1)
  const [lastPage, setLastPage] = useState<number>(1)
  const [totalTickets, setTotalTickets] = useState<number>(0)

  // Master data state
  const [divisions, setDivisions] = useState<Array<{ id: number; name: string }>>([])
  const [applications, setApplications] = useState<Array<{ id: number; name: string }>>([])
  const [categories, setCategories] = useState<Array<{ id: number; name: string }>>([])
  const [priorities, setPriorities] = useState<Array<{ id: number; name: string }>>([])
  const [assignees, setAssignees] = useState<EligibleAssignee[]>([])

  const [filters, setFilters] = useState<FilterState>({
    search: searchParams.get('search') || '',
    status: searchParams.get('status') || '',
    division_id: searchParams.get('division_id') || '',
    application_id: searchParams.get('application_id') || '',
    category_id: searchParams.get('category_id') || '',
    priority_id: searchParams.get('priority_id') || '',
    pic_id: searchParams.get('pic_id') || '',
    unassigned: searchParams.get('unassigned') === 'true',
    overdue: searchParams.get('overdue') === 'true',
  })

  useEffect(() => {
    fetchMasterData()
  }, [])

  useEffect(() => {
    fetchTickets()
  }, [filters, currentPage])

  const fetchMasterData = async () => {
    try {
      const [divsRes, appsRes, catsRes, prioRes, assigneesRes] = await Promise.all([
        masterDataService.getDivisions(),
        masterDataService.getApplications(),
        masterDataService.getTicketCategories(),
        masterDataService.getTicketPriorities(),
        ticketService.supervisorItAssignees(),
      ])

      setDivisions(divsRes.map((d: { id: number; name: string }) => ({ id: d.id, name: d.name })))
      setApplications(appsRes.map((a: { id: number; name: string }) => ({ id: a.id, name: a.name })))
      setCategories(catsRes.map((c: { id: number; name: string }) => ({ id: c.id, name: c.name })))
      setPriorities(prioRes.map((p: { id: number; name: string }) => ({ id: p.id, name: p.name })))
      setAssignees(assigneesRes)
    } catch {
      // Non-blocking
    }
  }

  const fetchTickets = async () => {
    setLoading(true)
    setError(null)
    try {
      const params: Record<string, unknown> = {
        page: currentPage,
        per_page: 20,
      }
      if (filters.search) params.search = filters.search
      if (filters.status) params.status = filters.status
      if (filters.division_id) params.division_id = filters.division_id
      if (filters.application_id) params.application_id = filters.application_id
      if (filters.category_id) params.category_id = filters.category_id
      if (filters.priority_id) params.priority_id = filters.priority_id
      if (filters.pic_id) params.pic_id = filters.pic_id
      if (filters.unassigned) params.unassigned = true
      if (filters.overdue) params.overdue = true

      const res = await ticketService.supervisorItTickets(params)
      setTickets(res.data)
      if (res.meta?.pagination) {
        setCurrentPage(res.meta.pagination.current_page)
        setLastPage(res.meta.pagination.last_page)
        setTotalTickets(res.meta.pagination.total)
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gagal memuat daftar tiket Supervisor IT.')
    } finally {
      setLoading(false)
    }
  }

  const handleFilterChange = (newFilters: FilterState) => {
    setFilters(newFilters)
    setCurrentPage(1)

    const newParams = new URLSearchParams()
    Object.entries(newFilters).forEach(([k, v]) => {
      if (v) newParams.set(k, v.toString())
    })
    setSearchParams(newParams)
  }

  const handleFilterReset = () => {
    const emptyFilters: FilterState = {
      search: '',
      status: '',
      division_id: '',
      application_id: '',
      category_id: '',
      priority_id: '',
      pic_id: '',
      unassigned: false,
      overdue: false,
    }
    setFilters(emptyFilters)
    setCurrentPage(1)
    setSearchParams(new URLSearchParams())
  }

  return (
    <div className="space-y-6 pb-12">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Daftar Seluruh Tiket Intake (Supervisor IT)</h1>
        <p className="text-sm text-gray-500 mt-1">
          Menampilkan total {totalTickets} tiket lintas cabang dan divisi tanpa pembatasan.
        </p>
      </div>

      {error && (
        <div className="rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700 border border-red-200">
          {error}
        </div>
      )}

      <SupervisorTicketFilters
        filters={filters}
        onChange={handleFilterChange}
        onReset={handleFilterReset}
        divisions={divisions}
        applications={applications}
        categories={categories}
        priorities={priorities}
        assignees={assignees.map((a) => ({ id: a.id, name: a.name }))}
      />

      <SupervisorTicketTable tickets={tickets} loading={loading} />

      {lastPage > 1 && (
        <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 rounded-xl shadow-sm">
          <div className="text-xs text-gray-500">
            Halaman <span className="font-semibold text-gray-800">{currentPage}</span> dari{' '}
            <span className="font-semibold text-gray-800">{lastPage}</span>
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              disabled={currentPage <= 1 || loading}
              onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
              className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
              ← Sebelumnya
            </button>

            <button
              type="button"
              disabled={currentPage >= lastPage || loading}
              onClick={() => setCurrentPage((p) => Math.min(lastPage, p + 1))}
              className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            >
              Selanjutnya →
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
