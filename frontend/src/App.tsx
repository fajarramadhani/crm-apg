import { lazy, Suspense, type ReactNode } from 'react'
import { Loader2 } from 'lucide-react'
import { BrowserRouter, Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom'
import { Layout } from './components/Layout'
import { PublicLoadingScreen } from './components/public/PublicLoadingScreen'
import { AuthProvider, useAuth } from './context/AuthContext'
import { LoadingProvider } from './context/LoadingContext'
import type { Role } from './types'

import Login from './pages/Login'
import Unauthorized from './pages/Unauthorized'
import NotFound from './pages/NotFound'

const CreateTicket = lazy(() => import('./pages/user/CreateTicket'))
const PublicRequest = lazy(() => import('./pages/public/PublicRequest'))
const PublicTicketTracking = lazy(() => import('./pages/public/PublicTicketTracking'))
const PublicRequestHistory = lazy(() => import('./pages/public/PublicRequestHistory'))
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
const UserManagement = lazy(() => import('./pages/admin/UserManagement'))
const OfficeManagement = lazy(() => import('./pages/admin/OfficeManagement'))
const SLARules = lazy(() => import('./pages/admin/SLARules'))
const KnowledgeBaseList = lazy(() => import('./pages/knowledgeBase/KnowledgeBaseList'))
const KnowledgeBaseDetail = lazy(() => import('./pages/knowledgeBase/KnowledgeBaseDetail'))
const KnowledgeBaseForm = lazy(() => import('./pages/knowledgeBase/KnowledgeBaseForm'))
const KnowledgeBaseReview = lazy(() => import('./pages/knowledgeBase/KnowledgeBaseReview'))
const KnowledgeBaseTags = lazy(() => import('./pages/settings/KnowledgeBaseTags'))

const WorkflowList = lazy(() => import('./pages/admin/workflows/WorkflowList'))
const WorkflowForm = lazy(() => import('./pages/admin/workflows/WorkflowForm'))
const WorkflowDetail = lazy(() => import('./pages/admin/workflows/WorkflowDetail'))
const WhatsAppSettingsPage = lazy(() => import('./pages/admin/WhatsAppSettingsPage'))

const SupervisorItDashboard = lazy(() => import('./pages/supervisorIt/SupervisorDashboard'))
const SupervisorTicketList = lazy(() => import('./pages/supervisorIt/SupervisorTicketList'))
const SupervisorTicketDetail = lazy(() => import('./pages/supervisorIt/SupervisorTicketDetail'))

const PicUnifiedDashboard = lazy(() =>
  import('./pages/pic/PicUnifiedDashboard').then((m) => ({ default: m.PicUnifiedDashboard })),
)
const PicTicketList = lazy(() => import('./pages/pic/PicTicketList').then((m) => ({ default: m.PicTicketList })))
const PicTicketDetail = lazy(() => import('./pages/pic/PicTicketDetail').then((m) => ({ default: m.PicTicketDetail })))
const ForceChangePassword = lazy(() => import('./pages/settings/ChangePassword'))

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
  superadmin: '/admin/users',
}

function GlobalLoader({ label, fullscreen = false }: { label: string; fullscreen?: boolean }) {
  if (fullscreen) {
    return (
      <div
        className="fixed inset-0 z-[9999] overflow-hidden bg-gradient-to-br from-[#081225] via-[#0F2554] to-[#1E3A8A] text-white"
        role="status"
        aria-live="polite"
      >
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(96,165,250,0.26),_transparent_38%),radial-gradient(circle_at_bottom_left,_rgba(34,211,238,0.18),_transparent_28%)]" />
        <div className="absolute -left-24 top-1/3 h-72 w-72 rounded-full bg-cyan-400/15 blur-3xl" />
        <div className="absolute right-[-6rem] top-20 h-80 w-80 rounded-full bg-blue-500/20 blur-3xl" />
        <div className="absolute bottom-[-5rem] left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-indigo-500/15 blur-3xl" />

        <div className="relative flex min-h-screen flex-col items-center justify-center px-6 text-center">
          <div className="absolute top-0 left-0 h-1 w-full overflow-hidden bg-white/10">
            <div className="global-loader-bar h-full w-1/3 rounded-full bg-gradient-to-r from-cyan-300 via-blue-400 to-indigo-300" />
          </div>

          <div className="relative mb-8 flex h-28 w-28 items-center justify-center">
            <span className="absolute h-28 w-28 rounded-full border border-blue-200/20" />
            <span className="absolute h-20 w-20 rounded-full border border-cyan-200/30" />
            <span className="absolute h-32 w-32 rounded-full border border-white/10 animate-pulse" />
            <span className="absolute h-40 w-40 rounded-full bg-cyan-300/10 blur-3xl" />
            <div className="relative flex h-20 w-20 items-center justify-center rounded-3xl bg-white p-2 text-[#0F2554] shadow-2xl shadow-black/30 overflow-hidden">
              <img src="/logo/apg-logo.png" alt="APG Logo" className="w-full h-full object-contain" />
            </div>
          </div>

          <p className="text-sm uppercase tracking-[0.45em] text-blue-100/60">Tic Hub</p>
          <h2 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Menyiapkan pengalaman terbaik...</h2>
          <p className="mt-4 max-w-md text-sm leading-6 text-blue-100/80 sm:text-base">{label}</p>

          <div className="mt-8 flex items-center gap-3 rounded-full border border-white/10 bg-white/8 px-5 py-3 backdrop-blur-md">
            <Loader2 className="h-5 w-5 animate-spin text-cyan-300" />
            <span className="text-sm font-medium text-blue-50">Mohon tunggu sebentar</span>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div
      className="flex min-h-[24rem] items-center justify-center rounded-3xl bg-gradient-to-br from-[#0F2554] via-[#16397f] to-[#0b1227] p-6 text-white"
      role="status"
      aria-live="polite"
    >
      <div className="relative overflow-hidden rounded-[2rem] border border-white/10 bg-white/10 px-8 py-8 shadow-2xl backdrop-blur-xl">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(96,165,250,0.32),_transparent_55%)]" />
        <div className="relative flex min-w-[260px] flex-col items-center gap-5 text-center">
          <div className="relative flex h-18 w-18 items-center justify-center">
            <span className="absolute h-18 w-18 rounded-full border border-blue-300/25" />
            <span className="absolute h-14 w-14 rounded-full border border-cyan-300/30" />
            <span className="absolute h-24 w-24 animate-ping rounded-full bg-blue-400/10" />
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-white p-1.5 shadow-lg shadow-blue-950/30 overflow-hidden">
              <img src="/logo/apg-logo.png" alt="APG Logo" className="w-full h-full object-contain" />
            </div>
          </div>

          <div className="space-y-1">
            <p className="text-lg font-semibold tracking-wide">Tic Hub</p>
            <p className="text-sm text-blue-100/80">{label}</p>
          </div>

          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.28em] text-blue-100/60">
            <Loader2 className="h-4 w-4 animate-spin" />
            Please wait
          </div>

          <div className="h-1.5 w-48 overflow-hidden rounded-full bg-white/10">
            <div className="global-loader-bar h-full w-1/2 rounded-full bg-gradient-to-r from-cyan-300 via-blue-400 to-indigo-400" />
          </div>
        </div>
      </div>
    </div>
  )
}

