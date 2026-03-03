<?php

namespace App\Enums;

enum ScanPageStatus: string
{
    case Pending = 'pending';
    case Scanned = 'scanned';
    case Failed = 'failed';
}
