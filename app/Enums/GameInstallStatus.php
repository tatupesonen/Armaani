<?php

namespace App\Enums;

enum GameInstallStatus: string
{
    case Queued = 'queued';
    case Installing = 'installing';
    case Installed = 'installed';
    case Failed = 'failed';
}
