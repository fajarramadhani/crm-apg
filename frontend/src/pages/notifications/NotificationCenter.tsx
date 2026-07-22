import React, { useState, useEffect } from 'react'
import { NavLink } from 'react-router-dom'
import { getNotifications, markAsRead, markAllAsRead, archiveNotification } from '../../api/notifications'
import { Notification } from '../../types/notifications'

export const NotificationCenter: React.FC = () => {
  const [notifications, setNotifications] = useState<Notification[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(true)
  const [filter, setFilter] = useState<'all' | 'unread'>('all')

  const fetchNotifications = async (pageNumber: number, currentFilter: string) => {
    setIsLoading(true)
    setError(null)
    try {
      const params: any = { page: pageNumber, per_page: 20 }
      if (currentFilter === 'unread') {
        params.status = 'unread'
      }
      const res = await getNotifications(params)

      if (pageNumber === 1) {
        setNotifications(res.data)
      } else {
        setNotifications((prev) => [...prev, ...res.data])
      }
      setHasMore(res.meta.current_page < res.meta.last_page)
    } catch (e: any) {
      setError(e.message || 'Failed to load notifications')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    setPage(1)
    fetchNotifications(1, filter)
  }, [filter])

  const loadMore = () => {
    if (!isLoading && hasMore) {
      const nextPage = page + 1
      setPage(nextPage)
      fetchNotifications(nextPage, filter)
    }
  }

  const handleMarkRead = async (id: string) => {
    try {
      await markAsRead(id)
      setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, is_read: true } : n)))
    } catch (e) {
      console.error(e)
    }
  }

  const handleMarkAllRead = async () => {
    try {
      await markAllAsRead()
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })))
    } catch (e) {
      console.error(e)
    }
  }

  const handleArchive = async (id: string) => {
    try {
      await archiveNotification(id)
      setNotifications((prev) => prev.filter((n) => n.id !== id))
    } catch (e) {
      console.error(e)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Notification Center</h1>
          <p className="text-sm text-gray-500">Kelola semua pemberitahuan dan aktivitas</p>
        </div>
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <select
            value={filter}
            onChange={(e) => setFilter(e.target.value as any)}
            className="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 flex-1 sm:flex-none"
          >
            <option value="all">Semua</option>
            <option value="unread">Belum Dibaca</option>
          </select>
          <button
            onClick={handleMarkAllRead}
            className="px-4 py-2 bg-white border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 shrink-0"
          >
            Tandai Semua Dibaca
          </button>
          <NavLink
            to="/settings/notifications"
            className="px-4 py-2 bg-blue-50 text-blue-700 rounded-lg shadow-sm text-sm font-medium hover:bg-blue-100 shrink-0"
          >
            Pengaturan
          </NavLink>
        </div>
      </div>

      {error && (
        <div className="p-4 bg-red-50 text-red-700 rounded-lg border border-red-200 flex items-center justify-between">
          <span>{error}</span>
          <button onClick={() => fetchNotifications(page, filter)} className="text-sm underline">
            Coba Lagi
          </button>
        </div>
      )}

      <div className="bg-white shadow rounded-xl overflow-hidden border border-gray-200">
        {notifications.length === 0 && !isLoading ? (
          <div className="p-12 text-center text-gray-500">
            <span className="text-4xl mb-4 block">📭</span>
            Tidak ada notifikasi ditemukan.
          </div>
        ) : (
          <ul className="divide-y divide-gray-200">
            {notifications.map((notif) => (
              <li
                key={notif.id}
                className={`p-4 sm:px-6 hover:bg-gray-50 transition-colors ${!notif.is_read ? 'bg-blue-50/20' : ''}`}
              >
                <div className="flex gap-4">
                  <div className="shrink-0 mt-1">
                    {notif.severity === 'critical' && <span className="text-red-500 text-2xl">⚠️</span>}
                    {notif.severity === 'warning' && <span className="text-yellow-500 text-2xl">⚠️</span>}
                    {notif.severity === 'success' && <span className="text-green-500 text-2xl">✅</span>}
                    {notif.severity === 'info' && <span className="text-blue-500 text-2xl">ℹ️</span>}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex justify-between gap-4">
                      <p
                        className={`text-sm ${!notif.is_read ? 'font-bold text-gray-900' : 'font-medium text-gray-900'}`}
                      >
                        {notif.title}
                      </p>
                      <p className="text-xs text-gray-400 shrink-0">
                        {new Date(notif.created_at).toLocaleString('id-ID')}
                      </p>
                    </div>
                    <p className="mt-1 text-sm text-gray-600">{notif.message}</p>
                    <div className="mt-3 flex items-center gap-3">
                      {notif.action_url && (
                        <NavLink
                          to={notif.action_url}
                          onClick={() => handleMarkRead(notif.id)}
                          className="text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 px-2 py-1 rounded"
                        >
                          Lihat Detail
                        </NavLink>
                      )}
                      {!notif.is_read && (
                        <button
                          onClick={() => handleMarkRead(notif.id)}
                          className="text-xs font-medium text-gray-500 hover:text-gray-700"
                        >
                          Tandai Dibaca
                        </button>
                      )}
                      <button
                        onClick={() => handleArchive(notif.id)}
                        className="text-xs font-medium text-red-500 hover:text-red-700 ml-auto"
                      >
                        Arsip
                      </button>
                    </div>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>

      {hasMore && (
        <div className="text-center mt-6">
          <button
            onClick={loadMore}
            disabled={isLoading}
            className="px-6 py-2 bg-white border border-gray-300 rounded-full shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
          >
            {isLoading ? 'Memuat...' : 'Muat Lebih Banyak'}
          </button>
        </div>
      )}
    </div>
  )
}
