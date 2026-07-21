import { useState, useEffect } from 'react'
import { reportApi } from '../../api/reports'
import type { ReportFilter, ReportSummaryResponse } from '../../types/reports'

interface Props {
  role: 'manager' | 'it-lead' | 'supervisor' | 'executive' | 'pic'
}

export default function AnalyticsDashboard({ role }: Props) {
  const [filters, setFilters] = useState<ReportFilter>({
    date_from: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
    date_to: new Date().toISOString().split('T')[0],
  })

  const [data, setData] = useState<ReportSummaryResponse['data'] | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      let res
      if (role === 'manager') res = await reportApi.getManagerSummary(filters)
      else if (role === 'it-lead') res = await reportApi.getItLeadSummary(filters)
      else if (role === 'supervisor') res = await reportApi.getSupervisorSummary(filters)
      else if (role === 'executive') res = await reportApi.getExecutiveSummary(filters)
      else if (role === 'pic') res = await reportApi.getPicPerformance(filters)

      if (res) setData(res.data)
    } catch (err: any) {
      setError(err.message || 'Gagal memuat laporan')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  const handleExport = (type: string) => {
    if (role === 'manager') reportApi.exportManagerReport(filters, type)
    else if (role === 'executive') reportApi.exportExecutiveReport(filters)
    // Add other roles if needed
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">Analytics Dashboard ({role})</h1>
        <div className="flex space-x-2">
          {role !== 'pic' && role !== 'supervisor' && (
            <button
              onClick={() => handleExport('ticket_volume')}
              className="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm"
            >
              Export CSV
            </button>
          )}
        </div>
      </div>

      <div className="bg-white p-4 shadow rounded-lg flex space-x-4 items-end">
        <div>
          <label className="block text-sm font-medium text-gray-700">Date From</label>
          <input
            type="date"
            value={filters.date_from || ''}
            onChange={(e) => setFilters({ ...filters, date_from: e.target.value })}
            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Date To</label>
          <input
            type="date"
            value={filters.date_to || ''}
            onChange={(e) => setFilters({ ...filters, date_to: e.target.value })}
            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
          />
        </div>
        <button onClick={loadData} className="px-4 py-2 bg-gray-800 text-white rounded hover:bg-gray-900 text-sm">
          Terapkan Filter
        </button>
      </div>

      {error && <div className="text-red-600 bg-red-50 p-4 rounded">{error}</div>}

      {loading ? (
        <div className="text-center py-10">Memuat data analitik...</div>
      ) : data ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {data.volume && (
            <div className="bg-white p-6 rounded-lg shadow">
              <h3 className="text-lg font-medium border-b pb-2 mb-4">Volume Tiket</h3>
              <ul className="space-y-2">
                <li className="flex justify-between">
                  <span>Dibuat:</span> <span className="font-bold">{data.volume.tickets_created}</span>
                </li>
                <li className="flex justify-between">
                  <span>Selesai:</span> <span className="font-bold">{data.volume.tickets_completed}</span>
                </li>
                <li className="flex justify-between">
                  <span>Ditutup:</span> <span className="font-bold">{data.volume.tickets_closed}</span>
                </li>
                <li className="flex justify-between">
                  <span>Dibuka Kembali:</span> <span className="font-bold">{data.volume.tickets_reopened}</span>
                </li>
              </ul>
            </div>
          )}

          {data.sla && (
            <div className="bg-white p-6 rounded-lg shadow">
              <h3 className="text-lg font-medium border-b pb-2 mb-4">SLA Compliance</h3>
              <ul className="space-y-2">
                <li className="flex justify-between">
                  <span>Total Tiket dengan SLA:</span>{' '}
                  <span className="font-bold">{data.sla.total_tickets_with_sla}</span>
                </li>
                <li className="flex justify-between">
                  <span>Kepatuhan SLA:</span>{' '}
                  <span className="font-bold text-green-600">{data.sla.compliance_percentage ?? 0}%</span>
                </li>
                <li className="flex justify-between">
                  <span>Rata-rata Respon:</span>{' '}
                  <span className="font-bold">{data.sla.average_response_time_minutes ?? 0} min</span>
                </li>
                <li className="flex justify-between">
                  <span>Rata-rata Resolusi:</span>{' '}
                  <span className="font-bold">{data.sla.average_resolution_time_minutes ?? 0} min</span>
                </li>
              </ul>
            </div>
          )}

          {data.quality && (
            <div className="bg-white p-6 rounded-lg shadow">
              <h3 className="text-lg font-medium border-b pb-2 mb-4">Kualitas & Rework</h3>
              <ul className="space-y-2">
                <li className="flex justify-between">
                  <span>Defect QA:</span> <span className="font-bold">{data.quality.qa_defect_count}</span>
                </li>
                <li className="flex justify-between">
                  <span>Temuan UAT:</span> <span className="font-bold">{data.quality.uat_finding_count}</span>
                </li>
                <li className="flex justify-between">
                  <span>Insiden Pasca-Rilis:</span>{' '}
                  <span className="font-bold">{data.quality.post_release_incident_count}</span>
                </li>
                <li className="flex justify-between">
                  <span>Rollback Rate:</span>{' '}
                  <span className="font-bold text-red-600">{data.quality.rollback_rate ?? 0}%</span>
                </li>
              </ul>
            </div>
          )}

          {data.deployment && (
            <div className="bg-white p-6 rounded-lg shadow">
              <h3 className="text-lg font-medium border-b pb-2 mb-4">Deployment</h3>
              <ul className="space-y-2">
                <li className="flex justify-between">
                  <span>Total Deployment:</span>{' '}
                  <span className="font-bold">{data.deployment.deployments_started}</span>
                </li>
                <li className="flex justify-between">
                  <span>Sukses:</span>{' '}
                  <span className="font-bold text-green-600">{data.deployment.deployments_succeeded}</span>
                </li>
                <li className="flex justify-between">
                  <span>Gagal:</span>{' '}
                  <span className="font-bold text-red-600">{data.deployment.deployments_failed}</span>
                </li>
                <li className="flex justify-between">
                  <span>Success Rate:</span>{' '}
                  <span className="font-bold text-blue-600">{data.deployment.deployment_success_rate ?? 0}%</span>
                </li>
              </ul>
            </div>
          )}

          {role === 'pic' && data && (
            <div className="bg-white p-6 rounded-lg shadow">
              <h3 className="text-lg font-medium border-b pb-2 mb-4">Performa Pribadi</h3>
              <ul className="space-y-2">
                <li className="flex justify-between">
                  <span>Penugasan Aktif:</span> <span className="font-bold">{(data as any).active_assignments}</span>
                </li>
                <li className="flex justify-between">
                  <span>Total Jam Kerja:</span> <span className="font-bold">{(data as any).total_worklog_hours} h</span>
                </li>
              </ul>
            </div>
          )}
        </div>
      ) : null}
    </div>
  )
}
