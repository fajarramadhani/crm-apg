import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { STATUS_LABELS, PRIORITY_LABELS, formatDate } from '../../data'
import type { Ticket } from '../../types'
import { ticketService } from '../../services/ticketService'
import {
  PageHeader,
  Button,
  StatusBadge,
  PriorityBadge,
  SLAIndicator,
  Table,
  TR,
  TD,
  FilterBar,
  Select,
  Input,
} from '../../components/ui'

export default function TicketHistory() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [priorityFilter, setPriorityFilter] = useState('')
  const [myTickets, setMyTickets] = useState<Ticket[]>([])

  useEffect(() => {
    void ticketService.listMyTickets().then(setMyTickets)
  }, [])

  const filtered = myTickets.filter((t) => {
    const matchSearch =
      search === '' ||
      t.title.toLowerCase().includes(search.toLowerCase()) ||
      t.id.toLowerCase().includes(search.toLowerCase())
    const matchStatus = statusFilter === '' || t.status === statusFilter
    const matchPriority = priorityFilter === '' || t.priority === priorityFilter
    return matchSearch && matchStatus && matchPriority
  })

  const overSlaCount = myTickets.filter((t) => t.overSla).length

  return (
    <div>
      <PageHeader
        title="Riwayat Tiket Saya"
        subtitle={`${myTickets.length} tiket ditemukan`}
        actions={
          <Button variant="primary" onClick={() => navigate('/user/create-ticket')}>
            ➕ Buat Tiket
          </Button>
        }
      />

      {overSlaCount > 0 && (
        <div className="flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4">
          <span className="text-red-500 text-xl">🚨</span>
          <p className="text-sm font-semibold text-red-700">
            {overSlaCount} tiket Anda melewati batas SLA dan memerlukan perhatian
          </p>
        </div>
      )}

      <FilterBar>
        <Input
          placeholder="🔍 Cari tiket..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-48"
        />
        <Select
          options={[
            { value: '', label: 'Semua Status' },
            ...Object.entries(STATUS_LABELS).map(([v, l]) => ({ value: v, label: l })),
          ]}
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="w-44"
        />
        <Select
          options={[
            { value: '', label: 'Semua Prioritas' },
            ...Object.entries(PRIORITY_LABELS).map(([v, l]) => ({ value: v, label: l })),
          ]}
          value={priorityFilter}
          onChange={(e) => setPriorityFilter(e.target.value)}
          className="w-36"
        />
        {(search || statusFilter || priorityFilter) && (
          <button
            onClick={() => {
              setSearch('')
              setStatusFilter('')
              setPriorityFilter('')
            }}
            className="text-xs text-gray-500 hover:text-gray-700 underline"
          >
            Reset
          </button>
        )}
        <span className="ml-auto text-xs text-gray-400">{filtered.length} tiket</span>
      </FilterBar>

      <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <Table headers={['ID Tiket', 'Judul', 'Kategori', 'Prioritas', 'Status', 'SLA', 'Dibuat', 'Diupdate']}>
          {filtered.map((ticket) => (
            <TR key={ticket.id} onClick={() => navigate(`/user/tickets/${ticket.id}`)} highlight={ticket.overSla}>
              <TD>
                <span className="font-mono text-xs text-gray-600">{ticket.id}</span>
                {ticket.overSla && <span className="ml-1 text-red-500">🚨</span>}
              </TD>
              <TD>
                <div className="max-w-[240px]">
                  <p className="font-medium text-gray-900 truncate text-sm">{ticket.title}</p>
                  <p className="text-xs text-gray-400 truncate">{ticket.application}</p>
                </div>
              </TD>
              <TD>
                <span className="text-xs capitalize text-gray-600">{ticket.category}</span>
              </TD>
              <TD>
                <PriorityBadge priority={ticket.priority} />
              </TD>
              <TD>
                <StatusBadge status={ticket.status} />
              </TD>
              <TD>
                <div className="w-28">
                  <SLAIndicator
                    slaRemaining={ticket.slaRemaining}
                    overSla={ticket.overSla}
                    slaHours={ticket.slaHours}
                  />
                </div>
              </TD>
              <TD>
                <span className="text-xs text-gray-500 whitespace-nowrap">{formatDate(ticket.createdAt)}</span>
              </TD>
              <TD>
                <span className="text-xs text-gray-500 whitespace-nowrap">{formatDate(ticket.updatedAt)}</span>
              </TD>
            </TR>
          ))}
        </Table>
        {filtered.length === 0 && (
          <div className="text-center py-12 text-gray-500 text-sm">Tidak ada tiket yang sesuai filter</div>
        )}
      </div>
    </div>
  )
}
