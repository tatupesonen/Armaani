<?php

namespace App\Http\Controllers;

use App\Enums\InstallationStatus;
use App\Enums\ServerStatus;
use App\Models\GameInstall;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\SteamAccount;
use App\Models\WorkshopMod;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
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
            'diskUsage' => $this->getDiskUsage(),
            'memoryUsage' => $this->getMemoryUsage(),
            'cpuInfo' => $this->getCpuInfo(),
        ]);
    }

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

    private function getGameInstallStats(): array
    {
        $installs = GameInstall::query()->get(['id', 'installation_status', 'disk_size_bytes']);

        return [
            'total' => $installs->count(),
            'installed' => $installs->where('installation_status', InstallationStatus::Installed)->count(),
            'disk_size' => $installs->sum('disk_size_bytes'),
        ];
    }

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

    private function getDiskUsage(): array
    {
        $path = storage_path();
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }

    private function getMemoryUsage(): array
    {
        if (! is_readable('/proc/meminfo')) {
            return ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0];
        }

        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatch);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $availableMatch);

        $total = ((int) ($totalMatch[1] ?? 0)) * 1024;
        $available = ((int) ($availableMatch[1] ?? 0)) * 1024;
        $used = $total - $available;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $available,
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }

    private function getCpuInfo(): array
    {
        $loadAvg = sys_getloadavg();
        $cores = 1;

        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            $cores = max(1, substr_count($cpuinfo, 'processor'));
        }

        return [
            'load_1' => round($loadAvg[0], 2),
            'load_5' => round($loadAvg[1], 2),
            'load_15' => round($loadAvg[2], 2),
            'cores' => $cores,
            'percent' => round(($loadAvg[0] / $cores) * 100, 1),
        ];
    }
}
