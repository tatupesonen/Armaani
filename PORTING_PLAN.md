# armaani Porting Plan: Livewire → Inertia/React

Porting the game server manager from `~/armaani` (Livewire 4 + Flux UI + Alpine.js) to `~/armaani2` (Inertia v2 + React 19 + shadcn/ui).

## Decisions

- **No SSR** — Inertia SSR is disabled
- **WebSocket** — Reverb set up from the start for real-time features
- **UI library** — shadcn/ui replaces Flux UI
- **Registration** — Disabled (admin created via `user:create-admin` command only)
- **Porting order** — Backend first, then UI, then tests

---

## Phase 1: Backend

Backend code ports nearly verbatim since it has no Livewire dependencies.

### 1.1 Models, Enums, Config, Migrations

- [ ] Copy 11 models: `Server`, `GameInstall`, `WorkshopMod`, `ModPreset`, `ReforgerMod`, `SteamAccount`, `DifficultySettings`, `NetworkSettings`, `ReforgerSettings`, `DayZSettings`, `ServerBackup`
- [ ] Copy 3 enums: `GameType`, `ServerStatus`, `InstallationStatus`
- [ ] Copy `config/arma.php`
- [ ] Copy all 31 migrations
- [ ] Copy factories and seeders

### 1.2 Services, Contracts, GameManager

- [ ] Copy `app/Contracts/GameHandler.php` interface
- [ ] Copy `app/GameManager.php` (driver pattern)
- [ ] Copy `app/Services/SteamCmdService.php`
- [ ] Copy `app/Services/SteamWorkshopService.php`
- [ ] Copy `app/Services/ServerProcessService.php`
- [ ] Copy `app/Services/ServerBackupService.php`
- [ ] Copy `app/Services/PresetImportService.php`
- [ ] Copy `app/GameHandlers/Arma3Handler.php`
- [ ] Copy `app/GameHandlers/ReforgerHandler.php`
- [ ] Copy `app/GameHandlers/DayZHandler.php`
- [ ] Update `AppServiceProvider` to register `GameManager` singleton

### 1.3 Jobs, Events, Listeners

- [ ] Copy 5 jobs: `InstallServerJob`, `StartServerJob`, `StopServerJob`, `DownloadModJob`, `BatchDownloadModsJob`
- [ ] Copy `InteractsWithFileSystem` trait
- [ ] Copy 4 broadcast events: `GameInstallOutput`, `ModDownloadOutput`, `ServerLogOutput`, `ServerStatusChanged`
- [ ] Copy `DetectServerBooted` listener
- [ ] Register listener in `EventServiceProvider` (or auto-discovery)

### 1.4 Console Commands

- [ ] Copy `user:create-admin` command
- [ ] Copy `server:tail-log` command

### 1.5 Reverb & Broadcasting

- [ ] Install Laravel Reverb
- [ ] Configure broadcasting driver
- [ ] Set up `channels.php` broadcast routes

### 1.6 Inertia Controllers

Create new controllers to replace Livewire page components:

- [ ] `DashboardController` — stats, system resources, server status
- [ ] `GameInstallController` — CRUD, install/reinstall actions
- [ ] `ServerController` — CRUD, start/stop/restart, settings, backups
- [ ] `WorkshopModController` — add/delete/update mods, bulk operations
- [ ] `MissionController` — upload/download/delete PBO files
- [ ] `ModPresetController` — CRUD, HTML import
- [ ] `SteamSettingsController` — credential management

### 1.7 Routes

- [ ] Define all routes in `routes/web.php`
- [ ] Add download routes for backups and missions

### 1.8 Disable Registration

- [ ] Disable registration in `config/fortify.php`
- [ ] Remove registration UI references

### 1.9 Verify

- [ ] Run migrations
- [ ] Run `php artisan test --compact` to check nothing is broken

---

## Phase 2: UI (React/Inertia/shadcn)

Full rewrite of all Livewire/Flux/Alpine views to React components.

### 2.1 Shared Components

- [ ] `<LogViewer>` — real-time log streaming via Echo (replaces `<x-log-viewer>`)
- [ ] `<ToastManager>` — ephemeral + persistent server status toasts (replaces `<x-toast-manager>`)

### 2.2 Pages

- [ ] **Dashboard** — stat cards, system resources (CPU/memory/disk), server status table, quick info
- [ ] **Game Installs** — CRUD table, install progress, real-time log streaming
- [ ] **Servers** — full management UI (largest page ~830 lines of Livewire PHP):
    - Server cards with status-dependent gradients
    - Create/edit/delete with inline editing
    - Start/stop/restart controls
    - Real-time log viewer
    - Launch command display
    - Headless client management (Arma 3)
    - Difficulty settings (23+ fields with segmented radio groups)
    - Network settings (11 fields with "high performance" preset)
    - Backup management (create, upload, restore, download, delete)
- [ ] **Mods** — tabbed interface (Workshop/Reforger), bulk operations, search, sort, progress
- [ ] **Missions** — drag-and-drop PBO upload, file list with download/delete
- [ ] **Presets** — index (list + HTML import), create/edit (game-type-aware mod selection)
- [ ] **Steam Settings** — credential forms with verify buttons, batch size config

### 2.3 Navigation

- [ ] Update sidebar navigation to include all game management pages

---

## Phase 3: Tests

### 3.1 Backend Tests (minimal changes)

These test jobs, services, events, listeners — no UI dependency:

- [ ] `Jobs/InstallServerJobTest`
- [ ] `Jobs/StartServerJobTest`
- [ ] `Jobs/StopServerJobTest`
- [ ] `Jobs/DownloadModJobTest`
- [ ] `Jobs/BatchDownloadModsJobTest`
- [ ] `Services/PresetImportServiceTest`
- [ ] `Servers/ServerProcessServiceTest`
- [ ] `Servers/ServerBackupServiceTest`
- [ ] `Listeners/DetectServerBootedTest`
- [ ] `Events/BroadcastEventsTest`
- [ ] `GameHandlers/ReforgerHandlerTest`
- [ ] `GameHandlers/DayZHandlerTest`

### 3.2 UI Tests (convert Livewire::test → Inertia assertions)

These use `Livewire::test()` and need conversion to `$this->get()->assertInertia()` + controller action tests:

- [ ] `DashboardTest`
- [ ] `GameInstalls/GameInstallManagementTest`
- [ ] `Servers/ServerManagementTest`
- [ ] `Servers/MultiGameServerTest`
- [ ] `Servers/ServerBackupManagementTest`
- [ ] `Mods/WorkshopModManagementTest`
- [ ] `Missions/MissionManagementTest`
- [ ] `Presets/ModPresetManagementTest`
- [ ] `SteamSettings/SteamSettingsTest`
- [ ] `ToastManager/ToastManagerTest`

### 3.3 Auth Tests (already exist in starter kit)

- [ ] Verify existing auth tests still pass
- [ ] Port any additional auth tests from source that aren't covered

### 3.4 Test Concerns

- [ ] Copy `CreatesGameScenarios` trait
- [ ] Copy `MocksGameManager` trait
- [ ] Copy `MocksServerProcessService` trait
- [ ] Copy `MocksSteamCmdProcess` trait
