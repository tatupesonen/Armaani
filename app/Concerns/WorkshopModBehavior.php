<?php

namespace App\Concerns;

use App\Models\ModPreset;

/**
 * Default implementations for the SupportsWorkshopMods interface.
 *
 * Provides standard workshop mod preset behavior: a single "Workshop Mods"
 * section, sync via the mods() pivot, and count from the same relationship.
 */
trait WorkshopModBehavior
{
    public function requiresLowercaseConversion(): bool
    {
        return false;
    }

    /**
     * @return list<array{type: 'workshop'|'registered', label: string, relationship: string, formField: string}>
     */
    public function modSections(): array
    {
        return [
            [
                'type' => 'workshop',
                'label' => 'Workshop Mods',
                'relationship' => 'mods',
                'formField' => 'mod_ids',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function syncPresetMods(ModPreset $preset, array $validated): void
    {
        $preset->mods()->sync($validated['mod_ids'] ?? []);
    }

    public function getPresetModCount(ModPreset $preset): int
    {
        return $preset->mods()->count();
    }
}
