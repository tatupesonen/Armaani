<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.1
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/react (INERTIA_REACT) - v2
- laravel-echo (ECHO) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `wayfinder-development` — Activates whenever referencing backend routes in frontend components. Use when importing from @/actions or @/routes, calling Laravel routes from TypeScript, or working with Wayfinder route functions.
- `inertia-react-development` — Develops Inertia.js v2 React client-side applications. Activates when creating React pages, forms, or navigation; using &lt;Link&gt;, &lt;Form&gt;, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions React with Inertia, React pages, React forms, or React navigation.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.
- `game-handler-development` — Activates when adding a new game to the server manager. Use when creating game handlers, implementing GameHandler/SteamGameHandler contracts, configuring server settings schemas, generating config files, or when the user mentions adding a new game, game support, or game handler.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->

```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== wayfinder/core rules ===

# Laravel Wayfinder

Wayfinder generates TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

- IMPORTANT: Activate `wayfinder-development` skill whenever referencing backend routes in frontend components.
- Invokable Controllers: `import StorePost from '@/actions/.../StorePostController'; StorePost()`.
- Parameter Binding: Detects route keys (`{post:slug}`) — `show({ slug: "my-post" })`.
- Query Merging: `show(1, { mergeQuery: { page: 2, sort: null } })` merges with current URL, `null` removes params.
- Inertia: Use `.form()` with `<Form>` component or `form.submit(store())` with useForm.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>

# Armaani - Game Server Manager

## Project Overview

Armaani is a web-based game server manager built with Laravel 12, Inertia v2, React 19, and Tailwind CSS v4. It supports Arma 3, Arma Reforger, Project Zomboid, and DayZ (scaffolded). It allows users to install, configure, and manage multiple server instances (including starting/stopping/restarting processes), download Steam Workshop mods via SteamCMD, organize mods into presets, import Arma 3 Launcher HTML preset files, and assign presets to server instances. Game-specific logic is handled by the GameHandler pattern (Manager pattern). The application supports dynamic headless client management (Arma 3), server difficulty settings, and profile backup/restore. It ships as a single Docker container with SteamCMD bundled inside.

## Static Analysis

- After larger refactors (moving files, renaming classes, changing namespaces, modifying interfaces), run Larastan to catch type errors and missing references: `vendor/bin/phpstan analyse --memory-limit=512M`
- Fix any errors Larastan reports before considering the refactor complete.

## Architecture

- **Backend**: Laravel 12 with Inertia v2 controllers
- **Frontend**: React 19 pages in `resources/js/pages/`, components in `resources/js/components/`
- **Routing**: Wayfinder generates TypeScript route functions from Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).
- **Broadcast channels**: All 4 events use `PrivateChannel`. Channel authorization in `routes/channels.php`.
- **WebSocket**: Laravel Reverb + Echo with `echo.private()` subscriptions in React components.
- **Toast system**: Context-based `ToastProvider` + `useToast()` hook. Flash messages from Inertia are auto-displayed. Server status toasts with animated cross-fade between states.

## Scope

- **Multi-game support** — Arma 3 (full), Arma Reforger (full), Project Zomboid (full), DayZ (scaffolded — throws RuntimeException for unimplemented features).
- Game-specific logic isolated into handler classes via the `GameManager` (Laravel Manager pattern).
- Full server process control (start/stop/restart) from the web UI via queued jobs.
- Dynamic headless client support (Arma 3 only, max 10).
- Arma 3 Launcher HTML preset import supported.
- Per-game server settings: difficulty (Arma 3), network (Arma 3), Reforger settings, Project Zomboid settings, DayZ settings.
- `.vars.Arma3Profile` backup and restore (Arma 3 only).

## UI Patterns

### Server Card Status Animations

Server cards use **4 stacked gradient overlay divs** that are always in the DOM and cross-fade using `transition-opacity duration-700`. Only the div matching the current status has `opacity-100`; all others have `opacity-0`. This enables smooth cross-fade transitions when status changes (e.g., starting → booting → running).

```tsx
const statusGradients = [
    {
        status: 'starting',
        color: 'from-amber-400/20 to-zinc-300/5 dark:from-amber-500/15 dark:to-zinc-600/5',
    },
    {
        status: 'booting',
        color: 'from-blue-400/20 to-zinc-300/5 dark:from-blue-500/15 dark:to-zinc-600/5',
    },
    {
        status: 'running',
        color: 'from-emerald-400/20 to-zinc-300/5 dark:from-emerald-500/15 dark:to-zinc-600/5',
    },
    {
        status: 'stopping',
        color: 'from-red-400/20 to-zinc-300/5 dark:from-red-500/15 dark:to-zinc-600/5',
    },
];
// Render ALL divs always, toggle opacity based on current status
{
    statusGradients.map(({ status, color }) => (
        <div
            key={status}
            className={`absolute inset-0 bg-gradient-to-r transition-opacity duration-700 ${color} ${currentStatus === status ? 'opacity-100' : 'opacity-0'}`}
        />
    ));
}
```

**Do NOT** conditionally render a single gradient div — this prevents the CSS cross-fade from working since there's no element to fade out.

### LogViewer Component

`resources/js/components/log-viewer.tsx` is a reusable real-time log viewer that subscribes to private Echo channels. Used for:

- Server logs (`server-log.{id}`, event `ServerLogOutput`)
- Game install output (`game-install.{id}`, event `GameInstallOutput`)
- Mod download output (`mod-download.{id}`, event `ModDownloadOutput`)

### Toast Notifications

The toast system (`resources/js/components/toast-manager.tsx`) handles:

- Flash messages from Inertia page responses (success/error)
- Server status transitions via Echo (`ServerStatusChanged` events)
- Server status toasts persist and animate between states (starting → booting → running)

## Domain Concepts

### Game Installs

- A `GameInstall` represents a downloaded copy of a game's dedicated server files.
- Multiple installs can exist with different names and branches.
- Installed via `InstallServerJob`, which streams SteamCMD output and broadcasts `GameInstallOutput` events.
- Progress is parsed from SteamCMD output and written to DB (throttled every 1 pct).

### Server Instances

- Each server **must** be linked to a `GameInstall` (`game_install_id` required).
- Configuration is managed inline on the servers index page (expand/collapse edit panel).
- Status transitions: Stopped → Starting → Booting → Running (and Stopping).
- `Booting` → `Running` detected by `DetectServerBooted` listener when log contains boot detection string.
- Port uniqueness is validated across both `port` and `query_port` columns.

### Workshop Mods

- Downloaded individually (`DownloadModJob`) or in batches (`BatchDownloadModsJob`) via SteamCMD.
- Progress tracked by polling `du -sb` on the mod directory every 1s (SteamCMD doesn't output download progress).
- Composite unique constraint on `(workshop_id, game_type)`.

### Mod Presets

- Named collection of mods scoped by `game_type`. Composite unique on `(name, game_type)`.
- Import from Arma 3 Launcher HTML files dispatches batched download jobs.
- Reforger presets use `reforgerMods()` relationship.

### Missions (PBO Files)

- Filesystem-based (no database model). Scans directory for `.pbo` files.
- Symlinked into game install `mpmissions/` on server start.

### Server Backups

- `.vars.Arma3Profile` backups per server (Arma 3 only).
- Auto-backup on every server start. Manual create/upload/download/restore.
- Auto-pruned based on `config('arma.max_backups_per_server')`.

### Headless Clients

- Arma 3 only, max 10 per server. Offloads AI processing.
- Dynamic add/remove from UI. Auto-restored on server restart.

## Data Model

### Core Models

- `GameInstall` — name, branch, installation_status, progress_pct, disk_size_bytes, installed_at, game_type
- `Server` — name, port, query_port, max_players, password, admin_password, description, active_preset_id, game_install_id, game_type, status, additional_params, verify_signatures, allowed_file_patching, battle_eye, persistent, von_enabled, additional_server_options
- `DifficultySettings` — per-server Arma 3 difficulty options (one-to-one with Server)
- `ReforgerSettings` — per-server Reforger options (scenario_id, third_person_view_enabled)
- `ProjectZomboidSettings` — per-server PZ options (pvp, pause_empty, global_chat, map, safety_system, sleep, etc.)
- `DayZSettings` — per-server DayZ options (uses `$table = 'dayz_settings'`)
- `ServerBackup` — server_id, name, file_size, is_automatic, data
- `WorkshopMod` — workshop_id, name, file_size, installation_status, progress_pct, installed_at, game_type
- `ReforgerMod` — mod_id (GUID), name
- `ModPreset` — name, game_type; pivot tables: `mod_preset_workshop_mod`, `mod_preset_reforger_mod`
- `SteamAccount` — username, encrypted password/auth_token/steam_api_key, mod_download_batch_size; `static current(): ?self`

### Enums

- `InstallationStatus` — Queued, Installing, Installed, Failed (used by both GameInstall and WorkshopMod)
- `ServerStatus` — Stopped, Starting, Booting, Running, Stopping

**Note:** There is no `GameType` enum. Game types are plain strings (e.g. `'arma3'`, `'reforger'`, `'dayz'`). The `game_type` column on models has no cast. Game type values and labels are defined by handler classes in `app/GameHandlers/`. Validation uses `Rule::in(app(GameManager::class)->availableTypes())`.

## Key Files

### Controllers (Inertia)

- `app/Http/Controllers/DashboardController.php` — dashboard with server/install/mod stats, system resources
- `app/Http/Controllers/GameInstallController.php` — CRUD + install action
- `app/Http/Controllers/ServerController.php` — CRUD + start/stop/restart/log/status/launch-command endpoints
- `app/Http/Controllers/WorkshopModController.php` — CRUD + add/delete/update/check-updates/retry endpoints
- `app/Http/Controllers/ModPresetController.php` — CRUD + HTML import
- `app/Http/Controllers/MissionController.php` — upload/download/delete (filesystem-based)
- `app/Http/Controllers/SteamSettingsController.php` — credentials/api-key/settings/verify endpoints
- `app/Http/Controllers/ServerBackupController.php` — CRUD + upload/download/restore

### Game Handlers

- `app/Contracts/GameHandler.php` — Interface defining `value()`, `label()`, and game-specific methods
- `app/Contracts/SteamGameHandler.php` — Interface for Steam-specific methods (`serverAppId()`, `gameId()`, `consumerAppId()`)
- `app/GameManager.php` — Extends `Illuminate\Support\Manager`; `for(Server|GameInstall)` resolves handler; `allHandlers()`, `availableTypes()`, `fromConsumerAppId()`
- `app/Providers/GameServiceProvider.php` — Auto-discovers handler classes via glob and registers them with `GameManager::extend()`. Supports a cached manifest at `bootstrap/cache/game-handlers.php` via `game-handlers:cache` / `game-handlers:clear` artisan commands, integrated with `php artisan optimize`.
- `app/GameHandlers/Arma3Handler.php` — Full implementation (~510 lines); implements `GameHandler` + `SteamGameHandler`; generates server.cfg, server_basic.cfg, .Arma3Profile
- `app/GameHandlers/ReforgerHandler.php` — Full implementation; implements `GameHandler` + `SteamGameHandler`; generates JSON config
- `app/GameHandlers/ProjectZomboidHandler.php` — Full implementation; implements `GameHandler` + `SteamGameHandler` + `DetectsServerState`; generates INI config via Twig; PZ auto-expands partial INI on first boot
- `app/GameHandlers/DayZHandler.php` — Scaffold; implements `GameHandler` + `SteamGameHandler`; throws RuntimeException for unimplemented features

### Services

- `app/Services/SteamCmdService.php` — SteamCMD process management, `stripAnsi()` for ANSI code stripping
- `app/Services/SteamWorkshopService.php` — Steam API integration for mod metadata and API key validation
- `app/Services/Server/ServerProcessService.php` — Server lifecycle orchestrator (~440 lines), delegates to GameHandler. Uses `killProcessTree()` (recursive `pgrep -P`) to kill child processes for wrapper-script games (e.g., PZ's `start-server.sh` → Java). Log tail continues until process is fully dead so shutdown logs stream to UI.
- `app/Services/ServerBackupService.php` — Backup CRUD + pruning
- `app/Services/PresetImportService.php` — HTML preset parsing + batched download dispatch

### Broadcast Events (all use PrivateChannel)

- `GameInstallOutput` — channel: `private-game-install.{id}`, carries gameInstallId, progressPct, line
- `ModDownloadOutput` — channel: `private-mod-download.{id}`, carries modId, progressPct, line
- `ServerLogOutput` — channel: `private-server-log.{id}`, carries serverId, line
- `ServerStatusChanged` — channel: `private-servers` (global), carries serverId, status

### Jobs

- `InstallServerJob` — streams SteamCMD output, parses progress, broadcasts GameInstallOutput
- `DownloadModJob` — async download with du -sb polling, broadcasts ModDownloadOutput
- `BatchDownloadModsJob` — batched download in single SteamCMD invocation
- `StartServerJob` — starts server process, restores HC count on restart
- `StopServerJob` — stops all HCs then server process

### React Pages

- `resources/js/pages/dashboard.tsx` — usePoll(30000) for auto-refresh
- `resources/js/pages/game-installs/index.tsx` — install cards with collapsible LogViewer
- `resources/js/pages/servers/index.tsx` — server cards with inline edit panel, LogViewer, Echo integration
- `resources/js/pages/mods/index.tsx` — table layout with sortable columns, select-all, LogViewer per mod
- `resources/js/pages/presets/index.tsx`, `create.tsx`, `edit.tsx` — preset management
- `resources/js/pages/missions/index.tsx` — PBO upload with progress bar, download, delete
- `resources/js/pages/steam-settings.tsx` — credential management, verification, batch size

### React Components

- `resources/js/components/servers/server-card.tsx` — server card with gradient cross-fade, status actions
- `resources/js/components/servers/server-edit-panel.tsx` — inline settings panel with backup section
- `resources/js/components/servers/headless-client-controls.tsx` — HC add/remove controls
- `resources/js/components/log-viewer.tsx` — reusable real-time log viewer (Echo + auto-scroll)
- `resources/js/components/toast-manager.tsx` — toast notification system

### Form Requests

All controllers use dedicated Form Request classes for validation (no inline `$request->validate()` calls). Organized into subdirectories matching their controller domain:

- `app/Http/Requests/Server/StoreServerRequest.php` — validates game_type, name, port (unique), query_port (unique), max_players, passwords, game_install_id, boolean flags
- `app/Http/Requests/Server/UpdateServerRequest.php` — same as store with unique-ignore-self rules; dynamically merges game-handler rules via `GameManager::for($server)->serverValidationRules()` and `settingsValidationRules()`
- `app/Http/Requests/ModPreset/StoreModPresetRequest.php` — validates game_type, name (composite unique on game_type), mod_ids, reforger_mod_ids
- `app/Http/Requests/ModPreset/UpdateModPresetRequest.php` — name unique-ignore-self scoped by game_type, mod_ids, reforger_mod_ids
- `app/Http/Requests/ModPreset/ImportModPresetRequest.php` — validates import_file (file, max:2048), import_name
- `app/Http/Requests/SteamSettings/SaveCredentialsRequest.php` — username, password, auth_token
- `app/Http/Requests/SteamSettings/SaveApiKeyRequest.php` — steam_api_key
- `app/Http/Requests/SteamSettings/SaveSettingsRequest.php` — mod_download_batch_size
- `app/Http/Requests/SteamSettings/SaveDiscordWebhookRequest.php` — discord_webhook_url (url:https)
- `app/Http/Requests/WorkshopMod/StoreWorkshopModRequest.php` — workshop_id, game_type
- `app/Http/Requests/WorkshopMod/UpdateSelectedModsRequest.php` — mod_ids array with exists check
- `app/Http/Requests/ServerBackup/StoreServerBackupRequest.php` — backup_name (nullable)
- `app/Http/Requests/ServerBackup/UploadServerBackupRequest.php` — backup_file (file, max:10240), backup_name
- `app/Http/Requests/GameInstall/StoreGameInstallRequest.php` — game_type, name, branch (validated against game type branches)
- `app/Http/Requests/ReforgerMod/StoreReforgerModRequest.php` — mod_id (unique), name
- `app/Http/Requests/Mission/StoreMissionRequest.php` — missions array of files (max:524288)
- `app/Http/Requests/Settings/PasswordUpdateRequest.php` — current_password, password (uses PasswordValidationRules trait)
- `app/Http/Requests/Settings/ProfileUpdateRequest.php` — delegates to ProfileValidationRules trait
- `app/Http/Requests/Settings/ProfileDeleteRequest.php` — password confirmation
- `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php` — authorization only

Form Request conventions: array-based validation rules, no `authorize()` method (defaults to true), use `$this->route('paramName')` to access route-model-bound parameters (camelCase parameter names matching the route definition).

### Config

- `config/arma.php` — steamcmd_path, steam_api_key, games/servers/mods/missions base paths, max_backups_per_server
- `config/broadcasting.php` — Reverb connection with hardcoded credentials
- `config/reverb.php` — server defaults to 127.0.0.1:6001

## Testing

### Test Conventions

- PHPUnit, `RefreshDatabase` trait, `test_snake_case` method naming, `route()` helper for URLs.
- HTTP endpoint tests use `$this->get()`, `$this->post()`, `$this->put()`, `$this->delete()` with `assertInertia()`, `assertSessionHas()`, `assertSessionHasErrors()`.

### Test Traits

- `tests/Concerns/CreatesGameScenarios.php` — `createServer(string $gameType)`, `createArma3Server()`, `createReforgerServer()`, `createProjectZomboidServer()`, `createDayZServer()`
- `tests/Concerns/MocksGameManager.php` — mock GameManager singleton
- `tests/Concerns/MocksServerProcessService.php` — mock ServerProcessService with configurable status
- `tests/Concerns/MocksSteamCmdProcess.php` — `makeInvokedProcess(bool): InvokedProcess`

### Testing Notes

- Mock `SteamCmdService` directly in job tests via `$this->app->instance()`.
- For `DownloadModJob` tests: mock `startDownloadMod()` returning mock `InvokedProcess`.
- Broadcast events use `PrivateChannel` — tests check for `private-` prefixed channel names.
- `Process::fake()` does not intercept `rm -rf` reliably — use real filesystem assertions.
- `SteamCmdService` methods accept optional `?string $gameType` — mocks must match signature.
- `SteamWorkshopService::validateApiKey()` returns `array{valid: bool, error: string|null}`.
- Streamed downloads use `$response->streamedContent()` not `$response->getContent()`.

### Test Files (518 tests total across 33 files)

- `tests/Feature/DashboardTest.php` — 10 tests
- `tests/Feature/ExampleTest.php` — 1 test
- `tests/Feature/ReforgerScenarioServiceTest.php` — 11 tests
- `tests/Feature/Auth/AuthenticationTest.php` — 6 tests
- `tests/Feature/Auth/EmailVerificationTest.php` — 6 tests
- `tests/Feature/Auth/PasswordConfirmationTest.php` — 2 tests
- `tests/Feature/Auth/PasswordResetTest.php` — 5 tests
- `tests/Feature/Auth/TwoFactorChallengeTest.php` — 2 tests
- `tests/Feature/Auth/VerificationNotificationTest.php` — 2 tests
- `tests/Feature/Events/BroadcastEventsTest.php` — 9 tests
- `tests/Feature/GameHandlers/DayZHandlerTest.php` — 14 tests
- `tests/Feature/GameHandlers/ProjectZomboidHandlerTest.php` — 37 tests
- `tests/Feature/GameHandlers/ReforgerHandlerTest.php` — 23 tests
- `tests/Feature/GameInstalls/GameInstallManagementTest.php` — 8 tests
- `tests/Feature/Jobs/BatchDownloadModsJobTest.php` — 8 tests
- `tests/Feature/Jobs/DownloadModJobTest.php` — 9 tests
- `tests/Feature/Jobs/InstallServerJobTest.php` — 3 tests
- `tests/Feature/Jobs/StartServerJobTest.php` — 3 tests
- `tests/Feature/Jobs/StopServerJobTest.php` — 3 tests
- `tests/Feature/Listeners/DetectServerBootedTest.php` — 9 tests
- `tests/Feature/Missions/MissionManagementTest.php` — 14 tests
- `tests/Feature/Mods/WorkshopModManagementTest.php` — 37 tests
- `tests/Feature/Presets/ModPresetManagementTest.php` — 32 tests
- `tests/Feature/Servers/MultiGameServerTest.php` — 14 tests
- `tests/Feature/Servers/ServerBackupManagementTest.php` — 14 tests
- `tests/Feature/Servers/ServerBackupServiceTest.php` — 15 tests
- `tests/Feature/Servers/ServerManagementTest.php` — 36 tests
- `tests/Feature/Servers/ServerProcessServiceTest.php` — 33 tests
- `tests/Feature/Settings/PasswordUpdateTest.php` — 3 tests
- `tests/Feature/Settings/ProfileUpdateTest.php` — 5 tests
- `tests/Feature/Settings/TwoFactorAuthenticationTest.php` — 4 tests
- `tests/Feature/SteamSettings/SteamSettingsTest.php` — 27 tests
- `tests/Unit/ExampleTest.php` — 1 test

## File System Layout

- Game install files: `{GAMES_BASE_PATH}/{game_install_id}/` (default: `storage/arma/games/{id}/`)
- Server profiles/config: `{SERVERS_BASE_PATH}/{server_id}/` (default: `storage/arma/servers/{id}/`)
- Workshop mods: `{MODS_BASE_PATH}/steamapps/workshop/content/{game_id}/{workshop_id}/`
- Mod symlinks: `{game_install_path}/@{normalized_mod_name}`
- Mission PBOs: `{MISSIONS_BASE_PATH}/` (default: `storage/arma/missions/`)
- Mission symlinks: `{GAMES_BASE_PATH}/{id}/mpmissions/`

## Docker Deployment

- Single container based on `cm2network/steamcmd` with PHP 8.5 FPM/CLI, Caddy, SteamCMD, Supervisor, SQLite.
- `procps` package installed for `pgrep` (required by `killProcessTree()` in `ServerProcessService`).
- `network_mode: host` for dynamic game server ports.
- Single volume: `./storage:/var/www/html/storage`
- Supervisord manages: Caddy, PHP-FPM, queue worker, Reverb (127.0.0.1:6001).
- Caddy reverse-proxies `/app` and `/apps` to Reverb — no second external port needed.
- `APP_KEY` and `REVERB_APP_SECRET` auto-generated and persisted to storage volume.
