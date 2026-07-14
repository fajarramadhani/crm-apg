import { useState } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import type { Role } from './types'
import { Layout } from './components/Layout'

// Pages
import Login from './pages/Login'
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
import PICDashboard from './pages/pic/PICDashboard'
import Workspace from './pages/pic/Workspace'
import RCA from './pages/pic/RCA'
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
import NotFound from './pages/NotFound'

const DEFAULT_ROUTES: Record<Role, string> = {
  user: '/user/dashboard',
  supervisor: '/supervisor/dashboard',
  itlead: '/itlead/dashboard',
  pic: '/pic/dashboard',
  qa: '/qa/dashboard',
  manager: '/manager/approval',
  executive: '/executive/dashboard',
  admin: '/admin/console',
}

export default function App() {
  const [loggedIn, setLoggedIn] = useState(false)
  const [role, setRole] = useState<Role>('user')

  if (!loggedIn) {
    return (
      <BrowserRouter>
        <Login
          onLogin={(r) => {
            setRole(r)
            setLoggedIn(true)
          }}
        />
      </BrowserRouter>
    )
  }

  return (
    <BrowserRouter>
      <Layout
        role={role}
        setRole={setRole}
        onLogout={() => {
          setLoggedIn(false)
          setRole('user')
        }}
      >
        <Routes>
          {/* Default redirect */}
          <Route path="/" element={<Navigate to={DEFAULT_ROUTES[role]} replace />} />

          {/* USER ROUTES */}
          <Route path="/user/dashboard" element={<UserDashboard />} />
          <Route path="/user/create-ticket" element={<CreateTicket />} />
          <Route path="/user/tickets" element={<TicketHistory />} />
          <Route path="/user/tickets/:id" element={<TicketDetail />} />
          <Route path="/user/uat" element={<UAT />} />

          {/* SUPERVISOR ROUTES */}
          <Route path="/supervisor/dashboard" element={<SupervisorDashboard />} />
          <Route path="/supervisor/validation-queue" element={<ValidationQueue />} />

          {/* IT LEAD ROUTES */}
          <Route path="/itlead/dashboard" element={<ITLeadDashboard />} />
          <Route path="/itlead/triage" element={<TriageQueue />} />
          <Route path="/itlead/priority" element={<PriorityAssignment />} />

          {/* PIC ROUTES */}
          <Route path="/pic/dashboard" element={<PICDashboard />} />
          <Route path="/pic/workspace" element={<Workspace />} />
          <Route path="/pic/rca" element={<RCA />} />
          <Route path="/pic/testing" element={<InternalTestingPIC />} />

          {/* QA ROUTES */}
          <Route path="/qa/dashboard" element={<QADashboard />} />
          <Route path="/qa/testing" element={<TestingForm />} />

          {/* MANAGER ROUTES */}
          <Route path="/manager/approval" element={<ManagerApproval />} />

          {/* SHARED ROUTES (accessible by multiple roles) */}
          <Route path="/sla-monitoring" element={<SLAMonitoring />} />
          <Route path="/notifications" element={<NotificationCenter role={role} />} />

          {/* EXECUTIVE ROUTES */}
          <Route path="/executive/dashboard" element={<ExecutiveDashboard />} />
          <Route path="/executive/statistics" element={<Statistics />} />

          {/* ADMIN ROUTES */}
          <Route path="/admin/console" element={<AdminConsole />} />
          <Route path="/admin/users" element={<UserManagement />} />
          <Route path="/admin/divisions" element={<DivisionManagement />} />
          <Route path="/admin/sla-rules" element={<SLARules />} />
          <Route path="/admin/escalation" element={<EscalationMatrix />} />
          <Route path="/admin/audit-log" element={<AuditLog />} />

          {/* Fallback */}
          <Route path="*" element={<NotFound />} />
        </Routes>
      </Layout>
    </BrowserRouter>
  )
}
