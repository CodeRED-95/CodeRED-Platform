<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Jobs\SyncShalomAgenciesJob;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class ShalomSync extends Component
{
    use WithFileUploads;

    public $chosenFile;
    public $importRun;

    public function sync()
    {
        $this->validate([
            'chosenFile' => 'required|file',
        ]);

        $path = $this->chosenFile->store('imports/shalom');

        $this->importRun = AgencyImportRun::create([
            'type' => 'shalom_sync',
            'status' => 'pending',
            'chosen_original_name' => $this->chosenFile->getClientOriginalName(),
            'chosen_storage_path' => $path,
            'created_by' => Auth::id(),
        ]);

        SyncShalomAgenciesJob::dispatch($this->importRun->id, $path);

        return redirect()->route('admin.agencies.import.run', $this->importRun->id);
    }

    public function render()
    {
        return view('livewire.admin.agencies.shalom-sync');
    }
}
