<?php

namespace App\GameHandlers;

use App\Attributes\HandlesGame;
use App\Contracts\DetectsServerState;
use App\Contracts\GameHandler;
use App\Enums\GameType;
use App\Models\ReforgerSettings;
use App\Models\Server;

#[HandlesGame(GameType::ArmaReforger)]
final class ReforgerHandler implements DetectsServerState, GameHandler
{
    public function gameType(): GameType
    {
        return GameType::ArmaReforger;
    }

    public function serverAppId(): int
    {
        return 1874900;
    }

    public function gameId(): int
    {
        return 1874900;
    }

    public function defaultPort(): int
    {
        return 2001;
    }

    public function defaultQueryPort(): int
    {
        return 17777;
    }

    public function branches(): array
    {
        return ['public'];
    }

    public function supportsWorkshopMods(): bool
    {
        return false;
    }

    public function requiresLowercaseConversion(): bool
    {
        return false;
    }

    public function buildLaunchCommand(Server $server): array
    {
        $binary = $this->getBinaryPath($server);
        $configPath = $server->getProfilesPath().'/REFORGER_'.$server->id.'.json';

        $profilesPath = $server->getProfilesPath();
        $settings = $server->reforgerSettings;
        $maxFps = $settings?->max_fps ?? 60;

        $params = [
            $binary,
            '-config', $configPath,
            '-profile', $profilesPath,
            '-maxFPS', (string) $maxFps,
        ];

        if ($server->additional_params) {
            $additionalArgs = preg_split('/\s+/', trim($server->additional_params), -1, PREG_SPLIT_NO_EMPTY);
            array_push($params, ...$additionalArgs);
        }

        return $params;
    }

    public function generateConfigFiles(Server $server): void
    {
        $settings = $server->reforgerSettings;

        $preset = $server->activePreset;

        $mods = $preset
            ? $preset->reforgerMods->map(fn ($mod) => [
                'modId' => $mod->mod_id,
                'name' => $mod->name,
            ])->all()
            : [];

        $thirdPersonEnabled = $settings?->third_person_view_enabled ?? true;
        $crossPlatform = $settings?->cross_platform ?? false;

        $config = [
            'bindAddress' => '',
            'bindPort' => $server->port,
            'publicAddress' => '',
            'publicPort' => $server->port,
            'game' => [
                'name' => $server->name,
                'password' => (string) $server->password,
                'passwordAdmin' => (string) $server->admin_password,
                'scenarioId' => $settings?->scenario_id ?? '',
                'maxPlayers' => (int) $server->max_players,
                'visible' => true,
                'crossPlatform' => $crossPlatform,
                'gameProperties' => [
                    'serverMaxViewDistance' => 2500,
                    'serverMinGrassDistance' => 50,
                    'networkViewDistance' => 1000,
                    'disableThirdPerson' => ! $thirdPersonEnabled,
                    'fastValidation' => true,
                    'battlEye' => $server->battle_eye,
                    'VONDisableUI' => true,
                    'VONDisableDirectSpeechUI' => true,
                ],
                'mods' => $mods,
            ],
            'a2s' => [
                'address' => '0.0.0.0',
                'port' => $server->query_port,
            ],
        ];

        $configPath = $server->getProfilesPath().'/REFORGER_'.$server->id.'.json';

        file_put_contents(
            $configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    public function getBinaryPath(Server $server): string
    {
        return $server->gameInstall->getInstallationPath().'/ArmaReforgerServer';
    }

    public function getProfileName(Server $server): string
    {
        return 'reforger_'.$server->id;
    }

    public function getServerLogPath(Server $server): string
    {
        return $server->getProfilesPath().'/server.log';
    }

    // --- DetectsServerState ---

    public function getBootDetectionStrings(): array
    {
        return ['Server registered with addr'];
    }

    public function getModDownloadStartedString(): ?string
    {
        return 'Addon Download started';
    }

    public function getModDownloadFinishedString(): ?string
    {
        return 'Required addons are ready to use.';
    }

    public function getCrashDetectionStrings(): array
    {
        return [];
    }

    // --- Validation ---

    public function serverValidationRules(): array
    {
        return [];
    }

    public function settingsValidationRules(): array
    {
        return [
            'scenario_id' => ['required', 'string', 'regex:/^\{[0-9A-F]{16}\}[a-zA-Z0-9_.\/ -]+$/'],
            'third_person_view_enabled' => ['boolean'],
            'max_fps' => ['integer', 'min:10', 'max:240'],
            'cross_platform' => ['boolean'],
        ];
    }

    // --- Related Settings ---

    public function createRelatedSettings(Server $server): void
    {
        ReforgerSettings::query()->create(['server_id' => $server->id]);
    }

    public function updateRelatedSettings(Server $server, array $validated): void
    {
        $reforgerFields = collect($validated)->only(
            (new ReforgerSettings)->getFillable()
        )->except('server_id')->toArray();

        if (! empty($reforgerFields)) {
            $server->reforgerSettings()->updateOrCreate(
                ['server_id' => $server->id],
                $reforgerFields,
            );
        }
    }
}
