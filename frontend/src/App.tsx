import type { ReactNode } from 'react'
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom'
import { Layout } from './components/Layout'
import { AuthProvider, useAuth } from './context/AuthContext'
import type { Role } from './types'

import Login from './pages/Login'
import Unauthorized from './pages/Unauthorized'
import NotFound from './pages/NotFound'
import UserDashboard from './pages/user/UserDashboard'
import CreateTicket from './pages/user/CreateTicket'
import TicketHistory from './pages/user/TicketHistory'
import TicketDetail from './pages/user/TicketDetail'
import UAT from './pages/user/UAT'
import SupervisorDashboard from './pages/supervisor/SupervisorDashboard'
import ValidationQueue from './pages/supervisor/ValidationQueue'
import ITLeadDashboard from './pages/itlead/ITLeadDashboard'
import TriageQueue from './pages/itlead/TriageQueue'
import PriorityAssignment from './pages/itlead/PriorityAssignment'
import PlanReview from './pages/itlead/PlanReview'
import DevelopmentMonitoring from './pages/itlead/DevelopmentMonitoring'
import UatAssignmentQueue from './pages/itlead/UatAssignmentQueue'
import PICDashboard from './pages/pic/PICDashboard'
import Workspace from './pages/pic/Workspace'
import InternalTestingPIC from './pages/pic/InternalTesting'
import QADashboard from './pages/qa/QADashboard'
import TestingForm from './pages/qa/TestingForm'
import ManagerApproval from './pages/manager/ManagerApproval'
import SLAMonitoring from './pages/shared/SLAMonitoring'
import NotificationCenter from './pages/shared/NotificationCenter'
import ExecutiveDashboard from './pages/executive/ExecutiveDashboard'
import Statistics from './pages/executive/Statistics'
import AdminConsole from './pages/admin/AdminConsole'
import UserManagement from './pages/admin/UserManagement'
import DivisionManagement from './pages/admin/DivisionManagement'
import SLARules from './pages/admin/SLARules'
import EscalationMatrix from './pages/admin/EscalationMatrix'
import AuditLog from './pages/admin/AuditLog'

export const DEFAULT_ROUTES: Record<Role, string> = {
  requester: '/user/dashboard',
  supervisor: '/supervisor/dashboard',
  it_lead: '/itlead/dashboard',
  pic: '/pic/dashboard',
  qa: '/qa/dashboard',
  manager: '/manager/approval',
  executive: '/executive/dashboard',
  admin: '/admin/console',
}

function LoadingSession() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-[#F0F4F8]" role="status">
      <div className="flex items-center gap-3 text-sm font-medium text-gray-600">
        <span className="h-5 w-5 animate-spin rounded-full border-2 border-[#1E3A8A]/30 border-t-[#1E3A8A]" />
        Memeriksa sesi...
      </div>
    </div>
  )
}

function RequireAuth({ children }: { children: ReactNode }) {
  const { status } = useAuth()
  if (status === 'initializing') return <LoadingSession />
  return status === 'authenticated' ? children : <Navigate to="/login" replace />
}

function RequireRole({ allowed, children }: { allowed: Role[]; children: ReactNode }) {
  const { hasRole } = useAuth()
  return hasRole(...allowed) ? children : <Navigate to="/unauthorized" replace />
}

function AuthenticatedLayout() {
  const { user, logout } = useAuth()
  if (!user) return null
  return (
    <Layout role={user.role.key} user={user} onLogout={logout}>
      <Outlet />
    </Layout>
  )
}

function HomeRedirect() {
  const { user } = useAuth()
  return <Navigate to={user ? DEFAULT_ROUTES[user.role.key] : '/login'} replace />
}

function LoginRoute() {
  const { status, user } = useAuth()
  if (status === 'initializing') return <LoadingSession />
  return user ? <Navigate to={DEFAULT_ROUTES[user.role.key]} replace /> : <Login />
}

function AppRoutes() {
  const { user } = useAuth()
  const allRoles: Role[] = ['requester', 'supervisor', 'it_lead', 'pic', 'qa', 'manager', 'executive', 'admin']
  const guard = (roles: Role[], page: ReactNode) => <RequireRole allowed={roles}>{page}</RequireRole>

  return (
    <Routes>
      <Route path="/login" element={<LoginRoute />} />
      <Route
        element={
          <RequireAuth>
            <AuthenticatedLayout />
          </RequireAuth>
        }
      >
        <Route index element={<HomeRedirect />} />
        <Route path="/user/dashboard" element={guard(['requester'], <UserDashboard />)} />
        <Route path="/user/create-ticket" element={guard(['requester'], <CreateTicket />)} />
        <Route path="/user/tickets" element={guard(['requester'], <TicketHistory />)} />
        <Route path="/user/tickets/:id" element={guard(['requester'], <TicketDetail />)} />
        <Route path="/user/uat" element={guard(['requester'], <UAT />)} />
        <Route path="/supervisor/dashboard" element={guard(['supervisor'], <SupervisorDashboard />)} />
        <Route path="/supervisor/validation-queue" element={guard(['supervisor'], <ValidationQueue />)} />
        <Route path="/itlead/dashboard" element={guard(['it_lead'], <ITLeadDashboard />)} />
        <Route path="/itlead/triage" element={guard(['it_lead'], <TriageQueue />)} />
        <Route path="/itlead/priority" element={guard(['it_lead'], <PriorityAssignment />)} />
        <Route path="/itlead/plan-review" element={guard(['it_lead'], <PlanReview />)} />
        <Route path="/itlead/development" element={guard(['it_lead'], <DevelopmentMonitoring />)} />
        <Route path="/itlead/uat-assignment" element={guard(['it_lead'], <UatAssignmentQueue />)} />
        <Route path="/pic/dashboard" element={guard(['pic'], <PICDashboard />)} />
        <Route path="/pic/workspace" element={guard(['pic'], <Workspace />)} />
        <Route path="/pic/rca" element={guard(['pic'], <Navigate to="/pic/workspace" replace />)} />
        <Route path="/pic/testing" element={guard(['pic'], <InternalTestingPIC />)} />
        <Route path="/qa/dashboard" element={guard(['qa'], <QADashboard />)} />
        <Route path="/qa/testing" element={guard(['qa'], <TestingForm />)} />
        <Route path="/manager/approval" element={guard(['manager'], <ManagerApproval />)} />
        <Route path="/sla-monitoring" element={guard(['it_lead', 'manager', 'executive'], <SLAMonitoring />)} />
        <Route
          path="/notifications"
          element={guard(allRoles, <NotificationCenter role={user?.role.key ?? 'requester'} />)}
        />
        <Route path="/executive/dashboard" element={guard(['executive'], <ExecutiveDashboard />)} />
        <Route path="/executive/statistics" element={guard(['executive'], <Statistics />)} />
        <Route path="/admin/console" element={guard(['admin'], <AdminConsole />)} />
        <Route path="/admin/users" element={guard(['admin'], <UserManagement />)} />
        <Route path="/admin/divisions" element={guard(['admin'], <DivisionManagement />)} />
        <Route path="/admin/sla-rules" element={guard(['admin'], <SLARules />)} />
        <Route path="/admin/escalation" element={guard(['admin'], <EscalationMatrix />)} />
        <Route path="/admin/audit-log" element={guard(['admin'], <AuditLog />)} />
        <Route
          path="/unauthorized"
          element={<Unauthorized dashboardPath={user ? DEFAULT_ROUTES[user.role.key] : '/login'} />}
        />
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </BrowserRouter>
  )
}
