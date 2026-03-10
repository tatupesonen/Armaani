<?php

namespace App\GameHandlers;

use App\Attributes\HandlesGame;
use App\Contracts\GameHandler;
use App\Enums\GameType;
use App\Models\DayZSettings;
use App\Models\Server;

#[HandlesGame(GameType::DayZ)]
final class DayZHandler implements GameHandler
{
    public function gameType(): GameType
    {
        return GameType::DayZ;
    }

    public function serverAppId(): int
    {
        return 223350;
    }

    public function gameId(): int
    {
        return 221100;
    }

    public function defaultPort(): int
    {
        return 2302;
    }

    public function defaultQueryPort(): int
    {
        return 27016;
    }

    public function branches(): array
    {
        return ['public', 'experimental'];
    }

    public function supportsWorkshopMods(): bool
    {
        return true;
    }

    public function requiresLowercaseConversion(): bool
    {
        return true;
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

    // --- UI Schema ---

    public function settingsSchema(): array
    {
        return [];
    }

    // --- Validation ---

    public function serverValidationRules(?Server $server = null): array
    {
        return [];
    }

    public function settingsValidationRules(): array
    {
        return [];
    }

    // --- Related Settings ---

    public function createRelatedSettings(Server $server): void
    {
        DayZSettings::query()->create(['server_id' => $server->id]);
    }

    public function updateRelatedSettings(Server $server, array $validated): void
    {
        $dayzFields = collect($validated)->only(
            (new DayZSettings)->getFillable()
        )->except('server_id')->toArray();

        if (! empty($dayzFields)) {
            $server->dayzSettings()->updateOrCreate(
                ['server_id' => $server->id],
                $dayzFields,
            );
        }
    }
}
