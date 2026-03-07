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

ArmaMan is a web-based Arma 3 dedicated server manager built with Laravel 12, Livewire 4, and Flux UI. It allows users to install, configure, and manage multiple Arma 3 server instances (including starting/stopping/restarting processes), download Steam Workshop mods via SteamCMD, organize mods into presets, import Arma 3 Launcher HTML preset files, and assign presets to server instances. The application supports dynamic headless client management per server, server difficulty settings, and `.vars.Arma3Profile` backup/restore. It ships as a single Docker container with SteamCMD bundled inside.

## Scope

- **Arma 3 only** — no DayZ, DayZ Experimental, or Arma Reforger support.
- Full server process control (start/stop/restart) from the web UI via queued jobs (`StartServerJob`, `StopServerJob`).
- Dynamic headless client support (add/remove individual HC instances per server, max 10).
- Arma 3 Launcher HTML preset import supported.
- Server difficulty settings (per-server Arma 3 difficulty options).
- `.vars.Arma3Profile` backup and restore (automatic on server start, manual upload/download).

## Domain Concepts

### Game Installs
- A `GameInstall` represents a downloaded copy of the Arma 3 dedicated server files (Steam App ID 233780).
- Multiple installs can exist with different names and branches (public, contact, creatordlc, profiling, performance, legacy).
- Branches are hardcoded — `GetAppBetas` Steam API requires a Steamworks partner token and returns 403 with a regular key.
- Each install tracks: `name`, `branch`, `installation_status` (`InstallationStatus` enum — same enum used for both game installs and mods), `progress_pct` (0–100), `disk_size_bytes`, `installed_at`.
- Installed via `InstallServerJob`, which streams SteamCMD output line-by-line via a callback, parses progress lines, and writes `progress_pct` + `disk_size_bytes` to DB (throttled every 1 percentage point using `updateQuietly`).
- SteamCMD progress line format: `Update state (0x61) downloading, progress: 44.53 (2397543803 / 5384428737)` — emitted roughly every 2 seconds.
- On completion, actual disk size is recorded via `du -sb`.
- All SteamCMD output lines are logged with context prefix: `[GameInstall:1 'stable'] <line>`.
- Each SteamCMD output line and progress update is broadcast via `GameInstallOutput` event for real-time UI updates.

### Server Instances
- Each server **must** be linked to a `GameInstall` (`game_install_id` required — no server without one).
- Configuration is managed inline on the servers index page (expand/collapse panel) — no separate create/edit pages.
- Each server has: name, port, query_port, max_players, password, admin_password, description, additional_params, active_preset_id, game_install_id, status, verify_signatures, allowed_file_patching, battle_eye, persistent, von_enabled, additional_server_options.
- Port uniqueness is validated across both `port` and `query_port` columns for all servers.
- Servers are started/stopped via queued jobs (`StartServerJob`, `StopServerJob`) dispatched from the web UI.
- `Server::getBinaryPath()` derives the server binary path from the linked `GameInstall`.
- On every server start, `ServerProcessService` regenerates `server.cfg`, `server_basic.cfg`, and the `.Arma3Profile` difficulty file, symlinks mods and missions, copies BiKeys, and creates an automatic `.vars.Arma3Profile` backup.
- Server status transitions through: Stopped → Starting → Booting → Running (and Stopping when shutting down). The `Booting` → `Running` transition is detected by `DetectServerBooted` listener when the server log contains "Connected to Steam servers".
- Shared form fields are extracted into `resources/views/pages/servers/partials/form-fields.blade.php`.

### Workshop Mods
- Mods are identified by their Steam Workshop ID (numeric).
- Mods can be downloaded individually (`DownloadModJob`) or in batches (`BatchDownloadModsJob`) via SteamCMD as queued Laravel jobs.
- Each mod has: `workshop_id`, `name`, `file_size`, `installation_status` (`InstallationStatus` enum), `progress_pct` (0–100), `installed_at`.
- **Progress tracking**: SteamCMD does NOT output progress lines for workshop downloads. Progress is tracked by polling `du -sb` on the mod directory every 1 second while the SteamCMD process runs asynchronously (`Process::start()` + `while ($process->running())`). `progress_pct` is written to DB (throttled every 1 percentage point) using `updateQuietly`.
- Progress and SteamCMD output are broadcast via `ModDownloadOutput` event for real-time UI updates.
- Mod metadata (name, file size) is fetched from the Steam Workshop API before download begins. Bulk metadata fetching is supported via `SteamWorkshopService::getMultipleModDetails()`.
- On completion, actual disk size is recorded via `du -sb` on the mod directory.
- Mod files need to be converted to lowercase (Linux requirement for Arma 3) — handled by the `InteractsWithFileSystem` trait.
- Mods are symlinked into server directories when assigned via presets.

