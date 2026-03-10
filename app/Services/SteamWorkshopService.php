<?php

namespace App\Services;

use App\Enums\GameType;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class SteamWorkshopService
{
    /**
     * Fetch mod metadata from the Steam Web API.
     *
     * @return array{name: string|null, file_size: int|null, time_updated: int|null, game_type: GameType|null}|null
     */
    public function getModDetails(int $workshopId): ?array
    {
        return $this->getMultipleModDetails([$workshopId])[$workshopId] ?? null;
    }

    /**
     * Fetch metadata for multiple mods in a single Steam API call.
     *
     * @param  list<int>  $workshopIds
     * @return array<int, array{name: string|null, file_size: int|null, time_updated: int|null, game_type: GameType|null}>
     */
    public function getMultipleModDetails(array $workshopIds): array
    {
        if (empty($workshopIds)) {
            return [];
        }

        $formData = ['itemcount' => count($workshopIds)];

        foreach ($workshopIds as $index => $id) {
            $formData["publishedfileids[{$index}]"] = $id;
        }

        $response = Http::asForm()->post(
            'https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/',
            $formData,
        );

        if (! $response->successful()) {
            return [];
        }

        $results = [];
        $details = $response->json('response.publishedfiledetails', []);

        foreach ($details as $detail) {
            if (! $detail || ($detail['result'] ?? 0) !== 1) {
                continue;
            }

            $workshopId = (int) ($detail['publishedfileid'] ?? 0);

            if ($workshopId > 0) {
                $results[$workshopId] = [
                    'name' => $detail['title'] ?? null,
                    'file_size' => isset($detail['file_size']) ? (int) $detail['file_size'] : null,
                    'time_updated' => isset($detail['time_updated']) ? (int) $detail['time_updated'] : null,
                    'game_type' => isset($detail['consumer_appid'])
                        ? GameType::fromConsumerAppId((int) $detail['consumer_appid'])
                        : null,
                ];
            }
        }

        return $results;
    }

    /**
     * Validate that a Steam Web API key is accepted by the Steam Web API.
     *
     * Returns an array with 'valid' (bool) and 'error' (string|null).
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validateApiKey(string $apiKey): array
    {
        $response = Http::get('https://api.steampowered.com/ISteamWebAPIUtil/GetSupportedAPIList/v1/', [
            'key' => $apiKey,
        ]);

        if ($response->successful()) {
            return ['valid' => true, 'error' => null];
        }

        return [
            'valid' => false,
            'error' => 'HTTP '.$response->status(),
        ];
    }

    /**
     * Fetch and sync metadata from the Steam API for a single mod.
     * Always fetches to keep steam_updated_at current, but only overwrites
     * name/file_size if they were missing.
     */
    public function syncMetadata(WorkshopMod $mod): void
    {
        $details = $this->getModDetails($mod->workshop_id);

        if (! $details) {
            return;
        }

        $this->applyMetadata($mod, $details);
    }

    /**
     * Fetch and sync metadata from the Steam API for multiple mods.
     *
     * @param  Collection<int, WorkshopMod>  $mods
     */
    public function syncMetadataForMany(Collection $mods): void
    {
        $workshopIds = $mods->pluck('workshop_id')->all();
        $detailsMap = $this->getMultipleModDetails($workshopIds);

        foreach ($mods as $mod) {
            $details = $detailsMap[$mod->workshop_id] ?? null;

            if ($details) {
                $this->applyMetadata($mod, $details);
            }
        }
    }

    /**
     * Apply fetched Steam API metadata to a mod model.
     *
     * @param  array{name: string|null, file_size: int|null, time_updated: int|null, game_type: GameType|null}  $details
     */
    private function applyMetadata(WorkshopMod $mod, array $details): void
    {
        $updates = array_filter([
            'name' => $mod->name ?? $details['name'],
            'file_size' => $mod->file_size ?? $details['file_size'],
            'steam_updated_at' => isset($details['time_updated'])
                ? Carbon::createFromTimestamp($details['time_updated'])
                : null,
        ]);

        if (! empty($updates)) {
            $mod->update($updates);
        }
    }

    /**
     * Get the configured Steam API key, preferring DB over config.
     */
    public function getApiKey(): ?string
    {
        $account = SteamAccount::current();

        if ($account?->steam_api_key) {
            return $account->steam_api_key;
        }

        return config('arma.steam_api_key');
    }
}
