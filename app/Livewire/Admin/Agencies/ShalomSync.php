<?php

namespace App\Livewire\Admin\Agencies;

use App\Modules\Agencies\Jobs\SyncShalomAgenciesJob;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ShalomSync extends Component
{
    use WithFileUploads;

    public $chosenFile;

    public function mount(): void
    {
        Gate::authorize('import', Agency::class);
    }

    public function sync()
    {
        Gate::authorize('import', Agency::class);

        $maxKb = max(1, (int) config('services.shalom_extractor.max_file_mb', 10)) * 1024;
        $this->validate(['chosenFile' => ['required', 'file', 'max:'.$maxKb]]);

        $run = AgencyImportRun::create([
            'type' => 'shalom_sync',
            'status' => 'pending',
            'stage' => 'En cola',
            'chosen_original_name' => $this->chosenFile->getClientOriginalName(),
            'created_by' => Auth::id(),
        ]);

        $directory = 'imports/shalom/'.$run->id;
        $path = $this->chosenFile->storeAs($directory, 'chosen-original.json');
        $run->update(['chosen_storage_path' => $path]);
        Storage::put($directory.'/extractor.log', '['.now()->toIso8601String()."] Ejecución creada.\n");

        SyncShalomAgenciesJob::dispatch($run->id, $path)
            ->onConnection('redis')
            ->onQueue('agency-imports')
            ->afterCommit();

        return redirect()->route('admin.agencies.import.run', $run);
    }

    public function render()
    {
        return view('livewire.admin.agencies.shalom-sync')->layout('layouts.app', ['pageTitle' => 'Sincronización Shalom']);
    }
}
