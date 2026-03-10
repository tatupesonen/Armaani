---
name: game-handler-development
description: 'Activates when adding a new game to the server manager. Use when creating game handlers, implementing GameHandler/SteamGameHandler contracts, configuring server settings schemas, generating config files, or when the user mentions adding a new game, game support, or game handler.'
---

# Game Handler Development

## When to Apply

Activate this skill when:

- Adding support for a new game (e.g., Squad, Project Zomboid, Insurgency)
- Modifying an existing game handler in `app/GameHandlers/`
- Working with the `GameHandler`, `SteamGameHandler`, `SupportsRegisteredMods`, or `SupportsScenarios` contracts
- Creating or editing config templates in `resources/templates/configs/`
- Working with `settingsSchema()` UI definitions
- Running `php artisan make:game-handler`

## Research First

Before implementing a game handler, **research the game's dedicated server online** to understand:

- The game's Steam App IDs (server, game, consumer) via [SteamDB](https://steamdb.info)
- What config file format the server uses (key-value `.cfg`, JSON, XML, INI, etc.)
- What server parameters and launch flags it supports
- Default ports (game port, query port)
- Whether it supports Steam Workshop mods
- Whether it has GUID-based registered mods (like Reforger)
- Whether it has discoverable scenarios/missions

Use this research to populate the handler's methods and create accurate config templates.

## Scaffolding with make:game-handler

The `make:game-handler` Artisan command scaffolds a new handler with all required boilerplate:

```bash
# Interactive mode (prompts for all options)
php artisan make:game-handler SquadServer

# Non-interactive with all flags
php artisan make:game-handler Squad --steam --settings --registered-mods --scenarios --label="Squad"
```

**Flags:**

- `--steam` — Implement `SteamGameHandler` interface (most games need this)
- `--settings` — Generate a settings model, migration, and factory
- `--registered-mods` — Implement `SupportsRegisteredMods` interface
- `--scenarios` — Implement `SupportsScenarios` interface
- `--label="Display Name"` — Human-readable game name
- `--force` — Overwrite existing files

The command auto-appends `Handler` to the name if not present.

**Generated files:**

- `app/GameHandlers/{Name}Handler.php` — The handler class
- `app/Models/{Name}Settings.php` — Settings model (if `--settings`)
- `database/factories/{Name}SettingsFactory.php` — Factory (if `--settings`)
- `database/migrations/..._create_{name}_settings_table.php` — Migration (if `--settings`)

## Architecture Overview

### Auto-Discovery

Game handlers are auto-discovered by `GameServiceProvider` from `app/GameHandlers/`. No manual registration needed — just create the handler class and it works.

### Dynamic Relationships

`GameServiceProvider::boot()` calls `resolveRelationUsing()` on `Server` and `ModPreset` to register each handler's settings and mod relationships. The Server model has zero per-game code.

### TypeScript Type Generation

After creating or modifying a handler, run:

```bash
php artisan game:generate-types
```

This generates `resources/js/types/generated.d.ts` with a discriminated union `Server` type. The Vite plugin in `vite.config.ts` auto-runs this during dev when files in `app/GameHandlers/` change.

## Contracts Reference

### GameHandler (required)

Every handler must implement `App\Contracts\GameHandler`. Key methods:

| Method                                       | Purpose                                                               |
| -------------------------------------------- | --------------------------------------------------------------------- |
| `value(): string`                            | Unique identifier (e.g., `'squad'`). Used as DB value and driver key. |
| `label(): string`                            | Display name (e.g., `'Squad'`).                                       |
| `defaultPort(): int`                         | Default game server port.                                             |
| `defaultQueryPort(): int`                    | Default Steam query port.                                             |
| `branches(): array`                          | SteamCMD beta branches (at minimum `['public']`).                     |
| `supportsWorkshopMods(): bool`               | Whether the game uses Steam Workshop.                                 |
| `requiresLowercaseConversion(): bool`        | Whether mod files need lowercase conversion (Linux).                  |
| `buildLaunchCommand(Server): array`          | Command array to start the server process.                            |
| `generateConfigFiles(Server): void`          | Write config files before server start.                               |
| `getBinaryPath(Server): string`              | Path to the server executable.                                        |
| `getProfileName(Server): string`             | Profile directory name (e.g., `'squad_1'`).                           |
| `getServerLogPath(Server): string`           | Path to the server log file.                                          |
| `serverValidationRules(?Server): array`      | Validation rules for game-specific server fields.                     |
| `settingsValidationRules(): array`           | Validation rules for game-specific settings.                          |
| `settingsSchema(): array`                    | UI schema for the server settings panel.                              |
| `settingsModelClass(): ?string`              | FQCN of the settings model, or null.                                  |
| `settingsRelationName(): ?string`            | Relationship name on Server (e.g., `'squadSettings'`), or null.       |
| `createRelatedSettings(Server): void`        | Create default settings when a server is created.                     |
| `updateRelatedSettings(Server, array): void` | Update settings from validated form data.                             |
| `modSections(): array`                       | Define mod UI tabs (workshop and/or registered).                      |
| `syncPresetMods(ModPreset, array): void`     | Sync mod preset relationships from form data.                         |
| `getPresetModCount(ModPreset): int`          | Total mod count for a preset.                                         |

