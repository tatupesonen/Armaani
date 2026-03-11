<?php

namespace App\Http\Controllers;

use App\Enums\InstallationStatus;
use App\Enums\ServerStatus;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use App\Services\SystemResourceService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private SystemResourceService $systemResources,
    ) {}

    public function index(): Response
    {
        return Inertia::render('dashboard', [
            'serverStats' => $this->getServerStats(),
            'gameInstallStats' => $this->getGameInstallStats(),
            'modStats' => $this->getModStats(),
            'presetCount' => ModPreset::query()->count(),
            'missionCount' => $this->getMissionCount(),
            'queueStats' => [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
            'steamConfigured' => SteamAccount::query()->exists(),
            'servers' => Server::query()->with('gameInstall')->orderBy('name')->get(),
            'diskUsage' => $this->systemResources->getDiskUsage(),
            'memoryUsage' => $this->systemResources->getMemoryUsage(),
            'cpuInfo' => $this->systemResources->getCpuInfo(),
        ]);
    }

    /**
     * @return array{total: int, running: int, stopped: int}
     */
    private function getServerStats(): array
    {
        $servers = Server::query()->get(['id', 'status', 'max_players']);
        $statusCounts = $servers->groupBy(fn (Server $s) => $s->status->value)->map->count();

        return [
            'total' => $servers->count(),
            'running' => ($statusCounts[ServerStatus::Running->value] ?? 0)
                + ($statusCounts[ServerStatus::Booting->value] ?? 0),
            'stopped' => $statusCounts[ServerStatus::Stopped->value] ?? 0,
        ];
    }

    /**
     * @return array{total: int, installed: int, disk_size: int|float}
     */
    private function getGameInstallStats(): array
    {
        $installs = GameInstall::query()->get(['id', 'installation_status', 'disk_size_bytes']);

        return [
            'total' => $installs->count(),
            'installed' => $installs->where('installation_status', InstallationStatus::Installed)->count(),
            'disk_size' => $installs->sum('disk_size_bytes'),
        ];
    }

    /**
     * @return array{total: int, installed: int, total_size: int}
     */
    private function getModStats(): array
    {
        /** @var object{total: int|string, installed: int|string, total_size: int|string} $result */
        $result = WorkshopMod::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN installation_status = ? THEN 1 ELSE 0 END) as installed', [InstallationStatus::Installed->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN installation_status = ? THEN file_size ELSE 0 END), 0) as total_size', [InstallationStatus::Installed->value])
            ->first();

        return [
            'total' => (int) $result->total,
            'installed' => (int) $result->installed,
            'total_size' => (int) $result->total_size,
        ];
    }

    private function getMissionCount(): int
    {
        $path = config('arma.missions_base_path');
        if (! is_dir($path)) {
            return 0;
        }

        return count(glob($path.'/*.pbo') ?: []);
    }
}
