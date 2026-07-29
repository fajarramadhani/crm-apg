import React, { useEffect, useState } from 'react'
import { ticketService, type PicDashboardSummaryData } from '../../services/ticketService'
import { PicDashboardSummary } from '../../components/pic/PicDashboardSummary'
import { PicTicketTable } from '../../components/pic/PicTicketTable'
import { Inbox, AlertTriangle, ArrowRight, RefreshCw, Layers } from 'lucide-react'

export const PicUnifiedDashboard: React.FC = () => {
  const [data, setData] = useState<PicDashboardSummaryData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const loadDashboard = async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await ticketService.getPicDashboard()
      setData(res)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Gagal memuat dashboard PIC.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadDashboard()
  }, [])

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Layers className="w-7 h-7 text-primary" /> Workspace PIC IT
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Pusat penanganan tiket untuk PIC IT Support, PIC IT Develop, dan Supervisor IT.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={loadDashboard}
            disabled={loading}
            className="p-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 border border-slate-200 dark:border-slate-800 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition"
            title="Muat Ulang Data"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          <a
            href="/pic/tickets"
            className="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-primary hover:bg-primary/90 rounded-lg shadow-sm transition"
          >
            Lihat Semua Tiket <ArrowRight className="w-4 h-4" />
          </a>
        </div>
      </div>

      {error && (
        <div className="p-4 bg-red-50 text-red-600 border border-red-200 rounded-xl text-sm flex items-center gap-2">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* Summary Cards */}
      {data && (
        <PicDashboardSummary
          stats={data.summary_cards}
          onSelectFilter={(key) => {
            window.location.href = `/pic/tickets?status=${key}`
          }}
        />
      )}

      {/* Action Required Tickets */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Inbox className="w-5 h-5 text-amber-500" /> Perlu Tindakan PIC Saat Ini
          </h2>
          <a
            href="/pic/tickets"
            className="text-xs font-semibold text-primary hover:underline flex items-center gap-1"
          >
            Lihat Semua Tiket <ArrowRight className="w-3 h-3" />
          </a>
        </div>
        <PicTicketTable tickets={data?.action_required_tickets ?? []} loading={loading} />
      </div>

      {/* High Priority & Revision Sections Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* High Priority Tickets */}
        <div className="space-y-3">
          <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 text-red-500" /> Tiket Prioritas Tinggi
          </h3>
          <PicTicketTable tickets={data?.high_priority ?? []} loading={loading} />
        </div>

        {/* Revision Requested Tickets */}
        <div className="space-y-3">
          <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 text-orange-500" /> Tiket dengan Permintaan Revisi
          </h3>
          <PicTicketTable tickets={data?.revision_requested_tickets ?? []} loading={loading} />
        </div>
      </div>
    </div>
  )
}
