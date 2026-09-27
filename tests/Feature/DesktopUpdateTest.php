<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DesktopUpdateTest extends TestCase
{
    public function test_desktop_update_exposes_the_latest_release_and_three_history_items(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put('releases/desktop/CodeRED-Desktop-1.10.0.zip', 'zip');
        $disk->put('releases/desktop/manifest.json', json_encode([
            'version' => '1.10.0',
            'file' => 'CodeRED-Desktop-1.10.0.zip',
            'sha256' => str_repeat('a', 64),
            'notes' => 'Agrega el módulo de agencias.',
        ], JSON_THROW_ON_ERROR));
        $disk->put('releases/desktop/history.json', json_encode([
            ['version' => '1.10.0', 'released_at' => '2026-09-27T10:00:00Z', 'notes' => 'Agencias'],
            ['version' => '1.9.4', 'released_at' => '2026-09-21T10:00:00Z', 'notes' => 'Diseño oni'],
            ['version' => '1.9.3', 'released_at' => '2026-09-14T10:00:00Z', 'notes' => null],
            ['version' => '1.9.2', 'released_at' => '2026-09-13T10:00:00Z', 'notes' => 'No debe aparecer'],
        ], JSON_THROW_ON_ERROR));

        $this->getJson('/api/v1/desktop/update')
            ->assertOk()
            ->assertJsonPath('data.release.version', '1.10.0')
            ->assertJsonPath('data.release.notes', 'Agrega el módulo de agencias.')
            ->assertJsonCount(3, 'data.history')
            ->assertJsonPath('data.history.0.version', '1.10.0')
            ->assertJsonPath('data.history.2.notes', null);
    }
}
