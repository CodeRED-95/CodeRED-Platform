<?php

namespace Tests\Feature;

use App\Core\Api\Enums\ApiRequestType;
use App\Domain\DniNameSearch\Contracts\DniNameSearchProviderInterface;
use App\Domain\DniNameSearch\Data\DniNameMatch;
use App\Domain\DniNameSearch\Data\DniNameSearchResult;
use App\Livewire\Admin\ApiTools\DniNameSearchTester;
use App\Models\ApiRequestLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DniNameSearchTesterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('dni.name_search.enabled', true);
        config()->set('dni.name_search.cache_enabled', false);
        config()->set('dni.name_search.providers.dniperu.enabled', true);
    }

    public function test_only_users_with_the_dni_records_permission_can_access_the_page(): void
    {
        $this->actingAs($this->roleUser('super-admin'))
            ->get('/admin/api-tools/dni-name-search')
            ->assertOk()
            ->assertSee('Buscar DNI por nombres');

        $this->actingAs($this->roleUser('editor'))->get('/admin/api-tools/dni-name-search')->assertForbidden();
        $this->actingAs($this->roleUser('viewer'))->get('/admin/api-tools/dni-name-search')->assertForbidden();
    }

    public function test_it_shows_provider_matches_and_logs_the_search_without_storing_the_names(): void
    {
        $this->fakeProvider(DniNameSearchResult::found([
            new DniNameMatch('12345678', 'JUAN CARLOS', 'PEREZ', 'GOMEZ'),
        ]));

        Livewire::actingAs($this->roleUser('super-admin'))->test(DniNameSearchTester::class)
            ->set('nombres', 'Juan Carlos')
            ->set('apellidoPaterno', 'Perez')
            ->set('apellidoMaterno', 'Gomez')
            ->call('search')
            ->assertHasNoErrors()
            ->assertSet('matches.0.dni', '12345678')
            ->assertSet('matches.0.full_name', 'JUAN CARLOS PEREZ GOMEZ')
            ->assertSet('technical.match_count', 1)
            ->assertSet('errorMessage', null);

        $log = ApiRequestLog::query()->where('service', 'dni-name-search')->sole();

        // getAttribute() y no ->request_type: el modelo no declara @property y
        // larastan no infiere las columnas, asi que el acceso dinamico seria un
        // error de PHPStan que solo se puede callar metiendolo al baseline.
        self::assertSame(ApiRequestType::AdminTest, $log->getAttribute('request_type'));
        self::assertSame(200, (int) $log->getAttribute('status_code'));
        self::assertSame(hash('sha256', 'JUAN CARLOS|PEREZ|GOMEZ'), $log->getAttribute('identifier_hash'));

        // El log no debe contener los nombres en claro por ningún campo.
        self::assertStringNotContainsStringIgnoringCase('PEREZ', json_encode($log->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_it_reports_a_disabled_feature_instead_of_calling_the_provider(): void
    {
        config()->set('dni.name_search.enabled', false);
        $this->fakeProvider(DniNameSearchResult::found([new DniNameMatch('12345678', 'A', 'B', 'C')]));

        Livewire::actingAs($this->roleUser('super-admin'))->test(DniNameSearchTester::class)
            ->set('nombres', 'Juan')
            ->set('apellidoPaterno', 'Perez')
            ->set('apellidoMaterno', 'Gomez')
            ->call('search')
            ->assertSet('matches', null)
            ->assertSet('technical.result_status', 'provider_disabled')
            ->assertSet('technical.http_status', 503);
    }

    public function test_it_rejects_input_the_api_would_also_reject(): void
    {
        Livewire::actingAs($this->roleUser('super-admin'))->test(DniNameSearchTester::class)
            ->set('nombres', 'Juan9')
            ->set('apellidoPaterno', 'P')
            ->set('apellidoMaterno', '')
            ->call('search')
            ->assertHasErrors(['nombres', 'apellidoPaterno', 'apellidoMaterno']);

        self::assertSame(0, ApiRequestLog::query()->where('service', 'dni-name-search')->count());
    }

    private function fakeProvider(DniNameSearchResult $result): void
    {
        $this->app->bind(DniNameSearchProviderInterface::class, fn () => new class($result) implements DniNameSearchProviderInterface
        {
            public function __construct(private readonly DniNameSearchResult $result) {}

            public function isEnabled(): bool
            {
                return true;
            }

            public function search(string $nombres, string $apellidoPaterno, string $apellidoMaterno): DniNameSearchResult
            {
                return $this->result;
            }
        });
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
