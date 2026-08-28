import React, { useState, useEffect, useRef } from 'react'
import { NavLink } from 'react-router-dom'
import { AlertTriangle, CheckCircle, Info } from 'lucide-react'
import { getUnreadCount, getNotifications, markAsRead } from '../../api/notifications'
import { Notification } from '../../types/notifications'

const safeActionUrl = (url: string | null): string =>
  url && url.startsWith('/') && !url.startsWith('//') && !url.includes('\\') ? url : '/notifications'

export const NotificationBell: React.FC = () => {
  const [unreadCount, setUnreadCount] = useState(0)
  const [notifications, setNotifications] = useState<Notification[]>([])
  const [isOpen, setIsOpen] = useState(false)
  const dropdownRef = useRef<HTMLDivElement>(null)

  const fetchUnreadCount = async () => {
    try {
      const data = await getUnreadCount()
      setUnreadCount(data.count)
    } catch (e) {
      console.error('Failed to fetch unread count', e)
    }
  }

  const fetchLatestNotifications = async () => {
    try {
      const res = await getNotifications({ per_page: 5 })
      setNotifications(res.data)
    } catch (e) {
      console.error('Failed to fetch notifications', e)
    }
  }

  useEffect(() => {
    fetchUnreadCount()

    // Auto-refresh when window gains focus
    const handleFocus = () => {
      fetchUnreadCount()
      if (isOpen) {
        fetchLatestNotifications()
      }
    }

    window.addEventListener('focus', handleFocus)

    // Polling every 60 seconds
    const interval = setInterval(() => {
      fetchUnreadCount()
      if (isOpen) {
        fetchLatestNotifications()
      }
    }, 60000)

    return () => {
      window.removeEventListener('focus', handleFocus)
      clearInterval(interval)
    }
  }, [isOpen])

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const toggleDropdown = () => {
    if (!isOpen) {
      fetchLatestNotifications()
    }
    setIsOpen(!isOpen)
  }

  const handleNotificationClick = async (id: string, actionUrl: string | null) => {
    try {
      await markAsRead(id)
      setUnreadCount((prev) => Math.max(0, prev - 1))
      setIsOpen(false)
      if (actionUrl) {
        // Simple navigation using window.location or useNavigate. Since we are outside Routes context potentially, we might use window.location or Link component.
        // But action_url is usually an internal path.
        // Actually, this component is inside Layout which has Router context.
      }
    } catch (e) {
      console.error('Failed to mark read', e)
    }
  }

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        onClick={toggleDropdown}
        aria-label="Buka notifikasi"
        className="relative p-2 hover:bg-gray-100 rounded-lg transition-colors text-gray-500"
      >
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
          />
        </svg>
        {unreadCount > 0 && (
          <span className="absolute top-1.5 right-1.5 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center text-[10px] font-bold text-white border border-white">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden z-50">
          <div className="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 className="font-semibold text-gray-900">Notifikasi Terbaru</h3>
            <NavLink
              to="/notifications"
              onClick={() => setIsOpen(false)}
              className="text-sm text-blue-600 hover:text-blue-700 font-medium"
            >
              Lihat Semua
            </NavLink>
          </div>

          <div className="max-h-96 overflow-y-auto">
            {notifications.length === 0 ? (
              <div className="p-4 text-center text-sm text-gray-500">Tidak ada notifikasi baru</div>
            ) : (
              <div className="divide-y divide-gray-100">
                {notifications.map((notif) => (
                  <NavLink
                    key={notif.id}
                    to={safeActionUrl(notif.action_url)}
                    onClick={() => handleNotificationClick(notif.id, notif.action_url)}
                    className={`block p-4 hover:bg-gray-50 transition-colors ${!notif.is_read ? 'bg-blue-50/30' : ''}`}
                  >
                    <div className="flex gap-3">
                      <div className="shrink-0 mt-0.5">
                        {notif.severity === 'critical' && <AlertTriangle className="w-5 h-5 text-red-500" />}
                        {notif.severity === 'warning' && <AlertTriangle className="w-5 h-5 text-yellow-500" />}
                        {notif.severity === 'success' && <CheckCircle className="w-5 h-5 text-green-500" />}
                        {notif.severity === 'info' && <Info className="w-5 h-5 text-blue-500" />}
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className={`text-sm ${!notif.is_read ? 'font-semibold text-gray-900' : 'text-gray-800'}`}>
                          {notif.title}
                        </p>
                        <p className="text-xs text-gray-500 mt-1 line-clamp-2">{notif.message}</p>
                        <p className="text-xs text-gray-400 mt-1.5">
                          {new Date(notif.created_at).toLocaleString('id-ID')}
                        </p>
                      </div>
                      {!notif.is_read && (
                        <div className="shrink-0 flex items-center">
                          <span className="w-2 h-2 bg-blue-600 rounded-full"></span>
                        </div>
                      )}
                    </div>
                  </NavLink>
                ))}
              </div>
            )}
          </div>
          <div className="border-t border-gray-100 bg-gray-50/50 p-2">
            <NavLink
              to="/settings/notifications"
              onClick={() => setIsOpen(false)}
              className="block w-full text-center text-xs text-gray-500 hover:text-gray-700 font-medium py-1"
            >
              Pengaturan Notifikasi
            </NavLink>
          </div>
        </div>
      )}
    </div>
  )
}