### Mod Presets
- A preset is a named collection of workshop mods.
- Presets can be created manually (selecting individual mods) or by importing an Arma 3 Launcher HTML preset file.
- When importing an HTML preset, the system parses mod IDs from the file and dispatches batched download jobs (batch size configured via `SteamAccount::mod_download_batch_size`). Single-mod batches use `DownloadModJob`, multi-mod batches use `BatchDownloadModsJob`.
- A server instance uses one active preset at a time.
- Presets can be shared across multiple server instances.
- Presets have separate create and edit pages (unlike servers which use inline panels).
- Shared form fields are extracted into `resources/views/pages/presets/partials/form-fields.blade.php`.

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
- Each Arma 3 server instance can have headless clients launched alongside it (max 10).
- Headless clients offload AI processing from the main server.
- They connect to the server automatically using the server's configured password.
- HC management is dynamic: users add/remove individual HCs from the UI via `ServerProcessService::addHeadlessClient()` and `removeHeadlessClient()`.
- On server restart, the previous HC count is automatically restored.

### Server Backups
- `ServerBackupService` manages `.vars.Arma3Profile` file backups per server.
- An automatic backup is created on every server start.
- Users can manually create named backups, upload backup data, download, and restore backups.
- Old backups are auto-pruned based on `config('arma.max_backups_per_server')` (default: 20).
- Backups are stored in the `server_backups` table via the `ServerBackup` model.

### Server Difficulty Settings
- Each server has a `DifficultySettings` model (one-to-one relationship) that configures Arma 3 difficulty options.
- Settings include: reduced_damage, group_indicators, friendly_tags, enemy_tags, detected_mines, commands, waypoints, tactical_ping, weapon_info, stance_indicator, stamina_bar, weapon_crosshair, vision_aid, third_person_view, camera_shake, score_table, death_messages, von_id, map_content, auto_report, ai_level_preset, skill_ai, precision_ai.
- On server start, `ServerProcessService::generateProfileConfig()` writes these settings to the `.Arma3Profile` file.

### SteamCMD Integration
- SteamCMD is the command-line tool used to download server files and workshop mods.
- SteamCMD commands are executed as queued jobs to prevent blocking the web UI.
- Steam credentials (username, encrypted password, auth_token, steam_api_key, mod_download_batch_size) are managed via a settings page and stored encrypted in the database (`SteamAccount` model).
- The SteamCMD binary path is configurable via `STEAMCMD_PATH` env var (default: `/usr/games/steamcmd`).
- Arma 3 server Steam App ID: 233780 (hardcoded in `config/arma.php` as `server_app_id`)
- Arma 3 game ID (for workshop mods): 107410 (hardcoded in `config/arma.php` as `game_id`)
- `SteamCmdService::installServer()` accepts an optional `?callable $onOutput` and streams output line-by-line.
- `SteamCmdService::startDownloadMod()` starts the process asynchronously and returns an `InvokedProcess` for polling.
- `SteamCmdService::startBatchDownloadMods()` stacks multiple `+workshop_download_item` commands in a single SteamCMD invocation; timeout scales at 1hr per mod.

## Architecture Decisions

### Queue-Based Downloads
- All SteamCMD operations run as queued Laravel jobs.
- Mods can be downloaded individually (`DownloadModJob`) or in batches (`BatchDownloadModsJob`).
- Batch downloads use a single SteamCMD invocation with multiple `+workshop_download_item` commands.
- Server start/stop operations are also queued (`StartServerJob`, `StopServerJob`).
- The queue worker processes one job at a time to avoid SteamCMD conflicts.
- Uses the `database` queue driver (already configured).

