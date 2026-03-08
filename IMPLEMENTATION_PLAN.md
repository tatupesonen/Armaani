# Implementation Plan: armaani -> armaani2 Feature Parity

## Architecture Difference

- **armaani** (original): Laravel 12 + Livewire SFC + Blade + Alpine.js + Tailwind CSS
- **armaani2** (port): Laravel 12 + Inertia v2 + React 19 + Tailwind CSS v4

## What Is Already Identical

All backend logic was copied directly and is identical between both projects:

- All 12 Models (except User missing `initials()` method)
- All 3 Enums (GameType, ServerStatus, InstallationStatus)
- All 5 Services (SteamCmdService, SteamWorkshopService, ServerProcessService, ServerBackupService, PresetImportService)
- All 5 Jobs (InstallServerJob, StartServerJob, StopServerJob, DownloadModJob, BatchDownloadModsJob)
- All 3 GameHandlers (Arma3Handler, ReforgerHandler, DayZHandler)
- GameManager, GameHandler contract
- DetectServerBooted listener
- Both Console Commands (CreateAdminUser, TailServerLog)
- config/arma.php
- All 31 migrations
- All 12 factories
- DatabaseSeeder

### Intentional Improvements in armaani2

- All 4 Events changed from `Channel` (public) to `PrivateChannel` (auth-required)
- Proper Inertia controllers instead of Livewire SFCs
- Form Request classes for settings validation
- Channel authorization routes in channels.php

### Bugs Found & Fixed

- **DayZSettings table name**: Laravel infers `day_z_settings` from class `DayZSettings`, but migration creates `dayz_settings`. Fixed with `protected $table = 'dayz_settings'` (bug exists in original too).
- **BroadcastException when Reverb not running**: `event()` calls crash if Reverb isn't running. Fixed with `broadcastStatusChange()` helper wrapping in try/catch.
- **ServerBackupController upload bug**: Original passed `UploadedFile` object to service expecting `string`. Fixed to use `file_get_contents()`.
- **PresetImportService return type**: Returns `ModPreset` not array — controller was accessing `$result['preset']` which would crash. Fixed.
- **Missing network validation rules**: `Arma3Handler::settingsValidationRules()` was missing network fields (server_port, server_query_port, etc.), causing validated data to silently drop them. Fixed.
- **ANSI escape codes in SteamCMD output**: `[0m` and `[1m` fragments were visible in log viewer. Added `stripAnsi()` to `SteamCmdService.php`.
- **Duplicate log lines from React StrictMode**: Double-mounting effects caused duplicate WebSocket subscriptions. Removed StrictMode from `resources/js/app.tsx`.

---

## Phase 1: Backend Logic Fixes ✅ COMPLETE

### 1.1 Server status pre-set on start/stop/restart ✅

- In `ServerController::start()`, set `status => ServerStatus::Starting` and broadcast `ServerStatusChanged` BEFORE dispatching `StartServerJob`
- In `ServerController::stop()`, set `status => ServerStatus::Stopping` and broadcast BEFORE dispatching `StopServerJob`
- In `ServerController::restart()`, same pattern with `ServerStatus::Starting`

### 1.2 Backup name support ✅

- `ServerBackupController::store()` accepts `backup_name` from request and passes to `$backupService->createFromServer($server, $request->input('backup_name'))`

### 1.3 `supportsBackups()` flag ✅

- In `ServerController::index()`, pass `supports_backups` per server based on `GameManager->for($server)->getBackupFilePath($server) !== null`

### 1.4 Batch mod download chunking ✅

- Fixed `WorkshopModController::updateSelected()` to chunk by `SteamAccount::current()->mod_download_batch_size`
- Fixed `WorkshopModController::retryAllFailed()` to batch using `BatchDownloadModsJob` with chunking
- Fixed `WorkshopModController::updateAllOutdated()` to chunk similarly

### 1.5 Mission PBO validation ✅

- Added server-side `.pbo` extension check in `MissionController::store()` (skip non-PBO files)

### 1.6 Mission date field fix ✅

- Changed `filectime()` to `filemtime()` in `MissionController::index()`
- Key name is `modified_at` consistently

### 1.7 Preset import error handling ✅

- Wrapped `importFromHtml()` in try/catch for `\InvalidArgumentException`
- Returns `back()->withErrors(['file' => $e->getMessage()])` on failure

### 1.8 User model `initials()` method ✅

