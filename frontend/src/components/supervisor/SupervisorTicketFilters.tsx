import React from 'react'

export interface FilterState {
  search: string
  status: string
  division_id: string
  application_id: string
  category_id: string
  priority_id: string
  pic_id: string
  unassigned: boolean
  overdue: boolean
}

interface SupervisorTicketFiltersProps {
  filters: FilterState
  onChange: (newFilters: FilterState) => void
  onReset: () => void
  divisions?: Array<{ id: number; name: string }>
  applications?: Array<{ id: number; name: string }>
  categories?: Array<{ id: number; name: string }>
  priorities?: Array<{ id: number; name: string }>
  assignees?: Array<{ id: number; name: string }>
}

export const SupervisorTicketFilters: React.FC<SupervisorTicketFiltersProps> = ({
  filters,
  onChange,
  onReset,
  divisions = [],
  applications = [],
  categories = [],
  priorities = [],
  assignees = [],
}) => {
  const handleChange = (key: keyof FilterState, value: string | boolean) => {
    onChange({ ...filters, [key]: value })
  }

  return (
    <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm mb-6 space-y-4">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="relative flex-1">
          <input
            aria-label="Cari tiket"
            type="text"
            placeholder="Cari nomor tiket, judul, deskripsi, atau requester..."
            value={filters.search}
            onChange={(e) => handleChange('search', e.target.value)}
            className="w-full rounded-lg border border-gray-300 pl-10 pr-4 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          />
          <svg
            className="absolute left-3 top-2.5 h-4 w-4 text-gray-400"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
            />
          </svg>
        </div>

        <div className="flex items-center gap-4 text-sm font-medium">
          <label className="inline-flex items-center gap-2 cursor-pointer select-none">
            <input
              type="checkbox"
              checked={filters.unassigned}
              onChange={(e) => handleChange('unassigned', e.target.checked)}
              className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span className="text-gray-700">Belum Punya PIC</span>
          </label>

          <label className="inline-flex items-center gap-2 cursor-pointer select-none">
            <input
              type="checkbox"
              checked={filters.overdue}
              onChange={(e) => handleChange('overdue', e.target.checked)}
              className="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500"
            />
            <span className="text-red-700">Lewat Target (Overdue)</span>
          </label>

          <button
            type="button"
            onClick={onReset}
            className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
          >
            Reset Filter
          </button>
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 pt-2 border-t border-gray-100">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select
            aria-label="Filter status"
            value={filters.status}
            onChange={(e) => handleChange('status', e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          >
            <option value="">Semua Status</option>
            <option value="pending_validation">Diajukan (Baru)</option>
            <option value="under_analysis">Sedang Dianalisis</option>
            <option value="assigned">Sudah Diassign</option>
            <option value="in_progress">Sedang Ditangani</option>
            <option value="need_info">Memerlukan Informasi</option>
            <option value="need_revision">Memerlukan Revisi</option>
            <option value="pending_approval">Dalam Pemeriksaan Akhir</option>
            <option value="done">Selesai</option>
            <option value="rejected">Ditolak</option>
            <option value="cancelled">Dibatalkan</option>
            <option value="reopened">Dibuka Kembali</option>
          </select>
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Divisi</label>
          <select
            aria-label="Filter divisi"
            value={filters.division_id}
            onChange={(e) => handleChange('division_id', e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          >
            <option value="">Semua Divisi</option>
            {divisions.map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Sistem / Aplikasi</label>
          <select
            aria-label="Filter sistem atau aplikasi"
            value={filters.application_id}
            onChange={(e) => handleChange('application_id', e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          >
            <option value="">Semua Sistem</option>
            {applications.map((a) => (
              <option key={a.id} value={a.id}>
                {a.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Kategori</label>
          <select
            aria-label="Filter kategori"
            value={filters.category_id}
            onChange={(e) => handleChange('category_id', e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          >
            <option value="">Semua Kategori</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Prioritas</label>
          <select
            aria-label="Filter prioritas"
            value={filters.priority_id}
            onChange={(e) => handleChange('priority_id', e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          >
            <option value="">Semua Prioritas</option>
            {priorities.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">PIC Utama</label>
          <select
            aria-label="Filter PIC utama"
            value={filters.pic_id}
            onChange={(e) => handleChange('pic_id', e.target.value)}
            className="w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
          >
            <option value="">Semua PIC</option>
            {assignees.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name}
              </option>
            ))}
          </select>
        </div>
      </div>
    </div>
  )
}
