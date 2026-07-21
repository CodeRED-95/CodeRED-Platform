<?php

namespace App\Core\Api\Enums;

enum ApiRequestType: string
{
    case Api = 'api';
    case AdminTest = 'admin_test';
    case ProviderTest = 'provider_test';
}