### SteamGameHandler (most games)

Implement `App\Contracts\SteamGameHandler` for Steam-based games:

| Method                 | Purpose                                                     |
| ---------------------- | ----------------------------------------------------------- |
| `serverAppId(): int`   | Dedicated server Steam App ID (for `+app_update`).          |
| `gameId(): int`        | Game ID for workshop mod paths (`+workshop_download_item`). |
| `consumerAppId(): int` | Consumer App ID for Steam Web API mod detection.            |

Look up these IDs on [SteamDB](https://steamdb.info).

### SupportsRegisteredMods (optional)

Implement `App\Contracts\SupportsRegisteredMods` for games with GUID-based mods (not Steam Workshop). Requires its own mod model, pivot table, and CRUD logic.

### SupportsScenarios (optional)

Implement `App\Contracts\SupportsScenarios` for games with discoverable scenarios/missions.

## Config File Generation

Handlers generate server config files via the `generateConfigFiles(Server $server)` method. Two renderers are available:

### TwigConfigRenderer — For text-based configs

Use for key-value config formats (`.cfg`, `.ini`, custom text formats). Templates live in `resources/templates/configs/{game}/`.

```php
use App\Services\Renderer\TwigConfigRenderer;

final class SquadHandler implements GameHandler, SteamGameHandler
{
    public function __construct(
        protected TwigConfigRenderer $configRenderer,
    ) {}

    public function generateConfigFiles(Server $server): void
    {
        $content = $this->configRenderer->render('squad/server.cfg.twig', [
            'hostname' => $server->name,
            'max_players' => $server->max_players,
            'password' => $server->password ?? '',
        ]);

        file_put_contents(
            $server->getProfilesPath() . '/Server.cfg',
            $content,
        );
    }
}
```

Twig template example (`resources/templates/configs/squad/server.cfg.twig`):

```twig
ServerName="{{ hostname }}"
MaxPlayers={{ max_players }}
ServerPassword="{{ password }}"
```

Twig features available:

- Variable interpolation: `{{ variable }}`
- Conditionals: `{% if condition %}...{% endif %}`
- Loops: `{% for item in items %}...{% endfor %}`
- Custom filter: `{{ value|format_decimal }}` (formats floats, strips trailing zeros)
- Autoescape is **disabled** (config files are not HTML)

### JsonConfigRenderer — For JSON configs

Use for games with JSON-based server configuration (e.g., Arma Reforger). Build the config as a PHP array and the renderer handles encoding.

```php
use App\Services\Renderer\JsonConfigRenderer;

final class ReforgerHandler implements GameHandler, SteamGameHandler
{
    public function __construct(
        protected JsonConfigRenderer $configRenderer,
    ) {}

    public function generateConfigFiles(Server $server): void
    {
        $config = [
            'bindAddress' => '0.0.0.0',
            'bindPort' => $server->port,
            'publicAddress' => '',
            'game' => [
                'name' => $server->name,
                'maxPlayers' => $server->max_players,
                'passwordPlayerJoin' => $server->password ?? '',
            ],
        ];

        file_put_contents(
            $server->getProfilesPath() . '/config.json',
            $this->configRenderer->render('unused', $config),
        );
    }
}
```

The `JsonConfigRenderer` ignores the template parameter — it just encodes the context array as pretty-printed JSON.

## Settings Schema (UI)

The `settingsSchema()` method defines the server settings UI. The frontend renders these dynamically — no React code needed per game.

### Field Types

| Type        | Description            | Key Properties                                            |
| ----------- | ---------------------- | --------------------------------------------------------- |
| `toggle`    | Boolean switch         | `key`, `label`, `default`, `source`                       |
| `number`    | Numeric input          | `key`, `label`, `min`, `max`, `step`, `default`, `source` |
| `text`      | Text input             | `key`, `label`, `placeholder`, `inputMode`, `source`      |
| `textarea`  | Multi-line text        | `key`, `label`, `rows`, `source`                          |
| `segmented` | Toggle group           | `key`, `label`, `options`, `default`, `source`            |
| `separator` | Visual divider         | (no key/label needed)                                     |
| `custom`    | Custom React component | `component` (registered in `custom-components.ts`)        |

### Important Properties

- `source` — Dot-notation path to read the value from server data (e.g., `'arma3Settings.ai_level'`). Matches the relationship name registered by the handler.
- `key` — The form field name sent to the backend on save.
- `showOnCreate` — Show this section in the create server dialog.
- `collapsible` — Section can be collapsed.
- `advanced` — Section is collapsed by default.

### Example

```php
public function settingsSchema(): array
{
    return [
        [
            'title' => 'Server Settings',
            'showOnCreate' => true,
            'fields' => [
                ['key' => 'max_players', 'label' => 'Max Players', 'type' => 'number', 'min' => 1, 'max' => 100, 'default' => 40],
                ['key' => 'password', 'label' => 'Server Password', 'type' => 'text', 'placeholder' => 'Leave empty for no password'],
                ['key' => 'battle_eye', 'label' => 'BattlEye Anti-Cheat', 'type' => 'toggle', 'default' => true],
            ],
        ],
        [
            'title' => 'Advanced Settings',
            'collapsible' => true,
            'advanced' => true,
            'source' => 'squadSettings',
            'fields' => [
                ['key' => 'tick_rate', 'label' => 'Tick Rate', 'type' => 'number', 'min' => 10, 'max' => 60, 'default' => 30],
                ['key' => 'vehicle_claim_time', 'label' => 'Vehicle Claim Time (s)', 'type' => 'number', 'min' => 0, 'max' => 600, 'default' => 300],
            ],
        ],
    ];
}
```

### Custom Components

For game-specific UI that can't be expressed as simple fields (e.g., Reforger's scenario picker), use type `'custom'` with a `component` key. Register the React component in `resources/js/components/servers/custom-components.ts`:

