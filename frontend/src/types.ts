export type Role =
  | 'requester'
  | 'supervisor'
  | 'it_lead'
  | 'pic'
  | 'qa'
  | 'manager'
  | 'executive'
  | 'superadmin'
  | 'supervisor_it'
  | 'pic_it_support'
  | 'pic_it_develop'

export interface AuthenticatedUser {
  id: number
  name: string
  email: string
  role: { key: Role; name: string }
  permissions: string[]
  must_change_password?: boolean
}
export type TicketStatus =
  | 'draft'
  | 'pending_validation'
  | 'validated'
  | 'rejected'
  | 'need_revision'
  | 'cancelled'
  | 'transferred'
  | 'revision'
  | 'triage'
  | 'assigned'
  | 'analysis'
  | 'solution_planning'
  | 'plan_review'
  | 'ready_for_development'
  | 'development_in_progress'
  | 'in_progress'
  | 'internal_testing'
  | 'ready_for_qa'
  | 'qa_assignment'
  | 'qa_in_progress'
  | 'qa_failed'
  | 'qa_retest'
  | 'ready_for_uat'
  | 'uat_assignment'
  | 'uat_in_progress'
  | 'uat_failed'
  | 'uat_retest'
  | 'uat_approved'
  | 'approval_pending'
  | 'approval_revision'
  | 'release_preparation'
  | 'release_ready'
  | 'uat'
  | 'pending_approval'
  | 'approved'
  | 'deploying'
  | 'done'
  | 'closed'
  | 'over_sla'
  | 'under_analysis'
  | 'need_info'
  | 'waiting_external'
  | 'on_hold'
  | 'reopened'
  | 'submitted'

export type Priority = 'critical' | 'high' | 'medium' | 'low'
export type TicketCategory = 'incident' | 'request' | 'change' | 'problem'

export interface User {
  id: string
  name: string
  email: string
  role: Role
  division: string
  avatar: string
}

export interface Ticket {
  id: string
  title: string
  description: string
  affectedUrl?: string
  reference?: string
  category: TicketCategory
  priority: Priority
  status: TicketStatus
  application: string
  division: string
  requester: string
  requesterId: string
  supervisor: string
  pic: string
  picId: string
  createdAt: string
  updatedAt: string
  slaDeadline: string
  slaHours: number
  slaRemaining: number
  overSla: boolean
  tags: string[]
  attachments: string[]
}

export interface ActivityLog {
  id: string
  ticketId: string
  actor: string
  actorRole: Role
  action: string
  comment: string
  timestamp: string
  type: 'status_change' | 'comment' | 'assignment' | 'attachment' | 'escalation'
}

export interface Notification {
  id: string
  userId: string
  title: string
  message: string
  type: 'info' | 'warning' | 'error' | 'success'
  read: boolean
  ticketId?: string
  createdAt: string
}

export interface SupervisorDashboardStats {
  new_tickets: number
  under_analysis: number
  unassigned: number
  in_progress: number
  waiting_info: number
  waiting_external: number
  pending_final_review: number
  overdue: number
  completed_today: number
}

export interface AuditTimelineItem {
  id: string
  type: 'status_change' | 'assignment' | 'comment'
  action: string
  from_status?: string
  to_status?: string
  actor?: { id: number; name: string } | null
  actor_role?: string | null
  from_user?: { id: number; name: string } | null
  to_user?: { id: number; name: string } | null
  assignment_type?: string
  notes?: string | null
  reason?: string | null
  comment?: string
  is_internal?: boolean
  timestamp: string
}

export interface EligibleAssignee {
  id: number
  name: string
  email: string
  role: { id: number; key: string; name: string }
  division: { id: number; name: string } | null
  branch: { id: number; name: string } | null
  active_ticket_count: number
}

export interface PicDashboardStats {
  new_assigned: number
  in_progress: number
  waiting_info: number
  waiting_external: number
  need_revision: number
  nearing_due: number
  overdue: number
  pending_supervisor_check: number
}
