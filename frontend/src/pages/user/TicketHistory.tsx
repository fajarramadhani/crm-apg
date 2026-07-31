import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Button, FilterBar, Input, PageHeader, Select, Table, TD, TR } from '../../components/ui'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import { getPublicStatusLabel } from '../../presentation'

import { useAuth } from '../../context/AuthContext'

const statuses = [
  ['pending_validation', 'Pending Validation'],
  ['need_revision', 'Need Revision'],
  ['validated', 'Validated'],
  ['rejected', 'Rejected'],
  ['cancelled', 'Cancelled'],
]

export default function TicketHistory() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [pages, setPages] = useState(1)
  const [total, setTotal] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setLoading(true)
      setError('')
      ticketService
        .listMyTickets({ search, status, page, per_page: 15 })
        .then((response) => {
          setTickets(response.data)
          setTotal(response.meta.pagination.total)
          setPages(response.meta.pagination.last_page)
        })
        .catch(() => setError('Daftar tiket tidak dapat dimuat.'))
        .finally(() => setLoading(false))
    }, 250)
    return () => window.clearTimeout(timeout)
  }, [search, status, page])

  const isRequester = user?.role?.key === 'requester'

  return (
    <div>
      <PageHeader
        title={isRequester ? 'Riwayat Tiket Saya' : 'Daftar Tiket System'}
        subtitle={`${total} tiket ditemukan`}
        actions={
          isRequester ? (
            <Button variant="primary" onClick={() => navigate('/user/create-ticket')}>
              ＋ Buat Tiket
            </Button>
          ) : null
        }
      />
      <FilterBar>
        <Input
          placeholder="Cari nomor atau judul..."
          value={search}
          onChange={(event) => {
            setSearch(event.target.value)
            setPage(1)
          }}
          className="w-full sm:w-56"
        />
        <Select
          value={status}
          onChange={(event) => {
            setStatus(event.target.value)
            setPage(1)
          }}
          options={[{ value: '', label: 'Semua Status' }, ...statuses.map(([value, label]) => ({ value, label }))]}
          className="w-full sm:w-48"
        />
        {(search || status) && (
          <button
            className="text-xs underline text-gray-500"
            onClick={() => {
              setSearch('')
              setStatus('')
              setPage(1)
            }}
          >
            Reset
          </button>
        )}
      </FilterBar>
      {error && (
        <div className="p-4 mb-4 rounded-xl bg-red-50 text-sm text-red-700" role="alert">
          {error}{' '}
          <button className="underline ml-2" onClick={() => setPage((value) => value)}>
            Coba lagi
          </button>
        </div>
      )}
      <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        {loading ? (
          <div className="py-16 text-center text-sm text-gray-500" role="status">
            Memuat tiket...
          </div>
        ) : tickets.length === 0 ? (
          <div className="py-16 text-center">
            <p className="font-medium text-gray-700">Belum ada tiket yang cocok</p>
            <p className="text-sm text-gray-500 mt-1">Buat tiket baru atau ubah filter pencarian.</p>
          </div>
        ) : (
          <Table
            headers={[
              'Nomor',
              'Judul',
              'Kategori',
              'Sistem',
              'Urgency',
              'Status',
              'Informasi Penanganan',
              'Tanggal Pengajuan',
            ]}
          >
            {tickets.map((ticket) => (
              <TR key={ticket.id} onClick={() => navigate(`/user/tickets/${ticket.id}`)}>
                <TD>
                  <span className="font-mono text-xs text-gray-600">{ticket.ticket_number}</span>
                </TD>
                <TD>
                  <div className="max-w-[280px]">
                    <p className="font-medium text-sm truncate">{ticket.title}</p>
                  </div>
                </TD>
                <TD>
                  <span className="text-xs">{ticket.request_category?.label || 'Kategori belum tersedia'}</span>
                </TD>
                <TD>
                  <span className="text-xs">{ticket.application?.name || 'Sistem belum tersedia'}</span>
                </TD>
                <TD>
                  <span className="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold">
                    {ticket.urgency?.toUpperCase() || 'Urgency belum tersedia'}
                  </span>
                </TD>
                <TD>
                  <span className="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-800">
                    {getPublicStatusLabel(ticket.status)}
                  </span>
                </TD>
                <TD>
                  <p className="min-w-52 text-xs leading-relaxed text-gray-600">{ticket.handling.message}</p>
                </TD>
                <TD>
                  <span className="text-xs whitespace-nowrap text-gray-500">
                    {new Date(ticket.created_at).toLocaleDateString('id-ID')}
                  </span>
                </TD>
              </TR>
            ))}
          </Table>
        )}
      </div>
      {pages > 1 && (
        <div className="flex justify-between items-center mt-4">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>
            ← Sebelumnya
          </Button>
          <span className="text-xs text-gray-500">
            Halaman {page} dari {pages}
          </span>
          <Button variant="secondary" disabled={page >= pages} onClick={() => setPage((value) => value + 1)}>
            Berikutnya →
          </Button>
        </div>
      )}
    </div>
  )
}
