export interface TicketReference {
  id: number
  number: string
}

export interface Notification {
  id: string
  type: string
  severity: 'info' | 'success' | 'warning' | 'critical'
  title: string
  message: string
  ticket: TicketReference | null
  action_url: string | null
  is_read: boolean
  is_archived: boolean
  created_at: string
}

export interface NotificationDetail extends Notification {
  metadata: Record<string, any>
  read_at: string | null
}

export interface NotificationPreference {
  id: number
  notification_type: string
  in_app_enabled: boolean
  muted_until: string | null
}

export interface SlaEscalationPolicy {
  id: number
  name: string
  priority: string | null
  sla_type: 'response' | 'resolution'
  warning_threshold_percent: number
  critical_threshold_percent: number
  inactivity_threshold_minutes: number | null
  escalate_to_it_lead: boolean
  escalate_to_manager: boolean
  escalate_to_supervisor: boolean
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface TicketSlaAlert {
  id: number
  ticket: {
    id: number
    number: string
    priority: string | null
    status: string
    application_name: string | null
    requester_name: string | null
    pic_name: string | null
  }
  sla_type: string
  alert_level: 'approaching' | 'critical' | 'breached'
  threshold_percent: number
  elapsed_minutes: number
  target_minutes: number
  remaining_minutes: number
  triggered_at: string
  resolved_at: string | null
  action_url: string | null
}

export interface OperationalAlertSummary {
  sla_critical_count: number
  sla_breached_count: number
  high_risk_application_count: number
  critical_deployment_failure_count: number
  post_release_incident_count: number
  alert_trend: any[]
}
