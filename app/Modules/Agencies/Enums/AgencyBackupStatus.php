<?php

namespace App\Modules\Agencies\Enums;

enum AgencyBackupStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
