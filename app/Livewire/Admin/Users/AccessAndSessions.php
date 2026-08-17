<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Models\ClientSession;
use App\Models\User;
use App\Services\Auth\AuthAuditor;
use App\Services\Auth\ClientSessionManager;
use App\Services\Permissions\MobileAccess;
use App\Services\Permissions\MobileAccessManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Accesos por aplicación y sesiones activas de un usuario.
 *
 * Vive dentro de la ficha del usuario porque son dos caras del mismo gobierno:
 * en qué clientes puede entrar esta persona, y dónde está entrada ahora mismo.
 *
 * No introduce un sistema de permisos nuevo. Concede y retira con el mismo
 * MobileAccessManager que ya usaba la bandeja de solicitudes, que transporta
 * cada permiso mediante un rol dedicado.
 */
class AccessAndSessions extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        Gate::authorize('view', $user);

        $this->user = $user;
    }

    /**
     * Conceder o retirar el acceso a una aplicación.
     *
     * Cerrar las sesiones al retirar no es cosmético: sin ello la persona
     * conservaría su sesión abierta hasta que caducara el refresh. El middleware
     * ya la bloquearía en la siguiente petición, pero dejar la sesión viva en el
     * inventario contradice lo que la pantalla acaba de decir.
     */
    public function toggleApplication(string $permission, MobileAccessManager $access, ClientSessionManager $sessions): void
    {
        $this->authorizeManage();

        if (MobileAccess::scope($permission) !== MobileAccess::SCOPE_APPLICATION) {
            return;
        }

        if ($this->user->hasPermission($permission)) {
            $access->revoke($this->user, $permission);

            $application = $this->applicationFor($permission);

            if ($application !== null) {
                $sessions->revokeAllFor($this->user, $application, auth()->user(), 'app_access_revoked');
            }

            $this->dispatch('toast', tone: 'success', message: 'Acceso retirado y sesiones cerradas.');
        } else {
            $access->grant($this->user, $permission);

            $this->dispatch('toast', tone: 'success', message: 'Acceso concedido.');
        }

        $this->user->unsetRelation('roles');
        $this->user->refresh();
    }

    public function revokeSession(string $uuid, ClientSessionManager $sessions, AuthAuditor $auditor): void
    {
        $this->authorizeManage();

        $session = ClientSession::query()
            ->active()
            ->where('user_id', $this->user->getKey())
            ->where('uuid', $uuid)
            ->first();

        if (! $session instanceof ClientSession) {
            return;
        }

        $actor = auth()->user();
        $sessions->revoke($session, $actor instanceof User ? $actor : null, 'revoked_by_admin');
        $auditor->record(AuthAuditor::SESSION_REVOKED, $actor instanceof User ? $actor : null, request(), $session, [
            'target_user_id' => $this->user->getKey(),
        ]);

        $this->dispatch('toast', tone: 'success', message: 'Sesión cerrada.');
    }

    public function revokeAllSessions(ClientSessionManager $sessions, AuthAuditor $auditor): void
    {
        $this->authorizeManage();

        $actor = auth()->user();
        $revoked = $sessions->revokeAllFor(
            $this->user,
            null,
            $actor instanceof User ? $actor : null,
            'revoked_by_admin',
        );

        $auditor->record(AuthAuditor::SESSION_REVOKED, $actor instanceof User ? $actor : null, request(), null, [
            'target_user_id' => $this->user->getKey(),
            'scope' => 'all',
            'revoked' => $revoked,
        ]);

        $this->dispatch('toast', tone: 'success', message: 'Se cerraron '.$revoked.' sesiones.');
    }

    public function render(MobileAccessManager $access): View
    {
        return view('livewire.admin.users.access-and-sessions', [
            'applications' => $access->statusFor($this->user, MobileAccess::SCOPE_APPLICATION),
            'modules' => $access->statusFor($this->user, MobileAccess::SCOPE_MODULE),
            'sessions' => ClientSession::query()
                ->active()
                ->where('user_id', $this->user->getKey())
                ->orderByDesc('last_used_at')
                ->get(),
            'canManage' => Gate::allows('update', $this->user),
        ]);
    }

    private function applicationFor(string $permission): ?\App\Enums\ClientApplication
    {
        return match ($permission) {
            MobileAccess::PLATFORM_APP => \App\Enums\ClientApplication::Platform,
            MobileAccess::MOBILE_APP => \App\Enums\ClientApplication::Mobile,
            MobileAccess::DESKTOP_APP => \App\Enums\ClientApplication::Desktop,
            default => null,
        };
    }

    private function authorizeManage(): void
    {
        Gate::authorize('update', $this->user);
    }
}