### Real-Time Updates via WebSocket (Laravel Reverb + Echo)
- Laravel Reverb provides the WebSocket server; Laravel Echo (with pusher-js) handles client subscriptions.
- Nginx reverse-proxies `/app` and `/apps` paths to Reverb internally (port 6001, bound to 127.0.0.1) — no second port needed.
- `resources/js/app.js` derives `wsHost`, `wsPort`, and `forceTLS` from `window.location` at runtime, so the connection works on any external port/protocol without baked-in VITE env vars. `VITE_REVERB_APP_KEY` is hardcoded at build time (`armaman-key`).
- Four broadcast events push real-time data from jobs/commands to the UI:
  - `GameInstallOutput` — SteamCMD log lines + progress for game installs (channel: `game-install.{id}`)
  - `ModDownloadOutput` — progress + SteamCMD output for mod downloads (channel: `mod-download.{id}`)
  - `ServerLogOutput` — server log lines from the `server:tail-log` command (channel: `server-log.{id}`)
  - `ServerStatusChanged` — server status transitions (channel: `servers`, global)
- All broadcast events implement `ShouldBroadcastNow` (they are already dispatched from queue workers or commands).
- All channels are public (no authorization needed) — no ArmaMan-specific entries in `routes/channels.php`.
- Livewire components use `getListeners()` to dynamically subscribe to per-entity Echo channels (e.g. `echo:game-install.{id},GameInstallOutput`).
- Game installs and mods pages have **no `wire:poll`** — they rely entirely on Echo events for updates.
- Servers page uses `wire:poll.5s` for process status checks + Echo for real-time log streaming.
- Progress (`progress_pct`) is still written to DB by jobs; Echo events carry it too for instant UI updates.

### Event Listeners
- `DetectServerBooted` listener — listens to `ServerLogOutput` events. When a log line contains "Connected to Steam servers" and the server status is `Booting`, transitions the server to `Running` and dispatches a `ServerStatusChanged` event.

### Server Config Generation
- `ServerProcessService` generates three config files on every server start:
  - `generateServerConfig()` — writes `server.cfg` to `getProfilesPath()/server.cfg`. Maps DB fields: `name` → `hostname`, `password`, `admin_password` → `passwordAdmin`, `max_players` → `maxPlayers`, `description` → `motd[]`. Includes server options from `additional_server_options`.
  - `generateBasicConfig()` — writes `server_basic.cfg` (basic Arma 3 server settings).
  - `generateProfileConfig()` — writes `{profileName}.Arma3Profile` with difficulty settings from the `DifficultySettings` model.
- `symlinkMods()` — creates `@{normalized_name}` symlinks in the game install directory for each mod in the active preset.
- `symlinkMissions()` — symlinks all PBOs from the shared missions pool into the game install's `mpmissions/` directory.
- `copyBiKeys()` — copies BiKey files (`.bikey`) from mod `keys/` directories into the game install's `keys/` directory.
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
- Game install files: `{GAMES_BASE_PATH}/{game_install_id}/` (default: `storage/arma/games/{id}/`)
- Server profiles/config: `{SERVERS_BASE_PATH}/{server_id}/` (default: `storage/arma/servers/{id}/`)
- Workshop mods: `{MODS_BASE_PATH}/steamapps/workshop/content/107410/{workshop_id}/` (default: `storage/arma/mods/...`)
- Mod symlinks into game install dirs: `{game_install_path}/@{normalized_mod_name}`
- Mission PBOs (shared pool): `{MISSIONS_BASE_PATH}/` (default: `storage/arma/missions/`)
- Mission symlinks into game installs: `{GAMES_BASE_PATH}/{id}/mpmissions/`
- All paths fall back to `storage_path('arma/...')` when env vars are not set.

