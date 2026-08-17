<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Actions\Permissions\ChangeUserAccessAction;
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
 * Accesos y sesiones de un usuario, desde el panel web.
 *
 * El panel es la central: todo lo que se puede conceder o cerrar desde CodeRED
 * Mobile se puede hacer también aquí, y ambas interfaces pasan por la misma
 * acción para no divergir.
 *
 * Dos ámbitos, un mismo mecanismo:
 *
 *   Aplicaciones -> dónde puede entrar esta cuenta
 *   Módulos      -> qué puede consultar
 *
 * No introduce un sistema de permisos nuevo: concede y retira con el mismo
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
     * Conceder o retirar un acceso, sea de aplicación o de módulo.
     *
     * El estado actual decide la dirección: si lo tiene, se retira; si no, se
     * concede. Así el botón dice siempre lo que va a pasar.
     */
    public function toggleAccess(string $permission, ChangeUserAccessAction $change): void
    {
        $this->authorizeManage();

        if (! MobileAccess::isGrantable($permission)) {
            return;
        }

        $actor = auth()->user();

        $resultado = $change->execute(
            $this->user,
            $permission,
            grant: ! $this->user->hasPermission($permission),
            actor: $actor instanceof User ? $actor : null,
        );

        $this->user->refresh();

        $mensaje = $resultado['granted']
            ? $resultado['label'].': acceso concedido.'
            : $resultado['label'].': acceso retirado.'
                .($resultado['sessions_revoked'] > 0 ? ' Se cerraron '.$resultado['sessions_revoked'].' sesiones.' : '');

        $this->dispatch('toast', tone: 'success', message: $mensaje);
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

    private function authorizeManage(): void
    {
        Gate::authorize('update', $this->user);
    }
}
