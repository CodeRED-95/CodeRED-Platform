<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Policies;

use App\Models\User;
use App\Modules\Shalom\Models\ShalomApiKey;

class ShalomApiKeyPolicy
{
    /**
     * Ver cualquier API key
     */
    public function viewAny(User $user): bool
    {
        return true; // Los usuarios autenticados pueden ver sus propias keys
    }

    /**
     * Ver una API key específica
     */
    public function view(User $user, ShalomApiKey $key): bool
    {
        // Solo el dueño puede verla (o admin)
        return $key->user_id === $user->id || $user->hasRole('admin');
    }

    /**
     * Crear API key
     */
    public function create(User $user): bool
    {
        return true; // Cualquier usuario puede crear sus propias keys
    }

    /**
     * Actualizar API key
     */
    public function update(User $user, ShalomApiKey $key): bool
    {
        return $key->user_id === $user->id || $user->hasRole('admin');
    }

    /**
     * Eliminar/revocar API key
     */
    public function delete(User $user, ShalomApiKey $key): bool
    {
        return $key->user_id === $user->id || $user->hasRole('admin');
    }
}
