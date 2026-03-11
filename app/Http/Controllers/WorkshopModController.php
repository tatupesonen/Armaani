<?php

namespace App\Http\Controllers;

use App\Contracts\GameHandler;
use App\Contracts\SupportsRegisteredMods;
use App\Enums\InstallationStatus;
use App\GameManager;
use App\Http\Requests\WorkshopMod\IndexWorkshopModRequest;
use App\Http\Requests\WorkshopMod\StoreWorkshopModRequest;
use App\Http\Requests\WorkshopMod\UpdateSelectedModsRequest;
use App\Jobs\BatchDownloadModsJob;
use App\Jobs\DownloadModJob;
use App\Models\WorkshopMod;
use App\Services\Steam\SteamWorkshopService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class WorkshopModController extends Controller
{
    public function __construct(
        private GameManager $gameManager,
    ) {}

    public function index(IndexWorkshopModRequest $request): Response
    {
        $validated = $request->validated();
        $query = WorkshopMod::query();

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('workshop_id', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['sort_by'])) {
            $query->orderBy($validated['sort_by'], $validated['sort_direction'] ?? 'asc');
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

        // Collect mod sections from all handlers for the frontend tabs
        $allHandlers = $this->gameManager->allHandlers();
        $modSections = collect($allHandlers)->map(fn (GameHandler $handler) => [
            'gameType' => $handler->value(),
            'gameLabel' => $handler->label(),
            'sections' => $handler->modSections(),
        ])->values()->all();

        // Gather all registered mods keyed by game type
        $registeredMods = [];
        foreach ($allHandlers as $handler) {
            if ($handler instanceof SupportsRegisteredMods) {
                $modelClass = $handler->registeredModModelClass();
                $registeredMods[$handler->value()] = $modelClass::query()->orderBy('name')->get();
            }
        }

        return Inertia::render('mods/index', [
            'mods' => $mods,
            'registeredMods' => $registeredMods,
            'modSections' => $modSections,
            'filters' => array_filter($validated, fn ($v) => $v !== null),
            'installedStats' => [
                'count' => (int) $installedStats->count,
                'total_size' => (int) $installedStats->total_size,
            ],
        ]);
    }

    public function store(StoreWorkshopModRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $mod = WorkshopMod::query()->firstOrCreate(
            [
                'workshop_id' => $validated['workshop_id'],
                'game_type' => $validated['game_type'] ?? 'arma3',
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
