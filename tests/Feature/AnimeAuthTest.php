<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnimeAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_anime_api_requires_authentication_and_anime_ability(): void
    {
        $this->getJson('/api/v1/anime/search?q=one%20piece')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'No autenticado.']);

        $token = User::factory()->create()->createToken('Perfil', ['profile:read'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/anime/search?q=one%20piece')
            ->assertForbidden()
            ->assertExactJson(['message' => 'El token no tiene permiso para realizar esta acción.']);
    }
}
