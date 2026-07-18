<?php

namespace Tests\Feature;

use Tests\TestCase;

class ViteManifestTest extends TestCase
{
    public function test_vite_manifest_exists_and_points_to_current_assets(): void
    {
        $manifestPath = public_path('build/manifest.json');

        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('resources/css/app.css', $manifest);
        $this->assertArrayHasKey('resources/js/app.js', $manifest);
        $this->assertFileExists(public_path('build/'.$manifest['resources/css/app.css']['file']));
        $this->assertFileExists(public_path('build/'.$manifest['resources/js/app.js']['file']));
    }
}
