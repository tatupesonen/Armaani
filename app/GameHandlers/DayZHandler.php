<?php

namespace App\GameHandlers;

use App\Contracts\GameHandler;
use App\Enums\GameType;
use App\Models\Server;

class DayZHandler implements GameHandler
{
    public function gameType(): GameType
    {
        return GameType::DayZ;
    }

    public function buildLaunchCommand(Server $server): array
    {
        throw new \RuntimeException('DayZ server support is not yet implemented.');
    }

    public function generateConfigFiles(Server $server): void
    {
        throw new \RuntimeException('DayZ server support is not yet implemented.');
    }

    public function getBinaryPath(Server $server): string
    {
        return $server->gameInstall->getInstallationPath().'/DayZServer_x64';
    }

    public function getProfileName(Server $server): string
    {
        return 'dayz_'.$server->id;
    }

    public function getServerLogPath(Server $server): string
    {
        return $server->getProfilesPath().'/server.log';
    }

    public function getBootDetectionString(): ?string
    {
        return null;
    }

    public function getModDownloadStartedString(): ?string
    {
        return null;
    }

    public function getModDownloadFinishedString(): ?string
    {
        return null;
    }

    public function getCrashDetectionString(): ?string
    {
        return null;
    }

    public function symlinkMods(Server $server): void
    {
        throw new \RuntimeException('DayZ server support is not yet implemented.');
    }

    public function symlinkMissions(Server $server): void
    {
        // No-op: DayZ configures missions via server config
    }

    public function copyBiKeys(Server $server): void
    {
        throw new \RuntimeException('DayZ server support is not yet implemented.');
    }

    public function supportsHeadlessClients(): bool
    {
        return false;
    }

    public function buildHeadlessClientCommand(Server $server, int $index): ?array
    {
        return null;
    }

    public function getBackupFilePath(Server $server): ?string
    {
        return null;
    }

    public function getBackupDownloadFilename(Server $server): string
    {
        return 'dayz_'.$server->id.'_backup';
    }

    public function serverValidationRules(): array
    {
        return [];
    }

    public function settingsValidationRules(): array
    {
        return [];
    }
}
