<?php

namespace App\Enums;

enum DeploymentStepStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
