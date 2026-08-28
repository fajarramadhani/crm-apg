import { useState } from 'react'
import {
  MessageCircle,
  PlusCircle,
  ClipboardList,
  FlaskConical,
  Bell,
  LayoutDashboard,
  CheckCircle2,
  Laptop,
  Target,
  Check,
  Cog,
  ShieldAlert,
  Rocket,
  AlertTriangle,
  BarChart3,
  Home,
  Users,
  Building,
  Building2,
  Clock,
  BookOpen,
  FileEdit,
  Tag,
} from 'lucide-react'
import { NavLink, useNavigate } from 'react-router-dom'
import type { AuthenticatedUser, Role } from '../types'
import { ROLE_LABELS } from '../presentation'
import { Avatar } from './ui'
import { NotificationBell } from './notifications/NotificationBell'

const NAV_ITEMS: Record<Role, { path: string; label: string; icon: React.ReactNode }[]> = {
  requester: [
    { path: '/user/create-ticket', label: 'Buat Request / Tiket', icon: <PlusCircle className="h-4 w-4" /> },
    { path: '/user/tickets', label: 'Riwayat Tiket', icon: <ClipboardList className="h-4 w-4" /> },
    { path: '/user/uat', label: 'UAT', icon: <FlaskConical className="h-4 w-4" /> },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
  supervisor: [
    {
      path: '/supervisor-it/dashboard',
      label: 'Supervisor Control Center',
      icon: <LayoutDashboard className="h-4 w-4" />,
    },
    { path: '/supervisor-it/tickets', label: 'Daftar Tiket', icon: <ClipboardList className="h-4 w-4" /> },
    {
      path: '/supervisor/validation-queue',
      label: 'Antrean Validasi (Legacy)',
      icon: <CheckCircle2 className="h-4 w-4" />,
    },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
  supervisor_it: [
    {
      path: '/supervisor-it/dashboard',
      label: 'Dashboard Control Center',
      icon: <LayoutDashboard className="h-4 w-4" />,
    },
    { path: '/supervisor-it/tickets', label: 'Daftar Tiket Supervisor', icon: <ClipboardList className="h-4 w-4" /> },
    { path: '/pic/dashboard', label: 'PIC Workspace', icon: <Laptop className="h-4 w-4" /> },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
  it_lead: [
    {
      path: '/supervisor-it/dashboard',
      label: 'Supervisor Control Center',
      icon: <LayoutDashboard className="h-4 w-4" />,
    },
    { path: '/supervisor-it/tickets', label: 'Daftar Tiket', icon: <ClipboardList className="h-4 w-4" /> },
    { path: '/pic/dashboard', label: 'PIC Workspace', icon: <Laptop className="h-4 w-4" /> },
    { path: '/itlead/triage', label: 'Antrean Triage', icon: <Target className="h-4 w-4" /> },
    { path: '/itlead/plan-review', label: 'Plan Review', icon: <Check className="h-4 w-4" /> },
    { path: '/itlead/development', label: 'Development', icon: <Cog className="h-4 w-4" /> },
    { path: '/itlead/uat-assignment', label: 'Penugasan UAT', icon: <FlaskConical className="h-4 w-4" /> },
    { path: '/itlead/release-preparation', label: 'Approval & Release', icon: <ShieldAlert className="h-4 w-4" /> },
    { path: '/itlead/deployment', label: 'Deployment & Rilis', icon: <Rocket className="h-4 w-4" /> },
    { path: '/itlead/alerts', label: 'Operational Alerts', icon: <AlertTriangle className="h-4 w-4" /> },
    { path: '/itlead/reports', label: 'Laporan Operasional', icon: <BarChart3 className="h-4 w-4" /> },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
  pic: [
    { path: '/pic/dashboard', label: 'PIC Dashboard', icon: <BarChart3 className="h-4 w-4" /> },
    { path: '/pic/tickets', label: 'Daftar Tiket PIC', icon: <ClipboardList className="h-4 w-4" /> },
    { path: '/pic/workspace', label: 'Workspace Legacy', icon: <Laptop className="h-4 w-4" /> },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
  pic_it_support: [
    { path: '/pic/dashboard', label: 'PIC Dashboard', icon: <BarChart3 className="h-4 w-4" /> },
    { path: '/pic/tickets', label: 'Daftar Tiket PIC', icon: <ClipboardList className="h-4 w-4" /> },
    { path: '/pic/workspace', label: 'Workspace Legacy', icon: <Laptop className="h-4 w-4" /> },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
  pic_it_develop: [
    { path: '/pic/dashboard', label: 'PIC Dashboard', icon: <BarChart3 className="h-4 w-4" /> },
    { path: '/pic/tickets', label: 'Daftar Tiket PIC', icon: <ClipboardList className="h-4 w-4" /> },
    { path: '/pic/workspace', label: 'Workspace Legacy', icon: <Laptop className="h-4 w-4" /> },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
  qa: [
    { path: '/qa/dashboard', label: 'Dashboard', icon: <Home className="h-4 w-4" /> },
    { path: '/qa/testing', label: 'Pengujian', icon: <FlaskConical className="h-4 w-4" /> },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
  manager: [
    { path: '/manager/approvals', label: 'Business Approval', icon: <CheckCircle2 className="h-4 w-4" /> },
    { path: '/manager/alerts', label: 'Critical Alerts', icon: <AlertTriangle className="h-4 w-4" /> },
    { path: '/manager/reports', label: 'Laporan', icon: <BarChart3 className="h-4 w-4" /> },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
  executive: [
    { path: '/executive/reports', label: 'Executive Dashboard', icon: <BarChart3 className="h-4 w-4" /> },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
  superadmin: [
    { path: '/admin/users', label: 'Akun & Role', icon: <Users className="h-4 w-4" /> },
    { path: '/admin/offices', label: 'Management Cabang', icon: <Building2 className="h-4 w-4" /> },
    { path: '/admin/divisions', label: 'Divisi & Aplikasi', icon: <Building className="h-4 w-4" /> },
    { path: '/admin/sla-rules', label: 'Aturan SLA', icon: <Clock className="h-4 w-4" /> },
    { path: '/admin/workflows', label: 'Workflow', icon: <Cog className="h-4 w-4" /> },
    { path: '/notifications', label: 'Notifikasi', icon: <Bell className="h-4 w-4" /> },
  ],
}

export function Layout({
  role,
  user,
  onLogout,
  children,
}: {
  role: Role
  user: AuthenticatedUser
  onLogout: () => Promise<void>
  children: React.ReactNode
}) {
  const [sidebarOpen, setSidebarOpen] = useState(() => window.innerWidth >= 768)
  const navigate = useNavigate()
  const navItems = [
    ...(NAV_ITEMS[role] || []),
    ...(user.permissions.includes('notification.whatsapp.manage')
      ? [{ path: '/admin/whatsapp', label: 'WhatsApp Fonnte', icon: <MessageCircle className="h-4 w-4" /> }]
      : []),
    ...(user.permissions.includes('knowledge_base.view')
      ? [{ path: '/knowledge-base', label: 'Knowledge Base', icon: <BookOpen className="h-4 w-4" /> }]
      : []),
    ...(user.permissions.includes('knowledge_base.create') || user.permissions.includes('knowledge_base.review')
      ? [{ path: '/knowledge-base/manage', label: 'Kelola Knowledge', icon: <FileEdit className="h-4 w-4" /> }]
      : []),
    ...(user.permissions.includes('knowledge_base.manage_tags')
      ? [{ path: '/settings/knowledge-base/tags', label: 'Tag Knowledge', icon: <Tag className="h-4 w-4" /> }]
      : []),
  ]
  const initials = user.name
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()

  const handleNavigation = () => {
    if (window.innerWidth < 768) setSidebarOpen(false)
  }

  const handleLogout = async () => {
    await onLogout()
    navigate('/login', { replace: true })
  }

  return (
    <div className="flex h-dvh bg-[#F0F4F8] overflow-hidden">
      <a
        href="#main-content"
        className="fixed left-3 top-3 z-[60] -translate-y-20 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-900 shadow focus:translate-y-0"
      >
        Lewati ke konten utama
      </a>
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
          <div className="w-9 h-9 bg-white rounded-xl p-1 flex items-center justify-center shrink-0 shadow-sm overflow-hidden">
            <img src="/logo/apg-logo.png" alt="APG Logo" className="w-full h-full object-contain" />
          </div>
          {sidebarOpen && (
            <div className="min-w-0">
              <p className="text-white font-bold text-sm leading-tight">APG CRM</p>
              <p className="text-blue-300 text-xs leading-tight">IT Service Management</p>
            </div>
          )}
        </div>

        {/* The development label is informational only; it never changes authorization. */}
        {sidebarOpen && import.meta.env.DEV && (
          <div className="px-3 py-3 border-b border-white/10">
            <p className="text-blue-300 text-xs mb-1.5 px-1 font-medium uppercase tracking-wide">Authenticated Role</p>
            <div className="space-y-2 rounded-xl bg-white/8 px-3 py-3">
              <div className="rounded-lg bg-white/10 px-3 py-2 text-xs font-medium text-white">{ROLE_LABELS[role]}</div>
              {role === 'supervisor_it' && (
                <div className="inline-flex items-center rounded-full border border-blue-300/30 bg-blue-400/10 px-2.5 py-1 text-[11px] font-semibold text-blue-100">
                  Supervisor sebagai PIC
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
            <Avatar initials={initials} size="sm" />
            {sidebarOpen && (
              <div className="min-w-0">
                <p className="text-white text-xs font-semibold truncate">{user.name}</p>
                <p className="text-blue-300 text-xs truncate">{user.email}</p>
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
            {user.permissions.includes('notification.view_own') && <NotificationBell />}

            <div className="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-1.5 shadow-sm">
              <Avatar initials={initials} size="sm" />
              <div className="hidden sm:block">
                <p className="text-xs font-semibold text-gray-900">{user.name}</p>
                <p className="text-xs text-gray-500">{ROLE_LABELS[role]}</p>
              </div>
            </div>

            <button
              type="button"
              onClick={handleLogout}
              aria-label="Keluar dari sistem"
              className="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-medium text-gray-500 transition-colors hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-500/30"
            >
              <svg
                className="h-5 w-5 shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"
                />
              </svg>
              <span className="hidden lg:inline">Logout</span>
            </button>
          </div>
        </header>

        {/* Page Content */}
        <main id="main-content" tabIndex={-1} className="flex-1 overflow-y-auto">
          <div className="p-4 sm:p-6 max-w-[1400px] mx-auto">{children}</div>
        </main>
      </div>
    </div>
  )
}
