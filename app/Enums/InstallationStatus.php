<?php

namespace App\Enums;

enum InstallationStatus: string
{
    case Queued = 'queued';
    case Installing = 'installing';
    case Installed = 'installed';
    case Failed = 'failed';

    /**
     * Get the Flux UI badge variant for this status.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Installed => 'success',
            self::Installing => 'warning',
            self::Queued => 'secondary',
            self::Failed => 'danger',
        };
    }
}
