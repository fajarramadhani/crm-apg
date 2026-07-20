<?php

namespace App\Enums;

enum MonitoringCheckStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case NotApplicable = 'not_applicable';
}
