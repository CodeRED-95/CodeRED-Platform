<?php

namespace App\Modules\Agencies\Observers;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyChangeLog;
use App\Modules\Agencies\Support\AgencyVersion;

class AgencyObserver
{
    public function creating(Agency $agency): void
    {
        if (auth()->id() !== null) {
            $agency->created_by ??= auth()->id();
            $agency->updated_by ??= auth()->id();
        }
    }

    public function created(Agency $agency): void
    {
        $this->log($agency, 'created');
        $this->bump($agency);
    }

    public function updating(Agency $agency): void
    {
        if (auth()->id() !== null) {
            $agency->updated_by = auth()->id();
        }
    }

    public function updated(Agency $agency): void
    {
        $this->log($agency, 'updated');
        $this->bump($agency);
    }

    public function deleted(Agency $agency): void
    {
        if ($agency->isForceDeleting()) {
            return;
        }

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
