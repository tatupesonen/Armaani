<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.1
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4
- laravel-echo (ECHO) - v2

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `fluxui-development` — Develops UIs with Flux UI Free components. Activates when creating buttons, forms, modals, inputs, dropdowns, checkboxes, or UI components; replacing HTML form elements with Flux; working with flux: components; or when the user mentions Flux, component library, UI components, form fields, or asks about available Flux components.
- `livewire-development` — Develops reactive Livewire 4 components. Activates when creating, updating, or modifying Livewire components; working with wire:model, wire:click, wire:loading, or any wire: directives; adding real-time updates, loading states, or reactivity; debugging component behavior; writing Livewire tests; or when the user mentions Livewire, component, counter, or reactive UI.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

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

=== fluxui-free/core rules ===

# Flux UI Free

- Flux UI is the official Livewire component library. This project uses the free edition, which includes all free components and variants but not Pro components.
- Use `<flux:*>` components when available; they are the recommended way to build Livewire interfaces.
- IMPORTANT: Activate `fluxui-development` when working with Flux UI components.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces using only PHP — no JavaScript required.
- Instead of writing frontend code in JavaScript frameworks, you use Alpine.js to build the UI when client-side interactions are required.
- State lives on the server; the UI reflects it. Validate and authorize in actions (they're like HTTP requests).
- IMPORTANT: Activate `livewire-development` every time you're working with Livewire-related tasks.

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

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>

# ArmaMan - Arma 3 Server Manager

## Project Overview

ArmaMan is a web-based Arma 3 dedicated server manager built with Laravel 12, Livewire 4, and Flux UI. It allows users to install, configure, and manage multiple Arma 3 server instances (including starting/stopping/restarting processes), download Steam Workshop mods via SteamCMD, organize mods into presets, import Arma 3 Launcher HTML preset files, and assign presets to server instances. The application supports headless client management per server. It ships as a single Docker container with SteamCMD bundled inside.

## Scope

- **Arma 3 only** — no DayZ, DayZ Experimental, or Arma Reforger support.
- Full server process control (start/stop/restart) from the web UI.
- Headless client support (launch/stop HC instances per server).
- Arma 3 Launcher HTML preset import supported.

## Domain Concepts

### Game Installs
- A `GameInstall` represents a downloaded copy of the Arma 3 dedicated server files (Steam App ID 233780).
- Multiple installs can exist with different names and branches (public, contact, creatordlc, profiling, performance, legacy).
- Branches are hardcoded — `GetAppBetas` Steam API requires a Steamworks partner token and returns 403 with a regular key.
- Each install tracks: `name`, `branch`, `installation_status` (`GameInstallStatus` enum), `progress_pct` (0–100), `disk_size_bytes`, `installed_at`.
- Installed via `InstallServerJob`, which streams SteamCMD output line-by-line via a callback, parses progress lines, and writes `progress_pct` + `disk_size_bytes` to DB (throttled every 1 percentage point using `updateQuietly`).
- SteamCMD progress line format: `Update state (0x61) downloading, progress: 44.53 (2397543803 / 5384428737)` — emitted roughly every 2 seconds.
- On completion, actual disk size is recorded via `du -sb`.
- All SteamCMD output lines are logged with context prefix: `[GameInstall:1 'stable'] <line>`.
- Each SteamCMD output line and progress update is broadcast via `GameInstallOutput` event for real-time UI updates.

### Server Instances
- Each server **must** be linked to a `GameInstall` (`game_install_id` required — no server without one).
- Configuration is managed inline on the servers index page (expand/collapse panel) — no separate create/edit pages.
- Each server has: name, port, query_port, max_players, password, admin_password, description, headless_client_count, additional_params, active_preset_id, game_install_id.
- Port uniqueness is validated across both `port` and `query_port` columns for all servers.
- Servers can be started, stopped, and restarted from the web UI.
- `Server::getBinaryPath()` derives the server binary path from the linked `GameInstall`.
- `server.cfg` is regenerated on every server start by `ServerProcessService::generateServerConfig()`, so config changes take effect immediately.

### Workshop Mods
- Mods are identified by their Steam Workshop ID (numeric).
- Mods are downloaded using SteamCMD as queued Laravel jobs (one job per mod, `DownloadModJob`).
- Each mod has: `workshop_id`, `name`, `file_size`, `installation_status` (`InstallationStatus` enum), `progress_pct` (0–100), `installed_at`.
- **Progress tracking**: SteamCMD does NOT output progress lines for workshop downloads. Progress is tracked by polling `du -sb` on the mod directory every 1 second while the SteamCMD process runs asynchronously (`Process::start()` + `while ($process->running())`). `progress_pct` is written to DB (throttled every 1 percentage point) using `updateQuietly`.
- Progress and SteamCMD output are broadcast via `ModDownloadOutput` event for real-time UI updates.
- Mod metadata (name, file size) is fetched from the Steam Workshop API before download begins.
- On completion, actual disk size is recorded via `du -sb` on the mod directory.
- Mod files need to be converted to lowercase (Linux requirement for Arma 3).
- Mods are symlinked into server directories when assigned via presets.

### Mod Presets
- A preset is a named collection of workshop mods.
- Presets can be created manually (selecting individual mods) or by importing an Arma 3 Launcher HTML preset file.
- When importing an HTML preset, the system parses mod IDs from the file and queues individual download jobs for each mod.
- A server instance uses one active preset at a time.
- Presets can be shared across multiple server instances.
- Presets have separate create and edit pages (unlike servers which use inline panels).

### Missions (PBO Files)
- PBO (Packed Bank of Files) mission files uploaded by users and stored in a shared pool (`missions_base_path`).
- **No database model** — purely filesystem-based. The missions page scans the directory for `.pbo` files.
- Missions are global (shared across all game installs/servers).
- On server start, `ServerProcessService::symlinkMissions()` symlinks all PBOs from the shared pool into the game install's `mpmissions/` directory.
- Supports batch upload of multiple `.pbo` files with upload progress (Alpine.js + Livewire upload events).
- Users can upload, download, and delete mission files from the Missions page.
- Non-`.pbo` files are silently skipped during upload.
- Uploading a file with the same name silently overwrites it.
- Livewire `WithFileUploads` trait handles file uploads; max upload size configured to 512MB in `config/livewire.php`.

### Headless Clients
- Each Arma 3 server instance can have headless clients launched alongside it.
- Headless clients offload AI processing from the main server.
- They connect to the server automatically using the server's configured password.
- Users can start/stop headless clients per server from the UI.

### SteamCMD Integration
- SteamCMD is the command-line tool used to download server files and workshop mods.
- SteamCMD commands are executed as queued jobs to prevent blocking the web UI.
- Steam credentials (username, encrypted password, auth_token, steam_api_key) are managed via a settings page and stored encrypted in the database (`SteamAccount` model).
- The SteamCMD binary path is configurable via `STEAMCMD_PATH` env var (default: `/usr/games/steamcmd`).
- Arma 3 server Steam App ID: 233780 (hardcoded in `config/arma.php` as `server_app_id`)
- Arma 3 game ID (for workshop mods): 107410 (hardcoded in `config/arma.php` as `game_id`)
- `SteamCmdService::installServer()` accepts an optional `?callable $onOutput` and streams output line-by-line.
- `SteamCmdService::startDownloadMod()` starts the process asynchronously and returns an `InvokedProcess` for polling.
- `SteamCmdService::downloadMod()` (sync, no callback) still exists but is not used by the job.

## Architecture Decisions

### Queue-Based Downloads
- All SteamCMD operations run as queued Laravel jobs.
- Each mod download is a separate job so progress can be tracked individually.
- The queue worker processes one job at a time to avoid SteamCMD conflicts.
- Uses the `database` queue driver (already configured).

### Real-Time Updates via WebSocket (Laravel Reverb + Echo)
- Laravel Reverb provides the WebSocket server; Laravel Echo (with pusher-js) handles client subscriptions.
- Three broadcast events push real-time data from jobs/commands to the UI:
  - `GameInstallOutput` — SteamCMD log lines + progress for game installs (channel: `game-install.{id}`)
  - `ModDownloadOutput` — progress + SteamCMD output for mod downloads (channel: `mod-download.{id}`)
  - `ServerLogOutput` — server log lines from the `server:tail-log` command (channel: `server-log.{id}`)
- All broadcast events implement `ShouldBroadcastNow` (they are already dispatched from queue workers or commands).
- All channels are public (no authorization needed) — no entries in `routes/channels.php`.
- Livewire components use `getListeners()` to dynamically subscribe to per-entity Echo channels (e.g. `echo:game-install.{id},GameInstallOutput`).
- Game installs and mods pages have **no `wire:poll`** — they rely entirely on Echo events for updates.
- Servers page uses `wire:poll.5s` for process status checks + Echo for real-time log streaming.
- Progress (`progress_pct`) is still written to DB by jobs; Echo events carry it too for instant UI updates.

### Server Config Generation
- `ServerProcessService::generateServerConfig()` writes `server.cfg` to `getProfilesPath()/server.cfg` before every server start.
- Maps DB fields: `name` → `hostname`, `password`, `admin_password` → `passwordAdmin`, `max_players` → `maxPlayers`, `description` → `motd[]`.
- Includes Arma 3 defaults (BattlEye=1, verifySignatures=2, etc.).
- Config is always regenerated on start so changes take effect immediately.

### Server Log Tailing
- `TailServerLog` artisan command (`server:tail-log {server}`) tails a running server's `server.log` file.
- Polls at 250ms intervals, handles file rotation.
- Broadcasts each new line via `ServerLogOutput` event over WebSocket.
- Started/stopped automatically by `ServerProcessService` alongside the server process (PID file managed).

### Livewire Components
- Single-file Livewire components in `resources/views/pages/` using the `pages::` namespace.
- No `⚡` emoji prefixes on filenames (`livewire.php` has `make_command.emoji => false`).
- Server create/edit is inline on the servers index page (modal for create, expand panel for edit) — no separate pages.
- `flux:select` with a bound integer property: pre-initialise the property in the open method (e.g. `$this->gameInstalls->first()?->id`) to avoid the flux:select null-on-no-interaction bug.

### File System Layout
- Game install files: `{SERVERS_BASE_PATH}/game/{game_install_id}/`
- Workshop mods: `{MODS_BASE_PATH}/steamapps/workshop/content/107410/{workshop_id}/`
- Mod symlinks into server dirs: `{server_path}/@{normalized_mod_name}`
- Mission PBOs (shared pool): `{MISSIONS_BASE_PATH}/`
- Mission symlinks into game installs: `{SERVERS_BASE_PATH}/game/{id}/mpmissions/`
- All paths are configurable via environment variables.

### Docker Deployment
- The application ships as a single Docker container.
- The container includes: PHP-FPM, Nginx, SteamCMD, Node.js (for asset building), SQLite.
- Supervisord manages: Nginx, PHP-FPM, the Laravel queue worker, and Laravel Reverb (WebSocket server on port 8080).
- Storage volumes for: database, server files, mod files, mission files, Laravel storage.
- A `docker-compose.yml` is provided for easy deployment.
- `storage/arma/servers`, `storage/arma/mods`, and `storage/arma/missions` are gitignored.

### Environment Variables (Custom)
- `STEAMCMD_PATH` - Path to SteamCMD executable (default: `/usr/games/steamcmd`)
- `STEAM_API_KEY` - Steam Web API key for fetching workshop mod metadata
- `SERVERS_BASE_PATH` - Base directory for game install downloads
- `MODS_BASE_PATH` - Base directory for mod downloads
- `MISSIONS_BASE_PATH` - Base directory for uploaded PBO mission files

## Data Model

### Core Models
- `GameInstall` - A downloaded Arma 3 server installation (name, branch, installation_status, progress_pct, disk_size_bytes, installed_at)
- `Server` - Arma 3 server instance (name, port, query_port, max_players, password, admin_password, description, active_preset_id, game_install_id, headless_client_count, additional_params)
- `WorkshopMod` - A Steam Workshop mod (workshop_id, name, file_size, installation_status, progress_pct, installed_at)
- `ModPreset` - A named collection of mods (name)
- `mod_preset_workshop_mod` - Pivot table (mod_preset_id, workshop_mod_id)
- `SteamAccount` - Steam credentials for SteamCMD (username, encrypted password, encrypted auth_token, encrypted steam_api_key)

### Enums
- `InstallationStatus` - Queued, Installing, Installed, Failed (used by `WorkshopMod`)
- `GameInstallStatus` - Queued, Installing, Installed, Failed (used by `GameInstall`)
- `ServerStatus` - Stopped, Starting, Running, Stopping

## Key Files

### Models
- `app/Models/GameInstall.php` — `getInstallationPath(): string` returns `{servers_base_path}/game/{id}`
- `app/Models/Server.php` — `gameInstall(): BelongsTo`, `activePreset(): BelongsTo`, `getInstallationPath(): string`, `getProfilesPath(): string`, `getBinaryPath(): string`
- `app/Models/WorkshopMod.php` — `presets(): BelongsToMany`, `getInstallationPath(): string`, `getNormalizedName(): string`
- `app/Models/ModPreset.php` — `mods(): BelongsToMany`, `servers(): HasMany`
- `app/Models/SteamAccount.php` — stores encrypted credentials (password, auth_token, steam_api_key)

### Services
- `app/Services/SteamCmdService.php` — `installServer(dir, branch, ?callable): ProcessResult`, `startDownloadMod(dir, id): InvokedProcess`, `downloadMod(dir, id): ProcessResult`, `validateCredentials(user, pass): bool`
- `app/Services/SteamWorkshopService.php` — `getModDetails(id): ?array`, `validateApiKey(key): array`, `getApiKey(): ?string`
- `app/Services/ServerProcessService.php` — `start()`, `stop()`, `restart()`, `isRunning()`, `getStatus()`, `startHeadlessClients()`, `stopHeadlessClients()`, `generateServerConfig()`, `symlinkMissions()`, `startLogTail()`, `stopLogTail()`
- `app/Services/PresetImportService.php` — `parseHtmlPreset(html): Collection`, `parsePresetName(html): ?string`, `importFromHtml(html, ?name): ModPreset`

### Broadcast Events
- `app/Events/GameInstallOutput.php` — broadcasts on `game-install.{id}`, carries `gameInstallId`, `progressPct`, `line`
- `app/Events/ModDownloadOutput.php` — broadcasts on `mod-download.{id}`, carries `modId`, `progressPct`, `line`
- `app/Events/ServerLogOutput.php` — broadcasts on `server-log.{id}`, carries `serverId`, `line`

### Console Commands
- `app/Console/Commands/TailServerLog.php` — `server:tail-log {server}`, tails server.log, broadcasts via `ServerLogOutput`, handles file rotation

### Jobs
- `app/Jobs/InstallServerJob.php` — installs a `GameInstall`; streams SteamCMD output; parses `progress:` lines; throttled DB writes (every 1 pct); broadcasts `GameInstallOutput` events; tries=2, timeout=7200s
- `app/Jobs/DownloadModJob.php` — downloads a `WorkshopMod`; uses `startDownloadMod()` (async); polls `du -sb` every 1s; throttled DB writes (every 1 pct); broadcasts `ModDownloadOutput` events; tries=2, timeout=3600s

### Pages (Livewire single-file)
- `resources/views/pages/game-installs/index.blade.php` — game installs list, create modal, reinstall/delete, collapsible log viewer per install, Echo listeners (no wire:poll)
- `resources/views/pages/servers/index.blade.php` — server list with inline create modal + configure panel, game install dropdown (required), log viewer panel, `wire:poll.5s` for status + Echo for logs
- `resources/views/pages/mods/index.blade.php` — mod list, add by workshop ID, progress bar, collapsible log viewer per mod in table rows, Echo listeners (no wire:poll)
- `resources/views/pages/presets/index.blade.php` — preset list, delete
- `resources/views/pages/presets/create.blade.php` — create preset with mod selection + HTML import
- `resources/views/pages/presets/edit.blade.php` — edit preset, manage mods
- `resources/views/pages/missions/index.blade.php` — mission PBO upload/download/delete, upload progress bar, filesystem-based (no DB model)
- `resources/views/pages/steam-settings.blade.php` — manage Steam credentials and API key

### Frontend / JS
- `resources/js/app.js` — Laravel Echo configured for Reverb (pusher-js transport)

### Config
- `config/arma.php` — `steamcmd_path`, `steam_api_key`, `servers_base_path`, `mods_base_path`, `missions_base_path`, `server_app_id` (233780), `game_id` (107410)
- `config/broadcasting.php` — Reverb connection configured
- `config/reverb.php` — published Reverb config
- `config/livewire.php` — `emoji => false`, `temporary_file_upload.rules` set to 512MB max

### Docker
- `docker/supervisord.conf` — nginx, php-fpm, queue-worker (database driver), reverb (port 8080)
- `docker-compose.yml` — single container deployment

### Tests
- `tests/Feature/GameInstalls/GameInstallManagementTest.php`
- `tests/Feature/Servers/ServerManagementTest.php`
- `tests/Feature/Servers/ServerProcessServiceTest.php` — server.cfg generation + mission symlink tests
- `tests/Feature/Missions/MissionManagementTest.php` — PBO upload, download, delete, path traversal protection
- `tests/Feature/Mods/WorkshopModManagementTest.php`
- `tests/Feature/Jobs/DownloadModJobTest.php` — mocks `SteamCmdService::startDownloadMod()` returning a mock `InvokedProcess`; uses `Process::fake(['du *' => ...])` for disk size
- `tests/Feature/Presets/ModPresetManagementTest.php`
- `tests/Feature/SteamSettings/SteamSettingsTest.php`
- `tests/Feature/Events/BroadcastEventsTest.php` — broadcast event channel and property tests
- `tests/Unit/Services/PresetImportServiceTest.php`

## SteamCMD Commands Reference

### Install/Update Arma 3 Server
```
steamcmd +force_install_dir {install_path} +login {username} {password} +app_update 233780 validate +quit
# For non-public branch:
steamcmd +force_install_dir {install_path} +login {username} {password} +app_update 233780 -beta {branch} validate +quit
```

### Download Workshop Mod
```
steamcmd +force_install_dir {mods_base_path} +login {username} {password} +workshop_download_item 107410 {workshop_id} validate +quit
```

## Testing Notes
- Mock `SteamCmdService` directly in job tests (bind via `$this->app->instance()`).
- For `DownloadModJob` tests: mock `startDownloadMod()` to return a mock `InvokedProcess` with `running()` returning `false` and `wait()` returning a mock `ProcessResult`. Use `Process::fake(['du *' => Process::result('SIZE\t/path')])` to fake disk size reads.
- For `InstallServerJob` tests: mock `installServer()` to invoke the callback with fake output lines if testing progress parsing.
- `setUp()` in `ServerManagementTest` always creates a `GameInstall` — tests that need `createGameInstallId` to be null must explicitly `->set('createGameInstallId', null)` after `openCreateModal`.
- `SteamWorkshopService` no longer has `getDownloadProgress()` or `getDownloadedSize()` — do not mock them.
- Broadcast events use `ShouldBroadcastNow` — when testing dispatches from jobs, use `Event::fake([SpecificEvent::class])` to avoid breaking other event-driven behavior.
- `Process::fake()` does not intercept `rm -rf` calls in Livewire component tests reliably — use real filesystem + `assertDirectoryDoesNotExist` instead.