### Docker Deployment
- The application ships as a single Docker container based on `cm2network/steamcmd`.
- Multi-stage Dockerfile: `node:24-alpine` (LTS) builds frontend assets, `cm2network/steamcmd` is the runtime base.
- The container includes: PHP 8.5 FPM/CLI, Nginx, SteamCMD, Supervisor, SQLite.
- Uses `network_mode: host` — the container shares the host's network stack so dynamically-configured Arma 3 server ports are accessible without pre-declaring port mappings.
- Supervisord manages: Nginx, PHP-FPM, the Laravel queue worker (database driver), and Laravel Reverb (WebSocket server on 127.0.0.1:6001 internally).
- Nginx listens on a configurable port via `APP_PORT` env var (default: 8080). The entrypoint uses `sed` to set the port at startup.
- Nginx reverse-proxies `/app` and `/apps` paths to Reverb at 127.0.0.1:6001 — no second external port needed for WebSockets.
- `docker/entrypoint.sh` runs on container start: resolves/persists `APP_KEY` and `REVERB_APP_SECRET` to the storage volume, configures Nginx port, creates storage dirs, ensures SQLite DB exists, runs migrations, creates initial admin user (if `ADMIN_EMAIL`/`ADMIN_PASSWORD` set), caches config/routes/views, then starts supervisord.
- `APP_KEY` persistence: env var > `storage/.app_key` file > auto-generate. Survives container recreations without user intervention.
- `REVERB_APP_SECRET` persistence: auto-generated via `openssl rand -hex 32` on first boot, persisted to `storage/.reverb_secret`.
- `docker/php.ini` configures 512MB upload limits (aligned with Livewire config), copied to both FPM and CLI conf.d.
- `docker/nginx.conf` sets `client_max_body_size 520M` (aligned with `post_max_size`).
- Single bind-mount volume in `docker-compose.yml`:
  - `./storage:/var/www/html/storage` — all game installs, server profiles, mods, missions, logs, cache, SQLite database, persisted keys (everything under `storage/`)
- The SQLite database lives at `storage/database.sqlite` in Docker (set via `DB_DATABASE` env var in entrypoint). This keeps everything in a single volume. Local dev still uses `database/database.sqlite` (the default).
- No custom path env vars (`GAMES_BASE_PATH`, etc.) needed in Docker — config falls back to `storage_path('arma/...')`.
- All Reverb/broadcast config has sensible defaults hardcoded in `config/reverb.php` and `config/broadcasting.php` — no Reverb env vars needed in docker-compose.
- `VITE_REVERB_APP_KEY` is hardcoded at build time (`armaman-key`). Host/port/scheme are derived from `window.location` at runtime.
- `storage/arma/games`, `storage/arma/servers`, `storage/arma/mods`, and `storage/arma/missions` are gitignored.

### Environment Variables (Custom)
- `STEAMCMD_PATH` - Path to SteamCMD executable (default: `/usr/games/steamcmd`, Docker: `/home/steam/steamcmd/steamcmd.sh`)
- `STEAM_API_KEY` - Steam Web API key for fetching workshop mod metadata
- `GAMES_BASE_PATH` - Base directory for game install downloads (default: `storage_path('arma/games')`)
- `SERVERS_BASE_PATH` - Base directory for server profiles/config (default: `storage_path('arma/servers')`)
- `MODS_BASE_PATH` - Base directory for mod downloads (default: `storage_path('arma/mods')`)
- `MISSIONS_BASE_PATH` - Base directory for uploaded PBO mission files (default: `storage_path('arma/missions')`)
- `MAX_BACKUPS_PER_SERVER` - Max `.vars.Arma3Profile` backups per server (default: 20, set to 0 for unlimited)

### Environment Variables (Docker)
- `APP_PORT` - Port Nginx listens on (default: `8080`)
- `ADMIN_EMAIL` - Email for initial admin user (created on first boot if no users exist)
- `ADMIN_PASSWORD` - Password for initial admin user
- `ADMIN_NAME` - Name for initial admin user (default: `Admin`)

## Data Model

### Core Models
- `GameInstall` - A downloaded Arma 3 server installation (name, branch, installation_status, progress_pct, disk_size_bytes, installed_at)
- `Server` - Arma 3 server instance (name, port, query_port, max_players, password, admin_password, description, active_preset_id, game_install_id, status, additional_params, verify_signatures, allowed_file_patching, battle_eye, persistent, von_enabled, additional_server_options)
- `DifficultySettings` - Per-server Arma 3 difficulty options (server_id, reduced_damage, group_indicators, friendly_tags, enemy_tags, detected_mines, commands, waypoints, tactical_ping, weapon_info, stance_indicator, stamina_bar, weapon_crosshair, vision_aid, third_person_view, camera_shake, score_table, death_messages, von_id, map_content, auto_report, ai_level_preset, skill_ai, precision_ai)
- `ServerBackup` - `.vars.Arma3Profile` backup (server_id, name, file_size, is_automatic, data)
- `WorkshopMod` - A Steam Workshop mod (workshop_id, name, file_size, installation_status, progress_pct, installed_at)
- `ModPreset` - A named collection of mods (name)
- `mod_preset_workshop_mod` - Pivot table (mod_preset_id, workshop_mod_id)
- `SteamAccount` - Steam credentials for SteamCMD (username, encrypted password, encrypted auth_token, encrypted steam_api_key, mod_download_batch_size)

