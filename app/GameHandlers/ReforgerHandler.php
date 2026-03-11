<?php

namespace App\GameHandlers;

use App\Contracts\DetectsServerState;
use App\Contracts\GameHandler;
use App\Contracts\SteamGameHandler;
use App\Contracts\SupportsRegisteredMods;
use App\Contracts\SupportsScenarios;
use App\Models\ModPreset;
use App\Models\ReforgerMod;
use App\Models\ReforgerSettings;
use App\Models\Server;
use App\Services\Mod\ReforgerScenarioService;
use App\Services\Renderer\JsonConfigRenderer;
use Illuminate\Database\Eloquent\Model;

final class ReforgerHandler implements DetectsServerState, GameHandler, SteamGameHandler, SupportsRegisteredMods, SupportsScenarios
{
    public function __construct(
        protected JsonConfigRenderer $configRenderer,
        protected ReforgerScenarioService $scenarioService,
    ) {}

    public function value(): string
    {
        return 'reforger';
    }

    public function label(): string
    {
        return 'Arma Reforger';
    }

    public function consumerAppId(): int
    {
        return 1874900;
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
            $this->configRenderer->render('reforger/config.json', $config)
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

    public function shouldAutoRestart(Server $server): bool
    {
        return false;
    }

    // --- UI Schema ---

    public function settingsSchema(): array
    {
        return [
            [
                'title' => 'Reforger Settings',
                'source' => 'reforger_settings',
                'fields' => [
                    ['key' => 'scenario_id', 'label' => 'Scenario ID', 'type' => 'custom', 'component' => 'scenario-picker', 'default' => ''],
                    ['key' => 'third_person_view_enabled', 'label' => 'Third Person View', 'type' => 'toggle', 'default' => true],
                    ['key' => 'battle_eye', 'label' => 'BattlEye Anti-Cheat', 'type' => 'toggle', 'default' => true, 'source' => 'server'],
                    ['key' => 'cross_platform', 'label' => 'Cross-Platform', 'type' => 'toggle', 'default' => false],
                    ['key' => 'max_fps', 'label' => 'Max FPS', 'type' => 'number', 'default' => 60, 'min' => 10, 'max' => 240, 'description' => 'Recommended: 60-120. Limits server tick rate to prevent excessive CPU usage.'],
                ],
            ],
        ];
    }

    // --- Validation ---

    public function serverValidationRules(?Server $server = null): array
    {
        return [
            'battle_eye' => ['boolean'],
        ];
    }

    public function settingsValidationRules(): array
    {
        return [
            'scenario_id' => ['nullable', 'string', 'regex:/^\{[0-9A-F]{16}\}[a-zA-Z0-9_.\/ -]+$/'],
            'third_person_view_enabled' => ['boolean'],
            'max_fps' => ['integer', 'min:10', 'max:240'],
            'cross_platform' => ['boolean'],
        ];
    }

    // --- Related Settings ---

    public function settingsModelClass(): ?string
    {
        return ReforgerSettings::class;
    }

    public function settingsRelationName(): ?string
    {
        return 'reforgerSettings';
    }

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

    // --- Mod Presets ---

    public function modSections(): array
    {
        return [
            [
                'type' => 'registered',
                'label' => 'Reforger Mods',
                'relationship' => 'reforgerMods',
                'formField' => 'reforger_mod_ids',
            ],
        ];
    }

    public function syncPresetMods(ModPreset $preset, array $validated): void
    {
        $preset->reforgerMods()->sync($validated['reforger_mod_ids'] ?? []);
    }

    public function getPresetModCount(ModPreset $preset): int
    {
        return $preset->reforgerMods()->count();
    }

    // --- SupportsRegisteredMods ---

    public function registeredModModelClass(): string
    {
        return ReforgerMod::class;
    }

    public function registeredModRelationName(): string
    {
        return 'reforgerMods';
    }

    public function registeredModPivotTable(): string
    {
        return 'mod_preset_reforger_mod';
    }

    public function storeRegisteredMod(array $data): Model
    {
        return ReforgerMod::query()->create($data);
    }

    public function destroyRegisteredMod(Model $mod): void
    {
        /** @var ReforgerMod $mod */
        $mod->presets()->detach();
        $mod->delete();
    }

    public function registeredModValidationRules(): array
    {
        return [
            'mod_id' => ['required', 'string', 'unique:reforger_mods,mod_id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    // --- SupportsScenarios ---

    public function getScenarios(Server $server): array
    {
        return $this->scenarioService->getScenarios($server);
    }

    public function refreshScenarios(Server $server): array
    {
        return $this->scenarioService->refreshScenarios($server);
    }
}
