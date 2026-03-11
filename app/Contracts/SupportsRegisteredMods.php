<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implemented by game handlers that support manually-registered (GUID-based) mods,
 * as opposed to Steam Workshop mods downloaded via SteamCMD.
 *
 * Examples: Arma Reforger uses mod GUIDs registered by the user.
 */
interface SupportsRegisteredMods
{
    /**
     * The Eloquent model class for this game's registered mods.
     *
     * @return class-string<Model>
     */
    public function registeredModModelClass(): string;

    /**
     * The relationship name on ModPreset for this game's registered mods.
     * This will be dynamically registered via resolveRelationUsing.
     */
    public function registeredModRelationName(): string;

    /**
     * The pivot table name for the ModPreset <-> RegisteredMod relationship.
     */
    public function registeredModPivotTable(): string;

    /**
     * Create a new registered mod from validated request data.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeRegisteredMod(array $data): Model;

    /**
     * Delete a registered mod and detach it from all presets.
     */
    public function destroyRegisteredMod(Model $mod): void;

    /**
     * Validation rules for storing a new registered mod.
     *
     * @return array<string, mixed>
     */
    public function registeredModValidationRules(): array;
}
