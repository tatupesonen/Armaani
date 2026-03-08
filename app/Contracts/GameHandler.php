<?php

namespace App\Contracts;

use App\Enums\GameType;
use App\Models\Server;

interface GameHandler
{
    public function gameType(): GameType;

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

    /**
     * String that appears in server log when the server is fully booted.
     * Return null to skip auto-detection (server stays in Booting until manually changed or timed out).
     */
    public function getBootDetectionString(): ?string;

    /**
     * String that appears in server log when the server begins downloading mods.
     * Return null if this game does not download mods at startup.
     */
    public function getModDownloadStartedString(): ?string;

    /**
     * String that appears in server log when the server finishes downloading mods.
     * Return null if this game does not download mods at startup.
     */
    public function getModDownloadFinishedString(): ?string;

    /**
     * String that appears in server log when the server has crashed.
     * Return null to skip auto-detection of crashes.
     */
    public function getCrashDetectionString(): ?string;

    // --- Mods & Assets ---

    /**
     * Create mod symlinks in the game install directory for the server's active preset.
     * No-op for games where the server handles its own mod downloads (e.g., Reforger).
     */
    public function symlinkMods(Server $server): void;

    /**
     * Create mission file symlinks in the game install directory.
     * No-op for games that handle missions differently.
     */
    public function symlinkMissions(Server $server): void;

    /**
     * Copy BiKey signature files from mod directories to the server's keys directory.
     * No-op for games that don't use BiKeys.
     */
    public function copyBiKeys(Server $server): void;

    // --- Headless Clients ---

    /**
     * Whether this game supports headless clients.
     */
    public function supportsHeadlessClients(): bool;

    /**
     * Build the launch command arguments for a headless client instance.
     * Return null if headless clients are not supported.
     *
     * @return array<int, string>|null The binary path as the first element, followed by arguments.
     */
    public function buildHeadlessClientCommand(Server $server, int $index): ?array;

    // --- Backups ---

    /**
     * Get the path to the file that should be backed up (e.g., .vars.Arma3Profile).
     * Return null if this game has no profile backup concept.
     */
    public function getBackupFilePath(Server $server): ?string;

    /**
     * Get the filename to use when downloading a backup.
     */
    public function getBackupDownloadFilename(Server $server): string;

    // --- Validation ---

    /**
     * Validation rules for game-specific server fields.
     * Merged with common server validation rules in the Livewire component.
     */
    public function serverValidationRules(): array;

    /**
     * Validation rules for game-specific settings (difficulty, network, reforger settings, etc.)
     */
    public function settingsValidationRules(): array;
}