### Enums
- `InstallationStatus` - Queued, Installing, Installed, Failed (used by both `GameInstall` and `WorkshopMod`)
- `ServerStatus` - Stopped, Starting, Booting, Running, Stopping

## Key Files

### Models
- `app/Models/GameInstall.php` — `servers(): HasMany`, `getInstallationPath(): string` returns `{games_base_path}/{id}`
- `app/Models/Server.php` — `gameInstall(): BelongsTo`, `activePreset(): BelongsTo`, `difficultySettings(): HasOne`, `backups(): HasMany`, `getProfilesPath(): string`, `getBinaryPath(): string`, `getProfileName(): string`
- `app/Models/DifficultySettings.php` — `server(): BelongsTo`, stores per-server Arma 3 difficulty options
- `app/Models/ServerBackup.php` — `server(): BelongsTo`, stores `.vars.Arma3Profile` backup data
- `app/Models/WorkshopMod.php` — `presets(): BelongsToMany`, `getInstallationPath(): string`, `getNormalizedName(): string`
- `app/Models/ModPreset.php` — `mods(): BelongsToMany`, `servers(): HasMany`
- `app/Models/SteamAccount.php` — stores encrypted credentials (password, auth_token, steam_api_key), `mod_download_batch_size`, `static current(): ?self`

### Services
- `app/Services/SteamCmdService.php` — `installServer(dir, branch, ?callable): ProcessResult`, `startDownloadMod(dir, id): InvokedProcess`, `startBatchDownloadMods(dir, ids[]): InvokedProcess`, `validateCredentials(user, pass): bool`
- `app/Services/SteamWorkshopService.php` — `getModDetails(id): ?array`, `getMultipleModDetails(ids[]): array`, `validateApiKey(key): array`, `getApiKey(): ?string`
- `app/Services/ServerProcessService.php` — `start(Server)`, `stop(Server)`, `restart(Server)`, `isRunning(Server): bool`, `getStatus(Server): ServerStatus`, `addHeadlessClient(Server): ?int`, `removeHeadlessClient(Server): ?int`, `stopAllHeadlessClients(Server)`, `getRunningHeadlessClientCount(Server): int`, `buildLaunchCommand(Server): string`, `getServerLogPath(Server): string`, `getHeadlessClientLogPath(Server, int): string`
- `app/Services/ServerBackupService.php` — `getVarsFilePath(Server): string`, `createFromServer(Server, ?name, isAutomatic): ?ServerBackup`, `createFromUpload(Server, data, ?name): ServerBackup`, `restore(ServerBackup)`, `pruneOldBackups(Server)`
- `app/Services/PresetImportService.php` — `parseHtmlPreset(html): Collection`, `parsePresetName(html): ?string`, `importFromHtml(html, ?name): ModPreset`, `dispatchBatchedDownloads(Collection)`

### Broadcast Events
- `app/Events/GameInstallOutput.php` — broadcasts on `game-install.{id}`, carries `gameInstallId`, `progressPct`, `line`
- `app/Events/ModDownloadOutput.php` — broadcasts on `mod-download.{id}`, carries `modId`, `progressPct`, `line`
- `app/Events/ServerLogOutput.php` — broadcasts on `server-log.{id}`, carries `serverId`, `line`
- `app/Events/ServerStatusChanged.php` — broadcasts on `servers` (global), carries `serverId`, `status`

### Listeners
- `app/Listeners/DetectServerBooted.php` — listens to `ServerLogOutput`; transitions server from `Booting` to `Running` when log contains "Connected to Steam servers"

### Console Commands
- `app/Console/Commands/TailServerLog.php` — `server:tail-log {server}`, tails server.log, broadcasts via `ServerLogOutput`, handles file rotation
- `app/Console/Commands/CreateAdminUser.php` — `user:create-admin --email= --password= [--name=]`, creates an initial admin user if no users exist (used by Docker entrypoint)

