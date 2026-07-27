<?php

namespace App\Enums;

enum IntegrationPairingStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
