<?php

namespace App\Modules\Agencies\Actions;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyChangeLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplyAgencyMoveAction
{
    public function execute(Agency $agency, array $data, ?int $actorId = null, ?string $ipAddress = null, ?string $userAgent = null): Agency
    {
        return DB::transaction(function () use ($agency, $data, $actorId, $ipAddress, $userAgent): Agency {
            $original = $agency->fresh();

            $hasMoved = (bool) ($data['has_moved'] ?? false);
            $destinationId = $data['moved_to_agency_id'] ?? null;
            $destinationAddress = $this->normalizeNullable($data['moved_to_address'] ?? null);
            $notice = $this->normalizeNullable($data['move_notice'] ?? null);
            $movedAt = $data['moved_at'] ?? null;

            if (! $hasMoved) {
                $newStatus = $data['status'] ?? AgencyStatus::UnderReview->value;
                if ($newStatus === AgencyStatus::Moved->value) {
                    throw new InvalidArgumentException('status no puede ser moved cuando has_moved es false.');
                }

                $agency->forceFill([
                    'has_moved' => false,
                    'moved_to_agency_id' => null,
                    'moved_to_address' => null,
                    'move_notice' => $notice,
                    'moved_at' => $movedAt,
                    'status' => $newStatus,
                ]);

                $agency->save();
                $this->log($agency, 'agency_move_cancelled', $original, $actorId, $ipAddress, $userAgent);

                return $agency;
            }

            if (! $destinationId && ! $destinationAddress) {
                throw new InvalidArgumentException('Debe existir un destino o una dirección de traslado.');
            }

            if ($destinationId) {
                $destination = Agency::query()->whereKey($destinationId)->whereNull('deleted_at')->firstOrFail();

                if ($destination->id === $agency->id) {
                    throw new InvalidArgumentException('Una agencia no puede trasladarse a sí misma.');
                }

                if ($this->wouldCreateCycle($agency->id, $destination->id)) {
                    throw new InvalidArgumentException('El traslado generaría un ciclo.');
                }

                $agency->moved_to_agency_id = $destination->id;
                if (! $destinationAddress) {
                    $agency->moved_to_address = null;
                }
            } else {
                $agency->moved_to_agency_id = null;
                $agency->moved_to_address = $destinationAddress;
            }

            $agency->has_moved = true;
            $agency->status = AgencyStatus::Moved;
            $agency->move_notice = $notice;
            $agency->moved_at = $movedAt ?: now()->toDateString();
            $agency->save();

            $this->log($agency, 'agency_marked_as_moved', $original, $actorId, $ipAddress, $userAgent);

            return $agency;
        });
    }

    private function wouldCreateCycle(int $agencyId, int $destinationId): bool
    {
        $visited = [$agencyId];
        $current = $destinationId;

        while ($current) {
            if (in_array($current, $visited, true)) {
                return true;
            }

            $visited[] = $current;
            $current = (int) (Agency::query()->whereKey($current)->value('moved_to_agency_id') ?? 0);
            if ($current === 0) {
                break;
            }
        }

        return false;
    }

    private function normalizeNullable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }

    private function log(Agency $agency, string $action, ?Agency $original, ?int $actorId, ?string $ipAddress, ?string $userAgent): void
    {
        AgencyChangeLog::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'old_values' => $original?->only([
                'status', 'has_moved', 'moved_to_agency_id', 'moved_to_address', 'move_notice', 'moved_at',
            ]),
            'new_values' => $agency->only([
                'status', 'has_moved', 'moved_to_agency_id', 'moved_to_address', 'move_notice', 'moved_at',
            ]),
            'changed_fields' => ['status', 'has_moved', 'moved_to_agency_id', 'moved_to_address', 'move_notice', 'moved_at'],
            'ip_address' => $ipAddress ?? request()?->ip(),
            'user_agent' => $userAgent ?? request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
