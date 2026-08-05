import React from 'react'
import { Search, X } from 'lucide-react'

export interface PicFilterValues {
  keyword: string
  status: string
  application_id: string
  ticket_category_id: string
  priority: string
  assignment_role: string
  near_due: boolean
  overdue: boolean
  date_from: string
  date_to: string
}

interface PicTicketFiltersProps {
  filters: PicFilterValues
  onChange: (filters: PicFilterValues) => void
  onReset: () => void
  applications?: Array<{ id: number; name: string }>
  categories?: Array<{ id: number; name: string }>
}

export const PicTicketFilters: React.FC<PicTicketFiltersProps> = ({
  filters,
  onChange,
  onReset,
  applications = [],
  categories = [],
}) => {
  const handleChange = (key: keyof PicFilterValues, value: any) => {
    onChange({
      ...filters,
      [key]: value,
    })
  }

  return (
    <div className="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl space-y-4 shadow-sm">
      <div className="flex flex-col md:flex-row gap-3">
        {/* Search keyword */}
        <div className="relative flex-1">
          <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
          <input
            aria-label="Cari tiket"
            type="text"
            placeholder="Cari nomor tiket, judul, deskripsi, atau requester..."
            value={filters.keyword}
            onChange={(e) => handleChange('keyword', e.target.value)}
            className="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>

        {/* Peran Assignment Filter */}
        <select
          aria-label="Filter peran PIC"
          value={filters.assignment_role}
          onChange={(e) => handleChange('assignment_role', e.target.value)}
          className="px-3 py-2 text-sm border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary"
        >
          <option value="">Semua Peran PIC</option>
          <option value="primary">PIC Utama</option>
          <option value="secondary">PIC Pendamping</option>
          <option value="supervisor">Supervisor (Acting PIC)</option>
        </select>

        {/* Status Filter */}
        <select
          aria-label="Filter status"
          value={filters.status}
          onChange={(e) => handleChange('status', e.target.value)}
          className="px-3 py-2 text-sm border border-slate-300 dark:border-slate-700 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary"
        >
          <option value="">Semua Status</option>
          <option value="assigned">Ditugaskan</option>
          <option value="in_progress">Sedang Ditangani</option>
          <option value="need_info">Menunggu Informasi</option>
          <option value="waiting_external">Menunggu Eksternal</option>
          <option value="revision">Perlu Perbaikan</option>
          <option value="pending_approval">Menunggu Examination Supervisor</option>
          <option value="done">Selesai</option>
        </select>

        <button
          onClick={onReset}
          className="px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 flex items-center justify-center gap-1 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
        >
          <X className="w-3.5 h-3.5" /> Reset Filter
        </button>
      </div>

      {/* Extended Filters */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
        <div>
          <label className="block text-xs font-medium text-slate-500 mb-1">Sistem / Aplikasi</label>
          <select
            aria-label="Filter sistem atau aplikasi"
            value={filters.application_id}
            onChange={(e) => handleChange('application_id', e.target.value)}
            className="w-full px-2.5 py-1.5 text-xs border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
          >
            <option value="">Semua Sistem</option>
            {applications.map((app) => (
              <option key={app.id} value={app.id}>
                {app.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-xs font-medium text-slate-500 mb-1">Kategori Masalah</label>
          <select
            aria-label="Filter kategori masalah"
            value={filters.ticket_category_id}
            onChange={(e) => handleChange('ticket_category_id', e.target.value)}
            className="w-full px-2.5 py-1.5 text-xs border border-slate-300 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100"
          >
            <option value="">Semua Kategori</option>
            {categories.map((cat) => (
              <option key={cat.id} value={cat.id}>
                {cat.name}
              </option>
            ))}
          </select>
        </div>

        <div className="flex items-center gap-4 pt-4 col-span-2">
          <label className="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-700 dark:text-slate-300">
            <input
              type="checkbox"
              checked={filters.near_due}
              onChange={(e) => handleChange('near_due', e.target.checked)}
              className="rounded text-primary focus:ring-primary h-4 w-4"
            />
            Mendekati Target SLA (&lt; 24 jam)
          </label>
          <label className="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-red-600 dark:text-red-400">
            <input
              type="checkbox"
              checked={filters.overdue}
              onChange={(e) => handleChange('overdue', e.target.checked)}
              className="rounded text-red-600 focus:ring-red-500 h-4 w-4"
            />
            Melewati Target SLA (Overdue)
          </label>
        </div>
      </div>
    </div>
  )
}