function LoadingSession() {
  return <GlobalLoader label="Memeriksa sesi dan menyiapkan workspace..." fullscreen />
}

function LoadingPage() {
  return <GlobalLoader label="Memuat halaman, sebentar ya..." />
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
  const location = useLocation()
  if (!user) return null
  // Users flagged for mandatory password rotation are locked out of every
  // workspace route until they finish the rotation on the dedicated screen.
  if (user.must_change_password && location.pathname !== '/change-password') {
    return <Navigate to="/change-password" replace />
  }
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
  const allRoles: Role[] = [
    'requester',
    'supervisor',
    'supervisor_it',
    'it_lead',
    'pic',
    'pic_it_support',
    'pic_it_develop',
    'qa',
    'manager',
    'executive',
    'superadmin',
  ]
  const guard = (roles: Role[], page: ReactNode) => <RequireRole allowed={roles}>{page}</RequireRole>
  const permit = (permissions: string[], page: ReactNode) => (
    <RequirePermission allowed={permissions}>{page}</RequirePermission>
  )

  return (
    <Routes>
      <Route path="/login" element={<LoginRoute />} />
      <Route
        path="/request"
        element={
          <Suspense fallback={<PublicLoadingScreen label="Menyiapkan formulir pengajuan..." />}>
            <PublicRequest />
          </Suspense>
        }
      />
      <Route
        path="/track/:token"
        element={
          <Suspense fallback={<PublicLoadingScreen label="Menyiapkan pelacakan tiket..." />}>
            <PublicTicketTracking />
          </Suspense>
        }
      />
      <Route
        path="/request/history"
        element={
          <Suspense fallback={<PublicLoadingScreen label="Menyiapkan riwayat pengajuan..." />}>
            <PublicRequestHistory />
          </Suspense>
        }
      />
      <Route
        element={
          <RequireAuth>
            <AuthenticatedLayout />
          </RequireAuth>
        }
      >
        <Route index element={<HomeRedirect />} />
        <Route
          path="/change-password"
          element={
            <Suspense fallback={<LoadingPage />}>
              <ForceChangePassword />
            </Suspense>
          }
        />
        <Route path="/user/dashboard" element={guard(['requester'], <Navigate to="/user/tickets" replace />)} />
        <Route path="/user/create-ticket" element={guard(['requester'], <CreateTicket />)} />
        <Route path="/user/tickets" element={guard(allRoles, <TicketHistory />)} />
        <Route path="/user/tickets/:id" element={guard(allRoles, <TicketDetail />)} />
        <Route path="/tickets" element={<Navigate to="/user/tickets" replace />} />
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
        <Route path="/admin/dashboard" element={guard(['superadmin'], <Navigate to="/admin/users" replace />)} />
        <Route path="/admin/console" element={guard(['superadmin'], <Navigate to="/admin/users" replace />)} />
        <Route path="/admin/users" element={permit(['users.manage'], <UserManagement />)} />
        <Route path="/admin/offices" element={guard(['superadmin'], <OfficeManagement />)} />
        <Route path="/admin/divisions" element={guard(['superadmin'], <DivisionManagement />)} />
        <Route path="/admin/sla-rules" element={guard(['superadmin'], <SLARules />)} />
        <Route path="/admin/workflows" element={guard(['superadmin'], <WorkflowList />)} />
        <Route path="/admin/workflows/new" element={guard(['superadmin'], <WorkflowForm />)} />
        <Route path="/admin/workflows/:id" element={guard(['superadmin'], <WorkflowDetail />)} />
        <Route path="/admin/workflows/:id/edit" element={guard(['superadmin'], <WorkflowForm />)} />
        <Route path="/admin/whatsapp" element={permit(['notification.whatsapp.manage'], <WhatsAppSettingsPage />)} />
        <Route path="/admin/escalation" element={guard(['superadmin'], <Navigate to="/admin/sla-rules" replace />)} />
        <Route path="/admin/audit-log" element={guard(['superadmin'], <Navigate to="/admin/divisions" replace />)} />
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
      <LoadingProvider>
        <AuthProvider>
          <AppRoutes />
        </AuthProvider>
      </LoadingProvider>
    </BrowserRouter>
  )
}
