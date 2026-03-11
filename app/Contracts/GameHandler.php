<?php

namespace App\Contracts;

use App\Models\ModPreset;
use App\Models\Server;

/**
 * Core contract for game handlers in the manager pattern.
 *
 * Installation strategy is determined by which additional interface the handler
 * implements (SteamGameHandler → SteamGameInstaller, DownloadsDirectly → HttpGameInstaller),
 * resolved automatically by InstallerResolver.
 *
 * @phpstan-type SettingsFieldOption array{value: string, label: string}
 * @phpstan-type SettingsField array{
 *     key?: string,
 *     label?: string,
 *     type: 'toggle'|'number'|'text'|'textarea'|'segmented'|'separator'|'custom',
 *     default?: string|int|float|bool,
 *     description?: string,
 *     source?: string,
 *     halfWidth?: bool,
 *     options?: list<SettingsFieldOption>,
 *     min?: int|float,
 *     max?: int|float,
 *     step?: int|float,
 *     storeAsString?: bool,
 *     inputMode?: string,
 *     placeholder?: string,
 *     required?: bool,
 *     rows?: int,
 *     component?: string,
 * }
 * @phpstan-type SettingsPreset array{
 *     label: string,
 *     variant: 'ghost'|'default',
 *     icon: 'reset'|'zap',
 *     values: array<string, string|int|float|bool>,
 * }
 * @phpstan-type SettingsFieldGroup array{
 *     columns?: int,
 *     fields: list<SettingsField>,
 * }
 * @phpstan-type SettingsSection array{
 *     title?: string,
 *     description?: string,
 *     collapsible?: bool,
 *     showOnCreate?: bool,
 *     createLabel?: string,
 *     source?: string,
 *     layout?: 'columns'|'rows',
 *     advanced?: bool,
 *     presets?: list<SettingsPreset>,
 *     fields?: list<SettingsField>,
 *     groups?: list<SettingsFieldGroup>,
 * }
 */
interface GameHandler
{
    /**
     * Unique string identifier for this game type (e.g., 'arma3', 'reforger', 'dayz').
     * Used as the database value and driver key.
     */
    public function value(): string;

    /**
     * Human-readable display name (e.g., 'Arma 3', 'Arma Reforger', 'DayZ').
     */
    public function label(): string;

    // --- Game Metadata ---

    /**
     * Default game port for new servers.
     */
    public function defaultPort(): int;

    /**
     * Default Steam query port for new servers.
     */
    public function defaultQueryPort(): int;

    /**
     * Available installation branches for this game (e.g., 'public', 'stable', 'experimental').
     *
     * @return list<string>
     */
    public function branches(): array;

    // --- Server Process ---

    /**
     * Build the command arguments to start the game server.
     *
     * @return array<int, string> The binary path as the first element, followed by arguments.
     */
    public function buildLaunchCommand(Server $server): array;

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

    // --- Validation ---

    /**
     * Validation rules for game-specific server fields.
     * Merged with common server validation rules in the form request.
     *
     * The optional $server parameter is provided during updates so that
     * unique rules can ignore the current server (e.g., query_port uniqueness).
     * On store, $server is null.
     *
     * @return array<string, mixed>
     */
    public function serverValidationRules(?Server $server = null): array;

    /**
     * Validation rules for game-specific settings (difficulty, network, reforger settings, etc.)
     *
     * @return array<string, mixed>
     */
    public function settingsValidationRules(): array;

    // --- UI Schema ---

    /**
     * Define the UI schema for game-specific settings sections.
     *
     * Returns an array of sections, each containing fields or groups of fields
     * that the frontend renders dynamically. This allows adding new games without
     * writing new frontend code for settings panels.
     *
     * Supported field types:
     * - 'toggle'     — Switch component (boolean)
     * - 'number'     — Numeric input (set min/max/step as needed, storeAsString for decimal strings)
     * - 'text'       — Text input (set inputMode='decimal' for decimal text fields)
     * - 'textarea'   — Multi-line text input (set rows for height)
     * - 'segmented'  — ToggleGroup with predefined options (e.g., Never/Limited/Always)
     * - 'separator'  — Visual separator between fields (no key/label needed)
     * - 'custom'     — Delegates to a named React component via the 'component' property
     *
     * Section layout options:
     * - 'columns' — Groups rendered side-by-side as columns (e.g., difficulty settings)
     * - 'rows'    — Groups rendered as stacked rows, each with its own column count
     *
     * @return list<SettingsSection>
     */
    public function settingsSchema(): array;

    // --- Related Settings ---

    /**
     * The Eloquent model class for this game's server settings, or null if none.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>|null
     */
    public function settingsModelClass(): ?string;

    /**
     * The relationship name to register on Server via resolveRelationUsing.
     * Returns null if no settings model exists.
     */
    public function settingsRelationName(): ?string;

    /**
     * Create default related settings models for a newly created server.
     * Called from ServerController::store().
     */
    public function createRelatedSettings(Server $server): void;

    /**
     * Update game-specific related settings from validated request data.
     * Called from ServerController::update().
     *
     * @param  array<string, mixed>  $validated
     */
    public function updateRelatedSettings(Server $server, array $validated): void;

    // --- Mod Presets ---

    /**
     * Define the mod sections this game supports for the frontend UI.
     *
     * Each section describes a tab/panel of mods that can be assigned to a preset.
     * The frontend renders these dynamically.
     *
     * @return list<array{type: 'workshop'|'registered', label: string, relationship: string, formField: string}>
     */
    public function modSections(): array;

    /**
     * Sync a preset's mods from validated request data.
     * Each handler knows which relationships to sync.
     *
     * @param  array<string, mixed>  $validated
     */
    public function syncPresetMods(ModPreset $preset, array $validated): void;

    /**
     * Get the total mod count for a preset (across all mod types this game uses).
     */
    public function getPresetModCount(ModPreset $preset): int;
}
