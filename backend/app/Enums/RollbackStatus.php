<?php

namespace App\Enums;

enum RollbackStatus: string
{
    case Requested = 'requested';
    case InProgress = 'in_progress';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
