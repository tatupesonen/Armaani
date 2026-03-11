# Audit Plan — Round 2

## Phase 1: Bug Fixes (13 items)

### 1.1 `Server.$fillable` missing `description`

- **File:** `app/Models/Server.php`
- **Fix:** Add `'description'` to the `$fillable` array.
- **Test:** Verify server creation/update with description persists correctly.

### 1.2 PZ handler missing `parent::serverValidationRules()` call

- **File:** `app/GameHandlers/ProjectZomboidHandler.php`
- **Fix:** Change `serverValidationRules()` to spread `parent::serverValidationRules($server)` like Arma3/Factorio handlers do.
- **Test:** Existing handler capabilities tests should cover this; verify PZ server creation with duplicate query_port is rejected.

### 1.3 `MissionController::destroy()` returns success when file not found

- **File:** `app/Http/Controllers/MissionController.php`
- **Fix:** Return `back()->with('error', 'Mission file not found.')` when `resolveSecureMissionPath()` returns null.
- **Test:** Add test for deleting a non-existent mission.

### 1.4 `ReforgerScenarioService::refreshScenarios()` not in a transaction

- **File:** `app/Services/Mod/ReforgerScenarioService.php`
- **Fix:** Wrap the delete + re-insert loop in `DB::transaction()`. Consider using bulk `insert()` instead of per-row `create()`.
- **Test:** Existing ReforgerScenarioServiceTest covers the happy path; transaction safety is structural.

### 1.5 `SystemResourceService::getDiskUsage()` crashes on invalid path

- **File:** `app/Services/SystemResourceService.php`
- **Fix:** Guard against `false` return from `disk_total_space()`/`disk_free_space()`. Return zeroes or null if the path is invalid.
- **Test:** Add a direct test for `getDiskUsage()` with a nonexistent path.

### 1.6 `stop()`/`restart()` don't handle `DownloadingMods` status

- **File:** `app/Http/Controllers/ServerController.php`
- **Fix:** Add `ServerStatus::DownloadingMods` to the allowed statuses in `stop()` and `restart()`. Use the new `ServerStatus::isStoppable()` helper (see Phase 2).
- **Test:** Add test for stopping/restarting a server in DownloadingMods state.

### 1.7 `HandleInertiaRequests` activeServers omits `DownloadingMods`

- **File:** `app/Http/Middleware/HandleInertiaRequests.php`
- **Fix:** Add `ServerStatus::DownloadingMods` to the `whereIn` clause. Use `ServerStatus::isActive()` helper.
- **Test:** N/A (middleware shared prop, covered by integration).

### 1.8 Can't delete a crashed server or restore backup on crashed server

- **Files:** `app/Http/Controllers/ServerController.php`, `app/Http/Controllers/ServerBackupController.php`
- **Fix:** Allow `destroy()` when status is `Stopped` OR `Crashed`. Allow `restore()` when status is `Stopped` OR `Crashed`. Use `ServerStatus::isDeletable()` helper.
- **Test:** Add tests for deleting a crashed server and restoring a backup on a crashed server.

### 1.9 `PresetImportService` can throw unhandled `QueryException`

- **File:** `app/Http/Controllers/ModPresetController.php`
- **Fix:** Add `QueryException` to the catch block in the `import()` method, returning a user-friendly error about duplicate preset names.
- **Test:** Add test for importing a preset with a duplicate name.

### 1.10 Log viewer `loadInitialLines` missing `.catch()`

- **File:** `resources/js/components/log-viewer.tsx`
- **Fix:** Add `.catch()` to the `loadInitialLines().then(...)` call to handle fetch failures gracefully.

### 1.11 Crash on empty `gameTypes` array

- **Files:** `resources/js/pages/game-installs/index.tsx`, `resources/js/components/servers/create-server-dialog.tsx`
- **Fix:** Add early return / guard when `gameTypes` is empty before accessing `gameTypes[0]`.

### 1.12 `GameServiceProvider::discoverHandlers()` — `ReflectionClass` before `class_exists`

- **File:** `app/Providers/GameServiceProvider.php`
- **Fix:** Move the `class_exists($class)` check before `new ReflectionClass($class)`.

### 1.13 Delete placeholder test file

- **File:** `tests/Feature/Services/HttpDownloadServiceTest.php`
- **Fix:** Delete this file. The real tests are in `tests/Feature/HttpDownloadServiceTest.php`.

---

## Phase 2: Database Schema Improvements (4 items)

### 2.1 Add indexes on all FK columns

- **Migration:** Create `add_foreign_key_indexes` migration.
- **Columns:** `servers.game_install_id`, `servers.active_preset_id`, `server_backups.server_id`, `arma3_settings.server_id`, `reforger_settings.server_id`, `project_zomboid_settings.server_id`, `factorio_settings.server_id`, `dayz_settings.server_id`, `mod_preset_workshop_mod.workshop_mod_id`, `mod_preset_reforger_mod.reforger_mod_id`
- **Note:** `reforger_scenarios.server_id` is partially covered by composite unique, skip standalone index.

### 2.2 Add unique constraints on settings `server_id` columns

- **Migration:** Create `add_settings_server_id_unique` migration (or combine with 2.1).
- **Tables:** `arma3_settings`, `reforger_settings`, `project_zomboid_settings`, `factorio_settings`, `dayz_settings` — add `unique('server_id')` on each.