### Jobs
- `app/Jobs/InstallServerJob.php` — installs a `GameInstall`; streams SteamCMD output; parses `progress:` lines; throttled DB writes (every 1 pct); broadcasts `GameInstallOutput` events; tries=2, timeout=7200s
- `app/Jobs/DownloadModJob.php` — downloads a single `WorkshopMod`; uses `startDownloadMod()` (async); polls `du -sb` every 1s; throttled DB writes (every 1 pct); broadcasts `ModDownloadOutput` events; tries=2, timeout=3600s
- `app/Jobs/BatchDownloadModsJob.php` — downloads multiple `WorkshopMod`s in a single SteamCMD invocation; polls all mod directories; broadcasts per-mod `ModDownloadOutput` events; tries=2, timeout=1hr per mod
- `app/Jobs/StartServerJob.php` — starts a server (or restarts with HC restoration); sets status to `Booting`; tries=1
- `app/Jobs/StopServerJob.php` — stops all HCs then the server; sets status to `Stopped`; tries=1, timeout=30s
- `app/Jobs/Concerns/InteractsWithFileSystem.php` — trait providing `getDirectorySize(path): int` and `convertToLowercase(path)`

### Pages (Livewire single-file)
- `resources/views/pages/game-installs/index.blade.php` — game installs list, create modal, reinstall/delete, collapsible log viewer per install, Echo listeners (no wire:poll)
- `resources/views/pages/servers/index.blade.php` — server list with inline create modal + configure panel, game install dropdown (required), log viewer panel, `wire:poll.5s` for status + Echo for logs
- `resources/views/pages/servers/partials/form-fields.blade.php` — shared server form fields partial
- `resources/views/pages/mods/index.blade.php` — mod list, add by workshop ID, progress bar, collapsible log viewer per mod in table rows, Echo listeners (no wire:poll)
- `resources/views/pages/presets/index.blade.php` — preset list, delete
- `resources/views/pages/presets/create.blade.php` — create preset with mod selection + HTML import
- `resources/views/pages/presets/edit.blade.php` — edit preset, manage mods
- `resources/views/pages/presets/partials/form-fields.blade.php` — shared preset form fields partial
- `resources/views/pages/missions/index.blade.php` — mission PBO upload/download/delete, upload progress bar, filesystem-based (no DB model)
- `resources/views/pages/steam-settings.blade.php` — manage Steam credentials, API key, and mod download batch size

### Frontend / JS
- `resources/js/app.js` — Laravel Echo configured for Reverb (pusher-js transport). Uses `window.location` for wsHost/wsPort/forceTLS at runtime (no baked VITE vars needed for host/port/scheme). `VITE_REVERB_APP_KEY` is hardcoded at build time (`armaman-key`).

### Config
- `config/arma.php` — `steamcmd_path`, `steam_api_key`, `games_base_path`, `servers_base_path`, `mods_base_path`, `missions_base_path`, `server_app_id` (233780), `game_id` (107410), `max_backups_per_server`
- `config/broadcasting.php` — Reverb connection configured (defaults to `reverb` driver with hardcoded credentials)
- `config/reverb.php` — published Reverb config (server defaults to 127.0.0.1:6001)
- `config/livewire.php` — `emoji => false`, `temporary_file_upload.rules` set to 512MB max

