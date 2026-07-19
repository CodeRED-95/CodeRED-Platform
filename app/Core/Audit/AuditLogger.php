<?php

namespace App\Core\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'api_token',
    ];

    public function log(Model $auditable, string $action, array $oldValues, array $newValues, array $changedFields): ActivityLog
    {
        return ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'changed_fields' => $this->sanitizeFields($changedFields),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function sanitize(array $values): array
    {
        return collect($values)
            ->except(self::SENSITIVE_FIELDS)
            ->all();
    }

    private function sanitizeFields(array $fields): array
    {
        $containsCredentials = collect($fields)->intersect(self::SENSITIVE_FIELDS)->isNotEmpty();
        $safeFields = collect($fields)->diff(self::SENSITIVE_FIELDS)->values();

        if ($containsCredentials) {
            $safeFields->push('credentials');
        }

        return $safeFields->unique()->values()->all();
    }
}
