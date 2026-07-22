import React, { useState, useEffect } from 'react'
import { NavLink } from 'react-router-dom'
import { getManagerAlerts } from '../../api/alerts'
import { TicketSlaAlert } from '../../types/notifications'

export const ManagerAlerts: React.FC = () => {
  const [alerts, setAlerts] = useState<TicketSlaAlert[]>([])
  const [isLoading, setIsLoading] = useState(true)

  const fetchAlerts = async () => {
    try {
      const res = await getManagerAlerts({ per_page: 20 })
      setAlerts(res.data)
    } catch (e) {
      console.error('Failed to fetch manager alerts', e)
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
        <h1 className="text-2xl font-bold text-gray-900">Critical Business Alerts</h1>
        <p className="text-sm text-gray-500">Peringatan eskalasi tinggi untuk ranah bisnis Anda.</p>
      </div>

      <div className="bg-white shadow rounded-xl border border-gray-200 overflow-hidden">
        {isLoading ? (
          <div className="p-8 text-center text-gray-400">Memuat peringatan...</div>
        ) : alerts.length === 0 ? (
          <div className="p-12 text-center">
            <span className="text-5xl block mb-4">👍</span>
            <p className="text-gray-500 font-medium">Tidak ada peringatan kritikal saat ini.</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-100">
            {alerts.map((alert) => (
              <div key={alert.id} className="p-5 flex items-start gap-4 hover:bg-gray-50 transition-colors">
                <div className="mt-1">
                  {alert.alert_level === 'breached' ? (
                    <span className="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xl">
                      ⚠️
                    </span>
                  ) : (
                    <span className="w-10 h-10 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center text-xl">
                      ⚠️
                    </span>
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="font-bold text-gray-900 text-sm">{alert.ticket.number}</span>
                    <span className="px-2 py-0.5 rounded bg-gray-100 text-gray-600 text-xs font-semibold uppercase">
                      {alert.sla_type} SLA
                    </span>
                    <span
                      className={`px-2 py-0.5 rounded text-xs font-semibold ${alert.alert_level === 'breached' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'}`}
                    >
                      {alert.alert_level === 'breached' ? 'BREACHED' : 'CRITICAL'}
                    </span>
                  </div>
                  <p className="text-sm text-gray-600 mb-2">
                    Tiket untuk aplikasi <strong>{alert.ticket.application_name || 'N/A'}</strong> diajukan oleh{' '}
                    {alert.ticket.requester_name}.
                    {alert.alert_level === 'breached'
                      ? ` Telah melewati batas waktu sebesar ${Math.abs(alert.remaining_minutes)} menit.`
                      : ` Tersisa waktu ${alert.remaining_minutes} menit.`}
                  </p>
                  <p className="text-xs text-gray-400">
                    Dilaporkan pada {new Date(alert.triggered_at).toLocaleString('id-ID')}
                  </p>
                </div>
                <div className="shrink-0">
                  {alert.action_url && (
                    <NavLink
                      to={alert.action_url}
                      className="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                      Lihat Tiket
                    </NavLink>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
