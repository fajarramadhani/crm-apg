<?php

namespace App\Enums;

enum MonitoringStatus: string
{
    case Active = 'active';
    case Healthy = 'healthy';
    case IssueDetected = 'issue_detected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
