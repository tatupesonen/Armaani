<?php

namespace App\GameHandlers;

use App\Contracts\GameHandler;
use App\Enums\GameType;
use App\Models\Server;

class ReforgerHandler implements GameHandler
{
    public function gameType(): GameType
    {
        return GameType::ArmaReforger;
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

        $mods = [];
        $preset = $server->activePreset;

        if ($preset) {
            foreach ($preset->reforgerMods as $mod) {
                $mods[] = [
                    'modId' => $mod->mod_id,
                    'name' => $mod->name,
                ];
            }
        }

        $thirdPersonEnabled = (bool) ($settings?->third_person_view_enabled ?? true);
        $crossPlatform = (bool) ($settings?->cross_platform ?? false);

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
                    'battlEye' => (bool) $server->battle_eye,
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

    public function getBootDetectionString(): ?string
    {
        return 'Game::LoadEntities took:';
    }

    public function getModDownloadStartedString(): ?string
    {
        return 'Addon Download started';
    }

    public function getModDownloadFinishedString(): ?string
    {
        return 'Required addons are ready to use.';
    }

    public function getCrashDetectionString(): ?string
    {
        return null;
    }

    public function symlinkMods(Server $server): void
    {
        // No-op: Reforger downloads its own mods at server startup
    }

    public function symlinkMissions(Server $server): void
    {
        // No-op: Reforger uses scenarios, not PBO mission files
    }

    public function copyBiKeys(Server $server): void
    {
        // No-op: Reforger doesn't use BiKey files
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
        return 'reforger_'.$server->id.'_backup';
    }

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
}
