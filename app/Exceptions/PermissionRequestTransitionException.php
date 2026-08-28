<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Se intentó decidir sobre una solicitud que ya no admitía decisión —porque
 * otro administrador se adelantó— o sobre la propia. El mensaje está escrito
 * para mostrarse tal cual a quien lo provocó.
 */
class PermissionRequestTransitionException extends RuntimeException {}
