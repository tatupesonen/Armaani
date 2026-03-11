<?php

namespace App\Services;

class SystemResourceService
{
    /**
     * @return array{total: int|float|false, used: int|float, free: int|float|false, percent: float|int}
     */
    public function getDiskUsage(): array
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

    /**
     * @return array{total: int, used: int, free: int, percent: float|int}
     */
    public function getMemoryUsage(): array
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

    /**
     * @return array{load_1: float, load_5: float, load_15: float, cores: int, percent: float}
     */
    public function getCpuInfo(): array
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
