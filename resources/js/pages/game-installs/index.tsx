import { Head, router, useForm, usePage, usePoll } from '@inertiajs/react';
import { Download, Plus, RotateCw, Terminal, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import Heading from '@/components/heading';
import LogViewer from '@/components/log-viewer';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatBytes, installStatusVariant } from '@/lib/utils';
import { index as gameInstallsIndex } from '@/routes/game-installs';
import { store, reinstall, destroy } from '@/routes/game-installs';
import type { BreadcrumbItem, GameInstall, GameTypeInfo } from '@/types';

type Props = {
    installs: GameInstall[];
    gameTypes: GameTypeInfo[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Game Installs', href: gameInstallsIndex() },
];

export default function GameInstallsIndex({ installs, gameTypes }: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [deletingInstallId, setDeletingInstallId] = useState<number | null>(
        null,
    );
    const [reinstallingInstallId, setReinstallingInstallId] = useState<
        number | null
    >(null);

    const shouldPoll = installs.some(
        (i) =>
            i.installation_status === 'installing' ||
            i.installation_status === 'queued',
    );

    usePoll(5000, { only: ['installs'] }, { autoStart: shouldPoll });

    const createForm = useForm({
        game_type: (gameTypes[0]?.value ?? '') as string,
        name: (gameTypes[0]?.defaultName ?? '') as string,
        branch: 'public',
    });

    const selectedGameType = gameTypes.find(
        (gt) => gt.value === createForm.data.game_type,
    );

    function openCreateModal() {
        const defaultType = gameTypes[0];
        createForm.setData({
            game_type: defaultType.value,
            name: defaultType.defaultName,
            branch: 'public',
        });
        createForm.clearErrors();
        setShowCreateModal(true);
    }

    function onGameTypeChange(value: string) {
        const gt = gameTypes.find((g) => g.value === value);
        createForm.setData({
            game_type: value,
            name: gt?.defaultName ?? '',
            branch: 'public',
        });
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => setShowCreateModal(false),
        });
    }

    function handleReinstall() {
        if (reinstallingInstallId === null) {
            return;
        }

        router.post(
            reinstall.url(reinstallingInstallId),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setReinstallingInstallId(null),
            },
        );
    }

    function handleDelete() {
        if (deletingInstallId === null) {
            return;
        }

        router.delete(destroy.url(deletingInstallId), {
            preserveScroll: true,
            onSuccess: () => setDeletingInstallId(null),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Game Installs" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Game Installs"
                        description="Manage dedicated server installations."
                    />
                    <Button onClick={openCreateModal}>
                        <Plus className="mr-2 size-4" />
                        New Install
                    </Button>
                </div>

                {installs.length === 0 ? (
                    <Alert>
                        <AlertDescription>
                            No game installs yet. Create one to download
                            dedicated server files via SteamCMD.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <div className="space-y-4">
                        {installs.map((install) => (
                            <InstallCard
                                key={install.id}
                                install={install}
                                onReinstall={(id) =>
                                    setReinstallingInstallId(id)
                                }
                                onDelete={(id) => setDeletingInstallId(id)}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Create Modal */}
            <Dialog open={showCreateModal} onOpenChange={setShowCreateModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>New Game Install</DialogTitle>
                        <DialogDescription>
                            Downloads a dedicated server via SteamCMD. Only the
                            public branch is officially supported.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitCreate} className="space-y-4">
                        <div className="space-y-2">
                            <Label>Game</Label>
                            <Select
                                value={createForm.data.game_type}
                                onValueChange={onGameTypeChange}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {gameTypes.map((gt) => (
                                        <SelectItem
                                            key={gt.value}
                                            value={gt.value}
                                        >
                                            {gt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label>Name</Label>
                            <Input
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                                placeholder="Server Name"
                                required
                            />
                            {createForm.errors.name && (
                                <p className="text-sm text-destructive">
                                    {createForm.errors.name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>Branch</Label>
                            <Select
                                value={createForm.data.branch}
                                onValueChange={(v) =>
                                    createForm.setData('branch', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {selectedGameType?.branches.map((b) => (
                                        <SelectItem key={b} value={b}>
                                            {b}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {createForm.errors.branch && (
                                <p className="text-sm text-destructive">
                                    {createForm.errors.branch}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowCreateModal(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                            >
                                <Download className="mr-2 size-4" />
                                Create & Install
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Reinstall Confirmation */}
            <ConfirmDeleteDialog
                open={reinstallingInstallId !== null}
                onOpenChange={(open) => !open && setReinstallingInstallId(null)}
                onConfirm={handleReinstall}
                title="Re-install Game"
                description="Re-install/update this game install? The queue will start a fresh SteamCMD run."
                confirmLabel="Re-install"
                confirmVariant="default"
            />

            {/* Delete Confirmation */}
            <ConfirmDeleteDialog
                open={deletingInstallId !== null}
                onOpenChange={(open) => !open && setDeletingInstallId(null)}
                onConfirm={handleDelete}
                title="Delete Game Install"
                description="Are you sure you want to delete this game install? This will also permanently remove all server files from disk."
            />
        </AppLayout>
    );
}

function InstallCard({
    install,
    onReinstall,
    onDelete,
}: {
    install: GameInstall;
    onReinstall: (id: number) => void;
    onDelete: (id: number) => void;
}) {
    const { gameTypeLabels } = usePage().props;
    const isActive =
        install.installation_status === 'installing' ||
        install.installation_status === 'queued';

    const [showLogs, setShowLogs] = useState(isActive);

    return (
        <div className="overflow-hidden rounded-lg border">
            <div className="flex items-center justify-between p-4">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <h3 className="text-lg font-semibold">
                            {install.name}
                        </h3>
                        <Badge variant="outline">
                            {gameTypeLabels[install.game_type] ??
                                install.game_type}
                        </Badge>
                        <Badge
                            variant={installStatusVariant(
                                install.installation_status,
                            )}
                        >
                            {install.installation_status}
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Branch:{' '}
                        <span className="font-mono">{install.branch}</span>
                        {install.build_id && (
                            <>
                                {' '}
                                &middot; Build:{' '}
                                <span className="font-mono">
                                    {install.build_id}
                                </span>
                            </>
                        )}
                        {install.disk_size_bytes &&
                            install.disk_size_bytes > 0 && (
                                <>
                                    {' '}
                                    &middot;{' '}
                                    {formatBytes(install.disk_size_bytes)}
                                </>
                            )}
                        {install.installed_at && (
                            <>
                                {' '}
                                &middot; Last installed:{' '}
                                {new Date(
                                    install.installed_at,
                                ).toLocaleDateString()}
                            </>
                        )}
                    </p>
                    {install.installation_path && (
                        <p className="mt-0.5 font-mono text-xs text-zinc-400 dark:text-zinc-500">
                            {install.installation_path}
                        </p>
                    )}

                    {isActive && (
                        <div className="mt-2 w-64">
                            <div className="mb-1 flex items-center justify-between">
                                <span className="text-xs text-muted-foreground">
                                    {install.installation_status === 'queued'
                                        ? 'Queued...'
                                        : 'Downloading...'}
                                </span>
                                <span className="text-xs font-medium">
                                    {install.progress_pct}%
                                </span>
                            </div>
                            <Progress
                                value={install.progress_pct}
                                className="h-1.5"
                            />
                        </div>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setShowLogs((prev) => !prev)}
                    >
                        <Terminal className="size-4" />
                    </Button>
                    {install.installation_status !== 'installing' && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => onReinstall(install.id)}
                        >
                            <RotateCw className="mr-2 size-4" />
                            {install.installation_status === 'installed'
                                ? 'Update'
                                : 'Install'}
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => onDelete(install.id)}
                        disabled={isActive}
                    >
                        <Trash2 className="mr-2 size-4" />
                        Delete
                    </Button>
                </div>
            </div>

            {showLogs && (
                <div className="border-t p-4">
                    <LogViewer
                        channel={`game-install.${install.id}`}
                        event="GameInstallOutput"
                    />
                </div>
            )}
        </div>
    );
}