### Docker
- `Dockerfile` — Multi-stage build: `composer-deps` → `frontend` (node:24-alpine) → `runtime` (cm2network/steamcmd + PHP 8.5). `VITE_REVERB_APP_KEY` hardcoded as ENV
- `docker-compose.yml` — single service, `network_mode: host`, single `./storage` volume, minimal env vars (`APP_PORT`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`)
- `docker/entrypoint.sh` — APP_KEY + REVERB_APP_SECRET generation/persistence, Nginx port config, storage dir setup, DB migration, initial admin user creation, config/route/view caching, supervisord launch
- `docker/supervisord.conf` — nginx, php-fpm, queue-worker (database driver), reverb (127.0.0.1:6001)
- `docker/nginx.conf` — serves app on port 80 (rewritten to APP_PORT by entrypoint), reverse-proxies `/app` and `/apps` to Reverb at 127.0.0.1:6001, `client_max_body_size 520M`
- `docker/php.ini` — `upload_max_filesize=512M`, `post_max_size=520M`

### Tests
- `tests/Feature/GameInstalls/GameInstallManagementTest.php`
- `tests/Feature/Servers/ServerManagementTest.php`
- `tests/Feature/Servers/ServerProcessServiceTest.php` — server.cfg generation, mission symlink, mod symlink, BiKey copy tests
- `tests/Feature/Servers/ServerBackupManagementTest.php` — backup UI tests
- `tests/Feature/Servers/ServerBackupServiceTest.php` — backup service unit tests
- `tests/Feature/Missions/MissionManagementTest.php` — PBO upload, download, delete, path traversal protection
- `tests/Feature/Mods/WorkshopModManagementTest.php`
- `tests/Feature/Jobs/DownloadModJobTest.php` — mocks `SteamCmdService::startDownloadMod()` returning a mock `InvokedProcess`; uses `Process::fake(['du *' => ...])` for disk size
- `tests/Feature/Jobs/BatchDownloadModsJobTest.php` — tests batched mod downloads
- `tests/Feature/Jobs/StartServerJobTest.php` — tests server start job
- `tests/Feature/Jobs/StopServerJobTest.php` — tests server stop job
- `tests/Feature/Presets/ModPresetManagementTest.php`
- `tests/Feature/SteamSettings/SteamSettingsTest.php`
- `tests/Feature/Events/BroadcastEventsTest.php` — broadcast event channel and property tests
- `tests/Feature/Listeners/DetectServerBootedTest.php` — tests Booting → Running transition
- `tests/Feature/Services/PresetImportServiceTest.php`
- `tests/Concerns/MocksSteamCmdProcess.php` — trait providing `makeInvokedProcess(bool): InvokedProcess`
- `tests/Concerns/MocksServerProcessService.php` — trait providing `mockServerProcessService(ServerStatus): void`

## SteamCMD Commands Reference

### Install/Update Arma 3 Server
```
steamcmd +force_install_dir {install_path} +login {username} {password} +app_update 233780 validate +quit
# For non-public branch:
steamcmd +force_install_dir {install_path} +login {username} {password} +app_update 233780 -beta {branch} validate +quit
```

### Download Workshop Mod (single)
```
steamcmd +force_install_dir {mods_base_path} +login {username} {password} +workshop_download_item 107410 {workshop_id} validate +quit
```

### Download Workshop Mods (batch)
```
steamcmd +force_install_dir {mods_base_path} +login {username} {password} +workshop_download_item 107410 {id1} +workshop_download_item 107410 {id2} validate +quit
```

## Testing Notes
- Mock `SteamCmdService` directly in job tests (bind via `$this->app->instance()`).
- For `DownloadModJob` tests: mock `startDownloadMod()` to return a mock `InvokedProcess` with `running()` returning `false` and `wait()` returning a mock `ProcessResult`. Use `Process::fake(['du *' => Process::result('SIZE\t/path')])` to fake disk size reads.
- For `BatchDownloadModsJob` tests: mock `startBatchDownloadMods()` similarly. The job polls multiple mod directories simultaneously.
- For `InstallServerJob` tests: mock `installServer()` to invoke the callback with fake output lines if testing progress parsing.
- For `StartServerJob`/`StopServerJob` tests: mock `ServerProcessService` methods.
- `setUp()` in `ServerManagementTest` always creates a `GameInstall` — tests that need `createGameInstallId` to be null must explicitly `->set('createGameInstallId', null)` after `openCreateModal`.
- Use `MocksSteamCmdProcess` trait for creating mock `InvokedProcess` instances.
- Use `MocksServerProcessService` trait for mocking `ServerProcessService` in Livewire component tests.
- `SteamWorkshopService` no longer has `getDownloadProgress()` or `getDownloadedSize()` — do not mock them.
- Broadcast events use `ShouldBroadcastNow` — when testing dispatches from jobs, use `Event::fake([SpecificEvent::class])` to avoid breaking other event-driven behavior (especially `DetectServerBooted` listener).
- `Process::fake()` does not intercept `rm -rf` calls in Livewire component tests reliably — use real filesystem + `assertDirectoryDoesNotExist` instead.
