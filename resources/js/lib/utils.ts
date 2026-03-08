import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { InstallationStatus, ServerStatus } from '@/types/game';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatBytes(bytes: number | null | undefined): string {
    if (!bytes || bytes === 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));

    return `${(bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
}

export function gameTypeLabel(gameType: string): string {
    const labels: Record<string, string> = {
        arma3: 'Arma 3',
        reforger: 'Arma Reforger',
        dayz: 'DayZ',
    };

    return labels[gameType] ?? gameType;
}

export function serverStatusVariant(
    status: ServerStatus,
): 'success' | 'warning' | 'info' | 'danger' | 'secondary' {
    const map: Record<
        ServerStatus,
        'success' | 'warning' | 'info' | 'danger' | 'secondary'
    > = {
        running: 'success',
        starting: 'warning',
        booting: 'info',
        downloading_mods: 'warning',
        stopping: 'danger',
        stopped: 'secondary',
        crashed: 'danger',
    };

    return map[status] ?? 'secondary';
}

export function installStatusVariant(
    status: InstallationStatus,
): 'success' | 'warning' | 'danger' | 'secondary' {
    const map: Record<
        InstallationStatus,
        'success' | 'warning' | 'danger' | 'secondary'
    > = {
        installed: 'success',
        installing: 'warning',
        queued: 'secondary',
        failed: 'danger',
    };

    return map[status] ?? 'secondary';
}

export function usageBarColor(percent: number): string {
    if (percent >= 90) {
        return 'bg-red-500';
    }

    if (percent >= 70) {
        return 'bg-amber-500';
    }

    return 'bg-emerald-500';
}
