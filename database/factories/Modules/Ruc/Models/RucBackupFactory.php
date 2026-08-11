<?php

namespace Database\Factories\Modules\Ruc\Models;

use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use Illuminate\Database\Eloquent\Factories\Factory;

class RucBackupFactory extends Factory
{
    protected $model = RucBackup::class;

    public function definition(): array
    {
        return [
            'name' => 'Backup '.$this->faker->dateTime()->format('Y-m-d H:i:s'),
            'backup_type' => RucBackup::TYPE_MANUAL,
            'status' => RucBackup::STATUS_CREATING,
            'storage_path' => 'backups/'.uniqid().'.dump',
            'file_size_bytes' => $this->faker->numberBetween(1000000, 100000000),
            'total_records' => $this->faker->numberBetween(1000, 50000),
            'checksum_sha256' => hash('sha256', $this->faker->sha256()),
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => RucBackup::STATUS_COMPLETED,
        ]);
    }

    public function safety(): static
    {
        return $this->state([
            'backup_type' => RucBackup::TYPE_SAFETY,
        ]);
    }
}
