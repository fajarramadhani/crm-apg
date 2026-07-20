<?php

namespace App\Enums;

enum DeploymentStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
