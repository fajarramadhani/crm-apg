<?php

namespace App\Enums;

enum NotificationType: string
{
    case TicketSubmitted = 'ticket_submitted';
    case SupervisorValidationRequired = 'supervisor_validation_required';
    case TriageRequired = 'triage_required';
    case PicAssigned = 'pic_assigned';
    case QaAssignmentRequired = 'qa_assignment_required';
    case QaFailed = 'qa_failed';
    case QaPassed = 'qa_passed';
    case UatAssignmentRequired = 'uat_assignment_required';
    case UatRejected = 'uat_rejected';
    case UatApproved = 'uat_approved';
    case BusinessApprovalRequired = 'business_approval_required';
    case TechnicalApprovalRequired = 'technical_approval_required';
    case ApprovalRejected = 'approval_rejected';
    case ReleaseReady = 'release_ready';
    case DeploymentScheduled = 'deployment_scheduled';
    case DeploymentFailed = 'deployment_failed';
    case RollbackRequired = 'rollback_required';
    case MonitoringIssueDetected = 'monitoring_issue_detected';
    case RequesterConfirmationRequired = 'requester_confirmation_required';
    case RequesterRejected = 'requester_rejected';
    case TicketClosed = 'ticket_closed';
    case SlaApproaching = 'sla_approaching';
    case SlaBreached = 'sla_breached';
    case TicketInactive = 'ticket_inactive';
    case TicketEscalated = 'ticket_escalated';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
