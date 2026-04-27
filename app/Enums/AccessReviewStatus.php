<?php

namespace App\Enums;

enum AccessReviewStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}
