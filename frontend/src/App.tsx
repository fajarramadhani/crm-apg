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
import Confirmations from './pages/user/Confirmations'
import UAT from './pages/user/UAT'
import SupervisorDashboard from './pages/supervisor/SupervisorDashboard'
import ValidationQueue from './pages/supervisor/ValidationQueue'
import ITLeadDashboard from './pages/itlead/ITLeadDashboard'
import TriageQueue from './pages/itlead/TriageQueue'
import PriorityAssignment from './pages/itlead/PriorityAssignment'
import PlanReview from './pages/itlead/PlanReview'
import DevelopmentMonitoring from './pages/itlead/DevelopmentMonitoring'
import DeploymentQueue from './pages/itlead/DeploymentQueue'
import UatAssignmentQueue from './pages/itlead/UatAssignmentQueue'
import ReleasePreparation from './pages/itlead/ReleasePreparation'
import PICDashboard from './pages/pic/PICDashboard'
import Workspace from './pages/pic/Workspace'
import InternalTestingPIC from './pages/pic/InternalTesting'
import ReleasePreparationPIC from './pages/pic/ReleasePreparation'
import QADashboard from './pages/qa/QADashboard'
import TestingForm from './pages/qa/TestingForm'
import ManagerApproval from './pages/manager/ManagerApproval'
import SLAMonitoring from './pages/shared/SLAMonitoring'
import { NotificationCenter } from './pages/notifications/NotificationCenter'
import { NotificationPreferences } from './pages/settings/NotificationPreferences'
import { ItLeadAlerts } from './pages/itlead/ItLeadAlerts'
import { ManagerAlerts } from './pages/manager/ManagerAlerts'
import ExecutiveDashboard from './pages/executive/ExecutiveDashboard'
import Statistics from './pages/executive/Statistics'
import AnalyticsDashboard from './pages/shared/AnalyticsDashboard'
import AdminConsole from './pages/admin/AdminConsole'
import UserManagement from './pages/admin/UserManagement'
import DivisionManagement from './pages/admin/DivisionManagement'
import SLARules from './pages/admin/SLARules'
import EscalationMatrix from './pages/admin/EscalationMatrix'
import AuditLog from './pages/admin/AuditLog'
import KnowledgeBaseList from './pages/knowledgeBase/KnowledgeBaseList'
import KnowledgeBaseDetail from './pages/knowledgeBase/KnowledgeBaseDetail'
import KnowledgeBaseForm from './pages/knowledgeBase/KnowledgeBaseForm'
import KnowledgeBaseReview from './pages/knowledgeBase/KnowledgeBaseReview'
import KnowledgeBaseTags from './pages/settings/KnowledgeBaseTags'

export const DEFAULT_ROUTES: Record<Role, string> = {
  requester: '/user/dashboard',
  supervisor: '/supervisor/dashboard',
  it_lead: '/itlead/dashboard',
  pic: '/pic/dashboard',
  qa: '/qa/dashboard',
  manager: '/manager/approvals',
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

function RequirePermission({ allowed, children }: { allowed: string[]; children: ReactNode }) {
  const { hasPermission } = useAuth()
  return allowed.some(hasPermission) ? children : <Navigate to="/unauthorized" replace />
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
  const permit = (permissions: string[], page: ReactNode) => (
    <RequirePermission allowed={permissions}>{page}</RequirePermission>
  )

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
        <Route path="/user/confirmations" element={guard(['requester'], <Confirmations />)} />
        <Route path="/user/uat" element={guard(['requester'], <UAT />)} />
        <Route path="/supervisor/dashboard" element={guard(['supervisor'], <SupervisorDashboard />)} />
        <Route path="/supervisor/validation-queue" element={guard(['supervisor'], <ValidationQueue />)} />
        <Route path="/supervisor/reports" element={guard(['supervisor'], <AnalyticsDashboard role="supervisor" />)} />
        <Route path="/itlead/dashboard" element={guard(['it_lead'], <ITLeadDashboard />)} />
        <Route path="/itlead/triage" element={guard(['it_lead'], <TriageQueue />)} />
        <Route path="/itlead/priority" element={guard(['it_lead'], <PriorityAssignment />)} />
        <Route path="/itlead/plan-review" element={guard(['it_lead'], <PlanReview />)} />
        <Route path="/itlead/development" element={guard(['it_lead'], <DevelopmentMonitoring />)} />
        <Route path="/itlead/uat-assignment" element={guard(['it_lead'], <UatAssignmentQueue />)} />
        <Route path="/itlead/release-preparation" element={guard(['it_lead'], <ReleasePreparation />)} />
        <Route path="/itlead/deployment" element={guard(['it_lead'], <DeploymentQueue />)} />
        <Route path="/itlead/alerts" element={guard(['it_lead'], <ItLeadAlerts />)} />
        <Route path="/itlead/reports" element={guard(['it_lead'], <AnalyticsDashboard role="it-lead" />)} />
        <Route path="/pic/dashboard" element={guard(['pic'], <PICDashboard />)} />
        <Route path="/pic/workspace" element={guard(['pic'], <Workspace />)} />
        <Route path="/pic/rca" element={guard(['pic'], <Navigate to="/pic/workspace" replace />)} />
        <Route path="/pic/testing" element={guard(['pic'], <InternalTestingPIC />)} />
        <Route path="/pic/release-preparation" element={guard(['pic'], <ReleasePreparationPIC />)} />
        <Route path="/pic/reports" element={guard(['pic'], <AnalyticsDashboard role="pic" />)} />
        <Route path="/qa/dashboard" element={guard(['qa'], <QADashboard />)} />
        <Route path="/qa/testing" element={guard(['qa'], <TestingForm />)} />
        <Route path="/manager/approvals" element={guard(['manager'], <ManagerApproval />)} />
        <Route path="/manager/alerts" element={guard(['manager'], <ManagerAlerts />)} />
        <Route path="/manager/reports" element={guard(['manager'], <AnalyticsDashboard role="manager" />)} />
        <Route path="/sla-monitoring" element={guard(['it_lead', 'manager', 'executive'], <SLAMonitoring />)} />
        <Route path="/notifications" element={guard(allRoles, <NotificationCenter />)} />
        <Route path="/settings/notifications" element={guard(allRoles, <NotificationPreferences />)} />
        <Route path="/knowledge-base" element={permit(['knowledge_base.view'], <KnowledgeBaseList />)} />
        <Route
          path="/knowledge-base/manage"
          element={permit(['knowledge_base.create', 'knowledge_base.review'], <KnowledgeBaseList manage />)}
        />
        <Route path="/knowledge-base/new" element={permit(['knowledge_base.create'], <KnowledgeBaseForm />)} />
        <Route
          path="/knowledge-base/:id/edit"
          element={permit(['knowledge_base.edit_own', 'knowledge_base.edit_any'], <KnowledgeBaseForm />)}
        />
        <Route path="/knowledge-base/:id/review" element={permit(['knowledge_base.review'], <KnowledgeBaseReview />)} />
        <Route path="/knowledge-base/:slug" element={permit(['knowledge_base.view'], <KnowledgeBaseDetail />)} />
        <Route
          path="/settings/knowledge-base/tags"
          element={permit(['knowledge_base.manage_tags'], <KnowledgeBaseTags />)}
        />
        <Route path="/executive/dashboard" element={guard(['executive'], <ExecutiveDashboard />)} />
        <Route path="/executive/statistics" element={guard(['executive'], <Statistics />)} />
        <Route path="/executive/reports" element={guard(['executive'], <AnalyticsDashboard role="executive" />)} />
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
