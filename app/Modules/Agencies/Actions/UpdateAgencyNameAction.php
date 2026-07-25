<?php

namespace App\Modules\Agencies\Actions;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyNameHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UpdateAgencyNameAction
{
    public function __invoke(
        Agency $agency,
        string $newName,
        string $source,
        ?int $importRunId = null,
        ?int $changedById = null
    ): bool {
        $currentName = $agency->getOriginal('name') ?: $agency->name;
        $newName = trim(html_entity_decode($newName, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($newName === '' || ! $agency->exists || blank($currentName)) {
            return false;
        }

        if ($this->normalizeName((string) $currentName) === $this->normalizeName($newName)) {
            return false;
        }

        $oldName = (string) $currentName;
        $agency->old_name = $oldName;
        $agency->name = $newName;

        $alreadyRecorded = AgencyNameHistory::query()
            ->where('agency_id', $agency->id)
            ->where('old_name', $oldName)
            ->where('new_name', $newName)
            ->where('source', $source)
            ->when($importRunId, fn ($query) => $query->where('import_run_id', $importRunId))
            ->exists();

        if ($alreadyRecorded) {
            return true;
        }

        AgencyNameHistory::create([
            'agency_id' => $agency->id,
            'old_name' => $oldName,
            'new_name' => $newName,
            'source' => $source,
            'import_run_id' => $importRunId,
            'changed_by' => $changedById ?? Auth::id(),
            'changed_at' => now(),
        ]);

        return true;
    }

    public function normalizeName(string $name): string
    {
        return Str::of($name)
            ->trim()
            ->pipe(fn ($value) => html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->upper()
            ->ascii()
            ->replaceMatches('/[^\pL\pN\s]/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
