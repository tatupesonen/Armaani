<?php

namespace App\Services;

use App\Enums\InstallationStatus;
use App\Jobs\BatchDownloadModsJob;
use App\Jobs\DownloadModJob;
use App\Models\ModPreset;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PresetImportService
{
    /**
     * Parse an Arma 3 Launcher HTML preset file and return workshop mod IDs.
     *
     * @return Collection<int, int>
     */
    public function parseHtmlPreset(string $htmlContent): Collection
    {
        if (empty($htmlContent)) {
            throw new InvalidArgumentException('HTML preset content is empty.');
        }

        preg_match_all(
            '/https?:\/\/steamcommunity\.com\/sharedfiles\/filedetails\/\?id=(\d+)/',
            $htmlContent,
            $matches
        );

        if (empty($matches[1])) {
            throw new InvalidArgumentException('No workshop mod IDs found in the HTML preset file.');
        }

        return collect($matches[1])->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * Extract the preset name from the HTML content.
     */
    public function parsePresetName(string $htmlContent): ?string
    {
        if (preg_match('/<meta\s+name=["\']arma:presetName["\']\s+content=["\']([^"\']+)["\']/i', $htmlContent, $match)) {
            return $match[1];
        }

        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $htmlContent, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * Import an HTML preset: create/find mods, create the preset, and queue downloads.
     * Downloads are batched into groups based on the configured mod_download_batch_size.
     * Mod names and file sizes are fetched in bulk from the Steam API before queuing.
     * Already-installed mods are not re-queued.
     */
    public function importFromHtml(string $htmlContent, ?string $presetName = null): ModPreset
    {
        $workshopIds = $this->parseHtmlPreset($htmlContent);
        $name = $presetName ?? $this->parsePresetName($htmlContent) ?? 'Imported Preset '.now()->format('Y-m-d H:i');

        $metadataMap = $this->fetchBulkMetadata($workshopIds->all());

        $preset = ModPreset::query()->create(['name' => $name]);
        $modIds = [];
        $modsToDownload = collect();

        foreach ($workshopIds as $workshopId) {
            $metadata = $metadataMap[$workshopId] ?? [];

            $mod = WorkshopMod::query()->firstOrCreate(
                ['workshop_id' => $workshopId],
                [
                    'name' => $metadata['name'] ?? null,
                    'file_size' => $metadata['file_size'] ?? null,
                    'installation_status' => InstallationStatus::Queued,
                ]
            );

            if (! $mod->name && isset($metadata['name'])) {
                $mod->update(array_filter([
                    'name' => $metadata['name'],
                    'file_size' => $mod->file_size ?? ($metadata['file_size'] ?? null),
                ]));
            }

            $modIds[] = $mod->id;

            if ($mod->installation_status !== InstallationStatus::Installed) {
                $mod->update(['installation_status' => InstallationStatus::Queued]);
                $modsToDownload->push($mod);
            }
        }

        $preset->mods()->sync($modIds);

        $this->dispatchBatchedDownloads($modsToDownload);

        return $preset;
    }

    /**
     * Fetch metadata for all workshop IDs in bulk from the Steam API.
     *
     * @param  list<int>  $workshopIds
     * @return array<int, array{name: string|null, file_size: int|null}>
     */
    protected function fetchBulkMetadata(array $workshopIds): array
    {
        return app(SteamWorkshopService::class)->getMultipleModDetails($workshopIds);
    }

    /**
     * Dispatch download jobs for the given mods, batching them according to the configured batch size.
     * Single-mod batches use DownloadModJob; multi-mod batches use BatchDownloadModsJob.
     *
     * @param  Collection<int, WorkshopMod>  $mods
     */
    public function dispatchBatchedDownloads(Collection $mods): void
    {
        if ($mods->isEmpty()) {
            return;
        }

        $batchSize = SteamAccount::current()?->mod_download_batch_size ?? 5;

        foreach ($mods->chunk($batchSize) as $batch) {
            if ($batch->count() === 1) {
                DownloadModJob::dispatch($batch->first());
            } else {
                BatchDownloadModsJob::dispatch($batch);
            }
        }
    }
}
