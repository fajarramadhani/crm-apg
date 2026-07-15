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
    case Rejected = 'rejected';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';
}
