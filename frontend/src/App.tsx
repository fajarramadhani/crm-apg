import { lazy, Suspense, type ReactNode } from 'react'
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom'
import { Layout } from './components/Layout'
import { AuthProvider, useAuth } from './context/AuthContext'
import type { Role } from './types'

import Login from './pages/Login'
import Unauthorized from './pages/Unauthorized'
import NotFound from './pages/NotFound'

const CreateTicket = lazy(() => import('./pages/user/CreateTicket'))
const TicketHistory = lazy(() => import('./pages/user/TicketHistory'))
const TicketDetail = lazy(() => import('./pages/user/TicketDetail'))
const Confirmations = lazy(() => import('./pages/user/Confirmations'))
const UAT = lazy(() => import('./pages/user/UAT'))
const ValidationQueue = lazy(() => import('./pages/supervisor/ValidationQueue'))
const TriageQueue = lazy(() => import('./pages/itlead/TriageQueue'))
const PlanReview = lazy(() => import('./pages/itlead/PlanReview'))
const DevelopmentMonitoring = lazy(() => import('./pages/itlead/DevelopmentMonitoring'))
const DeploymentQueue = lazy(() => import('./pages/itlead/DeploymentQueue'))
const UatAssignmentQueue = lazy(() => import('./pages/itlead/UatAssignmentQueue'))
const ReleasePreparation = lazy(() => import('./pages/itlead/ReleasePreparation'))
const Workspace = lazy(() => import('./pages/pic/Workspace'))
const InternalTestingPIC = lazy(() => import('./pages/pic/InternalTesting'))
const ReleasePreparationPIC = lazy(() => import('./pages/pic/ReleasePreparation'))
const QADashboard = lazy(() => import('./pages/qa/QADashboard'))
const TestingForm = lazy(() => import('./pages/qa/TestingForm'))
const ManagerApproval = lazy(() => import('./pages/manager/ManagerApproval'))
const NotificationCenter = lazy(() =>
  import('./pages/notifications/NotificationCenter').then((module) => ({ default: module.NotificationCenter })),
)
const NotificationPreferences = lazy(() =>
  import('./pages/settings/NotificationPreferences').then((module) => ({ default: module.NotificationPreferences })),
)
const ItLeadAlerts = lazy(() =>
  import('./pages/itlead/ItLeadAlerts').then((module) => ({ default: module.ItLeadAlerts })),
)
const ManagerAlerts = lazy(() =>
  import('./pages/manager/ManagerAlerts').then((module) => ({ default: module.ManagerAlerts })),
)
const AnalyticsDashboard = lazy(() => import('./pages/shared/AnalyticsDashboard'))
const DivisionManagement = lazy(() => import('./pages/admin/DivisionManagement'))
const SLARules = lazy(() => import('./pages/admin/SLARules'))
const KnowledgeBaseList = lazy(() => import('./pages/knowledgeBase/KnowledgeBaseList'))
const KnowledgeBaseDetail = lazy(() => import('./pages/knowledgeBase/KnowledgeBaseDetail'))
const KnowledgeBaseForm = lazy(() => import('./pages/knowledgeBase/KnowledgeBaseForm'))
const KnowledgeBaseReview = lazy(() => import('./pages/knowledgeBase/KnowledgeBaseReview'))
const KnowledgeBaseTags = lazy(() => import('./pages/settings/KnowledgeBaseTags'))

const WorkflowList = lazy(() => import('./pages/admin/workflows/WorkflowList'))
const WorkflowForm = lazy(() => import('./pages/admin/workflows/WorkflowForm'))
const WorkflowDetail = lazy(() => import('./pages/admin/workflows/WorkflowDetail'))

const SupervisorItDashboard = lazy(() => import('./pages/supervisorIt/SupervisorDashboard'))
const SupervisorTicketList = lazy(() => import('./pages/supervisorIt/SupervisorTicketList'))
const SupervisorTicketDetail = lazy(() => import('./pages/supervisorIt/SupervisorTicketDetail'))

