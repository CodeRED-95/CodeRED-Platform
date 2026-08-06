<?php

declare(strict_types=1);

namespace Tests\Feature\Shalom;

use App\Modules\Shalom\Models\ShalomApiKey;
use App\Modules\Shalom\Models\ShalomDeliveryRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreShalomSyncTest extends TestCase
{
    use RefreshDatabase;

    private ?string $validApiKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear una API key válida para testing
        $result = ShalomApiKey::createNewKey('Test API Key', null, 'Testing key');
        $this->validApiKey = $result['plain_key'];
    }

    private function makeRequest(array $payload)
    {
        return $this->postJson('/api/v1/shalom/sync', $payload, [
            'X-Shalom-API-Key' => $this->validApiKey,
        ]);
    }

    public function test_receive_shalom_sync_stores_records(): void
    {
        $timestamp = now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');

        $payload = [
            'username' => 'test_user_123',
            'records' => [
                ['field' => 'DNI', 'value' => '12345678', 'timestamp' => $timestamp],
                ['field' => 'OS', 'value' => 'OS-9876543', 'timestamp' => $timestamp],
                ['field' => 'RUC', 'value' => '20123456789', 'timestamp' => $timestamp],
            ],
        ];

        $response = $this->makeRequest($payload);

        $response->assertOk()
            ->assertJsonStructure(['success', 'batch_id', 'record_count'])
            ->assertJson([
                'success' => true,
                'record_count' => 3,
            ]);

        $this->assertDatabaseCount('shalom_delivery_records', 3);

        $this->assertDatabaseHas('shalom_delivery_records', [
            'username' => 'test_user_123',
            'field' => 'DNI',
            'value' => '12345678',
        ]);

        $this->assertDatabaseHas('shalom_delivery_records', [
            'username' => 'test_user_123',
            'field' => 'OS',
            'value' => 'OS-9876543',
        ]);

        $this->assertDatabaseHas('shalom_delivery_records', [
            'username' => 'test_user_123',
            'field' => 'RUC',
            'value' => '20123456789',
        ]);
    }

    public function test_reject_invalid_field_type(): void
    {
        $payload = [
            'username' => 'test_user_123',
            'records' => [
                ['field' => 'INVALID', 'value' => 'test', 'timestamp' => now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z')],
            ],
        ];

        $response = $this->makeRequest($payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['records.0.field']);

        $this->assertDatabaseCount('shalom_delivery_records', 0);
    }

    public function test_reject_missing_required_fields(): void
    {
        $payload = [
            'username' => 'test_user_123',
            'records' => [
                ['field' => 'DNI'],  // Missing value and timestamp
            ],
        ];

        $response = $this->makeRequest($payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['records.0.value', 'records.0.timestamp']);
    }

    public function test_reject_invalid_timestamp_format(): void
    {
        $payload = [
            'username' => 'test_user_123',
            'records' => [
                ['field' => 'DNI', 'value' => '12345678', 'timestamp' => '2026-08-06 10:30:00'],  // Wrong format
            ],
        ];

        $response = $this->makeRequest($payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['records.0.timestamp']);
    }

    public function test_reject_too_many_records(): void
    {
        $timestamp = now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
        $records = array_fill(0, 501, [
            'field' => 'DNI',
            'value' => '12345678',
            'timestamp' => $timestamp,
        ]);

        $payload = [
            'username' => 'test_user_123',
            'records' => $records,
        ];

        $response = $this->makeRequest($payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['records']);
    }

    public function test_batch_id_groups_records(): void
    {
        $timestamp = now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');

        $payload = [
            'username' => 'test_user_123',
            'records' => [
                ['field' => 'DNI', 'value' => '12345678', 'timestamp' => $timestamp],
                ['field' => 'OS', 'value' => 'OS-9876543', 'timestamp' => $timestamp],
            ],
        ];

        $response = $this->makeRequest($payload);
        $batchId = $response->json('batch_id');

        $this->assertNotEmpty($batchId);

        $recordsInBatch = ShalomDeliveryRecord::where('sync_batch_id', $batchId)->get();
        $this->assertCount(2, $recordsInBatch);
    }

    public function test_multiple_syncs_have_different_batch_ids(): void
    {
        $timestamp = now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');

        $payload1 = [
            'username' => 'user1',
            'records' => [
                ['field' => 'DNI', 'value' => '11111111', 'timestamp' => $timestamp],
            ],
        ];

        $payload2 = [
            'username' => 'user2',
            'records' => [
                ['field' => 'RUC', 'value' => '20222222222', 'timestamp' => $timestamp],
            ],
        ];

        $response1 = $this->makeRequest($payload1);
        $response2 = $this->makeRequest($payload2);

        $batchId1 = $response1->json('batch_id');
        $batchId2 = $response2->json('batch_id');

        $this->assertNotEquals($batchId1, $batchId2);
    }

    public function test_all_field_types_accepted(): void
    {
        $timestamp = now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');

        $fields = ['DNI', 'CE', 'RUC', 'OS', 'Clave'];
        $records = array_map(fn ($field) => [
            'field' => $field,
            'value' => "value_for_$field",
            'timestamp' => $timestamp,
        ], $fields);

        $payload = [
            'username' => 'test_user_123',
            'records' => $records,
        ];

        $response = $this->makeRequest($payload);

        $response->assertOk();
        $this->assertDatabaseCount('shalom_delivery_records', 5);

        foreach ($fields as $field) {
            $this->assertDatabaseHas('shalom_delivery_records', [
                'field' => $field,
                'value' => "value_for_$field",
            ]);
        }
    }

    public function test_reject_missing_api_key(): void
    {
        $timestamp = now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');

        $payload = [
            'username' => 'test_user_123',
            'records' => [
                ['field' => 'DNI', 'value' => '12345678', 'timestamp' => $timestamp],
            ],
        ];

        // Sin API key
        $response = $this->postJson('/api/v1/shalom/sync', $payload);

        $response->assertUnauthorized()
            ->assertJson(['success' => false]);
    }

    public function test_reject_invalid_api_key(): void
    {
        $timestamp = now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');

        $payload = [
            'username' => 'test_user_123',
            'records' => [
                ['field' => 'DNI', 'value' => '12345678', 'timestamp' => $timestamp],
            ],
        ];

        // API key inválida
        $response = $this->postJson('/api/v1/shalom/sync', $payload, [
            'X-Shalom-API-Key' => 'shalom_invalid_key_that_does_not_exist',
        ]);

        $response->assertUnauthorized()
            ->assertJson(['success' => false]);
    }

}
