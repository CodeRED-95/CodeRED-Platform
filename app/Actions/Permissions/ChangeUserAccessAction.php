<?php

declare(strict_types=1);

namespace App\Actions\Permissions;

use App\Enums\ClientApplication;
use App\Models\User;
use App\Services\Auth\AuthAuditor;
use App\Services\Auth\ClientSessionManager;
use App\Services\Permissions\MobileAccess;
use App\Services\Permissions\MobileAccessManager;

/**
 * Conceder o retirar un acceso a un usuario.
 *
 * Existe para que el panel web y CodeRED Mobile hagan exactamente lo mismo. Son
 * dos interfaces sobre una única decisión de gobierno, y si cada una llevara su
 * propia lógica acabarían divergiendo: una cerraría las sesiones al retirar un
 * acceso y la otra no, o una admitiría permisos que la otra no.
 *
 * Cubre los dos ámbitos del catálogo:
 *
 *   módulo      (ruc.view, dni-records.view)  -> qué puede consultar
 *   aplicación  (platform/mobile/desktop.access) -> dónde puede entrar
 *
 * Retirar el acceso a una aplicación cierra además sus sesiones ahí. Sin eso, la
 * persona conservaría la sesión abierta en el inventario hasta que caducara el
 * refresh, contradiciendo lo que la pantalla acaba de decir. El middleware ya la
 * bloquearía en la siguiente petición, pero el inventario debe ser honesto.
 */
final class ChangeUserAccessAction
{
    public function __construct(
        private readonly MobileAccessManager $access,
        private readonly ClientSessionManager $sessions,
        private readonly AuthAuditor $auditor,
    ) {}

    /**
     * @return array{changed:bool, granted:bool, label:string, sessions_revoked:int}
     */
    public function execute(User $target, string $permission, bool $grant, ?User $actor = null): array
    {
        if (! MobileAccess::isGrantable($permission)) {
            return ['changed' => false, 'granted' => $target->hasPermission($permission), 'label' => $permission, 'sessions_revoked' => 0];
        }

        $changed = $grant
            ? $this->access->grant($target, $permission)
            : $this->access->revoke($target, $permission);

        $target->unsetRelation('roles');

        $revoked = 0;

        if (! $grant) {
            $revoked = $this->revokeSessionsFor($target, $permission, $actor);
        }

        return [
            'changed' => $changed,
            'granted' => $target->hasPermission($permission),
            'label' => MobileAccess::label($permission),
            'sessions_revoked' => $revoked,
        ];
    }

    /**
     * Cierra las sesiones que el acceso retirado deja sin sentido.
     *
     * Sólo aplica a los accesos de aplicación: retirar un módulo no invalida la
     * sesión, únicamente deja de autorizar esa consulta en la siguiente petición.
     */
    private function revokeSessionsFor(User $target, string $permission, ?User $actor): int
    {
        $application = $this->applicationFor($permission);

        if ($application === null) {
            return 0;
        }

        $revoked = $this->sessions->revokeAllFor($target, $application, $actor, 'app_access_revoked');

        if ($revoked > 0) {
            $this->auditor->record(AuthAuditor::SESSION_REVOKED, $actor, request(), null, [
                'target_user_id' => $target->getKey(),
                'application' => $application->value,
                'reason' => 'app_access_revoked',
                'revoked' => $revoked,
            ]);
        }

        return $revoked;
    }

    private function applicationFor(string $permission): ?ClientApplication
    {
        return match ($permission) {
            MobileAccess::PLATFORM_APP => ClientApplication::Platform,
            MobileAccess::MOBILE_APP => ClientApplication::Mobile,
            MobileAccess::DESKTOP_APP => ClientApplication::Desktop,
            default => null,
        };
    }
}
