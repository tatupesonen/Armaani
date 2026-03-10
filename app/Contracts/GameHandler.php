<?php

namespace App\Contracts;

use App\Enums\GameType;
use App\Models\Server;

/**
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
    public function gameType(): GameType;

    // --- Game Metadata ---

    /**
     * Steam App ID for the dedicated server binary.
     */
    public function serverAppId(): int;

    /**
     * Steam Game ID (used for workshop mod downloads).
     */
    public function gameId(): int;

    /**
     * Default game port for new servers.
     */
    public function defaultPort(): int;

    /**
     * Default Steam query port for new servers.
     */
    public function defaultQueryPort(): int;

    /**
     * Available SteamCMD beta branches for this game.
     *
     * @return list<string>
     */
    public function branches(): array;

    /**
     * Whether this game uses Steam Workshop mods downloaded via SteamCMD.
     */
    public function supportsWorkshopMods(): bool;

    /**
     * Whether mod files need to be converted to lowercase (Linux requirement).
     */
    public function requiresLowercaseConversion(): bool;

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
     */
    public function serverValidationRules(?Server $server = null): array;

    /**
     * Validation rules for game-specific settings (difficulty, network, reforger settings, etc.)
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
     * Create default related settings models for a newly created server.
     * Called from ServerController::store().
     */
    public function createRelatedSettings(Server $server): void;

    /**
     * Update game-specific related settings from validated request data.
     * Called from ServerController::update().
     */
    public function updateRelatedSettings(Server $server, array $validated): void;
}
