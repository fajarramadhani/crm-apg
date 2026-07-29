<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Draft = 'draft';
    case PendingValidation = 'pending_validation';
    case NeedRevision = 'need_revision';
    case Validated = 'validated';
    case Triage = 'triage';
    case Assigned = 'assigned';
    case Analysis = 'analysis';
    case SolutionPlanning = 'solution_planning';
    case PlanReview = 'plan_review';
    case ReadyForDevelopment = 'ready_for_development';
    case DevelopmentInProgress = 'development_in_progress';
    case InternalTesting = 'internal_testing';
    case ReadyForQa = 'ready_for_qa';
    case QaAssignment = 'qa_assignment';
    case QaInProgress = 'qa_in_progress';
    case QaFailed = 'qa_failed';
    case QaRetest = 'qa_retest';
    case UatAssignment = 'uat_assignment';
    case UatInProgress = 'uat_in_progress';
    case UatFailed = 'uat_failed';
    case UatRetest = 'uat_retest';
    case UatApproved = 'uat_approved';
    case ApprovalPending = 'approval_pending';
    case ApprovalRevision = 'approval_revision';
    case ReleasePreparation = 'release_preparation';
    case ReleaseReady = 'release_ready';
    case DeploymentScheduled = 'deployment_scheduled';
    case DeploymentInProgress = 'deployment_in_progress';
    case DeploymentFailed = 'deployment_failed';
    case RollbackInProgress = 'rollback_in_progress';
    case RolledBack = 'rolled_back';
    case Deployed = 'deployed';
    case Monitoring = 'monitoring';
    case PostReleaseIssue = 'post_release_issue';
    case AwaitingRequesterConfirmation = 'awaiting_requester_confirmation';
    case Reopened = 'reopened';
    case Closed = 'closed';
    case ReadyForUat = 'ready_for_uat';
    case Rejected = 'rejected';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';
    case UnderAnalysis = 'under_analysis';
    case NeedInfo = 'need_info';
    case WaitingExternal = 'waiting_external';
    case OnHold = 'on_hold';
    case PendingApproval = 'pending_approval';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Done = 'done';
    case Revision = 'revision';
}
