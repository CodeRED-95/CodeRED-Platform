<?php

namespace App\Modules\Ruc\Data;

class RollbackResult
{
    public bool $success = false;
    public int $recordsDeleted = 0;
    public int $recordsRestored = 0;
    public string $message = '';
}
