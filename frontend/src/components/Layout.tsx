import { useState } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import type { Role } from '../types'
import { ROLE_LABELS, USERS } from '../data'
import { Avatar } from './ui'

const NAV_ITEMS: Record<Role, { path: string; label: string; icon: string }[]> = {
  user: [
    { path: '/user/dashboard', label: 'Dashboard', icon: '🏠' },
    { path: '/user/create-ticket', label: 'Buat Request / Tiket', icon: '➕' },
    { path: '/user/tickets', label: 'Riwayat Tiket', icon: '📋' },
    { path: '/user/uat', label: 'UAT', icon: '🧪' },
    { path: '/notifications', label: 'Notifikasi', icon: '🔔' },
  ],
  supervisor: [
    { path: '/supervisor/dashboard', label: 'Dashboard', icon: '🏠' },
    { path: '/supervisor/validation-queue', label: 'Antrean Validasi', icon: '✅' },
    { path: '/notifications', label: 'Notifikasi', icon: '🔔' },
  ],
  itlead: [
    { path: '/itlead/dashboard', label: 'Dashboard', icon: '🏠' },
    { path: '/itlead/triage', label: 'Antrean Triage', icon: '🎯' },
    { path: '/itlead/priority', label: 'Prioritas & SLA', icon: '⚡' },
    { path: '/sla-monitoring', label: 'Monitoring SLA', icon: '⏱️' },
    { path: '/notifications', label: 'Notifikasi', icon: '🔔' },
  ],
  pic: [
    { path: '/pic/dashboard', label: 'Dashboard', icon: '🏠' },
    { path: '/pic/workspace', label: 'Workspace Tiket', icon: '💻' },
    { path: '/pic/rca', label: 'Root Cause Analysis', icon: '🔍' },
    { path: '/pic/testing', label: 'Internal Testing', icon: '🔬' },
    { path: '/notifications', label: 'Notifikasi', icon: '🔔' },
  ],
  qa: [
    { path: '/qa/dashboard', label: 'Dashboard', icon: '🏠' },
    { path: '/qa/testing', label: 'Pengujian', icon: '🧪' },
    { path: '/notifications', label: 'Notifikasi', icon: '🔔' },
  ],
  manager: [
    { path: '/manager/approval', label: 'Manager Approval', icon: '✅' },
    { path: '/sla-monitoring', label: 'Monitoring SLA', icon: '⏱️' },
    { path: '/notifications', label: 'Notifikasi', icon: '🔔' },
  ],
  executive: [
    { path: '/executive/dashboard', label: 'Executive Dashboard', icon: '📊' },
    { path: '/executive/statistics', label: 'Statistik & Analitik', icon: '📈' },
    { path: '/sla-monitoring', label: 'Monitoring SLA', icon: '⏱️' },
  ],
  admin: [
    { path: '/admin/console', label: 'Admin Console', icon: '⚙️' },
    { path: '/admin/users', label: 'Manajemen User', icon: '👥' },
    { path: '/admin/divisions', label: 'Divisi & Aplikasi', icon: '🏢' },
    { path: '/admin/sla-rules', label: 'Aturan SLA', icon: '📏' },
    { path: '/admin/escalation', label: 'Matrix Eskalasi', icon: '🔺' },
    { path: '/admin/audit-log', label: 'Audit Log', icon: '📜' },
  ],
}

