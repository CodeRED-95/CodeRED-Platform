<?php

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Form;
use App\Livewire\Admin\Agencies\Index;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencySize;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyChangeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_creates_normalized_manual_agency(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(Form::class)
            ->set('code', ' ag-100 ')
            ->set('name', '  Agencia   Central  ')
            ->set('short_name', ' Central ')
            ->set('slug', ' Agencia Central ')
            ->set('department', ' Lima ')
            ->set('province', ' Lima ')
            ->set('district', ' Miraflores ')
            ->set('address', ' Av. Principal 123 ')
            ->set('email', ' CONTACTO@EXAMPLE.TEST ')
            ->set('servicesInput', "Envíos, Paquetería\nRecojo")
            ->set('status', AgencyStatus::Active->value)
            ->set('size', AgencySize::Medium->value)
            ->set('is_operations_center', true)
            ->set('source', 'github_gist')
            ->set('source_reference', 'no-debe-persistir')
            ->call('save')
            ->assertHasNoErrors();

        $agency = Agency::query()->where('code', 'AG-100')->firstOrFail();

        $this->assertSame('Agencia Central', $agency->name);
        $this->assertSame('agencia-central', $agency->slug);
        $this->assertSame('contacto@example.test', $agency->email);
        $this->assertSame('manual', $agency->source);
        $this->assertNull($agency->source_reference);
        $this->assertSame(['Envíos', 'Paquetería', 'Recojo'], $agency->services);
        $this->assertSame(AgencyStatus::Active, $agency->status);
        $this->assertSame(AgencySize::Medium, $agency->size);
        $this->assertTrue($agency->is_operations_center);
        $this->assertTrue($agency->changeLogs()->where('action', 'created')->exists());
    }

    public function test_authorized_user_edits_agency_without_changing_provenance(): void
    {
        $this->actingAs($this->superAdmin());
        $agency = Agency::factory()->create([
            'code' => 'AG-OLD',
            'name' => 'Agencia Original',
            'slug' => 'agencia-original',
            'source' => 'github_gist',
            'source_reference' => 'gist-10',
            'source_text' => 'Texto original',
            'status' => AgencyStatus::UnderReview,
        ]);

        Livewire::test(Form::class, ['agency' => $agency])
            ->set('code', ' ag-new ')
            ->set('name', 'Agencia Editada')
            ->set('slug', 'Agencia Editada')
            ->set('department', 'Arequipa')
            ->set('province', 'Arequipa')
            ->set('district', 'Cayma')
            ->set('address', 'Av. Editada 456')
            ->set('status', AgencyStatus::Active->value)
            ->set('source', 'manual')
            ->set('source_reference', 'alterado')
            ->set('source_text', 'alterado')
            ->call('save')
            ->assertHasNoErrors();

        $agency->refresh();
        $this->assertSame('AG-NEW', $agency->code);
        $this->assertSame('Agencia Editada', $agency->name);
        $this->assertSame('agencia-editada', $agency->slug);
        $this->assertSame('github_gist', $agency->source);
        $this->assertSame('gist-10', $agency->source_reference);
        $this->assertSame('Texto original', $agency->source_text);
        $this->assertSame(AgencyStatus::Active, $agency->status);
    }

    public function test_create_validates_normalized_unique_code_and_slug(): void
    {
        $this->actingAs($this->superAdmin());
        Agency::factory()->create([
            'code' => 'AG-200',
            'slug' => 'agencia-repetida',
        ]);

        $this->validCreateForm()
            ->set('code', ' ag-200 ')
            ->set('slug', ' Agencia Repetida ')
            ->call('save')
            ->assertHasErrors(['code' => 'unique', 'slug' => 'unique']);
    }

    public function test_create_validates_required_location_coordinates_and_contact(): void
    {
        $this->actingAs($this->superAdmin());

        $this->validCreateForm()
            ->set('department', '')
            ->set('province', '')
            ->set('district', '')
            ->set('address', '')
            ->set('email', 'correo-invalido')
            ->set('latitude', '91')
            ->set('longitude', '-181')
            ->call('save')
            ->assertHasErrors(['department', 'province', 'district', 'address', 'email', 'latitude', 'longitude']);
    }

    public function test_moved_agency_requires_destination_and_moved_status_requires_move(): void
    {
        $this->actingAs($this->superAdmin());

        $this->validCreateForm()
            ->set('has_moved', true)
            ->call('save')
            ->assertHasErrors(['moved_to_agency_id', 'moved_to_address']);

        $this->validCreateForm()
            ->set('has_moved', false)
            ->set('status', AgencyStatus::Moved->value)
            ->call('save')
            ->assertHasErrors(['status']);
    }

    public function test_listing_searches_by_normalized_text(): void
    {
        $this->actingAs($this->superAdmin());
        $visible = Agency::factory()->create(['name' => 'Agencia Única Miraflores', 'department' => 'Lima']);
        $hidden = Agency::factory()->create(['name' => 'Agencia Norte', 'department' => 'Piura']);

        Livewire::test(Index::class)
            ->set('search', 'unica miraflores')
            ->assertSee($visible->code)
            ->assertDontSee($hidden->code);
    }

    public function test_listing_combines_location_status_size_and_boolean_filters(): void
    {
        $this->actingAs($this->superAdmin());
        $matching = Agency::factory()->create([
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Surco',
            'status' => AgencyStatus::Active,
            'size' => AgencySize::Large,
            'source' => 'manual',
            'is_operations_center' => false,
            'has_moved' => false,
        ]);
        $other = Agency::factory()->create([
            'department' => 'Cusco',
            'status' => AgencyStatus::UnderReview,
            'size' => AgencySize::Small,
            'is_operations_center' => true,
        ]);

        Livewire::test(Index::class)
            ->set('department', 'Lima')
            ->set('province', 'Lima')
            ->set('district', 'Surco')
            ->set('status', AgencyStatus::Active->value)
            ->set('size', AgencySize::Large->value)
            ->set('source', 'manual')
            ->set('operationsCenter', '0')
            ->set('moved', '0')
            ->assertSee($matching->code)
            ->assertDontSee($other->code);
    }

    public function test_soft_deleted_filter_and_relations_are_consistent(): void
    {
        $actor = $this->superAdmin();
        $this->actingAs($actor);
        $destination = Agency::factory()->create();
        $agency = Agency::factory()->create([
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'moved_to_agency_id' => $destination->id,
        ]);
        $deleted = Agency::factory()->create();
        $deleted->delete();

        AgencyChangeLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actor->id,
            'action' => 'manual_test',
            'created_at' => now(),
        ]);

        $this->assertTrue($agency->createdBy->is($actor));
        $this->assertTrue($agency->updatedBy->is($actor));
        $this->assertTrue($agency->movedToAgency->is($destination));
        $this->assertTrue($destination->movedFromAgencies->contains($agency));
        $this->assertTrue($agency->changeLogs->contains('action', 'manual_test'));

        Livewire::test(Index::class)
            ->assertDontSee($deleted->code)
            ->set('withTrashed', '1')
            ->assertSee($deleted->code);
    }

    public function test_factory_returns_casted_agency_with_valid_defaults(): void
    {
        $agency = Agency::factory()->create();

        $this->assertInstanceOf(Agency::class, $agency);
        $this->assertInstanceOf(AgencyStatus::class, $agency->status);
        $this->assertIsArray($agency->services);
        $this->assertNotSame('', $agency->code);
        $this->assertNotSame('', $agency->slug);
    }

    public function test_external_id_and_chosen_identifiers_persist_independently(): void
    {
        $this->actingAs($this->superAdmin());

        $this->validCreateForm()
            ->set('external_id', 610)
            ->set('texto_chosen_terrestre', ' 610 - TERRESTRE ')
            ->set('texto_chosen_aereo', ' 610 - AEREO ')
            ->call('save')
            ->assertHasNoErrors();

        $agency = Agency::query()->where('external_id', 610)->firstOrFail();
        $this->assertSame('AG-NEW-001', $agency->code);
        $this->assertSame('610 - TERRESTRE', $agency->texto_chosen_terrestre);
        $this->assertSame('610 - AEREO', $agency->texto_chosen_aereo);

        Livewire::test(Form::class, ['agency' => $agency])
            ->set('texto_chosen_terrestre', null)
            ->set('texto_chosen_aereo', '610 - AEREO EDITADO')
            ->call('save')
            ->assertHasNoErrors();

        $agency->refresh();
        $this->assertNull($agency->texto_chosen_terrestre);
        $this->assertSame('610 - AEREO EDITADO', $agency->texto_chosen_aereo);
    }

    public function test_external_id_is_unique_but_edit_ignores_current_agency(): void
    {
        $this->actingAs($this->superAdmin());
        $agency = Agency::factory()->create(['external_id' => 611]);

        Livewire::test(Form::class, ['agency' => $agency])->call('save')->assertHasNoErrors();
        $this->validCreateForm()->set('external_id', 611)->call('save')->assertHasErrors(['external_id' => 'unique']);
    }

    public function test_listing_searches_external_id_and_both_chosen_identifiers(): void
    {
        $this->actingAs($this->superAdmin());
        $agency = Agency::factory()->create([
            'external_id' => 612,
            'texto_chosen_terrestre' => 'IDENTIFICADOR TIERRA UNICO',
            'texto_chosen_aereo' => 'IDENTIFICADOR AIRE UNICO',
        ]);

        foreach (['612', 'tierra unico', 'aire unico'] as $search) {
            Livewire::test(Index::class)->set('search', $search)->assertSee($agency->code);
        }
    }

    private function validCreateForm(): Testable
    {
        return Livewire::test(Form::class)
            ->set('code', 'AG-NEW-001')
            ->set('name', 'Agencia Nueva')
            ->set('department', 'Lima')
            ->set('province', 'Lima')
            ->set('district', 'Surco')
            ->set('address', 'Av. Prueba 123')
            ->set('status', AgencyStatus::Active->value);
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Administrador', 'is_system' => true]
        );
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
