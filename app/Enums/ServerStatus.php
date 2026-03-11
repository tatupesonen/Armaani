<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Stopped = 'stopped';
    case Starting = 'starting';
    case Booting = 'booting';
    case DownloadingMods = 'downloading_mods';
    case Running = 'running';
    case Stopping = 'stopping';
    case Crashed = 'crashed';

    /**
     * Whether the server is in an active (non-terminal) state.
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::Starting,
            self::Booting,
            self::DownloadingMods,
            self::Running,
            self::Stopping,
        ]);
    }

    /**
     * Whether the server can be started from this state.
     */
    public function isStartable(): bool
    {
        return in_array($this, [self::Stopped, self::Crashed]);
    }

    /**
     * Whether the server can be stopped or restarted from this state.
     */
    public function isStoppable(): bool
    {
        return in_array($this, [self::Running, self::Booting, self::DownloadingMods]);
    }

    /**
     * Whether the server can be deleted from this state.
     */
    public function isDeletable(): bool
    {
        return in_array($this, [self::Stopped, self::Crashed]);
    }
}
