<?php

namespace App\Modules\Ruc\Data;

class BatchInsertResult
{
    public int $inserted = 0;
    public int $updated = 0;
    public int $skipped = 0;

    public function total(): int
    {
        return $this->inserted + $this->updated + $this->skipped;
    }
}
