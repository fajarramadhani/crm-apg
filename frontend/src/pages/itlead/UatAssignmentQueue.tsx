import { useEffect, useState } from 'react'
import {
  EmptyState,
  FilterBar,
  PageHeader,
  PriorityBadge,
  SectionCard,
  Select,
  StatusBadge,
  Toast,
} from '../../components/ui'
import { ApiRequestError } from '../../api/client'
import { ticketService, type TicketRecord } from '../../services/ticketService'

export default function UatAssignmentQueue() {
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [selected, setSelected] = useState<TicketRecord | null>(null)
  const [notes, setNotes] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [toast, setToast] = useState('')

  const load = () => {
    setLoading(true)
    ticketService
      .uatQueue({ search, status, per_page: 20 })
      .then((response) => setTickets(response.data))
      .catch((cause) => setError((cause as ApiRequestError).message))
      .finally(() => setLoading(false))
  }

  useEffect(load, [search, status])

  const assign = () => {
    if (!selected || selected.requester.id === null) return
    setBusy(true)
    ticketService
      .assignUat(selected.id, { requester_user_id: selected.requester.id, notes: notes || undefined })
      .then(() => {
        setToast(`${selected.ticket_number} berhasil ditugaskan untuk UAT requester.`)
        setSelected(null)
        setNotes('')
        load()
      })
      .catch((cause) => setError((cause as ApiRequestError).message))
      .finally(() => setBusy(false))
  }

  return (
    <div>
      {toast && <Toast type="success" message={toast} onClose={() => setToast('')} />}
      {error && <Toast type="error" message={error} onClose={() => setError('')} />}
      <PageHeader title="UAT Assignment Queue" subtitle="Tugaskan requester sebagai penguji UAT setelah QA lulus" />
      <FilterBar>
        <input
          aria-label="Cari tiket UAT"
          className="min-w-56 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"
          placeholder="Cari nomor atau judul..."
          value={search}
          onChange={(event) => setSearch(event.target.value)}
        />
        <Select
          value={status}
          onChange={(event) => setStatus(event.target.value)}
          options={[
            { value: '', label: 'Semua status' },
            { value: 'ready_for_uat', label: 'Ready for UAT' },
            { value: 'uat_assignment', label: 'UAT Assignment' },
          ]}
        />
      </FilterBar>

      {loading ? (
        <p className="py-16 text-center text-sm text-gray-500">Memuat UAT assignment queue...</p>
      ) : tickets.length === 0 ? (
        <EmptyState title="Queue kosong" message="Belum ada tiket yang menunggu penugasan UAT." />
      ) : (
        <div className="overflow-x-auto rounded-xl border bg-white">
          <table className="min-w-[900px] w-full text-sm">
            <thead className="bg-gray-50 text-left text-xs text-gray-500">
              <tr>
                {['Ticket', 'Requester', 'Priority', 'Status', 'Aplikasi', 'Aksi'].map((heading) => (
                  <th key={heading} className="px-4 py-3">
                    {heading}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {tickets.map((ticket) => (
                <tr key={ticket.id} className="border-t hover:bg-blue-50">
                  <td className="px-4 py-3">
                    <b className="font-mono text-xs">{ticket.ticket_number}</b>
                    <p className="max-w-xs truncate">{ticket.title}</p>
                  </td>
                  <td className="px-4 py-3">{ticket.requester.name}</td>
                  <td className="px-4 py-3">
                    {ticket.final_priority && <PriorityBadge priority={ticket.final_priority.key} />}
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge status={ticket.status} />
                  </td>
                  <td className="px-4 py-3 text-gray-600">{ticket.application?.name || '—'}</td>
                  <td className="px-4 py-3">
                    <button
                      className="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
                      disabled={ticket.status === 'uat_assignment' || ticket.requester.id === null}
                      onClick={() => setSelected(ticket)}
                    >
                      {ticket.status === 'uat_assignment'
                        ? 'Sudah Ditugaskan'
                        : ticket.requester.id === null
                          ? 'Requester tanpa akun'
                          : 'Tugaskan UAT'}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {selected && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4"
          onClick={() => setSelected(null)}
        >
          <div
            className="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl"
            onClick={(event) => event.stopPropagation()}
          >
            <SectionCard title="Konfirmasi Penugasan UAT">
              <div className="space-y-4">
                <div className="rounded-lg bg-gray-50 p-3 text-sm">
                  <p className="font-mono font-semibold">{selected.ticket_number}</p>
                  <p className="mt-1 font-medium">{selected.title}</p>
                  <p className="mt-2 text-gray-500">Penguji: {selected.requester.name}</p>
                </div>
                <div>
                  <label className="mb-1 block text-xs font-semibold text-gray-700" htmlFor="uat-notes">
                    Catatan UAT (opsional)
                  </label>
                  <textarea
                    id="uat-notes"
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    rows={3}
                    value={notes}
                    onChange={(event) => setNotes(event.target.value)}
                    placeholder="Tambahkan arahan pengujian untuk requester..."
                  />
                </div>
                <div className="flex justify-end gap-2">
                  <button className="rounded-lg border px-4 py-2 text-sm" onClick={() => setSelected(null)}>
                    Batal
                  </button>
                  <button
                    className="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    disabled={busy}
                    onClick={assign}
                  >
                    {busy ? 'Menyimpan...' : 'Konfirmasi Penugasan'}
                  </button>
                </div>
              </div>
            </SectionCard>
          </div>
        </div>
      )}
    </div>
  )
}
