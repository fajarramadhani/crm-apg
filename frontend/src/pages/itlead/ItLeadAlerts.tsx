import React, { useState, useEffect } from 'react'
import { NavLink } from 'react-router-dom'
import { getItLeadAlerts } from '../../api/alerts'
import { TicketSlaAlert } from '../../types/notifications'

export const ItLeadAlerts: React.FC = () => {
  const [alerts, setAlerts] = useState<TicketSlaAlert[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const fetchAlerts = async () => {
    try {
      const res = await getItLeadAlerts({ per_page: 50 })
      setAlerts(res.data)
    } catch (e: any) {
      setError(e.message || 'Failed to load alerts')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    fetchAlerts()
  }, [])

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Operational Alerts</h1>
        <p className="text-sm text-gray-500">Peringatan operasional dan SLA kritis untuk tim IT.</p>
      </div>

      {error && <div className="p-4 bg-red-50 text-red-700 rounded-lg">{error}</div>}

      <div className="bg-white shadow rounded-xl border border-gray-200 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse min-w-[800px]">
            <thead>
              <tr className="bg-gray-50 border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold">
                <th className="py-3 px-4">Level</th>
                <th className="py-3 px-4">Tiket</th>
                <th className="py-3 px-4">Tipe SLA</th>
                <th className="py-3 px-4">Remaining / Breached</th>
                <th className="py-3 px-4">PIC</th>
                <th className="py-3 px-4">Waktu Alert</th>
                <th className="py-3 px-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 text-sm">
              {isLoading ? (
                <tr>
                  <td colSpan={7} className="py-8 text-center text-gray-400">
                    Memuat data...
                  </td>
                </tr>
              ) : alerts.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-8 text-center text-gray-400">
                    Tidak ada alert saat ini
                  </td>
                </tr>
              ) : (
                alerts.map((alert) => (
                  <tr key={alert.id} className="hover:bg-gray-50/50 transition-colors">
                    <td className="py-3 px-4">
                      {alert.alert_level === 'critical' && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                          Critical
                        </span>
                      )}
                      {alert.alert_level === 'breached' && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-600 text-white">
                          Breached
                        </span>
                      )}
                      {alert.alert_level === 'approaching' && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                          Warning
                        </span>
                      )}
                    </td>
                    <td className="py-3 px-4 font-medium text-gray-900">{alert.ticket.number}</td>
                    <td className="py-3 px-4 uppercase text-xs font-semibold text-gray-600">{alert.sla_type}</td>
                    <td className="py-3 px-4">
                      <span
                        className={alert.remaining_minutes < 0 ? 'text-red-600 font-bold' : 'text-gray-900 font-medium'}
                      >
                        {alert.remaining_minutes < 0
                          ? `Lebat ${Math.abs(alert.remaining_minutes)} menit`
                          : `Sisa ${alert.remaining_minutes} menit`}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      {alert.ticket.pic_name || <span className="text-gray-400 italic">Belum assign</span>}
                    </td>
                    <td className="py-3 px-4 text-gray-500 whitespace-nowrap">
                      {new Date(alert.triggered_at).toLocaleString('id-ID')}
                    </td>
                    <td className="py-3 px-4 text-right">
                      {alert.action_url && (
                        <NavLink
                          to={alert.action_url}
                          className="text-blue-600 hover:text-blue-800 font-medium text-xs bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors"
                        >
                          Lihat Tiket
                        </NavLink>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