```typescript
import { registerCustomComponent } from '@/components/servers/server-edit-panel';
registerCustomComponent(
    'ReforgerScenarioPicker',
    lazy(() => import('./reforger-scenario-picker')),
);
```

Then reference it in the schema:

```php
['type' => 'custom', 'component' => 'ReforgerScenarioPicker'],
```

## Mod Sections

The `modSections()` method declares what mod tabs appear on the mods and presets pages:

```php
public function modSections(): array
{
    return [
        [
            'type' => 'workshop',           // 'workshop' or 'registered'
            'label' => 'Workshop Mods',      // Tab label
            'relationship' => 'mods',        // Relationship name on ModPreset
            'formField' => 'mod_ids',        // Backend validation field name
        ],
    ];
}
```

The `formField` key must exactly match the field name expected by the backend validation rules. Do not rely on automatic name derivation.

## Existing Handlers (Reference)

| Handler           | Game          | Complexity | Best Reference For                                               |
| ----------------- | ------------- | ---------- | ---------------------------------------------------------------- |
| `DayZHandler`     | DayZ          | Simplest   | Minimal scaffold, unimplemented methods throw `RuntimeException` |
| `ReforgerHandler` | Arma Reforger | Medium     | JSON config, registered mods, scenarios, custom component        |
| `Arma3Handler`    | Arma 3        | Full       | Twig templates, complex settings schema, headless clients        |

## Post-Creation Checklist

After scaffolding a handler:

1. Set Steam App IDs (look up on SteamDB)
2. Implement `buildLaunchCommand()` and `getBinaryPath()` based on the game's docs
3. Create config templates in `resources/templates/configs/{game}/`
4. Define `settingsSchema()` for the server settings UI
5. Fill in `settingsValidationRules()` matching the schema fields
6. Run `php artisan game:generate-types` to update TypeScript types
7. If settings model was generated, add columns to the migration and run `php artisan migrate`
8. Write tests (use `tests/Concerns/CreatesGameScenarios.php` trait — add a `create{Game}Server()` method)
9. Run `vendor/bin/pint --dirty --format agent`
10. Run `vendor/bin/phpstan analyse --memory-limit=512M`
11. Run `php artisan test --compact`

## Common Pitfalls

- Forgetting to add `@phpstan-ignore return.unusedType` on `settingsModelClass()` and `settingsRelationName()` when they return non-null but the interface declares `?string`
- Using `formField` values that don't match backend validation rule keys (causes silent form submission failures)
- Forgetting to run `game:generate-types` after changing a handler (TypeScript types will be stale)
- Not researching the actual game server configuration format before writing templates
