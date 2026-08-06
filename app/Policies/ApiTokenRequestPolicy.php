<?php

namespace App\Policies;

use App\Models\ApiTokenRequest;
use App\Models\User;

class ApiTokenRequestPolicy
{
    /**
     * Determine whether the user can view any api token requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('api-token-requests.view');
    }

    /**
     * Determine whether the user can view the api token request.
     */
    public function view(User $user, ApiTokenRequest $request): bool
    {
        return $user->hasPermission('api-token-requests.view');
    }

    /**
     * Determine whether the user can approve the api token request.
     */
    public function approve(User $user, ApiTokenRequest $request): bool
    {
        return $user->hasPermission('api-token-requests.approve');
    }

    /**
     * Determine whether the user can reject the api token request.
     */
    public function reject(User $user, ApiTokenRequest $request): bool
    {
        return $user->hasPermission('api-token-requests.reject');
    }

    /**
     * Determine whether the user can reveal the token.
     */
    public function revealToken(User $user, ApiTokenRequest $request): bool
    {
        return $user->hasPermission('api-token-requests.reveal_token');
    }

    /**
     * Determine whether the user can view protected data.
     */
    public function viewProtectedData(User $user, ApiTokenRequest $request): bool
    {
        return $user->hasPermission('api-token-requests.view-protected-data');
    }

    /**
     * Determine whether the user can confirm delivery.
     */
    public function confirmDelivery(User $user, ApiTokenRequest $request): bool
    {
        return $user->hasPermission('api-token-requests.confirm-delivery');
    }

    /**
     * Determine whether the user can cancel approval.
     */
    public function cancelApproval(User $user, ApiTokenRequest $request): bool
    {
        return $user->hasPermission('api-token-requests.cancel-approval');
    }

    /**
     * Determine whether the user can regenerate the token.
     */
    public function regenerateToken(User $user, ApiTokenRequest $request): bool
    {
        // Solo si nunca fue entregado
        if ($request->isDelivered()) {
            return false;
        }

        return $user->hasPermission('api-token-requests.regenerate-token');
    }

    /**
     * Determine whether the user can delete the api token request.
     */
    public function delete(User $user, ApiTokenRequest $request): bool
    {
        return $user->hasPermission('api-token-requests.delete');
    }
}
