import React from 'react'
import type { PicDashboardStats } from '../../types'
import {
  Inbox,
  Clock,
  HelpCircle,
  ExternalLink,
  AlertTriangle,
  AlertCircle,
  CheckSquare,
} from 'lucide-react'

interface PicDashboardSummaryProps {
  stats: PicDashboardStats
  activeFilter?: string
  onSelectFilter?: (filterKey: string) => void
}

export const PicDashboardSummary: React.FC<PicDashboardSummaryProps> = ({
  stats,
  activeFilter,
  onSelectFilter,
}) => {
  const cards = [
    {
      key: 'new_assigned',
      title: 'Baru Ditugaskan',
      count: stats.new_assigned,
      icon: Inbox,
      color: 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 border-blue-200 dark:border-blue-800',
    },
    {
      key: 'in_progress',
      title: 'Sedang Ditangani',
      count: stats.in_progress,
      icon: Clock,
      color: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800',
    },
    {
      key: 'waiting_info',
      title: 'Menunggu Informasi',
      count: stats.waiting_info,
      icon: HelpCircle,
      color: 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400 border-amber-200 dark:border-amber-800',
    },
    {
      key: 'waiting_external',
      title: 'Menunggu Eksternal',
      count: stats.waiting_external,
      icon: ExternalLink,
      color: 'bg-purple-50 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400 border-purple-200 dark:border-purple-800',
    },
    {
      key: 'need_revision',
      title: 'Perlu Perbaikan',
      count: stats.need_revision,
      icon: AlertTriangle,
      color: 'bg-orange-50 text-orange-600 dark:bg-orange-900/20 dark:text-orange-400 border-orange-200 dark:border-orange-800',
    },
    {
      key: 'nearing_due',
      title: 'Mendekati Target',
      count: stats.nearing_due,
      icon: AlertCircle,
      color: 'bg-yellow-50 text-yellow-600 dark:bg-yellow-900/20 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800',
    },
    {
      key: 'overdue',
      title: 'Melewati Target',
      count: stats.overdue,
      icon: AlertCircle,
      color: 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 border-red-200 dark:border-red-800',
    },
    {
      key: 'pending_supervisor_check',
      title: 'Menunggu Supervisor',
      count: stats.pending_supervisor_check,
      icon: CheckSquare,
      color: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
    },
  ]

  return (
    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
      {cards.map((card) => {
        const IconComponent = card.icon
        const isSelected = activeFilter === card.key

        return (
          <button
            key={card.key}
            onClick={() => onSelectFilter?.(card.key)}
            className={`flex flex-col p-4 rounded-xl border transition-all text-left ${card.color} ${
              isSelected ? 'ring-2 ring-primary border-transparent shadow-md' : 'hover:shadow-sm hover:border-slate-300'
            }`}
          >
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-semibold uppercase tracking-wider opacity-80">
                {card.title}
              </span>
              <IconComponent className="w-5 h-5 opacity-80" />
            </div>
            <div className="text-2xl font-bold">{card.count}</div>
          </button>
        )
      })}
    </div>
  )
}