export function Layout({
  role,
  setRole,
  children,
}: {
  role: Role
  setRole: (r: Role) => void
  children: React.ReactNode
}) {
  const [sidebarOpen, setSidebarOpen] = useState(() => window.innerWidth >= 768)
  const [roleDropdown, setRoleDropdown] = useState(false)
  const navigate = useNavigate()
  const currentUser = USERS.find((u) => u.role === role) || USERS[0]
  const navItems = NAV_ITEMS[role] || []

  const handleRoleChange = (r: Role) => {
    setRole(r)
    setRoleDropdown(false)
    const firstPath = NAV_ITEMS[r]?.[0]?.path || '/user/dashboard'
    navigate(firstPath)
    if (window.innerWidth < 768) setSidebarOpen(false)
  }

  const handleNavigation = () => {
    if (window.innerWidth < 768) setSidebarOpen(false)
  }

  return (
    <div className="flex h-screen bg-[#F0F4F8] overflow-hidden">
      {/* Sidebar */}
      {sidebarOpen && (
        <button
          className="fixed inset-0 z-30 bg-black/40 md:hidden"
          onClick={() => setSidebarOpen(false)}
          aria-label="Tutup menu navigasi"
        />
      )}
      <aside
        className={`${sidebarOpen ? 'translate-x-0 md:w-60' : '-translate-x-full md:translate-x-0 md:w-16'} fixed inset-y-0 left-0 z-40 w-60 md:static shrink-0 bg-[#0F2554] flex flex-col transition-all duration-300 overflow-hidden`}
      >
        {/* Logo */}
        <div className="h-16 flex items-center gap-3 px-4 border-b border-white/10 shrink-0">
          <div className="w-8 h-8 bg-white rounded-lg flex items-center justify-center shrink-0">
            <span className="text-[#0F2554] font-black text-sm">A</span>
          </div>
          {sidebarOpen && (
            <div className="min-w-0">
              <p className="text-white font-bold text-sm leading-tight">APG CRM</p>
              <p className="text-blue-300 text-xs leading-tight">IT Service Management</p>
            </div>
          )}
        </div>

        {/* Role Switcher */}
        {sidebarOpen && (
          <div className="px-3 py-3 border-b border-white/10">
            <p className="text-blue-300 text-xs mb-1.5 px-1 font-medium uppercase tracking-wide">Demo Role</p>
            <div className="relative">
              <button
                onClick={() => setRoleDropdown(!roleDropdown)}
                className="w-full flex items-center justify-between gap-2 bg-white/10 hover:bg-white/20 text-white rounded-lg px-3 py-2 text-xs font-medium transition-colors"
              >
                <span className="truncate">{ROLE_LABELS[role]}</span>
                <span className={`transition-transform ${roleDropdown ? 'rotate-180' : ''}`}>▼</span>
              </button>
              {roleDropdown && (
                <div className="absolute top-full left-0 right-0 mt-1 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden">
                  {(Object.keys(ROLE_LABELS) as Role[]).map((r) => (
                    <button
                      key={r}
                      onClick={() => handleRoleChange(r)}
                      className={`w-full text-left px-3 py-2 text-xs hover:bg-blue-50 transition-colors ${r === role ? 'text-[#1E3A8A] font-semibold bg-blue-50' : 'text-gray-700'}`}
                    >
                      {ROLE_LABELS[r]}
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        {/* Navigation */}
        <nav className="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto">
          {navItems.map((item) => (
            <NavLink
              key={item.path}
              to={item.path}
              onClick={handleNavigation}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all ${isActive ? 'bg-white/20 text-white' : 'text-blue-200 hover:bg-white/10 hover:text-white'}`
              }
            >
              <span className="text-base shrink-0">{item.icon}</span>
              {sidebarOpen && <span className="truncate">{item.label}</span>}
            </NavLink>
          ))}
        </nav>

        {/* User Info */}
        <div className="px-3 py-3 border-t border-white/10 shrink-0">
          <div className={`flex items-center gap-3 ${!sidebarOpen ? 'justify-center' : ''}`}>
            <Avatar initials={currentUser.avatar} size="sm" />
            {sidebarOpen && (
              <div className="min-w-0">
                <p className="text-white text-xs font-semibold truncate">{currentUser.name}</p>
                <p className="text-blue-300 text-xs truncate">{currentUser.division}</p>
              </div>
            )}
          </div>
        </div>
      </aside>

      {/* Main */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Top Header */}
        <header className="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-3 sm:px-6 shrink-0 shadow-sm min-w-0">
          <div className="flex items-center gap-2 sm:gap-4 min-w-0">
            <button
              onClick={() => setSidebarOpen(!sidebarOpen)}
              aria-label={sidebarOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi'}
              className="p-2 hover:bg-gray-100 rounded-lg transition-colors text-gray-500"
            >
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            <div className="min-w-0">
              <p className="text-sm font-semibold text-gray-900 truncate">APG Enterprise Internal CRM</p>
              <p className="hidden sm:block text-xs text-gray-400">IT Service Management System</p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <NavLink
              to="/notifications"
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
              <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full" />
            </NavLink>

            <div className="flex items-center gap-2 bg-gray-50 rounded-xl px-3 py-1.5 border border-gray-200">
              <Avatar initials={currentUser.avatar} size="sm" />
              <div className="hidden sm:block">
                <p className="text-xs font-semibold text-gray-800">{currentUser.name}</p>
                <p className="text-xs text-gray-400">{ROLE_LABELS[role]}</p>
              </div>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <main className="flex-1 overflow-y-auto">
          <div className="p-4 sm:p-6 max-w-[1400px] mx-auto">{children}</div>
        </main>
      </div>
    </div>
  )
}
