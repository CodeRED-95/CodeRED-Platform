<?php

namespace App\Services\Events;

final class EventType
{
    public const TOKEN_REQUEST_CREATED = 'token.request.created';

    public const TOKEN_REQUEST_APPROVED = 'token.request.approved';

    public const TOKEN_REQUEST_REJECTED = 'token.request.rejected';

    public const AGENCY_CREATED = 'agency.created';

    public const AGENCY_UPDATED = 'agency.updated';

    public const AGENCY_DELETED = 'agency.deleted';

    public const DNI_IMPORT_STARTED = 'dni.import.started';

    public const DNI_IMPORT_FINISHED = 'dni.import.finished';

    public const BACKUP_STARTED = 'backup.started';

    public const BACKUP_FINISHED = 'backup.finished';

    public const AGENT_CONNECTED = 'agent.connected';

    public const AGENT_DISCONNECTED = 'agent.disconnected';

    public const SYSTEM_WARNING = 'system.warning';

    public const SYSTEM_ERROR = 'system.error';

    public const HEARTBEAT_FAILED = 'heartbeat.failed';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::TOKEN_REQUEST_CREATED,
            self::TOKEN_REQUEST_APPROVED,
            self::TOKEN_REQUEST_REJECTED,
            self::AGENCY_CREATED,
            self::AGENCY_UPDATED,
            self::AGENCY_DELETED,
            self::DNI_IMPORT_STARTED,
            self::DNI_IMPORT_FINISHED,
            self::BACKUP_STARTED,
            self::BACKUP_FINISHED,
            self::AGENT_CONNECTED,
            self::AGENT_DISCONNECTED,
            self::SYSTEM_WARNING,
            self::SYSTEM_ERROR,
            self::HEARTBEAT_FAILED,
        ];
    }
}
