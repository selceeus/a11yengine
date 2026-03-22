<?php

namespace App\Enums;

enum IssueActivityType: string
{
    case Comment = 'comment';
    case StatusChange = 'status_change';
    case Assignment = 'assignment';
    case DueDateChange = 'due_date_change';
    case BulkAction = 'bulk_action';
}
