<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['slug' => 'dashboard.view', 'name' => 'Ver dashboard'],
            ['slug' => 'agencies.view', 'name' => 'Ver agencias'],
            ['slug' => 'agencies.manage', 'name' => 'Gestionar agencias'],
            ['slug' => 'agencies.create', 'name' => 'Crear agencias'],
            ['slug' => 'agencies.update', 'name' => 'Editar agencias'],
            ['slug' => 'agencies.delete', 'name' => 'Eliminar agencias'],
            ['slug' => 'agencies.restore', 'name' => 'Restaurar agencias'],
            ['slug' => 'agencies.import', 'name' => 'Importar agencias'],
            ['slug' => 'agencies.export', 'name' => 'Exportar agencias'],
            ['slug' => 'agencies.view_history', 'name' => 'Ver historial de agencias'],
            ['slug' => 'agencies.manage_status', 'name' => 'Gestionar estado de agencias'],
        ])->each(fn (array $item) => Permission::query()->updateOrCreate(
            ['slug' => $item['slug']],
            ['name' => $item['name'], 'description' => null]
        ));
    }
}
