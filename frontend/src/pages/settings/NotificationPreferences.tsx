import React, { useState, useEffect } from 'react'
import { getNotificationPreferences, updateNotificationPreference } from '../../api/notifications'
import { NotificationPreference } from '../../types/notifications'

export const NotificationPreferences: React.FC = () => {
  const [preferences, setPreferences] = useState<NotificationPreference[]>([])
  const [isLoading, setIsLoading] = useState(true)

  const configurableTypes = [
    { type: 'ticket_submitted', label: 'Tiket Baru Dibuat' },
    { type: 'pic_assigned', label: 'Penugasan Tiket' },
    { type: 'approval_required', label: 'Permintaan Approval' },
    { type: 'qa_assignment_required', label: 'Penugasan QA' },
    { type: 'uat_assignment_required', label: 'Penugasan UAT' },
  ]

  const fetchPreferences = async () => {
    try {
      const data = await getNotificationPreferences()
      setPreferences(data)
    } catch (e) {
      console.error(e)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    fetchPreferences()
  }, [])

  const handleToggle = async (type: string, currentValue: boolean) => {
    try {
      // Optimistic update
      setPreferences((prev) => {
        const exists = prev.find((p) => p.notification_type === type)
        if (exists) {
          return prev.map((p) => (p.notification_type === type ? { ...p, in_app_enabled: !currentValue } : p))
        } else {
          return [
            ...prev,
            { id: Date.now(), notification_type: type, in_app_enabled: !currentValue, muted_until: null },
          ]
        }
      })
      await updateNotificationPreference(type, { in_app_enabled: !currentValue })
    } catch (e) {
      console.error('Failed to update preference', e)
      // Revert on failure by refetching
      fetchPreferences()
    }
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Pengaturan Notifikasi</h1>
        <p className="text-sm text-gray-500 mt-1">Kelola notifikasi mana saja yang ingin Anda terima.</p>
      </div>

      <div className="bg-white shadow rounded-xl border border-gray-200 overflow-hidden">
        <div className="p-4 sm:p-6 border-b border-gray-200 bg-gray-50">
          <h2 className="font-semibold text-gray-800">Notifikasi Dalam Aplikasi (In-App)</h2>
          <p className="text-sm text-gray-500 mt-1">Pemberitahuan kritis dan eskalasi SLA tidak dapat dimatikan.</p>
        </div>

        {isLoading ? (
          <div className="p-8 text-center text-gray-400">Memuat...</div>
        ) : (
          <ul className="divide-y divide-gray-100 p-2 sm:p-4">
            {configurableTypes.map((item) => {
              const pref = preferences.find((p) => p.notification_type === item.type)
              const isEnabled = pref ? pref.in_app_enabled : true

              return (
                <li key={item.type} className="flex items-center justify-between py-4 px-2">
                  <div>
                    <p className="font-medium text-gray-900">{item.label}</p>
                  </div>
                  <div>
                    <label className="relative inline-flex items-center cursor-pointer">
                      <input
                        type="checkbox"
                        className="sr-only peer"
                        checked={isEnabled}
                        onChange={() => handleToggle(item.type, isEnabled)}
                      />
                      <div className="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </div>
    </div>
  )
}