const PicUnifiedDashboard = lazy(() =>
  import('./pages/pic/PicUnifiedDashboard').then((m) => ({ default: m.PicUnifiedDashboard })),
)
const PicTicketList = lazy(() => import('./pages/pic/PicTicketList').then((m) => ({ default: m.PicTicketList })))
const PicTicketDetail = lazy(() => import('./pages/pic/PicTicketDetail').then((m) => ({ default: m.PicTicketDetail })))

export const DEFAULT_ROUTES: Record<Role, string> = {
  requester: '/user/tickets',
  supervisor: '/supervisor-it/dashboard',
  supervisor_it: '/supervisor-it/dashboard',
  it_lead: '/supervisor-it/dashboard',
  pic: '/pic/dashboard',
  pic_it_support: '/pic/dashboard',
  pic_it_develop: '/pic/dashboard',
  qa: '/qa/dashboard',
  manager: '/manager/approvals',
  executive: '/executive/reports',
  admin: '/admin/divisions',
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

function LoadingPage() {
  return (
    <div className="flex min-h-64 items-center justify-center" role="status">
      <div className="flex items-center gap-3 text-sm font-medium text-gray-600">
        <span className="h-5 w-5 animate-spin rounded-full border-2 border-[#1E3A8A]/30 border-t-[#1E3A8A]" />
        Memuat halaman...
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
      <Suspense fallback={<LoadingPage />}>
        <Outlet />
      </Suspense>
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
        <Route path="/user/dashboard" element={guard(['requester'], <Navigate to="/user/tickets" replace />)} />
        <Route path="/user/create-ticket" element={guard(['requester'], <CreateTicket />)} />
        <Route path="/user/tickets" element={guard(allRoles, <TicketHistory />)} />
        <Route path="/user/tickets/:id" element={guard(allRoles, <TicketDetail />)} />
        <Route path="/tickets/:id" element={guard(allRoles, <TicketDetail />)} />
        <Route path="/user/confirmations" element={guard(['requester'], <Confirmations />)} />
        <Route path="/user/uat" element={guard(['requester'], <UAT />)} />
        <Route path="/requester/uat" element={<Navigate to="/user/uat" replace />} />
        <Route path="/requester/uat-assignments" element={<Navigate to="/user/uat" replace />} />
        <Route path="/requester/tickets" element={<Navigate to="/user/tickets" replace />} />
        <Route path="/requester/tickets/:id" element={guard(allRoles, <TicketDetail />)} />
        <Route path="/requester/create-ticket" element={<Navigate to="/user/create-ticket" replace />} />
        <Route path="/requester/confirmations" element={<Navigate to="/user/confirmations" replace />} />
        {/* Supervisor IT Control Center Routes */}
        <Route path="/supervisor-it/dashboard" element={guard(allRoles, <SupervisorItDashboard />)} />
        <Route path="/supervisor-it/tickets" element={guard(allRoles, <SupervisorTicketList />)} />
        <Route path="/supervisor-it/tickets/:id" element={guard(allRoles, <SupervisorTicketDetail />)} />

        <Route
          path="/supervisor/dashboard"
          element={guard(['supervisor'], <Navigate to="/supervisor/validation-queue" replace />)}
        />
        <Route path="/supervisor/validation-queue" element={guard(['supervisor'], <ValidationQueue />)} />
        <Route path="/supervisor/reports" element={guard(['supervisor'], <AnalyticsDashboard role="supervisor" />)} />
        <Route
          path="/supervisor/queue"
          element={guard(['supervisor'], <Navigate to="/supervisor/validation-queue" replace />)}
        />

        <Route path="/itlead/dashboard" element={guard(['it_lead'], <Navigate to="/itlead/triage" replace />)} />
        <Route path="/itlead/triage" element={guard(['it_lead'], <TriageQueue />)} />
        <Route path="/itlead/priority" element={guard(['it_lead'], <Navigate to="/itlead/triage" replace />)} />
        <Route path="/itlead/plan-review" element={guard(['it_lead'], <PlanReview />)} />
        <Route path="/itlead/development" element={guard(['it_lead'], <DevelopmentMonitoring />)} />
        <Route path="/itlead/uat-assignment" element={guard(['it_lead'], <UatAssignmentQueue />)} />
        <Route path="/itlead/release-preparation" element={guard(['it_lead'], <ReleasePreparation />)} />
        <Route path="/itlead/deployment" element={guard(['it_lead'], <DeploymentQueue />)} />
        <Route path="/itlead/alerts" element={guard(['it_lead'], <ItLeadAlerts />)} />
        <Route path="/itlead/reports" element={guard(['it_lead'], <AnalyticsDashboard role="it-lead" />)} />

        {/* IT Lead Aliases with hyphen and queue names */}
        <Route path="/it-lead/dashboard" element={guard(['it_lead'], <Navigate to="/itlead/triage" replace />)} />
        <Route path="/it-lead/triage" element={guard(['it_lead'], <Navigate to="/itlead/triage" replace />)} />
        <Route path="/it-lead/triage-queue" element={guard(['it_lead'], <Navigate to="/itlead/triage" replace />)} />
        <Route path="/itlead/triage-queue" element={guard(['it_lead'], <Navigate to="/itlead/triage" replace />)} />
        <Route path="/it-lead/priority" element={guard(['it_lead'], <Navigate to="/itlead/triage" replace />)} />
        <Route
          path="/it-lead/plan-review"
          element={guard(['it_lead'], <Navigate to="/itlead/plan-review" replace />)}
        />
        <Route
          path="/it-lead/plan-review-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/plan-review" replace />)}
        />
        <Route
          path="/itlead/plan-review-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/plan-review" replace />)}
        />
        <Route
          path="/it-lead/development"
          element={guard(['it_lead'], <Navigate to="/itlead/development" replace />)}
        />
        <Route
          path="/it-lead/development-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/development" replace />)}
        />
        <Route
          path="/itlead/development-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/development" replace />)}
        />
        <Route
          path="/it-lead/uat-assignment"
          element={guard(['it_lead'], <Navigate to="/itlead/uat-assignment" replace />)}
        />
        <Route
          path="/it-lead/uat-assignment-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/uat-assignment" replace />)}
        />
        <Route
          path="/itlead/uat-assignment-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/uat-assignment" replace />)}
        />
        <Route
          path="/it-lead/qa-assignment"
          element={guard(['it_lead'], <Navigate to="/itlead/uat-assignment" replace />)}
        />
        <Route
          path="/it-lead/qa-assignment-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/uat-assignment" replace />)}
        />
        <Route
          path="/itlead/qa-assignment"
          element={guard(['it_lead'], <Navigate to="/itlead/uat-assignment" replace />)}
        />
        <Route
          path="/itlead/qa-assignment-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/uat-assignment" replace />)}
        />
        <Route
          path="/it-lead/release-preparation"
          element={guard(['it_lead'], <Navigate to="/itlead/release-preparation" replace />)}
        />
        <Route
          path="/it-lead/approval-request-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/release-preparation" replace />)}
        />
        <Route
          path="/it-lead/technical-approval-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/release-preparation" replace />)}
        />
        <Route
          path="/itlead/approval-request-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/release-preparation" replace />)}
        />
        <Route
          path="/itlead/technical-approval-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/release-preparation" replace />)}
        />
        <Route
          path="/it-lead/approvals"
          element={guard(['it_lead'], <Navigate to="/itlead/release-preparation" replace />)}
        />
        <Route
          path="/itlead/approvals"
          element={guard(['it_lead'], <Navigate to="/itlead/release-preparation" replace />)}
        />
        <Route path="/it-lead/deployment" element={guard(['it_lead'], <Navigate to="/itlead/deployment" replace />)} />
        <Route
          path="/it-lead/deployment-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/deployment" replace />)}
        />
        <Route
          path="/itlead/deployment-queue"
          element={guard(['it_lead'], <Navigate to="/itlead/deployment" replace />)}
        />
        <Route path="/it-lead/alerts" element={guard(['it_lead'], <Navigate to="/itlead/alerts" replace />)} />
        <Route path="/it-lead/reports" element={guard(['it_lead'], <Navigate to="/itlead/reports" replace />)} />

        <Route
          path="/pic/dashboard"
          element={guard(['pic', 'pic_it_support', 'pic_it_develop', 'supervisor_it'], <PicUnifiedDashboard />)}
        />
        <Route
          path="/pic/tickets"
          element={guard(['pic', 'pic_it_support', 'pic_it_develop', 'supervisor_it'], <PicTicketList />)}
        />
        <Route
          path="/pic/tickets/:id"
          element={guard(['pic', 'pic_it_support', 'pic_it_develop', 'supervisor_it'], <PicTicketDetail />)}
        />
        <Route
          path="/pic/workspace"
          element={guard(['pic', 'pic_it_support', 'pic_it_develop', 'supervisor_it'], <Workspace />)}
        />
        <Route
          path="/pic/rca"
          element={guard(
            ['pic', 'pic_it_support', 'pic_it_develop', 'supervisor_it'],
            <Navigate to="/pic/workspace" replace />,
          )}
        />
        <Route
          path="/pic/assignments"
          element={guard(
            ['pic', 'pic_it_support', 'pic_it_develop', 'supervisor_it'],
            <Navigate to="/pic/tickets" replace />,
          )}
        />
        <Route
          path="/pic/testing"
          element={guard(['pic', 'pic_it_support', 'pic_it_develop', 'supervisor_it'], <InternalTestingPIC />)}
        />
        <Route
          path="/pic/release-preparation"
          element={guard(['pic', 'pic_it_support', 'pic_it_develop', 'supervisor_it'], <ReleasePreparationPIC />)}
        />
        <Route
          path="/pic/reports"
          element={guard(
            ['pic', 'pic_it_support', 'pic_it_develop', 'supervisor_it'],
            <AnalyticsDashboard role="pic" />,
          )}
        />

        <Route path="/qa/dashboard" element={guard(['qa'], <QADashboard />)} />
        <Route path="/qa/testing" element={guard(['qa'], <TestingForm />)} />
        <Route path="/qa/assignments" element={guard(['qa'], <Navigate to="/qa/dashboard" replace />)} />

        <Route path="/manager/approvals" element={guard(['manager'], <ManagerApproval />)} />
        <Route
          path="/manager/business-approval-queue"
          element={guard(['manager'], <Navigate to="/manager/approvals" replace />)}
        />
        <Route path="/manager/dashboard" element={guard(['manager'], <Navigate to="/manager/approvals" replace />)} />
        <Route path="/manager/alerts" element={guard(['manager'], <ManagerAlerts />)} />
        <Route path="/manager/reports" element={guard(['manager'], <AnalyticsDashboard role="manager" />)} />

        <Route path="/sla-monitoring" element={<HomeRedirect />} />
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
        <Route
          path="/executive/dashboard"
          element={guard(['executive'], <Navigate to="/executive/reports" replace />)}
        />
        <Route
          path="/executive/statistics"
          element={guard(['executive'], <Navigate to="/executive/reports" replace />)}
        />
        <Route path="/executive/reports" element={guard(['executive'], <AnalyticsDashboard role="executive" />)} />
        <Route path="/admin/dashboard" element={guard(['admin'], <Navigate to="/admin/divisions" replace />)} />
        <Route path="/admin/console" element={guard(['admin'], <Navigate to="/admin/divisions" replace />)} />
        <Route path="/admin/users" element={guard(['admin'], <Navigate to="/admin/divisions" replace />)} />
        <Route path="/admin/divisions" element={guard(['admin'], <DivisionManagement />)} />
        <Route path="/admin/sla-rules" element={guard(['admin'], <SLARules />)} />
        <Route path="/admin/workflows" element={guard(['admin'], <WorkflowList />)} />
        <Route path="/admin/workflows/new" element={guard(['admin'], <WorkflowForm />)} />
        <Route path="/admin/workflows/:id" element={guard(['admin'], <WorkflowDetail />)} />
        <Route path="/admin/workflows/:id/edit" element={guard(['admin'], <WorkflowForm />)} />
        <Route path="/admin/escalation" element={guard(['admin'], <Navigate to="/admin/sla-rules" replace />)} />
        <Route path="/admin/audit-log" element={guard(['admin'], <Navigate to="/admin/divisions" replace />)} />
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