### 2.3 Change `game_install_id` FK to `restrictOnDelete`

- **Migration:** Create `change_game_install_fk_to_restrict` migration.
- **Details:** Drop the existing FK, re-add with `restrictOnDelete()`. Also change `game_install_id` column to NOT NULL since it's required.
- **Side effect:** GameInstall deletion will now throw an error if any server references it. Update `GameInstallController::destroy()` to check for associated servers before deleting and return a user-friendly error.

### 2.4 Add unique constraints on port and query_port

- **Migration:** Create `add_port_unique_constraints` migration.
- **Columns:** `servers.port` (unique), `servers.query_port` (unique, where not null).
- **Note:** Cross-column uniqueness (port != any query_port) remains validation-only — can't be expressed as a simple constraint.

---

## Phase 3: Backend Code Quality (7 items)

### 3.1 Add helper methods to `ServerStatus` enum

- **File:** `app/Enums/ServerStatus.php`
- **Methods:**
    - `isActive(): bool` — true for Starting, Booting, DownloadingMods, Running, Stopping
    - `isStoppable(): bool` — true for Running, Booting, DownloadingMods
    - `isDeletable(): bool` — true for Stopped, Crashed
- **Then:** Refactor `ServerController`, `ServerBackupController`, and `HandleInertiaRequests` to use these helpers instead of inline `in_array` checks.

### 3.2 Add flash messages to HC add/remove

- **File:** `app/Http/Controllers/ServerController.php`
- **Fix:** Return `back()->with('success', 'Headless client added.')` and `back()->with('success', 'Headless client removed.')`.

### 3.3 Add audit logging to 5 SteamSettingsController methods

- **File:** `app/Http/Controllers/SteamSettingsController.php`
- **Methods:** `saveSettings()`, `verifyLogin()`, `verifyApiKey()`, `saveDiscordWebhook()`, `testDiscordWebhook()`
- **Fix:** Add `Log::info(auth_context() . " ...")` to each.

### 3.4 Reverse delete-then-cleanup ordering

- **Files:** `app/Http/Controllers/GameInstallController.php`, `app/Http/Controllers/WorkshopModController.php`
- **Fix:** Delete files/directories first, then delete the DB record. If file deletion fails, return an error instead of leaving orphaned data.

### 3.5 Create Form Request for RegisteredModController

- **File:** Create `app/Http/Requests/ReforgerMod/StoreRegisteredModRequest.php` (or a more generic name).
- **Fix:** Move inline `Validator::make()` logic into a dedicated Form Request that resolves the handler from the route parameter.

### 3.6 Fix `saveDiscordWebhook()` empty URL behavior

- **File:** `app/Http/Controllers/SteamSettingsController.php`
- **Fix:** If `discord_webhook_url` is empty/null, clear the stored value (set to null) and return `back()->with('success', 'Discord webhook removed.')`. Or make the validation require the field.

### 3.7 Remove implicit `'arma3'` default in WorkshopModController::store()

- **File:** `app/Http/Controllers/WorkshopModController.php`
- **Fix:** Remove the `?? 'arma3'` fallback. Make `game_type` required in `StoreWorkshopModRequest`.

---

## Phase 4: Tests (3 groups)

### 4.1 New test files for critical untested code

- **`tests/Feature/Jobs/SendDiscordWebhookJobTest.php`** — Test handle() calls DiscordWebhookService::send(), test retry behavior, test failure scenarios.
- **`tests/Feature/Services/InstallerResolverTest.php`** — Test SteamGameHandler resolves to SteamGameInstaller, DownloadsDirectly resolves to HttpGameInstaller, unknown handler type throws.

### 4.2 Missing edge case tests in existing files

- **ServerManagementTest:** start from Booting (rejected), stop from Stopping (rejected), restart from Starting/Stopping (rejected), destroy from Starting/Booting/Stopping (rejected), destroy from Crashed (allowed), port = query_port same value, description exceeding 1000 chars, launchCommand/serverLog/status require auth, stop/restart from DownloadingMods (allowed after fix).
- **ServerBackupManagementTest:** restore on Crashed server (allowed after fix), restore on Starting/Booting/Stopping (rejected).
- **MissionManagementTest:** destroy returns error when file not found.
- **ModPresetManagementTest:** import with duplicate preset name returns error.
- **WorkshopModManagementTest:** store with negative workshop_id, store with invalid game_type, sort validation (invalid sort_by column, invalid sort_direction).
- **GameInstallManagementTest:** store with invalid game_type, store with invalid branch.
- **Broadcast channel auth tests:** Verify authenticated users can subscribe, unauthenticated users cannot (new test file or add to BroadcastEventsTest).

### 4.3 Test cleanup

- **Delete:** `tests/Concerns/MocksSteamServices.php` (unused trait).
- **Bolster weak tests:** Add response status/session assertions to the ~7 tests in WorkshopModManagementTest and ModPresetManagementTest that only assert queue dispatch without checking the response.

---

## Phase 5: Final Verification

- Run `vendor/bin/pint --format agent`
- Run `vendor/bin/phpstan analyse --memory-limit=512M`
- Run `npx eslint resources/js --max-warnings=0`
- Run `npx tsc --noEmit`
- Run `php artisan test --compact`
- Run `npm run build`
