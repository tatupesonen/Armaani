# Multi-Game Support Implementation Spec

> This document captures the full context, architectural decisions, and implementation plan for adding multi-game support to ArmaMan. It was produced from a detailed planning session that explored the current codebase, analyzed the `arma-server-manager/` reference project, and made 12+ architectural decisions. A new session should read this file in full before starting implementation.

---

## Table of Contents

1. [Current State Analysis](#1-current-state-analysis)
2. [Reference Project Analysis](#2-reference-project-analysis)
3. [Architectural Decisions](#3-architectural-decisions)
4. [Phase 1: GameType Enum & Config](#4-phase-1-gametype-enum--config)
5. [Phase 2: Database Migrations](#5-phase-2-database-migrations)
6. [Phase 3: Models](#6-phase-3-models)
7. [Phase 4: GameManager & Handlers](#7-phase-4-gamemanager--handlers)
8. [Phase 5: Refactor ServerProcessService](#8-phase-5-refactor-serverprocessservice)
9. [Phase 6: Service & Job Updates](#9-phase-6-service--job-updates)
10. [Phase 7: UI Changes](#10-phase-7-ui-changes)
11. [Phase 8: Testing](#11-phase-8-testing)
12. [Implementation Order](#12-implementation-order)
13. [Game-Specific Reference Data](#13-game-specific-reference-data)

---

## 1. Current State Analysis

The project is deeply Arma 3-specific. The following are ALL locations where Arma 3 assumptions are hardcoded:

### Hardcoded Values That Must Change

| Location | What's Hardcoded | Current Value |
|----------|-----------------|---------------|
| `config/arma.php` | `server_app_id` | `233780` |
| `config/arma.php` | `game_id` | `107410` |
| `ServerProcessService` lines ~256, ~658 | Server binary name | `arma3server_x64` |
| `Server::getProfileName()` | Profile prefix | `'arma3_' . $this->id` |
| `ServerProcessService::generateProfileConfig()` | Config file extension | `.Arma3Profile` |
| `ServerBackupService::getVarsFilePath()` | Backup file extension | `.vars.Arma3Profile` |
| `ServerProcessService::generateProfileConfig()` | Entire file content format | Arma 3 SQF-class syntax (`class DifficultyPresets`, `class CustomDifficulty`, etc.) |
| `ServerProcessService::generateServerConfig()` | `server.cfg` format | Arma 3-specific keys (`verifySignatures=2`, `vonCodec=1`, `headlessClients[]`, etc.) |
| `ServerProcessService::generateBasicConfig()` | `server_basic.cfg` format | Arma 3 network config format |
| `ServerProcessService::buildLaunchCommand()` | Launch flags | `-nosplash -skipIntro -world=empty -mod=@...` |
| `ServerProcessService` (HC methods) | HC launch command | `-client -connect=127.0.0.1 -nosound` |
| `DetectServerBooted` listener | Boot detection string | `"Connected to Steam servers"` |
| `game-installs/index.blade.php` | Branch list | `public, contact, creatordlc, profiling, performance, legacy` |
| `game-installs/index.blade.php` | Default name, UI text | `'Arma 3 Server'`, various strings |
| `routes/web.php` backup download | Download filename | `'arma3_' . $serverBackup->server_id . '.vars.Arma3Profile'` |
| `WorkshopMod::getInstallationPath()` | Game ID in path | `config('arma.game_id')` (107410) |
| `InteractsWithFileSystem::convertToLowercase()` | Lowercase requirement | Arma 3 Linux requirement |
| `PresetImportService` | HTML preset parsing | Arma 3 Launcher HTML format, `<meta name="arma:presetName">` |

### Current Database Schema (relevant tables)

**`game_installs`**: id, name, branch, build_id, installation_status, progress_pct, disk_size_bytes, installed_at, timestamps
- NO game_type column

**`servers`**: id, name, port, query_port, max_players, password, admin_password, description, active_preset_id (FK mod_presets), game_install_id (FK game_installs), status, additional_params, verify_signatures, allowed_file_patching, battle_eye, persistent, von_enabled, additional_server_options, timestamps
- NO game_type column
- Arma 3-specific booleans (verify_signatures, battle_eye, etc.) are on this table

**`difficulty_settings`**: id, server_id (FK servers), 22 Arma 3-specific difficulty fields, timestamps
- Entirely Arma 3-specific (class DifficultyPresets format)

**`network_settings`**: id, server_id (FK servers), 11 network tuning fields, timestamps
- Arma 3-specific but conceptually similar across games

**`workshop_mods`**: id, workshop_id (unique), name, file_size, installation_status, progress_pct, installed_at, steam_updated_at, timestamps
- NO game_type column

**`mod_presets`**: id, name (unique), timestamps
- NO game_type column

**`mod_preset_workshop_mod`**: id, mod_preset_id (FK), workshop_mod_id (FK), unique(mod_preset_id, workshop_mod_id), timestamps

**`server_backups`**: id, server_id (FK), name, file_size, is_automatic, data (binary), timestamps

### Current Enums

- `InstallationStatus`: Queued, Installing, Installed, Failed (string-backed, shared by GameInstall and WorkshopMod)
- `ServerStatus`: Stopped, Starting, Booting, Running, Stopping (string-backed)
- NO GameType enum exists

### Current Services

- `SteamCmdService` — uses `config('arma.server_app_id')` and `config('arma.game_id')` for all SteamCMD commands
- `SteamWorkshopService` — fetches mod metadata from Steam API; currently does NOT extract `consumer_appid` from responses
- `ServerProcessService` — 819 lines, monolithic, all Arma 3 logic (config generation, launch commands, symlinks, BiKeys, HCs)
- `ServerBackupService` — hardcoded `.vars.Arma3Profile` paths
- `PresetImportService` — Arma 3 HTML preset parsing only

---

## 2. Reference Project Analysis

The `arma-server-manager/` directory contains a Java/Spring reference project that supports Arma 3, Arma Reforger, DayZ, and DayZ Experimental. Key patterns:

### How It Defines Games

A `ServerType` enum (ARMA3, DAYZ, DAYZ_EXP, REFORGER) is the central discriminator. All game-specific constants are stored as `Map<ServerType, Long>` in a `Constants.java` class.

### Database Approach

JPA JOINED table inheritance: abstract `server` base table + game-specific child tables (`arma3server`, `dayzserver`, `reforger_server`) joined on the same PK. Workshop mods have a `server_type` column. Mod presets have a `type` field for game scoping.

### How Reforger Mods Differ (CRITICAL)

Reforger mods are **fundamentally different** from Workshop mods:
- `ReforgerMod` is a simple embeddable with TWO string fields: `id` (a GUID like `"5965731B836A7E5B"`) and `name`
- **NOT downloaded via SteamCMD** — the Reforger server binary itself downloads mods at startup
- No installation_status, no file_size, no progress tracking
- No symlinks, no BiKeys, no filesystem management by the manager
- Configured inline in the server's **JSON** config file under `game.mods[]`
- Stored as an `@ElementCollection` on the server entity (not a separate managed entity in the reference, but we chose to make it a model — see decisions)

### Config Formats Per Game

- **Arma 3**: SQF-class syntax, 3 files (server.cfg, network.cfg, .Arma3Profile)
- **DayZ**: SQF-class syntax, 1 file (server.cfg with DayZ-specific keys)
- **Reforger**: **JSON format**, 1 file (contains bindPort, game settings, mods array, a2s query config)

### Launch Parameters Per Game

- **Arma 3**: `-port= -name= -profiles= -config= -cfg= -nosplash -skipIntro -world=empty -mod=@Mod1 -mod=@Mod2`
- **DayZ**: `-port= -config= -limitFPS=60 -dologs -adminlog -freezeCheck -mod=@Mod1;@Mod2` (semicolon-separated single -mod param)
- **Reforger**: `-config path/config.json -maxFPS 60 -backendlog -logAppend` (space-separated flags, no `=`)

### Headless Clients

Arma 3 ONLY. The reference has a separate `Arma3ServerProcess` subclass that extends `ServerProcess` to add HC support.

### Missions Per Game

- **Arma 3**: PBO files uploaded to mpmissions/ directory
- **Reforger**: Scenarios enumerated by running server with `-listScenarios` flag, identified by config path strings
- **DayZ**: Map/mission configured in additionalOptions as raw SQF class Missions block

---

## 3. Architectural Decisions

All decisions were made explicitly during the planning session:

| # | Decision | Choice | Rationale |
|---|----------|--------|-----------|
| 1 | Game install model | **Multiple installs per game type** | Add game_type to game_installs. Each game can have multiple installs with different branches. Preserves current flexibility. |
| 2 | Server settings schema | **Separate tables per game** | Keep DifficultySettings/NetworkSettings for Arma 3. New ReforgerSettings, DayZSettings tables. Server has nullable HasOne to each. Clean separation. |
| 3 | Game logic architecture | **Laravel Manager pattern** | `GameManager extends Illuminate\Support\Manager`. Idiomatic Laravel (used by CacheManager, MailManager, etc.). Resolves correct GameHandler per game type. |
| 4 | Reforger mod model | **Separate lightweight ReforgerMod model** | `mod_id` (string GUID) + `name`. No download tracking. Separate from WorkshopMod. |
| 5 | Mod presets | **Scoped by game_type** | Add game_type to mod_presets. Arma 3 presets contain WorkshopMods, Reforger presets contain ReforgerMods. Server can only use presets matching its game type. |
| 6 | UI navigation | **Game-type tabs/filters on existing pages** | Keep current page structure. Add game-type filter tabs within pages. No separate per-game pages. |
| 7 | Mission management | **Game-specific handling per GameHandler** | PBO uploads for Arma 3. Scenario selector for Reforger. Config block for DayZ. Each handler defines mission behavior. |
| 8 | Server column strategy | **Keep Arma 3 booleans on servers table** | verify_signatures, battle_eye, persistent, von_enabled, allowed_file_patching stay on servers. Each GameHandler decides which fields it uses. Avoids complex migration. Game-unique fields go in dedicated settings tables. |
| 9 | Mod game type detection | **Auto-detect from Steam API** | SteamWorkshopService extracts `consumer_appid` from API response, maps to GameType. No manual game selection needed when adding workshop mods. |
| 10 | Reforger mods UI location | **Dedicated tab on Mods page** | Consistent — all mod management in one place. Reforger tab shows simple CRUD for GUID+name entries. |
| 11 | Server form partials | **@include based on game_type** | Single Livewire component, conditional `@include('servers.partials.form-fields-arma3')` etc. One component manages all state, partials handle rendering. |
| 12 | Workshop ID uniqueness | **Composite unique (workshop_id, game_type)** | Allows same numeric workshop_id across different games. Technically correct for Steam Workshop. |
| 13 | Existing settings models | **GameHandler controls access** | Arma3Handler creates/uses DifficultySettings and NetworkSettings. Other handlers ignore them. UI shows panels conditionally. No schema changes to existing settings tables. |
| 14 | Implementation scope | **Full Arma 3 + full Reforger, DayZ scaffolded** | Extract Arma 3 logic into handler (must preserve all behavior). Implement Reforger fully (simpler — no SteamCMD mods, no HCs, no BiKeys). DayZ handler stubs that throw "not yet implemented". |

---

## 4. Phase 1: GameType Enum & Config

### Create `app/Enums/GameType.php`

```php
<?php

namespace App\Enums;

enum GameType: string
{
    case Arma3 = 'arma3';
    case ArmaReforger = 'reforger';
    case DayZ = 'dayz';
    // case Arma4 = 'arma4';  // Future

    /**
     * Human-readable display name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Arma3 => 'Arma 3',
            self::ArmaReforger => 'Arma Reforger',
            self::DayZ => 'DayZ',
        };
    }

    /**
     * Steam App ID for the dedicated server binary.
     */
    public function serverAppId(): int
    {
        return match ($this) {
            self::Arma3 => 233780,
            self::ArmaReforger => 1874900,
            self::DayZ => 223350,
        };
    }

    /**
     * Steam Game ID (used for workshop mod downloads).
     * For Reforger, this is the same as serverAppId.
     */
    public function gameId(): int
    {
        return match ($this) {
            self::Arma3 => 107410,
            self::ArmaReforger => 1874900,
            self::DayZ => 221100,
        };
    }

    /**
     * Server executable filename (no path).
     */
    public function binaryName(): string
    {
        return match ($this) {
            self::Arma3 => 'arma3server_x64',
            self::ArmaReforger => 'ArmaReforgerServer',
            self::DayZ => 'DayZServer_x64',
        };
    }

    /**
     * Default game port.
     */
    public function defaultPort(): int
    {
        return match ($this) {
            self::Arma3 => 2302,
            self::ArmaReforger => 2001,
            self::DayZ => 2302,
        };
    }

    /**
     * Default Steam query port.
     */
    public function defaultQueryPort(): int
    {
        return match ($this) {
            self::Arma3 => 2303,
            self::ArmaReforger => 17777,
            self::DayZ => 27016,
        };
    }

    /**
     * Available SteamCMD beta branches for this game.
     * Branches are hardcoded because the Steam API requires a Steamworks partner token.
     */
    public function branches(): array
    {
        return match ($this) {
            self::Arma3 => ['public', 'contact', 'creatordlc', 'profiling', 'performance', 'legacy'],
            self::ArmaReforger => ['public'],
            self::DayZ => ['public', 'experimental'],
        };
    }

    /**
     * Whether this game uses Steam Workshop mods downloaded via SteamCMD.
     * Reforger downloads its own mods at server startup via the server binary.
     */
    public function supportsWorkshopMods(): bool
    {
        return match ($this) {
            self::Arma3 => true,
            self::ArmaReforger => false,
            self::DayZ => true,
        };
    }

    /**
     * Whether this game supports headless clients for AI offloading.
     */
    public function supportsHeadlessClients(): bool
    {
        return match ($this) {
            self::Arma3 => true,
            self::ArmaReforger => false,
            self::DayZ => false,
        };
    }

    /**
     * Whether this game supports PBO mission file uploads.
     */
    public function supportsMissionUpload(): bool
    {
        return match ($this) {
            self::Arma3 => true,
            self::ArmaReforger => false,
            self::DayZ => false,
        };
    }

    /**
     * Whether mod files need to be converted to lowercase (Linux requirement).
     */
    public function requiresLowercaseConversion(): bool
    {
        return match ($this) {
            self::Arma3 => true,
            self::ArmaReforger => false,
            self::DayZ => true,
        };
    }

    /**
     * String to detect in server log indicating the server has fully booted.
     * Return null if no auto-detection is available.
     */
    public function bootDetectionString(): ?string
    {
        return match ($this) {
            self::Arma3 => 'Connected to Steam servers',
            self::ArmaReforger => null, // Research needed — may use different log format
            self::DayZ => null, // Research needed
        };
    }

    /**
     * File extension for the server profile config file, if applicable.
     */
    public function profileExtension(): ?string
    {
        return match ($this) {
            self::Arma3 => '.Arma3Profile',
            self::ArmaReforger => null,
            self::DayZ => null,
        };
    }

    /**
     * Map a Steam API consumer_appid to a GameType.
     * Used for auto-detecting which game a workshop mod belongs to.
     */
    public static function fromConsumerAppId(int $appId): ?self
    {
        return match ($appId) {
            107410 => self::Arma3,
            221100 => self::DayZ,
            1874900 => self::ArmaReforger,
            default => null,
        };
    }
}
```

### Update `config/arma.php`

Remove `server_app_id` and `game_id` keys. These are now on the `GameType` enum. Keep all base paths and `steamcmd_path` as they are game-agnostic infrastructure.

```php
return [
    'steamcmd_path' => env('STEAMCMD_PATH', '/usr/games/steamcmd'),
    'steam_api_key' => env('STEAM_API_KEY'),
    'games_base_path' => env('GAMES_BASE_PATH') ?: storage_path('arma/games'),
    'servers_base_path' => env('SERVERS_BASE_PATH') ?: storage_path('arma/servers'),
    'mods_base_path' => env('MODS_BASE_PATH') ?: storage_path('arma/mods'),
    'missions_base_path' => env('MISSIONS_BASE_PATH') ?: storage_path('arma/missions'),
    // REMOVED: 'server_app_id' => 233780,  — now GameType::Arma3->serverAppId()
    // REMOVED: 'game_id' => 107410,        — now GameType::Arma3->gameId()
    'max_backups_per_server' => (int) env('MAX_BACKUPS_PER_SERVER', 20),
];
```

Update all call sites that reference `config('arma.server_app_id')` or `config('arma.game_id')` to use the `GameType` enum instead. Grep for these:
- `config('arma.server_app_id')` — used in `SteamCmdService`, `InstallServerJob`
- `config('arma.game_id')` — used in `SteamCmdService`, `WorkshopMod::getInstallationPath()`

---

## 5. Phase 2: Database Migrations

Create these migrations via `php artisan make:migration`. All use `DEFAULT 'arma3'` so existing data is preserved.

### Migration 1: `add_game_type_to_game_installs_table`
```php
Schema::table('game_installs', function (Blueprint $table) {
    $table->string('game_type')->default('arma3')->after('id');
});
```

### Migration 2: `add_game_type_to_servers_table`
```php
Schema::table('servers', function (Blueprint $table) {
    $table->string('game_type')->default('arma3')->after('id');
});
```

### Migration 3: `add_game_type_to_workshop_mods_table`
```php
// Drop existing unique on workshop_id, add composite unique
Schema::table('workshop_mods', function (Blueprint $table) {
    $table->string('game_type')->default('arma3')->after('id');
    $table->dropUnique(['workshop_id']);
    $table->unique(['workshop_id', 'game_type']);
});
```

### Migration 4: `add_game_type_to_mod_presets_table`
```php
Schema::table('mod_presets', function (Blueprint $table) {
    $table->string('game_type')->default('arma3')->after('id');
    $table->dropUnique(['name']);
    $table->unique(['name', 'game_type']);
});
```

### Migration 5: `create_reforger_mods_table`
```php
Schema::create('reforger_mods', function (Blueprint $table) {
    $table->id();
    $table->string('mod_id')->unique(); // GUID string like "5965731B836A7E5B"
    $table->string('name');
    $table->timestamps();
});
```

### Migration 6: `create_mod_preset_reforger_mod_table`
```php
Schema::create('mod_preset_reforger_mod', function (Blueprint $table) {
    $table->id();
    $table->foreignId('mod_preset_id')->constrained()->cascadeOnDelete();
    $table->foreignId('reforger_mod_id')->constrained()->cascadeOnDelete();
    $table->unique(['mod_preset_id', 'reforger_mod_id']);
    $table->timestamps();
});
```

### Migration 7: `create_reforger_settings_table`
```php
Schema::create('reforger_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('server_id')->constrained()->cascadeOnDelete();
    $table->string('scenario_id')->nullable(); // e.g. "{ECC61978EDCC2B5A}Missions/23_Campaign.conf"
    $table->boolean('third_person_view_enabled')->default(true);
    $table->timestamps();
});
```

### Migration 8 (scaffold for future): `create_dayz_settings_table`
```php
Schema::create('dayz_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('server_id')->constrained()->cascadeOnDelete();
    $table->integer('respawn_time')->default(0);
    $table->decimal('time_acceleration', 5, 2)->default(1.0);
    $table->decimal('night_time_acceleration', 5, 2)->default(1.0);
    $table->boolean('force_same_build')->default(true);
    $table->boolean('third_person_view_enabled')->default(true);
    $table->boolean('crosshair_enabled')->default(true);
    $table->boolean('persistent')->default(true);
    $table->timestamps();
});
```

---

## 6. Phase 3: Models

### New Models to Create

#### `app/Models/ReforgerMod.php`
```php
- fillable: mod_id, name
- relationships: presets(): BelongsToMany (through mod_preset_reforger_mod)
- Create factory + seeder
```

#### `app/Models/ReforgerSettings.php`
```php
- fillable: server_id, scenario_id, third_person_view_enabled
- casts: third_person_view_enabled -> boolean
- relationships: server(): BelongsTo
- Create factory
```

#### `app/Models/DayZSettings.php` (scaffold)
```php
- fillable: server_id, respawn_time, time_acceleration, night_time_acceleration, force_same_build, third_person_view_enabled, crosshair_enabled, persistent
- casts: decimals, booleans
- relationships: server(): BelongsTo
- Create factory
```

### Models to Update

#### `GameInstall`
- Add `game_type` to `$fillable`
- Add cast: `'game_type' => GameType::class`
- Add scope: `scopeForGame(Builder $query, GameType $gameType): Builder`
- Update factory to include `game_type` (default `GameType::Arma3`)

#### `Server`
- Add `game_type` to `$fillable`
- Add cast: `'game_type' => GameType::class`
- Add relationships: `reforgerSettings(): HasOne`, `dayzSettings(): HasOne`
- Add scope: `scopeForGame(Builder $query, GameType $gameType): Builder`
- **Remove** `getProfileName()` — delegate to GameHandler
- **Remove** `getBinaryPath()` — delegate to GameHandler
- Update factory to include `game_type` (default `GameType::Arma3`)

#### `WorkshopMod`
- Add `game_type` to `$fillable`
- Add cast: `'game_type' => GameType::class`
- Update `getInstallationPath()`: replace `config('arma.game_id')` with `$this->game_type->gameId()`
- Add scope: `scopeForGame(Builder $query, GameType $gameType): Builder`
- Update factory

#### `ModPreset`
- Add `game_type` to `$fillable`
- Add cast: `'game_type' => GameType::class`
- Add relationship: `reforgerMods(): BelongsToMany` (through `mod_preset_reforger_mod`)
- Add scope: `scopeForGame(Builder $query, GameType $gameType): Builder`
- Update factory

---

## 7. Phase 4: GameManager & Handlers

### Create `app/Contracts/GameHandler.php`

```php
<?php

namespace App\Contracts;

use App\Enums\GameType;
use App\Models\Server;

interface GameHandler
{
    public function gameType(): GameType;

    // --- Server Process ---

    /**
     * Build the full shell command to start the game server.
     */
    public function buildLaunchCommand(Server $server): string;

    /**
     * Generate all config files needed by this game (server.cfg, JSON config, profiles, etc.)
     * Called on every server start.
     */
    public function generateConfigFiles(Server $server): void;

    /**
     * Get the full path to the server executable.
     */
    public function getBinaryPath(Server $server): string;

    /**
     * Get the profile name used for this server (e.g., 'arma3_1').
     */
    public function getProfileName(Server $server): string;

    /**
     * Get the path to the server's log file.
     */
    public function getServerLogPath(Server $server): string;

    /**
     * String that appears in server log when the server is fully booted.
     * Return null to skip auto-detection (server stays in Booting until manually changed or timed out).
     */
    public function getBootDetectionString(): ?string;

    // --- Mods & Assets ---

    /**
     * Create mod symlinks in the game install directory for the server's active preset.
     * No-op for games where the server handles its own mod downloads (e.g., Reforger).
     */
    public function symlinkMods(Server $server): void;

    /**
     * Create mission file symlinks in the game install directory.
     * No-op for games that handle missions differently.
     */
    public function symlinkMissions(Server $server): void;

    /**
     * Copy BiKey signature files from mod directories to the server's keys directory.
     * No-op for games that don't use BiKeys.
     */
    public function copyBiKeys(Server $server): void;

    // --- Headless Clients ---

    /**
     * Whether this game supports headless clients.
     */
    public function supportsHeadlessClients(): bool;

    /**
     * Build the launch command for a headless client instance.
     * Return null if headless clients are not supported.
     */
    public function buildHeadlessClientCommand(Server $server, int $index): ?string;

    // --- Backups ---

    /**
     * Get the path to the file that should be backed up (e.g., .vars.Arma3Profile).
     * Return null if this game has no profile backup concept.
     */
    public function getBackupFilePath(Server $server): ?string;

    /**
     * Get the filename to use when downloading a backup.
     */
    public function getBackupDownloadFilename(Server $server): string;

    // --- Validation ---

    /**
     * Validation rules for game-specific server fields.
     * Merged with common server validation rules in the Livewire component.
     */
    public function serverValidationRules(): array;

    /**
     * Validation rules for game-specific settings (difficulty, network, reforger settings, etc.)
     */
    public function settingsValidationRules(): array;
}
```

### Create `app/GameManager.php`

```php
<?php

namespace App;

use App\Contracts\GameHandler;
use App\Enums\GameType;
use App\GameHandlers\Arma3Handler;
use App\GameHandlers\DayZHandler;
use App\GameHandlers\ReforgerHandler;
use App\Models\Server;
use Illuminate\Support\Manager;

class GameManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return GameType::Arma3->value;
    }

    public function createArma3Driver(): GameHandler
    {
        return new Arma3Handler();
    }

    public function createReforgerDriver(): GameHandler
    {
        return new ReforgerHandler();
    }

    public function createDayzDriver(): GameHandler
    {
        return new DayZHandler();
    }

    /**
     * Convenience: resolve the handler for a server's game type.
     */
    public function for(Server $server): GameHandler
    {
        return $this->driver($server->game_type->value);
    }
}
```

Register in `AppServiceProvider::register()`:
```php
$this->app->singleton(GameManager::class);
```

### Create `app/GameHandlers/Arma3Handler.php`

Extract ALL Arma 3-specific logic from the current `ServerProcessService` into this class. This includes:

- `buildLaunchCommand()` — current `ServerProcessService::buildLaunchCommand()` logic verbatim: `arma3server_x64 -port= -name= -profiles= -config= -cfg= -nosplash -skipIntro -world=empty -mod=@...`
- `generateConfigFiles()` — calls three internal methods:
  - `generateServerConfig()` — extracted from `ServerProcessService::generateServerConfig()` (writes server.cfg with SQF-class syntax)
  - `generateBasicConfig()` — extracted from `ServerProcessService::generateBasicConfig()` (writes server_basic.cfg)
  - `generateProfileConfig()` — extracted from `ServerProcessService::generateProfileConfig()` (writes .Arma3Profile with difficulty classes)
- `getBinaryPath()` — `$server->gameInstall->getInstallationPath() . '/arma3server_x64'`
- `getProfileName()` — `'arma3_' . $server->id`
- `getServerLogPath()` — current logic from ServerProcessService
- `getBootDetectionString()` — `'Connected to Steam servers'`
- `symlinkMods()` — extracted from `ServerProcessService::symlinkMods()` (creates @-prefixed symlinks)
- `symlinkMissions()` — extracted from `ServerProcessService::symlinkMissions()`
- `copyBiKeys()` — extracted from `ServerProcessService::copyBiKeys()`
- `supportsHeadlessClients()` — `true`
- `buildHeadlessClientCommand()` — extracted from `ServerProcessService::addHeadlessClient()` command-building portion
- `getBackupFilePath()` — `{profiles}/home/{profileName}/{profileName}.vars.Arma3Profile`
- `getBackupDownloadFilename()` — `'arma3_' . $server->id . '.vars.Arma3Profile'`
- `serverValidationRules()` — rules for verify_signatures, battle_eye, persistent, von_enabled, allowed_file_patching, additional_server_options
- `settingsValidationRules()` — rules for all 22 difficulty fields + network fields

**Critical**: After extracting, the existing behavior must be preserved exactly. Run all existing tests to verify.

### Create `app/GameHandlers/ReforgerHandler.php`

Full implementation:

- `buildLaunchCommand()` — `ArmaReforgerServer -config {profiles}/REFORGER_{id}.json -maxFPS 60 -backendlog -logAppend {additional_params}`
- `generateConfigFiles()` — writes a JSON config file containing:
  ```json
  {
      "bindAddress": "0.0.0.0",
      "bindPort": 2001,
      "publicAddress": "",
      "a2s": {
          "address": "0.0.0.0",
          "port": 17777
      },
      "game": {
          "name": "Server Name",
          "password": "",
          "passwordAdmin": "",
          "maxPlayers": 32,
          "scenarioId": "{ECC61978EDCC2B5A}Missions/...",
          "gameProperties": {
              "battlEye": true,
              "thirdPersonViewEnabled": true
          },
          "mods": [
              {"modId": "GUID", "name": "Mod Name"},
              ...
          ]
      }
  }
  ```
  Mods come from the server's active preset's `reforgerMods` relationship.
- `getBinaryPath()` — `$server->gameInstall->getInstallationPath() . '/ArmaReforgerServer'`
- `getProfileName()` — `'reforger_' . $server->id`
- `getBootDetectionString()` — `null` (research needed, or skip auto-detection)
- `symlinkMods()` — **no-op** (Reforger downloads its own mods)
- `symlinkMissions()` — **no-op** (Reforger uses scenarios, not PBO uploads)
- `copyBiKeys()` — **no-op**
- `supportsHeadlessClients()` — `false`
- `buildHeadlessClientCommand()` — `null`
- `getBackupFilePath()` — `null`
- `getBackupDownloadFilename()` — `'reforger_' . $server->id . '_backup'`
- `serverValidationRules()` — rules for scenario_id
- `settingsValidationRules()` — rules for third_person_view_enabled

### Create `app/GameHandlers/DayZHandler.php` (scaffold)

Stub implementation. Methods that require full DayZ logic (buildLaunchCommand, generateConfigFiles) throw:
```php
throw new \RuntimeException('DayZ server support is not yet implemented.');
```

Simple methods return correct values:
- `gameType()` — `GameType::DayZ`
- `binaryName()` derived from `GameType::DayZ->binaryName()`
- `supportsHeadlessClients()` — `false`
- `symlinkMods()` — similar to Arma 3 (DayZ uses Workshop mods)
- etc.

---

## 8. Phase 5: Refactor ServerProcessService

Transform `ServerProcessService` from a monolithic Arma 3-specific service into a **thin game-agnostic orchestrator** that delegates to `GameManager`.

### Methods that become delegation calls:

```php
public function start(Server $server): void
{
    $handler = app(GameManager::class)->for($server);

    $this->ensureDirectories($server);

    // Backup (only for games that support it)
    if ($handler->getBackupFilePath($server)) {
        app(ServerBackupService::class)->createFromServer($server, null, true);
    }

    // Delegate all game-specific work
    $handler->symlinkMods($server);
    $handler->symlinkMissions($server);
    $handler->copyBiKeys($server);
    $handler->generateConfigFiles($server);

    // Build and spawn process (game-agnostic)
    $command = $handler->buildLaunchCommand($server);
    $this->spawnProcess($server, $command);
    $this->startLogTail($server);
}
```

### Methods that stay on ServerProcessService (game-agnostic):
- `stop()` — SIGTERM/SIGKILL logic (universal)
- `restart()` — stop + start
- `isRunning()` — PID file check (universal)
- `getStatus()` — status detection and self-healing
- `spawnProcess()` — proc_open/nohup (universal)
- `startLogTail()` / `stopLogTail()` — artisan command management
- PID file management

### Methods that move to GameHandlers:
- `buildLaunchCommand()` → `Arma3Handler::buildLaunchCommand()`
- `generateServerConfig()` → `Arma3Handler::generateConfigFiles()`
- `generateBasicConfig()` → part of `Arma3Handler::generateConfigFiles()`
- `generateProfileConfig()` → part of `Arma3Handler::generateConfigFiles()`
- `symlinkMods()` → `Arma3Handler::symlinkMods()`
- `symlinkMissions()` → `Arma3Handler::symlinkMissions()`
- `copyBiKeys()` → `Arma3Handler::copyBiKeys()`

### Headless Client methods:
- `addHeadlessClient()` — check `$handler->supportsHeadlessClients()` first, then use `$handler->buildHeadlessClientCommand()` to get the command
- `removeHeadlessClient()` — stays on ServerProcessService (kill logic is game-agnostic)
- `stopAllHeadlessClients()` — stays (PID file glob is game-agnostic)

---

## 9. Phase 6: Service & Job Updates

### `SteamCmdService`

- `installServer(string $installDir, string $branch, ?callable $onOutput, GameType $gameType)` — use `$gameType->serverAppId()` instead of `config('arma.server_app_id')`
- `startDownloadMod(string $installDir, int $workshopId, GameType $gameType)` — use `$gameType->gameId()` instead of `config('arma.game_id')`
- `startBatchDownloadMods(string $installDir, array $workshopIds, GameType $gameType)` — same

### `SteamWorkshopService`

- `getMultipleModDetails()` — extract `consumer_appid` from Steam API response and include in return data:
  ```php
  $workshopId => [
      'name' => $details['title'],
      'file_size' => $details['file_size'],
      'time_updated' => $details['time_updated'],
      'game_type' => GameType::fromConsumerAppId($details['consumer_appid']), // NEW
  ]
  ```

### `InstallServerJob`

- Pass `$this->gameInstall->game_type` to `SteamCmdService::installServer()`
- Use `$this->gameInstall->game_type->serverAppId()` when parsing `build_id` from `appmanifest_{appId}.acf`

### `DownloadModJob`

- Pass `$this->mod->game_type` to `SteamCmdService::startDownloadMod()`
- Conditional lowercase conversion: only if `$this->mod->game_type->requiresLowercaseConversion()`

### `BatchDownloadModsJob`

- All mods in a batch must be the same game_type (validated before dispatch)
- Pass game_type to `SteamCmdService::startBatchDownloadMods()`
- Conditional lowercase conversion per game type

### `DetectServerBooted` listener

- Load server, get handler: `$handler = app(GameManager::class)->for($server)`
- Use `$handler->getBootDetectionString()` instead of hardcoded string
- If `null`, skip detection (server stays in Booting)

### `ServerBackupService`

- `getVarsFilePath()` — delegate to handler: `app(GameManager::class)->for($server)->getBackupFilePath($server)`
- `createFromServer()` — check if handler returns a backup path; if null, skip
- Backup download route in `routes/web.php` — use handler for filename

### `PresetImportService`

- HTML import is Arma 3-specific — set `game_type = GameType::Arma3` on created presets
- No changes to parsing logic (it's inherently Arma 3 Launcher format)

---

## 10. Phase 7: UI Changes

### 10.1 Game Installs Page (`resources/views/pages/game-installs/index.blade.php`)

**Create modal changes:**
- Add `<flux:select>` for game type ABOVE the name field, bound to `$createGameType`
- Branch dropdown options populated from `GameType::from($createGameType)->branches()` (use `wire:model.live` on game type to reactively update branches)
- Default name derived from game type label

**List changes:**
- Add game type badge next to each install name
- Optional: filter dropdown at the top to filter by game type

**Livewire component changes:**
- Add `public string $createGameType = 'arma3';` property
- Add computed property for branches based on selected game type
- Pass game_type when creating the GameInstall model

### 10.2 Servers Page (`resources/views/pages/servers/index.blade.php`)

**Create modal changes:**
- Add game type selector (first field)
- Game install dropdown filtered: `GameInstall::where('game_type', $createGameType)->where('installation_status', 'installed')->get()`
- Preset dropdown filtered: `ModPreset::where('game_type', $createGameType)->get()`
- Default port/query_port set from `GameType::from($createGameType)->defaultPort()`
- `wire:model.live` on game type to reactively update dropdowns and form fields

**Configure panel (inline edit) changes:**
- Split `form-fields.blade.php` into:
  - `resources/views/pages/servers/partials/form-fields-common.blade.php` — name, port, query_port, max_players, password, admin_password, description, additional_params, game_install dropdown, preset dropdown
  - `resources/views/pages/servers/partials/form-fields-arma3.blade.php` — verify_signatures, allowed_file_patching, battle_eye, persistent, von_enabled, additional_server_options
  - `resources/views/pages/servers/partials/form-fields-reforger.blade.php` — scenario_id, third_person_view_enabled
  - `resources/views/pages/servers/partials/form-fields-dayz.blade.php` — (scaffold, empty or disabled)
- Main form includes:
  ```blade
  @include('pages.servers.partials.form-fields-common')
  @if($server->game_type->value === 'arma3')
      @include('pages.servers.partials.form-fields-arma3')
  @elseif($server->game_type->value === 'reforger')
      @include('pages.servers.partials.form-fields-reforger')
  @elseif($server->game_type->value === 'dayz')
      @include('pages.servers.partials.form-fields-dayz')
  @endif
  ```

**Server card changes:**
- Show game type icon/badge
- HC add/remove buttons: only if `$server->game_type->supportsHeadlessClients()`
- Backup panel: only if game handler returns a backup file path
- Difficulty settings panel: only for Arma 3
- Network settings panel: only for Arma 3

### 10.3 Mods Page (`resources/views/pages/mods/index.blade.php`)

**Tab structure:**
- Tabs at the top: "Workshop Mods" | "Reforger Mods"
- Workshop Mods tab: current mod list filtered to WorkshopMods (game_type badge on each). Sub-filter by Arma 3 / DayZ if both exist.
- Reforger Mods tab: simple CRUD table with columns: Mod ID (GUID), Name, Actions (edit/delete). Add form: two text inputs (mod_id, name) + add button. No download progress, no SteamCMD integration.

**Workshop mod addition:**
- When user enters a workshop ID and clicks Add:
  1. Fetch metadata from Steam API (existing flow)
  2. Auto-detect game_type from `consumer_appid` in API response
  3. Set `game_type` on the WorkshopMod model automatically
  4. Show the detected game type to the user

### 10.4 Presets Pages

**Create page (`presets/create.blade.php`):**
- Add game type selector at the top (locked after creation — cannot mix game types)
- `wire:model.live` on game type to:
  - Filter available mods by game type
  - For Arma 3/DayZ: show WorkshopMod multi-select (existing UI)
  - For Reforger: show ReforgerMod multi-select
- HTML import button: only visible when game type is Arma 3

**Edit page (`presets/edit.blade.php`):**
- Game type is read-only (displayed as badge, not editable)
- Mod list shows correct mod type based on preset's game_type

**List page (`presets/index.blade.php`):**
- Game type badge on each preset
- Optional filter by game type

### 10.5 Missions Page (`resources/views/pages/missions/index.blade.php`)

- Keep current PBO upload functionality (Arma 3)
- Add a note or section explaining mission handling for other games:
  - Reforger: "Scenarios are configured in server settings (scenario_id field)"
  - DayZ: "Mission/map is configured in server config"
- Could gate the upload section with a check for Arma 3 game installs existing

### 10.6 Dashboard (`resources/views/pages/dashboard.blade.php`)

- Group or badge stats by game type
- E.g., "Servers: 2 Arma 3, 1 Reforger" or show per-game sections

### 10.7 Sidebar Navigation

- No structural changes needed — same pages, game-type filtering happens within pages

### 10.8 Backup Download Route (`routes/web.php`)

Replace:
```php
$filename = 'arma3_'.$serverBackup->server_id.'.vars.Arma3Profile';
```
With:
```php
$handler = app(\App\GameManager::class)->for($serverBackup->server);
$filename = $handler->getBackupDownloadFilename($serverBackup->server);
```

---

## 11. Phase 8: Testing

### Test Structure Improvements

The current test suite has structural issues that will compound with multi-game support. These MUST be addressed as part of this work.

#### Problem 1: ReflectionMethod Hacks in ServerProcessServiceTest

`ServerProcessServiceTest.php` (706 lines, 24 tests) uses `ReflectionMethod` in 6 places to test private methods: `generateServerConfig`, `generateBasicConfig`, `symlinkMissions`, `symlinkMods`, `copyBiKeys`, `buildLaunchCommand`. These methods are moving to `Arma3Handler` where they become public — **the reflection hacks go away entirely**.

**Action**: Migrate ~90% of `ServerProcessServiceTest` assertions into `Arma3HandlerTest`. The remaining `ServerProcessServiceTest` should only test game-agnostic orchestration (start/stop delegates to handler, PID management, status detection, log tail management).

#### Problem 2: Source Code Reading Hack in ServerBackupManagementTest

`ServerBackupManagementTest::test_start_method_includes_auto_backup_call` reads `ServerProcessService.php` via `file_get_contents()` and asserts the source contains certain strings. This will break on refactor.

**Action**: Replace with a behavioral test — start a server with a mock handler that returns a backup file path, then assert a backup was created in the database. This tests the actual behavior rather than inspecting source code.

#### Problem 3: No Shared Test Helpers for Game Scenarios

Every test that needs a server manually wires up `GameInstall::factory()` + `Server::factory()`. With multiple games, this becomes repetitive and error-prone.

**Action**: Create a `CreatesGameScenarios` test trait.

#### Problem 4: MocksServerProcessService Mocks Methods Moving to Handler

`MocksServerProcessService` trait mocks `buildLaunchCommand` and `getServerLogPath` which move to `GameHandler`. Two test files use this trait: `ServerManagementTest` and `ServerBackupManagementTest`.

**Action**: Update the trait to remove handler-method mocks. Add a `MocksGameManager` trait for tests that need to mock handler behavior.

### New Test Traits

#### `tests/Concerns/CreatesGameScenarios.php`

Shared helper for creating complete game-specific test scenarios:

```php
trait CreatesGameScenarios
{
    protected function createArma3Server(array $overrides = []): Server
    {
        $install = GameInstall::factory()->installed()->create(['game_type' => GameType::Arma3]);
        $server = Server::factory()->create(array_merge([
            'game_type' => GameType::Arma3,
            'game_install_id' => $install->id,
        ], $overrides));
        $server->difficultySettings()->create(DifficultySettings::factory()->raw());
        $server->networkSettings()->create(NetworkSettings::factory()->raw());
        return $server->load('gameInstall', 'difficultySettings', 'networkSettings');
    }

    protected function createReforgerServer(array $overrides = []): Server
    {
        $install = GameInstall::factory()->installed()->create(['game_type' => GameType::ArmaReforger]);
        $server = Server::factory()->create(array_merge([
            'game_type' => GameType::ArmaReforger,
            'game_install_id' => $install->id,
            'port' => 2001,
            'query_port' => 17777,
        ], $overrides));
        $server->reforgerSettings()->create(['scenario_id' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf']);
        return $server->load('gameInstall', 'reforgerSettings');
    }

    protected function createDayZServer(array $overrides = []): Server
    {
        $install = GameInstall::factory()->installed()->create(['game_type' => GameType::DayZ]);
        return Server::factory()->create(array_merge([
            'game_type' => GameType::DayZ,
            'game_install_id' => $install->id,
            'query_port' => 27016,
        ], $overrides));
    }
}
```

#### `tests/Concerns/MocksGameManager.php`

For Livewire component tests that need to mock game handler behavior:

```php
trait MocksGameManager
{
    protected function mockGameManager(?GameType $gameType = null): void
    {
        $handler = Mockery::mock(GameHandler::class);
        $handler->shouldReceive('supportsHeadlessClients')->andReturn($gameType === GameType::Arma3);
        $handler->shouldReceive('getBackupFilePath')->andReturn(
            $gameType === GameType::Arma3 ? '/fake/backup/path' : null
        );
        $handler->shouldReceive('getBootDetectionString')->andReturn(
            $gameType === GameType::Arma3 ? 'Connected to Steam servers' : null
        );
        // ... other handler methods as needed

        $manager = Mockery::mock(GameManager::class);
        $manager->shouldReceive('for')->andReturn($handler);
        $manager->shouldReceive('driver')->andReturn($handler);
        $this->app->instance(GameManager::class, $manager);
    }
}
```

### Factory Updates

#### `GameInstallFactory` — add `game_type` + game-specific states
```php
// In definition():
'game_type' => GameType::Arma3,
'name' => 'Arma 3 Server',

// New states:
public function reforger(): static
{
    return $this->state(['game_type' => GameType::ArmaReforger, 'name' => 'Reforger Server']);
}

public function dayz(): static
{
    return $this->state(['game_type' => GameType::DayZ, 'name' => 'DayZ Server']);
}
```

#### `ServerFactory` — add `game_type` + game-specific states
```php
// In definition():
'game_type' => GameType::Arma3,

// New states:
public function forReforger(): static
{
    return $this->state(fn () => [
        'game_type' => GameType::ArmaReforger,
        'game_install_id' => GameInstall::factory()->installed()->reforger(),
        'port' => 2001,
        'query_port' => 17777,
    ]);
}

public function forDayZ(): static
{
    return $this->state(fn () => [
        'game_type' => GameType::DayZ,
        'game_install_id' => GameInstall::factory()->installed()->dayz(),
        'query_port' => 27016,
    ]);
}
```

#### `WorkshopModFactory` — add `game_type`
```php
// In definition():
'game_type' => GameType::Arma3,

// New state:
public function dayz(): static
{
    return $this->state(['game_type' => GameType::DayZ]);
}
```

#### `ModPresetFactory` — add `game_type` + states
```php
// In definition():
'game_type' => GameType::Arma3,

// New states:
public function reforger(): static
{
    return $this->state(['game_type' => GameType::ArmaReforger]);
}
```

#### New factories to create:
- `ReforgerModFactory` — `mod_id` (fake GUID string), `name` (random words)
- `ReforgerSettingsFactory` — `server_id`, `scenario_id`, `third_person_view_enabled`
- `DayZSettingsFactory` — `server_id`, all DayZ-specific fields with defaults

### Restructured Test Directory

```
tests/
  Concerns/
    MocksSteamCmdProcess.php        # Keep — game-agnostic
    MocksServerProcessService.php   # UPDATE — remove buildLaunchCommand/getServerLogPath mocks
    MocksGameManager.php            # NEW — mocks GameManager + handler
    CreatesGameScenarios.php        # NEW — helpers for creating per-game server setups
  Unit/
    Enums/
      GameTypeTest.php              # NEW — enum method tests + fromConsumerAppId
    GameManagerTest.php             # NEW — driver resolution
  Feature/
    GameHandlers/
      Arma3HandlerTest.php          # NEW — migrated from ServerProcessServiceTest
      ReforgerHandlerTest.php       # NEW — Reforger JSON config, launch cmd, no-ops
      DayZHandlerTest.php           # NEW — scaffold, stubs throw RuntimeException
    GameInstalls/
      GameInstallManagementTest.php # UPDATE — set createGameType in tests
    Servers/
      ServerManagementTest.php      # UPDATE — add createGameType, conditional UI assertions
      ServerProcessServiceTest.php  # REWRITE — only game-agnostic orchestration tests remain
      ServerBackupServiceTest.php   # UPDATE — handler-delegated path assertions
      ServerBackupManagementTest.php # UPDATE — remove file_get_contents hack
      MultiGameServerTest.php       # NEW — cross-game validation tests
    Mods/
      WorkshopModManagementTest.php # UPDATE — game_type tabs, auto-detection
      ReforgerModManagementTest.php # NEW — Reforger mod CRUD on mods page
    Presets/
      ModPresetManagementTest.php   # UPDATE — game_type scoping, unique constraint
      ScopedPresetTest.php          # NEW — cross-game preset validation
    Jobs/
      InstallServerJobTest.php      # UPDATE — game_type param, remove config() calls
      DownloadModJobTest.php        # UPDATE — game_type param, conditional lowercase
      BatchDownloadModsJobTest.php  # UPDATE — game_type param, conditional lowercase
      StartServerJobTest.php        # MINOR — factory updates only
      StopServerJobTest.php         # MINOR — factory updates only
    Listeners/
      DetectServerBootedTest.php    # UPDATE — dynamic boot string via handler
    Services/
      PresetImportServiceTest.php   # UPDATE — verify game_type=arma3 on imports
    ... (auth, settings, events, etc. — no changes needed)
```

### What Moves from ServerProcessServiceTest to Handler Tests

| Current ServerProcessServiceTest Method | Destination |
|----------------------------------------|-------------|
| `test_generates_server_config_*` (config content, passwords, VoN, BattlEye, HC whitelist, MOTD, additional options) | `Arma3HandlerTest` |
| `test_generates_basic_config_*` (network settings values, defaults) | `Arma3HandlerTest` |
| `test_generates_profile_config_*` (difficulty settings) | `Arma3HandlerTest` |
| `test_symlinks_mods_*` (creates symlinks, cleans old, normalized names) | `Arma3HandlerTest` |
| `test_symlinks_missions_*` (creates mission symlinks) | `Arma3HandlerTest` |
| `test_copies_bikeys_*` (BiKey file handling) | `Arma3HandlerTest` |
| `test_build_launch_command_*` (binary path, flags, mod params) | `Arma3HandlerTest` |
| `test_start_*` (process spawning, status transitions) | Stays in `ServerProcessServiceTest` |
| `test_stop_*` (SIGTERM, SIGKILL, PID cleanup) | Stays in `ServerProcessServiceTest` |
| `test_is_running_*` (PID checks) | Stays in `ServerProcessServiceTest` |
| `test_get_status_*` (status detection, self-healing) | Stays in `ServerProcessServiceTest` |

### Specific Existing Test Files Requiring Updates

#### `InstallServerJobTest.php` — HIGH priority
- Line 55: `config('arma.server_app_id')` in `parseBuildId()` test → change to `$install->game_type->serverAppId()` or hardcode `233780` with a comment
- Line 59: Hardcoded `"appid" "233780"` in ACF fixture — valid for Arma 3 test, keep as-is
- `SteamCmdService::installServer()` mock — must accept new `GameType` parameter

#### `DownloadModJobTest.php` — HIGH priority
- All `SteamCmdService::startDownloadMod()` mocks (8 places) — must accept new `GameType` parameter
- `convertToLowercase` test — becomes conditional on `game_type->requiresLowercaseConversion()`

#### `BatchDownloadModsJobTest.php` — HIGH priority (MISSING from original spec)
- All `SteamCmdService::startBatchDownloadMods()` mocks (7 places) — must accept new `GameType` parameter
- `convertToLowercase` test — same conditional logic
- Add test: batch rejects mods with mixed game_types

#### `DetectServerBootedTest.php` — MEDIUM priority
- Hardcoded `"Connected to Steam servers"` in 3 places — listener now resolves via handler
- Add test: Reforger servers with `null` boot detection string stay in Booting

#### `ServerBackupServiceTest.php` — MEDIUM priority
- Path assertions with hardcoded `arma3_` and `.vars.Arma3Profile` — still valid for Arma 3 but fragile if handler path construction changes
- Recommend: Use `$handler->getBackupFilePath($server)` in assertions instead of hardcoding

#### `ServerBackupManagementTest.php` — MEDIUM priority
- Line 310: `file_get_contents(app_path('Services/ServerProcessService.php'))` source inspection — **replace with behavioral test**
- Backup download route filename — will change to handler-based

#### `ServerManagementTest.php` — MEDIUM priority
- Create server form may need `createGameType` property if UI adds game type selector
- HC control visibility tests — add game_type check (`supportsHeadlessClients()`)
- Network settings tests — add conditional visibility check (Arma 3 only)

#### `ModPresetManagementTest.php` — LOW priority
- Unique name validation tests — update expected behavior for composite `(name, game_type)` constraint
- Preset form may need game_type selector

#### `GameInstallManagementTest.php` — LOW priority
- Create modal may need `createGameType` property
- Factory calls get game_type by default

#### `WorkshopModManagementTest.php` — LOW priority
- Game-type tabs on mods page — existing tests may need tab context
- Auto-detection from `consumer_appid` when adding mods

#### `PresetImportServiceTest.php` — LOW priority
- Add assertion: imported presets have `game_type = GameType::Arma3`

### New Test Scenarios (not covered in original spec)

| Scenario | Test File | Priority |
|----------|-----------|----------|
| Backup download route returns handler-based filename | `ServerBackupManagementTest` or new route test | Medium |
| `PresetImportService::importFromHtml()` sets `game_type = Arma3` on preset | `PresetImportServiceTest` | Medium |
| Workshop mod `game_type` auto-detection from `consumer_appid` | `WorkshopModManagementTest` or `SteamWorkshopServiceTest` | Medium |
| `ServerProcessService::addHeadlessClient()` rejects non-Arma3 servers | `ServerProcessServiceTest` | Medium |
| `WorkshopMod` composite unique `(workshop_id, game_type)` allows duplicates across games | `WorkshopModManagementTest` | Medium |
| Server rejects mismatched `game_install_id` game_type | `MultiGameServerTest` | High |
| Server rejects mismatched `active_preset_id` game_type | `MultiGameServerTest` | High |
| `TailServerLog` command uses handler for log path | New command test or `ServerProcessServiceTest` | Medium |

---

## 11b. Audit Findings — Additional Files Requiring Changes

The following files were identified during a comprehensive audit but were NOT in the original plan. They MUST be addressed.

### CRITICAL — Will Break if Missed

| File | Issue | Fix |
|------|-------|-----|
| `app/Console/Commands/TailServerLog.php` line 18 | Hardcodes log path as `$server->getProfilesPath().'/server.log'` — bypasses `ServerProcessService::getServerLogPath()` entirely | Delegate to `GameManager::for($server)->getServerLogPath($server)` |
| `tests/Feature/Servers/ServerProcessServiceTest.php` | 706 lines; 6 ReflectionMethod calls to private methods that are MOVING to `Arma3Handler`. ALL will break | Migrate ~90% of tests to `Arma3HandlerTest`. See Phase 8 restructuring above |
| `tests/Feature/Jobs/InstallServerJobTest.php` line 55 | Uses `config('arma.server_app_id')` which is being REMOVED | Change to `$install->game_type->serverAppId()` |
| `tests/Feature/Jobs/BatchDownloadModsJobTest.php` | All `startBatchDownloadMods()` mocks need new `GameType` parameter — file was MISSING from original spec entirely | Same pattern as `DownloadModJobTest` updates |

### HIGH — User-Facing Text and UI Strings

| File | Issue | Fix |
|------|-------|-----|
| `resources/views/pages/servers/index.blade.php` | 3 places reference `.vars.Arma3Profile` in user-facing text; 2 places say "Arma 3 server instances" | Make dynamic per `$server->game_type->label()` |
| `resources/views/pages/game-installs/index.blade.php` | `$name = 'Arma 3 Server'` default (line 17); `$this->name = 'Arma 3 Server'` reset (line 50); 4 UI strings with "Arma 3" | Use `$selectedGameType->label()` for dynamic text |
| `resources/views/pages/presets/index.blade.php` | 3 references to "Arma 3 Launcher" in HTML import section | Only show import section when game_type is Arma 3 |
| `resources/views/pages/dashboard.blade.php` | "Overview of your Arma 3 server manager" text; PBO-only mission count logic; "PBO files" label | Make game-agnostic; mission count should handle multiple game types |

### MEDIUM — Documentation and Config

| File | Issue | Fix |
|------|-------|-----|
| `AGENTS.md` | Dozens of Arma 3-specific references throughout project documentation | Update after implementation is complete to reflect multi-game architecture |
| `config/arma.php` | 6 comment lines reference "Arma 3" specifically | Update comments to be game-agnostic |
| `tests/Concerns/MocksServerProcessService.php` | Mocks `buildLaunchCommand` and `getServerLogPath` which move to handler | Remove those mocks; add `MocksGameManager` trait instead |
| `tests/Feature/Servers/ServerBackupManagementTest.php` line 310 | `file_get_contents(app_path('Services/ServerProcessService.php'))` source inspection hack | Replace with behavioral test |

### LOW — Cosmetic / Indirect

| File | Issue | Fix |
|------|-------|-----|
| `app/Models/Server.php` | PHPDoc comments reference "Arma 3" | Update comments |
| `database/factories/GameInstallFactory.php` | Hardcoded `'name' => 'Arma 3 Server'` | Dynamic per game_type or keep as default with factory states |
| `tests/Feature/Presets/ModPresetManagementTest.php` | Unique name validation tests will break when constraint changes to `(name, game_type)` | Update expected validation behavior |

### Files Confirmed Safe (no changes needed)

- `app/Console/Commands/CreateAdminUser.php` — no game-specific logic
- `routes/channels.php` — no game-specific channels
- `docker/entrypoint.sh`, `docker/supervisord.conf`, `Dockerfile` — no game-specific references
- `resources/js/app.js` — no game-specific logic
- All layout files (`resources/views/layouts/`) — no "Arma 3" text; uses generic labels
- All broadcast events (`app/Events/`) — game-agnostic payloads
- `app/Jobs/Concerns/InteractsWithFileSystem.php` — game-agnostic; caller decides whether to invoke
- `resources/views/pages/steam-settings.blade.php` — Steam credentials are shared across games
- Auth tests, settings tests, toast manager tests, broadcast event tests — unrelated to game type
- `tests/Concerns/MocksSteamCmdProcess.php` — mocks transport-level InvokedProcess, not service signatures

---

## 12. Implementation Order

Execute in this exact order. Each step should be committed and tested before proceeding.

| Step | Description | Files Changed | Risk | Verification |
|------|-------------|--------------|------|-------------|
| **1** | Create `GameType` enum | `app/Enums/GameType.php` | Low | Unit test enum methods |
| **2** | Update `config/arma.php` — remove `server_app_id` and `game_id` | `config/arma.php`, all call sites | Low | `grep -r "config('arma.server_app_id')"` and `grep -r "config('arma.game_id')"` return no results |
| **3** | Create all 8 database migrations | `database/migrations/` | Low | `php artisan migrate` succeeds; existing data gets `game_type = arma3` |
| **4** | Update existing models (add game_type, scopes, casts) + update factories (add game_type, game-specific states) | `GameInstall`, `Server`, `WorkshopMod`, `ModPreset` + all 4 factories | Low | Existing tests pass |
| **5** | Create new models + factories | `ReforgerMod`, `ReforgerSettings`, `DayZSettings` + `ReforgerModFactory`, `ReforgerSettingsFactory`, `DayZSettingsFactory` | Low | New model unit tests pass |
| **6** | Create `GameHandler` interface | `app/Contracts/GameHandler.php` | Low | No runtime impact |
| **7** | Create `Arma3Handler` — extract from `ServerProcessService` | `app/GameHandlers/Arma3Handler.php` | **HIGH** | ALL existing ServerProcessService tests must pass with handler |
| **8** | Create `GameManager` + register in AppServiceProvider | `app/GameManager.php`, `app/Providers/AppServiceProvider.php` | Low | Manager resolves correct handler |
| **9** | Refactor `ServerProcessService` to delegate to GameManager | `app/Services/ServerProcessService.php` | **HIGH** | ALL existing tests must pass unchanged |
| **9b** | Update `TailServerLog` command — use handler for log path | `app/Console/Commands/TailServerLog.php` | Low | Log path resolves correctly per game type |
| **10** | Update `SteamCmdService` — accept GameType param | `app/Services/SteamCmdService.php` | Medium | Job tests pass with updated signatures |
| **11** | Update `SteamWorkshopService` — extract consumer_appid from both `getModDetails()` and `getMultipleModDetails()` | `app/Services/SteamWorkshopService.php` | Low | Test auto-detection |
| **12** | Update all jobs — pass game_type to services | `InstallServerJob`, `DownloadModJob`, `BatchDownloadModsJob` | Medium | Job tests pass |
| **13** | Update `DetectServerBooted` — use handler boot string | `app/Listeners/DetectServerBooted.php` | Low | Listener test passes |
| **14** | Update `ServerBackupService` — delegate to handler | `app/Services/ServerBackupService.php`, `routes/web.php` | Low | Backup tests pass |
| **15** | Restructure tests — create `CreatesGameScenarios` + `MocksGameManager` traits, migrate `ServerProcessServiceTest` to `Arma3HandlerTest`, update `MocksServerProcessService`, fix `file_get_contents` hack in `ServerBackupManagementTest`, update all job test mocks for new signatures | All test files listed in Phase 8 | **HIGH** | `php artisan test --compact` — ALL GREEN |
| **16** | Implement `ReforgerHandler` + `ReforgerHandlerTest` | `app/GameHandlers/ReforgerHandler.php`, `tests/Feature/GameHandlers/ReforgerHandlerTest.php` | Medium | New handler tests pass |
| **17** | Scaffold `DayZHandler` + `DayZHandlerTest` | `app/GameHandlers/DayZHandler.php`, `tests/Feature/GameHandlers/DayZHandlerTest.php` | Low | Throws RuntimeException |
| **18** | UI: Game Installs page — game type in create + list + dynamic branch/name | `game-installs/index.blade.php` | Medium | Livewire test, update `GameInstallManagementTest` |
| **19** | UI: Servers page — game type + conditional form partials + dynamic UI text | `servers/index.blade.php`, new partials, update `ServerManagementTest` | **HIGH** | Livewire tests per game type |
| **20** | UI: Mods page — game type tabs (Workshop + Reforger CRUD) | `mods/index.blade.php`, new `ReforgerModManagementTest` | Medium | Livewire tests |
| **21** | UI: Presets pages — game type scoping + conditional mod selection | `presets/*.blade.php`, update `ModPresetManagementTest`, new `ScopedPresetTest` | Medium | Livewire tests |
| **22** | UI: Missions + Dashboard — dynamic text + conditional sections | `missions/index.blade.php`, `dashboard.blade.php` | Low | Visual + `DashboardTest` update |
| **23** | Cross-game validation tests — `MultiGameServerTest` (mismatch checks, feature gating) | `tests/Feature/Servers/MultiGameServerTest.php` | Medium | New test file passes |
| **24** | Update `AGENTS.md` project documentation | `AGENTS.md` | Low | Review |
| **25** | Run `vendor/bin/pint --dirty --format agent` | All PHP files | Low | Formatting |
| **26** | **Run full test suite** | — | — | `php artisan test --compact` — ALL GREEN |

Steps 1–14 are the "refactor without breaking" phase. Step 15 restructures tests. Steps 16–23 add visible multi-game support. Steps 24–26 finalize.

---

## 13. Game-Specific Reference Data

### Steam IDs

| Game | Game ID (workshop) | Server App ID | Binary |
|------|-------------------|---------------|--------|
| Arma 3 | 107410 | 233780 | `arma3server_x64` |
| Arma Reforger | 1874900 | 1874900 | `ArmaReforgerServer` |
| DayZ | 221100 | 223350 | `DayZServer_x64` |

### Default Ports

| Game | Game Port | Query Port |
|------|-----------|------------|
| Arma 3 | 2302 | 2303 |
| Arma Reforger | 2001 | 17777 |
| DayZ | 2302 | 27016 |

### Branches

| Game | Available Branches |
|------|--------------------|
| Arma 3 | public, contact, creatordlc, profiling, performance, legacy |
| Arma Reforger | public |
| DayZ | public, experimental |

### Config File Formats

| Game | Files | Format |
|------|-------|--------|
| Arma 3 | server.cfg, server_basic.cfg, .Arma3Profile | SQF-class syntax |
| Arma Reforger | config.json | JSON |
| DayZ | server.cfg | SQF-class syntax (different keys than Arma 3) |

### Launch Parameter Formats

| Game | Mod Parameter Style | Key Flags |
|------|-------------------|-----------|
| Arma 3 | `-mod=@Mod1 -mod=@Mod2` (separate per mod) | `-nosplash -skipIntro -world=empty` |
| Arma Reforger | Mods in JSON config, not CLI | `-maxFPS 60 -backendlog` (space-separated, no `=`) |
| DayZ | `-mod=@Mod1;@Mod2` (semicolon-separated, single param) | `-limitFPS=60 -dologs -adminlog -freezeCheck` |

### Feature Support Matrix

| Feature | Arma 3 | Reforger | DayZ |
|---------|--------|----------|------|
| Workshop mods (SteamCMD) | Yes | No (server self-downloads) | Yes |
| Headless clients | Yes (max 10) | No | No |
| Mission PBO upload | Yes | No (uses scenarios) | No (config block) |
| BiKey verification | Yes | No | Yes |
| Lowercase conversion | Yes | No | Yes |
| Profile backups (.vars) | Yes | No | TBD |
| Difficulty settings | Yes (22 fields) | No | No |
| Network settings | Yes (11 fields) | No | No |
| HTML preset import | Yes | No | No |

### Reforger JSON Config Template

```json
{
    "bindAddress": "0.0.0.0",
    "bindPort": 2001,
    "publicAddress": "",
    "a2s": {
        "address": "0.0.0.0",
        "port": 17777
    },
    "game": {
        "name": "My Reforger Server",
        "password": "",
        "passwordAdmin": "",
        "maxPlayers": 32,
        "scenarioId": "{ECC61978EDCC2B5A}Missions/23_Campaign.conf",
        "gameProperties": {
            "battlEye": true,
            "thirdPersonViewEnabled": true
        },
        "mods": [
            {
                "modId": "5965731B836A7E5B",
                "name": "ACE"
            }
        ]
    }
}
```

### Arma 3 server.cfg Template (current format for reference)

Generated by `ServerProcessService::generateServerConfig()`. Key fields:
- `hostname`, `password`, `passwordAdmin`, `maxPlayers`
- `verifySignatures = 2` (0=off, 2=on)
- `allowedFilePatching = 2` (0=off, 2=on)
- `BattlEye = 1`
- `persistent = 1`
- `disableVoN = 0`
- `vonCodec = 1; vonCodecQuality = 30;`
- `headlessClients[] = {"127.0.0.1"}; localClient[] = {"127.0.0.1"};`
- `motd[] = { ... }; motdInterval = 30;`
- `onUnsignedData`, `onHackedData`, `onDifferentData` callbacks

### DayZ server.cfg Template (from reference project)

Key DayZ-specific fields (different from Arma 3):
- `steamQueryPort` (separate from game port)
- `forceSameBuild = 1`
- `serverTimePersistent = 1`
- `serverTimeAcceleration = 1.0`
- `serverNightTimeAcceleration = 1.0`
- `disable3rdPerson = 0`
- `disableCrosshair = 0`
- `respawnTime = 0`
- `instanceId = 1`
- `class Missions { ... }` block for mission/map selection

---

## Notes for the Implementing Session

1. **The reference project is at `arma-server-manager/`** in the project root. Consult it for game-specific details (config templates, launch params, etc.).

2. **The highest-risk steps are 7, 9, 15, and 19** (extracting Arma3Handler, refactoring ServerProcessService, restructuring tests, and the servers UI). Run the full test suite after each.

3. **Do NOT change any behavior for Arma 3 servers**. The refactoring should be purely structural — same inputs produce same outputs. Existing tests are the contract.

4. **The `ServerProcessService` is 819 lines.** After refactoring, it should be ~200-300 lines of game-agnostic orchestration. The Arma 3-specific logic (~500 lines) moves to `Arma3Handler`.

5. **`flux:select` null bug**: When binding an integer property to `flux:select`, pre-initialize the property in the open/mount method to avoid the flux:select null-on-no-interaction bug. This applies to the new game type selectors.

6. **Run `vendor/bin/pint --dirty --format agent`** after modifying any PHP files, before finalizing.

7. **Follow existing code conventions** — check sibling files for structure, naming, and approach before creating new files. Use `php artisan make:model`, `php artisan make:migration`, etc.

8. **Every change must be tested** (per AGENTS.md rules). Write tests, then run `php artisan test --compact --filter=testName` for specific tests, full suite at checkpoints.

9. **Test restructuring is step 15, not an afterthought.** The test migration from `ServerProcessServiceTest` to `Arma3HandlerTest` is a prerequisite for steps 16+ (implementing ReforgerHandler). Do it before adding new game handlers.

10. **Handler methods should be public.** The entire point of extracting logic from `ServerProcessService` private methods to `GameHandler` implementations is that handler methods are public and directly testable — no more ReflectionMethod hacks.

11. **The `file_get_contents` hack in `ServerBackupManagementTest` line 310** must be replaced with a behavioral test before the ServerProcessService refactor (step 9), or it will break. Replace it with: start a server (mocked), assert a backup record was created in the database.

12. **`TailServerLog` command (step 9b)** is easy to miss. It directly hardcodes `$server->getProfilesPath().'/server.log'` without going through any service. Must delegate to `GameManager::for($server)->getServerLogPath($server)`.

13. **`SteamWorkshopService::getModDetails()` (single-mod method)** also needs `consumer_appid` extraction, not just `getMultipleModDetails()`. Both are used in different contexts — `getModDetails()` when adding a single mod from the UI.

14. **Current test stats**: 29 test files, ~256 test methods, ~6,437 lines of test code. After restructuring, expect ~33 test files, ~290+ test methods.