- Added `initials(): string` method to User model

### 1.9 Game install delete prevention ✅

- Added check for `installing`/`queued` status in `GameInstallController::destroy()`
- Returns error flash if install is currently active

---

## Phase 2: Toast Notification System ✅ COMPLETE

### 2.1 Create ToastProvider React component ✅

- Context-based toast manager with `useToast()` hook
- Support variants: success, error, info, warning
- Auto-dismiss with configurable timer (default 5s)
- Stack multiple toasts
- Smooth enter/exit animations

### 2.2 Wire Inertia flash messages ✅

- Listen to `page.props.flash.success` and `page.props.flash.error`
- Auto-display as toasts on every page navigation

### 2.3 Server status toasts ✅

- Subscribe to `Echo.private('servers')` for `ServerStatusChanged` events
- Show persistent server status toasts with state transitions:
    - Starting (blue gradient)
    - Booting (amber gradient)
    - Running (green gradient, auto-dismiss after 3s)
    - Stopping (orange gradient)
    - Stopped (auto-dismiss after 3s)
- Animated cross-fade between states for the same server

### 2.4 Seed active servers on page load ✅

- Pass currently active servers (starting/booting/running/stopping) via shared Inertia props in `HandleInertiaRequests`
- Toast manager renders initial status toasts for active servers on mount

### 2.5 Integrate into app layout ✅

- Mount `ToastProvider` in the main app layout (wrapping all authenticated pages)

---

## Phase 3: Real-Time / WebSocket Features ✅ COMPLETE

### 3.1 Dashboard polling ✅

- Use Inertia v2 `usePoll()` hook with 30s interval for auto-refreshing dashboard stats

### 3.2 Dashboard disk usage fix ✅

- Fixed disk usage to use `storage_path()` instead of `/`

### 3.3 Mod download log viewer ✅

- Extended existing `LogViewer` React component to support mod download channels
- Wired into mods page: each downloading mod can expand to show real-time SteamCMD output
- Subscribe to `Echo.private('mod-download.{id}')` for `ModDownloadOutput` events

### 3.4 Server status WebSocket on servers page ✅

- Subscribe to `Echo.private('servers')` for `ServerStatusChanged` events
- `usePoll()` + Echo private channel for instant status updates

---

## Phase 4: UI Feature Parity ✅ COMPLETE

### 4.1 Dashboard ✅

- [x] Complete with polling, disk usage fix

### 4.2 Servers page — `profiles_path` display ✅

- [x] Backend passes `profiles_path` per server (done in Phase 1)
- [x] Render `profiles_path` on each server card in `server-card.tsx` (monospace path display)

### 4.3 Game Installs page — `installation_path` display ✅

- [x] Pass `installation_path` from `GameInstallController::index()` via `setAttribute()`
- [x] Add `installation_path` to `GameInstall` TypeScript type in `game.ts`
- [x] Render `installation_path` on each install card

### 4.4 Mods page — table conversion + features ✅

Converted from card layout to table layout (matching original). Backend supports `sort_by`/`sort_direction` query params.

- [x] Convert mods display from cards to a proper HTML table
- [x] Sortable column headers (file_size, installation_status, steam_updated_at, installed_at) with clickable headers and direction arrows
- [x] Select-all checkbox in table header (excludes installing/queued mods)
- [x] Pass `is_outdated` from `WorkshopModController::index()` via `setAttribute()`
- [x] Pass `installedStats` (count + total_size) from controller
- [x] Per-mod "Update available" badge when `is_outdated` is true
- [x] Installed mod stats summary in heading ("X installed, Y.Z GB total")
- [x] Workshop Updated (`steam_updated_at`) date column in table
- [x] Reforger mod add/delete UI — inline form on Reforger tab + delete button per mod row with confirmation
- [x] Mod download log viewer per mod (done in Phase 3)
- [x] "Update All Outdated (N)" button shown conditionally with count

### 4.5 Missions page — upload progress ✅

- [x] Switch upload from `router.post()` to `useForm().post()` to get progress tracking
- [x] Render upload progress bar with percentage using `form.progress`

### 4.6 Presets page ✅

- [x] Import error handling done in Phase 1

### 4.7 Steam Settings page ✅

