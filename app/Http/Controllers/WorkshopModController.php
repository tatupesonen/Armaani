<?php

namespace App\Http\Controllers;

use App\Enums\GameType;
use App\Enums\InstallationStatus;
use App\Http\Requests\WorkshopMod\StoreWorkshopModRequest;
use App\Http\Requests\WorkshopMod\UpdateSelectedModsRequest;
use App\Jobs\BatchDownloadModsJob;
use App\Jobs\DownloadModJob;
use App\Models\ReforgerMod;
use App\Models\WorkshopMod;
use App\Services\SteamWorkshopService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class WorkshopModController extends Controller
{
    public function index(Request $request): Response
    {
        $query = WorkshopMod::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('workshop_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sort_by')) {
            $query->orderBy($request->input('sort_by'), $request->input('sort_direction', 'asc'));
        } else {
            $query->orderBy('name');
        }

        $mods = $query->get()->each(function (WorkshopMod $mod): void {
            $mod->setAttribute('is_outdated', $mod->isOutdated());
        });

        /** @var object{count: int|string, total_size: int|string} $installedStats */
        $installedStats = WorkshopMod::query()
            ->where('installation_status', InstallationStatus::Installed)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size')
            ->first();

        return Inertia::render('mods/index', [
            'mods' => $mods,
            'reforgerMods' => ReforgerMod::query()->orderBy('name')->get(),
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
            'installedStats' => [
                'count' => (int) $installedStats->count,
                'total_size' => (int) $installedStats->total_size,
            ],
        ]);
    }

    public function store(StoreWorkshopModRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $gameType = GameType::from($validated['game_type'] ?? 'arma3');

        $mod = WorkshopMod::query()->firstOrCreate(
            [
                'workshop_id' => $validated['workshop_id'],
                'game_type' => $gameType,
            ],
            [
                'installation_status' => InstallationStatus::Queued,
            ],
        );

        if ($mod->wasRecentlyCreated || $mod->installation_status === InstallationStatus::Failed) {
            $mod->update(['installation_status' => InstallationStatus::Queued, 'progress_pct' => 0]);
            DownloadModJob::dispatch($mod);
        }

        Log::info(auth_context()." added mod: {$mod->workshop_id}");

        return back()->with('success', "Mod '{$mod->workshop_id}' queued for download.");
    }

    public function retry(WorkshopMod $workshopMod): RedirectResponse
    {
        $workshopMod->update([
            'installation_status' => InstallationStatus::Queued,
            'progress_pct' => 0,
        ]);

        DownloadModJob::dispatch($workshopMod);

        Log::info(auth_context()." retried mod: {$workshopMod->workshop_id}");

        return back()->with('success', 'Mod download retried.');
    }

    public function retryAllFailed(): RedirectResponse
    {
        $failedMods = WorkshopMod::query()
            ->where('installation_status', InstallationStatus::Failed)
            ->get();

        if ($failedMods->isEmpty()) {
            return back()->with('info', 'No failed mods to retry.');
        }

        $this->queueModsForDownload($failedMods);

        return back()->with('success', "{$failedMods->count()} failed mods queued for retry.");
    }

    public function destroy(WorkshopMod $workshopMod): RedirectResponse
    {
        $workshopMod->presets()->detach();

        $path = $workshopMod->getInstallationPath();

        Log::info(auth_context()." deleted mod: {$workshopMod->name} ({$workshopMod->workshop_id})");

        $workshopMod->delete();

        if (is_dir($path)) {
            File::deleteDirectory($path);
        }

        return back()->with('success', 'Mod deleted.');
    }

    public function updateSelected(UpdateSelectedModsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $mods = WorkshopMod::query()
            ->whereIn('id', $validated['mod_ids'])
            ->whereNotIn('installation_status', [InstallationStatus::Queued, InstallationStatus::Installing])
            ->get();

        if ($mods->isEmpty()) {
            return back()->with('info', 'No mods available for update.');
        }

        $this->queueModsForDownload($mods);

        return back()->with('success', "{$mods->count()} mods queued for update.");
    }

    public function checkForUpdates(SteamWorkshopService $workshop): RedirectResponse
    {
        $mods = WorkshopMod::query()
            ->where('installation_status', InstallationStatus::Installed)
            ->get();

        if ($mods->isEmpty()) {
            return back()->with('info', 'No installed mods to check.');
        }

        $workshopIds = $mods->pluck('workshop_id')->toArray();
        $details = $workshop->getMultipleModDetails($workshopIds);

        $outdatedCount = 0;
        foreach ($details as $workshopId => $detail) {
            $mod = $mods->firstWhere('workshop_id', $workshopId);
            if ($mod && isset($detail['time_updated'])) {
                $steamUpdatedAt = Carbon::createFromTimestamp($detail['time_updated']);
                $mod->update(['steam_updated_at' => $steamUpdatedAt]);
                if ($mod->isOutdated()) {
                    $outdatedCount++;
                }
            }
        }

        return back()->with('success', "Update check complete. {$outdatedCount} mod(s) have updates available.");
    }

    public function updateAllOutdated(): RedirectResponse
    {
        $mods = WorkshopMod::query()
            ->where('installation_status', InstallationStatus::Installed)
            ->get()
            ->filter(fn (WorkshopMod $mod) => $mod->isOutdated());

        if ($mods->isEmpty()) {
            return back()->with('info', 'No outdated mods found.');
        }

        $this->queueModsForDownload($mods);

        return back()->with('success', "{$mods->count()} outdated mods queued for update.");
    }

    /**
     * Mark mods as queued and dispatch batch download jobs.
     *
     * @param  Collection<int, WorkshopMod>  $mods
     */
    private function queueModsForDownload(Collection $mods): void
    {
        foreach ($mods as $mod) {
            $mod->update([
                'installation_status' => InstallationStatus::Queued,
                'progress_pct' => 0,
            ]);
        }

        BatchDownloadModsJob::dispatchInBatches($mods);
    }
}
