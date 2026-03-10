import { router } from '@inertiajs/react';
import {
    Code,
    Loader2,
    Pause,
    Pencil,
    Play,
    RefreshCw,
    Terminal,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import LogViewer from '@/components/log-viewer';
import HeadlessClientControls from '@/components/servers/headless-client-controls';
import ServerEditPanel from '@/components/servers/server-edit-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    gameTypeLabel,
    serverStatusLabel,
    serverStatusVariant,
} from '@/lib/utils';
import {
    start,
    stop,
    restart,
    launchCommand,
    log as serverLog,
} from '@/routes/servers';
import type { GameInstall, ModPreset, Server, SettingsSection } from '@/types';

type ServerCardProps = {
    server: Server;
    presets: ModPreset[];
    gameInstalls: GameInstall[];
    settingsSchema: SettingsSection[];
    supportsHeadlessClients: boolean;
    onDelete: (id: number) => void;
};

const statusGradients = [
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

export default function ServerCard({
    server,
    presets,
    gameInstalls,
    settingsSchema,
    supportsHeadlessClients,
    onDelete,
}: ServerCardProps) {
    const [showLogs, setShowLogs] = useState(
        ['booting', 'downloading_mods', 'running'].includes(server.status),
    );
    const [showCommand, setShowCommand] = useState(false);
    const [commandText, setCommandText] = useState<string | null>(null);
    const [editing, setEditing] = useState(false);

    const showHC = supportsHeadlessClients && server.status === 'running';

    const loadInitialLogLines = useCallback(async (): Promise<string[]> => {
        const res = await fetch(serverLog.url(server.id));
        const data = await res.json();
        return data.lines ?? [];
    }, [server.id]);

    function toggleCommand() {
        if (!showCommand && commandText === null) {
            fetch(launchCommand.url(server.id))
                .then((res) => res.json())
                .then((data) => setCommandText(data.command));
        }
        setShowCommand((prev) => !prev);
    }

    return (
        <div className="overflow-hidden rounded-lg border">
            {/* Header */}
            <div className="relative flex items-center justify-between p-4">
                {statusGradients.map(({ status, color, shimmer }) => (
                    <div
                        key={status}
                        className={`absolute inset-0 overflow-hidden bg-gradient-to-r [mask-image:linear-gradient(to_right,black,black_20%,transparent_45%)] transition-opacity duration-700 ${color} ${server.status === status ? 'opacity-100' : 'opacity-0'}`}
                    >
                        {shimmer && (
                            <div
                                className={`absolute inset-y-0 left-0 w-1/3 bg-gradient-to-r from-transparent via-white/12 to-transparent dark:via-white/6 ${shimmer}`}
                            />
                        )}
                    </div>
                ))}

                <div className="relative min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <h3 className="text-lg font-semibold">{server.name}</h3>
                        <Badge variant="outline">
                            {gameTypeLabel(server.game_type)}
                        </Badge>
                        <Badge variant={serverStatusVariant(server.status)}>
                            {serverStatusLabel(server.status)}
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Port: {server.port} &middot; Players:{' '}
                        {server.max_players}
                        {server.game_install && (
                            <>
                                {' '}
                                &middot; Install: {
                                    server.game_install.name
                                }{' '}
                                <span className="font-mono text-xs">
                                    ({server.game_install.branch})
                                </span>
                            </>
                        )}
                        {server.active_preset && (
                            <> &middot; Preset: {server.active_preset.name}</>
                        )}
                    </p>
                    {server.profiles_path && (
                        <p className="mt-0.5 font-mono text-xs text-zinc-400 dark:text-zinc-500">
                            {server.profiles_path}
                        </p>
                    )}
                </div>

                <div className="relative flex items-center gap-2">
                    {server.status === 'starting' ||
                    server.status === 'stopping' ? (
                        <Button size="sm" disabled>
                            <Loader2 className="mr-2 size-4 animate-spin" />
                            {serverStatusLabel(server.status)}
                        </Button>
                    ) : server.status === 'booting' ||
                      server.status === 'downloading_mods' ? (
                        <>
                            <Button size="sm" disabled>
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                {serverStatusLabel(server.status)}
                            </Button>
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => router.post(stop.url(server.id))}
                            >
                                <Pause className="mr-2 size-4" />
                                Stop
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.post(restart.url(server.id))
                                }
                            >
                                <RefreshCw className="mr-2 size-4" />
                                Restart
                            </Button>
                        </>
                    ) : server.status === 'running' ? (
                        <>
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => router.post(stop.url(server.id))}
                            >
                                <Pause className="mr-2 size-4" />
                                Stop
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.post(restart.url(server.id))
                                }
                            >
                                <RefreshCw className="mr-2 size-4" />
                                Restart
                            </Button>
                        </>
                    ) : (
                        <Button
                            size="sm"
                            onClick={() => router.post(start.url(server.id))}
                        >
                            <Play className="mr-2 size-4" />
                            Start
                        </Button>
                    )}

                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setShowLogs((prev) => !prev)}
                    >
                        <Terminal className="size-4" />
                    </Button>

                    <Button size="sm" variant="ghost" onClick={toggleCommand}>
                        <Code className="size-4" />
                    </Button>

                    {editing ? (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setEditing(false)}
                        >
                            <X className="size-4" />
                        </Button>
                    ) : (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setEditing(true)}
                        >
                            <Pencil className="size-4" />
                        </Button>
                    )}

                    <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => onDelete(server.id)}
                        disabled={
                            server.status !== 'stopped' &&
                            server.status !== 'crashed'
                        }
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </div>

            {/* Headless client controls */}
            {showHC && <HeadlessClientControls server={server} />}

            {/* Log viewer */}
            {showLogs && (
                <div className="border-t p-4">
                    <LogViewer
                        channel={`server-log.${server.id}`}
                        event="ServerLogOutput"
                        maxHeight="max-h-[32rem]"
                        loadInitialLines={loadInitialLogLines}
                        label="Server Log"
                    />
                </div>
            )}

            {/* Launch command */}
            {showCommand && (
                <div className="border-t p-4">
                    <p className="mb-2 text-xs font-medium text-muted-foreground">
                        Launch Command
                    </p>
                    <div className="rounded bg-zinc-900 p-3 font-mono text-xs break-all whitespace-pre-wrap text-zinc-100 select-all">
                        {commandText ?? 'Loading...'}
                    </div>
                </div>
            )}

            {/* Edit panel */}
            {editing && (
                <ServerEditPanel
                    server={server}
                    presets={presets}
                    gameInstalls={gameInstalls}
                    settingsSchema={settingsSchema}
                    onCancel={() => setEditing(false)}
                />
            )}
        </div>
    );
}
