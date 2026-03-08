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
}
