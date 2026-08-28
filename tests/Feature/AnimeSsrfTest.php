<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AnimeSsrfTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_input_validation_blocks_suspicious_queries_before_provider_request(): void
    {
        Http::fake();

        $token = User::factory()->create()->createToken('Anime prueba', ['anime:read'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/anime/search?q=http://169.254.169.254/latest')
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_provider_allowlist_blocks_private_base_urls_before_network_request(): void
    {
        config([
            'anime.enabled' => true,
            'anime.providers.jkanime.enabled' => true,
            'anime.providers.jkanime.base_url' => 'https://169.254.169.254',
            'anime.providers.jkanime.allowed_hosts' => ['jkanime.test'],
            'anime.providers.anilist.enabled' => false,
        ]);
        Http::fake();

        $token = User::factory()->create()->createToken('Anime prueba', ['anime:read'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/anime/search?q=one%20piece')
            ->assertOk()
            ->assertJsonPath('data', []);

        Http::assertNothingSent();
    }
}
