<?php

namespace App\Modules\Agencies\Observers;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyChangeLog;
use App\Modules\Agencies\Support\AgencyVersion;

class AgencyObserver
{
    public function created(Agency $agency): void
    {
        $this->log($agency, 'created');
        $this->bump($agency);
    }

    public function updated(Agency $agency): void
    {
        $this->log($agency, 'updated');
        $this->bump($agency);
    }

    public function deleted(Agency $agency): void
    {
        $this->log($agency, 'deleted');
        $this->bump($agency);
    }

    public function restored(Agency $agency): void
    {
        $this->log($agency, 'restored');
        $this->bump($agency);
    }

    private function log(Agency $agency, string $action): void
    {
        AgencyChangeLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_values' => $agency->getOriginal(),
            'new_values' => $agency->getAttributes(),
            'changed_fields' => array_keys($agency->getChanges()),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function bump(Agency $agency): void
    {
        $agency->forceFill(['data_version' => AgencyVersion::bump()])->saveQuietly();
    }
}
