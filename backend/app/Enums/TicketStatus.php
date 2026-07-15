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
    case Rejected = 'rejected';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';
}
