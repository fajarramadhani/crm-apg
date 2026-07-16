export type Role = 'requester' | 'supervisor' | 'it_lead' | 'pic' | 'qa' | 'manager' | 'executive' | 'admin'

export interface AuthenticatedUser {
  id: number
  name: string
  email: string
  role: { key: Role; name: string }
  permissions: string[]
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
  | 'uat'
  | 'pending_approval'
  | 'approved'
  | 'deploying'
  | 'done'
  | 'closed'
  | 'over_sla'

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
