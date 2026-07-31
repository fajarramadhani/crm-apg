import React from 'react'
import type { PicDashboardStats } from '../../types'
import { AlertCircle, AlertTriangle, CheckSquare, Clock, ExternalLink, HelpCircle, Inbox } from 'lucide-react'

interface PicDashboardSummaryProps {
  stats: PicDashboardStats
  activeFilter?: string
  loading?: boolean
  onSelectFilter?: (filterKey: string) => void
}

export const PicDashboardSummary: React.FC<PicDashboardSummaryProps> = ({
  stats,
  activeFilter,
  loading = false,
  onSelectFilter,
}) => {
  const cards = [
    {
      key: 'new_assigned',
      title: 'Baru Ditugaskan',
      count: stats.new_assigned,
      icon: Inbox,
      accent: 'border-l-blue-500 text-blue-600 bg-blue-50',
    },
    {
      key: 'in_progress',
      title: 'Sedang Ditangani',
      count: stats.in_progress,
      icon: Clock,
      accent: 'border-l-indigo-500 text-indigo-600 bg-indigo-50',
    },
    {
      key: 'waiting_info',
      title: 'Menunggu Informasi',
      count: stats.waiting_info,
      icon: HelpCircle,
      accent: 'border-l-amber-500 text-amber-600 bg-amber-50',
    },
    {
      key: 'waiting_external',
      title: 'Menunggu Eksternal',
      count: stats.waiting_external,
      icon: ExternalLink,
      accent: 'border-l-violet-500 text-violet-600 bg-violet-50',
    },
    {
      key: 'need_revision',
      title: 'Perlu Perbaikan',
      count: stats.need_revision,
      icon: AlertTriangle,
      accent: 'border-l-orange-500 text-orange-600 bg-orange-50',
    },
    {
      key: 'nearing_due',
      title: 'Mendekati Target',
      count: stats.nearing_due,
      icon: AlertCircle,
      accent: 'border-l-yellow-600 text-yellow-700 bg-yellow-50',
    },
    {
      key: 'overdue',
      title: 'Melewati Target',
      count: stats.overdue,
      icon: AlertCircle,
      accent: 'border-l-red-500 text-red-600 bg-red-50',
    },
    {
      key: 'pending_supervisor_check',
      title: 'Menunggu Supervisor',
      count: stats.pending_supervisor_check,
      icon: CheckSquare,
      accent: 'border-l-teal-500 text-teal-600 bg-teal-50',
    },
  ]

  if (loading) {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-live="polite" aria-busy="true">
        {cards.map((card) => (
          <div key={card.key} className="h-[118px] animate-pulse rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-4 flex items-center justify-between">
              <div className="h-4 w-28 rounded bg-slate-200" />
              <div className="h-10 w-10 rounded-xl bg-slate-200" />
            </div>
            <div className="h-8 w-16 rounded bg-slate-200" />
            <div className="mt-2 h-4 w-14 rounded bg-slate-100" />
          </div>
        ))}
      </div>
    )
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      {cards.map((card) => {
        const IconComponent = card.icon
        const isSelected = activeFilter === card.key
        const clickable = Boolean(onSelectFilter)

        return (
          <button
            key={card.key}
            type="button"
            onClick={() => onSelectFilter?.(card.key)}
            className={`flex min-h-[118px] flex-col rounded-2xl border border-slate-200 border-l-4 bg-white p-5 text-left shadow-sm transition ${
              card.accent
            } ${
              clickable
                ? 'cursor-pointer hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500/30'
                : 'cursor-default'
            } ${isSelected ? 'ring-2 ring-blue-500/30 border-slate-300' : ''}`}
          >
            <div className="mb-4 flex items-start justify-between gap-3">
              <span className="text-sm font-semibold text-slate-700">{card.title}</span>
              <span className="flex h-10 w-10 items-center justify-center rounded-xl border border-current/10 bg-current/10">
                <IconComponent className="h-5 w-5" aria-hidden="true" />
              </span>
            </div>
            <span className="text-3xl font-bold leading-none text-slate-950">{card.count}</span>
            <span className="mt-2 text-sm text-slate-500">tiket</span>
          </button>
        )
      })}
    </div>
  )
}
