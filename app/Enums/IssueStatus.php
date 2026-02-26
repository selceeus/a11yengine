<?php

namespace App\Enums;

enum IssueStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case AcceptedRisk = 'accepted_risk';
}
