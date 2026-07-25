<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Models\AgencyImportRun;
use Livewire\Component;

class ShalomSyncRun extends Component
{
    public AgencyImportRun $importRun;

    public function mount(AgencyImportRun $importRun)
    {
        $this->importRun = $importRun;
    }

    public function render()
    {
        return view('livewire.admin.agencies.shalom-sync-run');
    }
}
