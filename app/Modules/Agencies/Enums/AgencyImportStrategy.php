<?php

namespace App\Modules\Agencies\Enums;

enum AgencyImportStrategy: string
{
    case IgnoreExisting = 'ignore_existing';
    case UpdateExisting = 'update_existing';
    case CreateOnlyNew = 'create_only_new';
    case MarkConflicts = 'mark_conflicts';
}
