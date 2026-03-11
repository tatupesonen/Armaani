export const statusGradients = [
    {
        status: 'starting',
        color: 'from-amber-400/20 to-zinc-300/5 dark:from-amber-500/15 dark:to-zinc-600/5',
        shimmer: 'motion-safe:animate-shimmer',
    },
    {
        status: 'booting',
        color: 'from-blue-400/20 to-zinc-300/5 dark:from-blue-500/15 dark:to-zinc-600/5',
        shimmer: 'motion-safe:animate-shimmer',
    },
    {
        status: 'downloading_mods',
        color: 'from-purple-400/20 to-zinc-300/5 dark:from-purple-500/15 dark:to-zinc-600/5',
        shimmer: 'motion-safe:animate-shimmer',
    },
    {
        status: 'running',
        color: 'from-emerald-400/20 to-zinc-300/5 dark:from-emerald-500/15 dark:to-zinc-600/5',
        shimmer: null,
    },
    {
        status: 'stopping',
        color: 'from-red-400/20 to-zinc-300/5 dark:from-red-500/15 dark:to-zinc-600/5',
        shimmer: 'motion-safe:animate-shimmer-fast',
    },
    {
        status: 'crashed',
        color: 'from-red-500/25 to-zinc-300/5 dark:from-red-600/20 dark:to-zinc-600/5',
        shimmer: null,
    },
] as const;

/**
 * Status gradients as a Record<string, string> — used by toast-manager
 * where only the color (no shimmer) is needed.
 */
export const statusGradientColors: Record<string, string> = Object.fromEntries(
    statusGradients.map(({ status, color }) => [status, color]),
);