- [x] Steam Guard 2FA warning callout (amber alert with exclamation icon, explaining email-based Steam Guard flow)
- [x] Direct clickable link to `https://steamcommunity.com/dev/apikey` in API key description
- [x] Inline verification status display — session-only green/red button variant + inline error code (resets on navigation)
- [x] Auth token placeholder masking (`********`) when token exists

### Infrastructure Fixes (discovered during Phase 4)

- [x] Fixed `.env` `REVERB_PORT=8080` → `6001` to match Reverb server's actual listen port
- [x] Added `php artisan reverb:start` to `composer run dev` script (was missing, causing broadcast failures)

---

## Phase 5: Test Suite ✅ COMPLETE

Ported the entire test suite from Livewire to Inertia v2 endpoint tests. Backend-only tests (jobs, events, listeners, game handlers, services) were copied nearly verbatim. Livewire UI tests were converted to HTTP endpoint tests using `assertInertia()`, `assertSessionHas()`, `assertSessionHasErrors()`, etc. Livewire-specific UI tests (toggleSelectAll, cancelEditing, sort cycling) were skipped as they test frontend state management.

### Testing Approach: Inertia v2 Endpoint Tests

```php
use Inertia\Testing\AssertableInertia as Assert;

// GET page:
$this->get('/servers')
    ->assertInertia(fn (Assert $page) => $page
        ->component('servers/index')
        ->has('servers', 3)
        ->where('servers.0.name', 'Test Server')
    );

// POST action with redirect + flash:
$this->post('/servers', $data)
    ->assertRedirect('/servers')
    ->assertSessionHas('success');
```

### 5.1 Port test concern traits (4 files) ✅

- [x] `tests/Concerns/CreatesGameScenarios.php` - Factory setup helpers
- [x] `tests/Concerns/MocksGameManager.php` - Mock GameManager singleton
- [x] `tests/Concerns/MocksServerProcessService.php` - Mock ServerProcessService
- [x] `tests/Concerns/MocksSteamCmdProcess.php` - Mock SteamCMD process interactions

### 5.2 Port test files ✅

| #   | File                       | Tests | Domain         | Status |
| --- | -------------------------- | ----- | -------------- | ------ |
| 1   | ServerManagementTest       | 31    | Servers        | ✅     |
| 2   | ServerProcessServiceTest   | 33    | Servers        | ✅     |
| 3   | MultiGameServerTest        | 14    | Servers        | ✅     |
| 4   | ServerBackupManagementTest | 14    | Servers        | ✅     |
| 5   | ServerBackupServiceTest    | 14    | Servers        | ✅     |
| 6   | WorkshopModManagementTest  | 37    | Mods           | ✅     |
| 7   | ModPresetManagementTest    | 32    | Presets        | ✅     |
| 8   | MissionManagementTest      | 14    | Missions       | ✅     |
| 9   | SteamSettingsTest          | 27    | Steam Settings | ✅     |
| 10  | GameInstallManagementTest  | 7     | Game Installs  | ✅     |
| 11  | InstallServerJobTest       | 3     | Jobs           | ✅     |
| 12  | StartServerJobTest         | 6     | Jobs           | ✅     |
| 13  | StopServerJobTest          | 3     | Jobs           | ✅     |
| 14  | DownloadModJobTest         | 9     | Jobs           | ✅     |
| 15  | BatchDownloadModsJobTest   | 8     | Jobs           | ✅     |
| 16  | ReforgerHandlerTest        | 16    | GameHandlers   | ✅     |
| 17  | DayZHandlerTest            | 12    | GameHandlers   | ✅     |
| 18  | BroadcastEventsTest        | 8     | Events         | ✅     |
| 19  | DetectServerBootedTest     | 4     | Listeners      | ✅     |
| 20  | DashboardTest              | 10    | Dashboard      | ✅     |

**Total: 302 tests, all passing**

### 5.3 Bug fixes during porting ✅

- Added missing network settings validation rules to `Arma3Handler::settingsValidationRules()`
- Fixed ANSI escape code stripping in `SteamCmdService.php` (`stripAnsi()` method)
- Dark mode CSS brightness adjustment (background + sidebar)
- Removed React StrictMode to prevent duplicate log lines

---

## Decisions Made

