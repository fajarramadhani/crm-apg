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
    case ReadyForUat = 'ready_for_uat';
    case Rejected = 'rejected';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';
}
