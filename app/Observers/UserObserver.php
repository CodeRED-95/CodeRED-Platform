<?php

namespace App\Observers;

use App\Core\Audit\AuditLogger;
use App\Models\User;

class UserObserver
{
    public function __construct(private readonly AuditLogger $logger) {}

    public function creating(User $user): void
    {
        if (auth()->id() !== null) {
            $user->created_by ??= auth()->id();
            $user->updated_by ??= auth()->id();
        }
    }

    public function created(User $user): void
    {
        $this->logger->log($user, 'created', [], $user->getAttributes(), array_keys($user->getAttributes()));
    }

    public function updating(User $user): void
    {
        if (auth()->id() !== null) {
            $user->updated_by = auth()->id();
        }
    }

    public function updated(User $user): void
    {
        $changes = $user->getChanges();
        $oldValues = collect(array_keys($changes))
            ->mapWithKeys(fn (string $field): array => [$field => $user->getOriginal($field)])
            ->all();

        $this->logger->log($user, 'updated', $oldValues, $changes, array_keys($changes));
    }

    public function deleted(User $user): void
    {
        $this->logger->log($user, $user->isForceDeleting() ? 'force_deleted' : 'deleted', $user->getOriginal(), $user->getAttributes(), ['deleted_at']);
    }

    public function restored(User $user): void
    {
        $this->logger->log($user, 'restored', $user->getOriginal(), $user->getAttributes(), ['deleted_at']);
    }
}
