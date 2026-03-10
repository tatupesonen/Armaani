# Settings Schema Migration Plan

## Context

Non-universal base fields (query_port, admin_password, auto_restart, etc.) are hardcoded in the server edit panel and create dialog. The `settingsSchema` feature was WIP but never committed. The `GameSettingsRenderer` component, `ReforgerScenarioPicker`, `SettingsSchemaTest`, and TS types exist as untracked files. The tracked file modifications (interface, handlers, controller) were lost and need to be recreated.

## What's Done

1. `GameHandler.php` interface — PHPDoc types added, `settingsSchema()` method declared

## Remaining Steps

### Backend (PHP)

2. **Arma3Handler** — implement `settingsSchema()` returning 4 sections:
    - Server Rules (`showOnCreate: true`, `createLabel: 'Arma 3 Options'`): verify_signatures, allowed_file_patching, battle_eye, von_enabled, persistent, auto_restart
    - Difficulty Settings (`collapsible: true`, `source: 'difficulty_settings'`, `layout: 'columns'`, 3 groups): 23 fields (10 toggles, 10 segmented, 2 number + separator)
    - Network Settings (`collapsible: true`, `source: 'network_settings'`, `layout: 'rows'`, 2 presets — "Reset to Default" and "Apply High Performance"): 11 fields (8 number, 3 text/decimal)
    - Advanced (`advanced: true`): additional_params (`source: 'server'`), additional_server_options

3. **ReforgerHandler** — implement `settingsSchema()` returning 1 section:
    - Reforger Settings (`source: 'reforger_settings'`): scenario_id (`type: 'custom'`, `component: 'scenario-picker'`), third_person_view_enabled, battle_eye (`source: 'server'`), cross_platform, max_fps

4. **DayZHandler** — implement `settingsSchema()` returning empty array `[]`

5. **ServerController::index()** — add `'settingsSchema' => $handler->settingsSchema()` to the `gameTypes` Inertia prop

### Frontend (TypeScript/React)

6. **server-edit-panel.tsx** — rewrite to use `GameSettingsRenderer` + `buildEditDataFromSchema`:
    - Keep hardcoded: name, port (with auto-sync to query_port if exists in data), max_players, description, game_install_id, active_preset_id, backup section
    - Schema-driven via renderer: all game-specific sections + connection fields (query_port, password, admin_password)
    - Advanced accordion: render `getAdvancedFields()` from schema only, remove hardcoded additional_params/additional_server_options
    - Remove imports of `DifficultySettingsSection`, `NetworkSettingsSection`
    - Replace `EditData` type with `Record<string, unknown>`

7. **create-server-dialog.tsx** — rewrite to use `GameSettingsRenderer` + `getSchemaDefaults`:
    - Keep hardcoded: game_type, name, port (with auto-sync), max_players, game_install_id, active_preset_id
    - Schema-driven: `showOnCreate` sections (Server Rules for Arma 3)
    - Remove hardcoded Arma 3 toggles, additional_params, additional_server_options
    - Use `getSchemaDefaults(gt.settingsSchema, true)` for form defaults

8. **servers/index.tsx** — pass `settingsSchema` from `gameTypes` to `ServerCard`

9. **server-card.tsx** — accept and pass `settingsSchema` to `ServerEditPanel`

### Testing & Cleanup

10. Update `SettingsSchemaTest.php` — adjust section counts if Connection fields are added as new sections (currently tests expect Arma3: 4, Reforger: 1, DayZ: 0). Add assertions for connection/advanced fields with `source: 'server'`.

11. Run Pint: `vendor/bin/pint --dirty --format agent`

12. Run affected tests: `php artisan test --compact tests/Feature/GameHandlers/SettingsSchemaTest.php`

13. Run full test suite: `php artisan test --compact`

## Design Decisions (Confirmed)

- `additional_params`: removed from create dialog, lives in advanced section only
- Port auto-sync: kept — port onChange checks if `query_port` exists in form data and sets `port + 1`
- `description`: stays hardcoded (universal field), edit-only (not on create dialog)
- Connection fields (query_port, password, admin_password): schema-driven with `source: 'server'`, in a section with `showOnCreate: true`
- `auto_restart`: only in Arma 3 Server Rules section (only game with crash detection strings)

## Key Files

### Surviving WIP (untracked, not lost)

- `resources/js/components/servers/game-settings-renderer.tsx` (698 lines)
- `resources/js/components/servers/reforger-scenario-picker.tsx` (163 lines)
- `tests/Feature/GameHandlers/SettingsSchemaTest.php` (495 lines)
- `app/Contracts/SupportsReforgerMods.php` (9 lines)
- `resources/js/types/game.ts` — TS schema types already committed

### Files to modify

- `app/Contracts/GameHandler.php` (done)
- `app/GameHandlers/Arma3Handler.php`
- `app/GameHandlers/ReforgerHandler.php`
- `app/GameHandlers/DayZHandler.php`
- `app/Http/Controllers/ServerController.php`
- `resources/js/components/servers/server-edit-panel.tsx`
- `resources/js/components/servers/create-server-dialog.tsx`
- `resources/js/pages/servers/index.tsx`
- `resources/js/components/servers/server-card.tsx`

### Files to eventually remove (replaced by schema renderer)

- `resources/js/components/servers/difficulty-settings-section.tsx`
- `resources/js/components/servers/network-settings-section.tsx`