- **Private channels**: Keep armaani2's PrivateChannel approach. Use `Echo.private()` in React.
- **Toast system**: Full port with server status toasts + flash message toasts.
- **Audit logging**: Skip. Current `Log::info()` is sufficient.
- **UI features**: Port everything for full parity.
- **Mod download logs**: Extend existing LogViewer component.
- **Dashboard polling**: Use Inertia v2 `usePoll()` hook.
- **Test concerns**: Port as shared traits adapted for Inertia endpoint testing.
- **Test pattern**: Inertia v2 endpoint tests using `assertInertia()`, `assertInertiaFlash()`, etc.
- **Mods layout**: Convert from card layout to table layout (matching original) for sortable columns and select-all.
- **Reforger mod UI**: Inline on page — text input + add button at top of Reforger tab, delete button per mod row.
- **Verification status**: Session-only — show green/red inline after verify click, resets on navigation.
- **Upload progress**: Use Inertia `useForm` progress tracking with percentage bar.
- **Implementation order**: Complete all Phase 4 UI features first, then port entire Phase 5 test suite.

---

## Modified Files

### Backend (modified in armaani2)

- `app/Http/Controllers/ServerController.php` — status pre-set, broadcast helper
- `app/Http/Controllers/ServerBackupController.php` — backup name, upload fix
- `app/Http/Controllers/WorkshopModController.php` — batch chunking
- `app/Http/Controllers/MissionController.php` — PBO validation, date fix
- `app/Http/Controllers/ModPresetController.php` — import error handling
- `app/Http/Controllers/GameInstallController.php` — delete prevention
- `app/Http/Controllers/DashboardController.php` — disk usage path fix
- `app/Http/Middleware/HandleInertiaRequests.php` — flash data + activeServers sharing
- `app/Models/User.php` — added `initials()` method
- `app/Models/DayZSettings.php` — added `$table = 'dayz_settings'`
- `app/GameHandlers/Arma3Handler.php` — added network settings validation rules
- `app/Services/SteamCmdService.php` — added `stripAnsi()` for ANSI code stripping

### Frontend (modified/created in armaani2)

- `resources/js/components/toast-manager.tsx` — NEW: full toast system
- `resources/js/layouts/app/app-sidebar-layout.tsx` — ToastProvider wrapper
- `resources/js/pages/dashboard.tsx` — usePoll(30000)
- `resources/js/pages/servers/index.tsx` — usePoll + Echo integration
- `resources/js/pages/mods/index.tsx` — usePoll + mod download LogViewer
- `resources/js/components/servers/server-edit-panel.tsx` — conditional BackupSection
- `resources/js/types/game.ts` — added supports_backups, profiles_path to Server type
- `resources/js/app.tsx` — removed StrictMode (duplicate log line fix)
- `resources/css/app.css` — dark mode brightness adjustments

### Tests (created in armaani2)

- `tests/Concerns/` — 4 trait files (CreatesGameScenarios, MocksGameManager, MocksServerProcessService, MocksSteamCmdProcess)
- `tests/Feature/Events/BroadcastEventsTest.php` — 8 tests
- `tests/Feature/Listeners/DetectServerBootedTest.php` — 4 tests
- `tests/Feature/Jobs/InstallServerJobTest.php` — 3 tests
- `tests/Feature/Jobs/StartServerJobTest.php` — 6 tests
- `tests/Feature/Jobs/StopServerJobTest.php` — 3 tests
- `tests/Feature/Jobs/DownloadModJobTest.php` — 9 tests
- `tests/Feature/Jobs/BatchDownloadModsJobTest.php` — 8 tests
- `tests/Feature/GameHandlers/ReforgerHandlerTest.php` — 16 tests
- `tests/Feature/GameHandlers/DayZHandlerTest.php` — 12 tests
- `tests/Feature/GameInstalls/GameInstallManagementTest.php` — 7 tests
- `tests/Feature/Servers/ServerManagementTest.php` — 31 tests
- `tests/Feature/Servers/ServerProcessServiceTest.php` — 33 tests
- `tests/Feature/Servers/MultiGameServerTest.php` — 14 tests
- `tests/Feature/Servers/ServerBackupManagementTest.php` — 14 tests
- `tests/Feature/Servers/ServerBackupServiceTest.php` — 14 tests
- `tests/Feature/Mods/WorkshopModManagementTest.php` — 37 tests
- `tests/Feature/Presets/ModPresetManagementTest.php` — 32 tests
- `tests/Feature/Missions/MissionManagementTest.php` — 14 tests
- `tests/Feature/SteamSettings/SteamSettingsTest.php` — 27 tests
- `tests/Feature/DashboardTest.php` — 10 tests (expanded)
