import { Head, router, useForm, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronDown,
    ChevronsUpDown,
    ChevronUp,
    Download,
    Plus,
    RefreshCw,
    Search,
    Terminal,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import Heading from '@/components/heading';
import LogViewer from '@/components/log-viewer';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { formatBytes, installStatusVariant } from '@/lib/utils';
import {
    index as modsIndex,
    store,
    retry,
    retryAllFailed,
    destroy,
    updateSelected,
    checkForUpdates,
    updateAllOutdated,
} from '@/routes/mods';
import {
    store as storeRegisteredMod,
    destroy as destroyRegisteredMod,
} from '@/routes/registered-mods';
import type {
    BreadcrumbItem,
    GameModSections,
    RegisteredMod,
    WorkshopMod,
} from '@/types';

type Filters = {
    search?: string;
    sort_by?: string;
    sort_direction?: string;
};

type InstalledStats = {
    count: number;
    total_size: number;
};

type Props = {
    mods: WorkshopMod[];
    registeredMods: Record<string, RegisteredMod[]>;
    modSections: GameModSections[];
    filters: Filters;
    installedStats: InstalledStats;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workshop Mods', href: modsIndex() },
];

function SortableHeader({
    label,
    field,
    currentSort,
    currentDirection,
}: {
    label: string;
    field: string;
    currentSort?: string;
    currentDirection?: string;
}) {
    function handleSort() {
        let nextSort: string | undefined;
        let nextDirection: string | undefined;

        if (currentSort !== field) {
            nextSort = field;
            nextDirection = 'asc';
        } else if (currentDirection === 'asc') {
            nextSort = field;
            nextDirection = 'desc';
        } else {
            nextSort = undefined;
            nextDirection = undefined;
        }

        router.get(
            modsIndex.url(),
            {
                sort_by: nextSort,
                sort_direction: nextDirection,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <button
            onClick={handleSort}
            className="flex items-center gap-1 transition-colors hover:text-zinc-900 dark:hover:text-zinc-100"
        >
            {label}
            {currentSort === field ? (
                currentDirection === 'asc' ? (
                    <ChevronUp className="size-3.5" />
                ) : (
                    <ChevronDown className="size-3.5" />
                )
            ) : (
                <ChevronsUpDown className="size-3.5 opacity-40" />
            )}
        </button>
    );
}

/**
 * Build the list of game types that support workshop mods,
 * derived from the modSections prop (handler-driven).
 */
function getWorkshopGameTypes(
    modSections: GameModSections[],
): { value: string; label: string }[] {
    return modSections
        .filter((gs) => gs.sections.some((s) => s.type === 'workshop'))
        .map((gs) => ({ value: gs.gameType, label: gs.gameLabel }));
}

/**
 * Build the list of registered mod tabs from all game handlers.
 * Each entry represents a game type that uses registered (non-workshop) mods.
 */
function getRegisteredModTabs(
    modSections: GameModSections[],
): { gameType: string; gameLabel: string; sectionLabel: string }[] {
    const tabs: {
        gameType: string;
        gameLabel: string;
        sectionLabel: string;
    }[] = [];
    for (const gs of modSections) {
        for (const section of gs.sections) {
            if (section.type === 'registered') {
                tabs.push({
                    gameType: gs.gameType,
                    gameLabel: gs.gameLabel,
                    sectionLabel: section.label,
                });
            }
        }
    }
    return tabs;
}

export default function ModsIndex({
    mods,
    registeredMods,
    modSections,
    filters,
    installedStats,
}: Props) {
    const [showAddModal, setShowAddModal] = useState(false);
    const [deletingModId, setDeletingModId] = useState<number | null>(null);
    const [deletingRegisteredMod, setDeletingRegisteredMod] = useState<{
        gameType: string;
        modId: number;
    } | null>(null);
    const [selectedMods, setSelectedMods] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [expandedModLogs, setExpandedModLogs] = useState<number[]>([]);

    const workshopGameTypes = getWorkshopGameTypes(modSections);
    const registeredModTabs = getRegisteredModTabs(modSections);

    const hasPending = mods.some(
        (m) =>
            m.installation_status === 'installing' ||
            m.installation_status === 'queued',
    );
    const failedCount = mods.filter(
        (m) => m.installation_status === 'failed',
    ).length;
    const outdatedCount = mods.filter((m) => m.is_outdated).length;

    const selectableMods = mods.filter(
        (m) =>
            m.installation_status !== 'installing' &&
            m.installation_status !== 'queued',
    );
    const isAllSelected =
        selectableMods.length > 0 &&
        selectableMods.every((m) => selectedMods.includes(m.id));

    // Poll when mods are downloading
    usePoll(5000, { only: ['mods'] }, { autoStart: hasPending });

    function toggleModLog(modId: number) {
        setExpandedModLogs((prev) =>
            prev.includes(modId)
                ? prev.filter((id) => id !== modId)
                : [...prev, modId],
        );
    }

    function toggleSelectAll() {
        if (isAllSelected) {
            setSelectedMods([]);
        } else {
            setSelectedMods(selectableMods.map((m) => m.id));
        }
    }

    const addForm = useForm({
        workshop_id: '',
        game_type: workshopGameTypes[0]?.value ?? '',
    });

    function submitAdd(e: React.FormEvent) {
        e.preventDefault();
        addForm.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setShowAddModal(false);
                addForm.reset();
            },
        });
    }

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(
            modsIndex.url(),
            { search: search || undefined },
            { preserveState: true },
        );
    }

    function handleDelete() {
        if (deletingModId === null) return;
        router.delete(destroy.url(deletingModId), {
            preserveScroll: true,
            onSuccess: () => setDeletingModId(null),
        });
    }

    function handleDeleteRegisteredMod() {
        if (!deletingRegisteredMod) return;
        router.delete(
            destroyRegisteredMod.url({
                gameType: deletingRegisteredMod.gameType,
                modId: deletingRegisteredMod.modId,
            }),
            {
                preserveScroll: true,
                onSuccess: () => setDeletingRegisteredMod(null),
            },
        );
    }

    function toggleSelectMod(modId: number) {
        setSelectedMods((prev) =>
            prev.includes(modId)
                ? prev.filter((id) => id !== modId)
                : [...prev, modId],
        );
    }

    function handleUpdateSelected() {
        router.post(
            updateSelected.url(),
            { mod_ids: selectedMods },
            {
                preserveScroll: true,
                onSuccess: () => setSelectedMods([]),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workshop Mods" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Workshop Mods"
                        description={
                            installedStats.count > 0
                                ? `Download and manage Steam Workshop mods. \u2014 ${installedStats.count} installed, ${formatBytes(installedStats.total_size)} total`
                                : 'Download and manage Steam Workshop mods.'
                        }
                    />
                    <Button onClick={() => setShowAddModal(true)}>
                        <Plus className="mr-2 size-4" />
                        Add Mod
                    </Button>
                </div>

                <Tabs defaultValue="workshop">
                    <TabsList>
                        <TabsTrigger value="workshop">
                            Workshop ({mods.length})
                        </TabsTrigger>
                        {registeredModTabs.map((tab) => (
                            <TabsTrigger
                                key={tab.gameType}
                                value={`registered-${tab.gameType}`}
                            >
                                {tab.sectionLabel} (
                                {registeredMods[tab.gameType]?.length ?? 0})
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="workshop" className="space-y-4">
                        {/* Actions Bar */}
                        <div className="flex flex-wrap items-center gap-2">
                            <form
                                onSubmit={handleSearch}
                                className="flex gap-2"
                            >
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search mods..."
                                    className="w-64"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                >
                                    <Search className="mr-2 size-4" />
                                    Search
                                </Button>
                            </form>

                            <div className="ml-auto flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            checkForUpdates.url(),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Search className="mr-2 size-4" />
                                    Check Updates
                                </Button>
                                {outdatedCount > 0 && (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                updateAllOutdated.url(),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <RefreshCw className="mr-2 size-4" />
                                        Update All Outdated ({outdatedCount})
                                    </Button>
                                )}
                                {selectedMods.length > 0 && (
                                    <Button
                                        size="sm"
                                        onClick={handleUpdateSelected}
                                    >
                                        <RefreshCw className="mr-2 size-4" />
                                        Update Selected ({selectedMods.length})
                                    </Button>
                                )}
                                {failedCount > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                retryAllFailed.url(),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <RefreshCw className="mr-2 size-4" />
                                        Retry All Failed ({failedCount})
                                    </Button>
                                )}
                            </div>
                        </div>

                        {mods.length === 0 ? (
                            <Alert>
                                <AlertDescription>
                                    No workshop mods found. Add mods using their
                                    Workshop ID.
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <div className="overflow-hidden rounded-lg border">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/50 text-left text-xs text-muted-foreground">
                                            <th className="w-10 px-4 py-3">
                                                <Checkbox
                                                    checked={isAllSelected}
                                                    onCheckedChange={
                                                        toggleSelectAll
                                                    }
                                                />
                                            </th>
                                            <th className="px-4 py-3">
                                                Workshop ID
                                            </th>
                                            <th className="px-4 py-3">Name</th>
                                            <th className="px-4 py-3">
                                                <SortableHeader
                                                    label="Size"
                                                    field="file_size"
                                                    currentSort={
                                                        filters.sort_by
                                                    }
                                                    currentDirection={
                                                        filters.sort_direction
                                                    }
                                                />
                                            </th>
                                            <th className="px-4 py-3">
                                                <SortableHeader
                                                    label="Status"
                                                    field="installation_status"
                                                    currentSort={
                                                        filters.sort_by
                                                    }
                                                    currentDirection={
                                                        filters.sort_direction
                                                    }
                                                />
                                            </th>
                                            <th className="px-4 py-3">
                                                <SortableHeader
                                                    label="Workshop Updated"
                                                    field="steam_updated_at"
                                                    currentSort={
                                                        filters.sort_by
                                                    }
                                                    currentDirection={
                                                        filters.sort_direction
                                                    }
                                                />
                                            </th>
                                            <th className="px-4 py-3">
                                                <SortableHeader
                                                    label="Installed"
                                                    field="installed_at"
                                                    currentSort={
                                                        filters.sort_by
                                                    }
                                                    currentDirection={
                                                        filters.sort_direction
                                                    }
                                                />
                                            </th>
                                            <th className="px-4 py-3">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {mods.map((mod) => (
                                            <ModRow
                                                key={mod.id}
                                                mod={mod}
                                                isSelected={selectedMods.includes(
                                                    mod.id,
                                                )}
                                                onToggleSelect={() =>
                                                    toggleSelectMod(mod.id)
                                                }
                                                onDelete={() =>
                                                    setDeletingModId(mod.id)
                                                }
                                                isLogExpanded={expandedModLogs.includes(
                                                    mod.id,
                                                )}
                                                onToggleLog={() =>
                                                    toggleModLog(mod.id)
                                                }
                                            />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </TabsContent>

                    {/* Dynamic registered mod tabs */}
                    {registeredModTabs.map((tab) => (
                        <RegisteredModTab
                            key={tab.gameType}
                            gameType={tab.gameType}
                            sectionLabel={tab.sectionLabel}
                            mods={registeredMods[tab.gameType] ?? []}
                            onDelete={(modId) =>
                                setDeletingRegisteredMod({
                                    gameType: tab.gameType,
                                    modId,
                                })
                            }
                        />
                    ))}
                </Tabs>
            </div>

            {/* Add Mod Modal */}
            <Dialog open={showAddModal} onOpenChange={setShowAddModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add Workshop Mod</DialogTitle>
                        <DialogDescription>
                            Enter the Steam Workshop ID to download a mod.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitAdd} className="space-y-4">
                        <div className="space-y-2">
                            <Label>Workshop ID</Label>
                            <Input
                                type="number"
                                value={addForm.data.workshop_id}
                                onChange={(e) =>
                                    addForm.setData(
                                        'workshop_id',
                                        e.target.value,
                                    )
                                }
                                placeholder="e.g. 450814997"
                                required
                            />
                            {addForm.errors.workshop_id && (
                                <p className="text-sm text-destructive">
                                    {addForm.errors.workshop_id}
                                </p>
                            )}
                        </div>
                        {workshopGameTypes.length > 1 && (
                            <div className="space-y-2">
                                <Label>Game</Label>
                                <Select
                                    value={addForm.data.game_type}
                                    onValueChange={(v) =>
                                        addForm.setData('game_type', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {workshopGameTypes.map((gt) => (
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
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowAddModal(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={addForm.processing}>
                                <Download className="mr-2 size-4" />
                                Add & Download
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Workshop Mod Confirmation */}
            <ConfirmDeleteDialog
                open={deletingModId !== null}
                onOpenChange={(open) => !open && setDeletingModId(null)}
                onConfirm={handleDelete}
                title="Delete Mod"
                description="This will remove the mod files and detach it from all presets."
            />

            {/* Delete Registered Mod Confirmation */}
            <ConfirmDeleteDialog
                open={deletingRegisteredMod !== null}
                onOpenChange={(open) => !open && setDeletingRegisteredMod(null)}
                onConfirm={handleDeleteRegisteredMod}
                title="Delete Mod"
                description="This will remove the mod and detach it from all presets."
            />
        </AppLayout>
    );
}

/**
 * Generic registered mod tab — renders an add form + table for any game type
 * that declares a 'registered' mod section in its handler.
 */
function RegisteredModTab({
    gameType,
    sectionLabel,
    mods,
    onDelete,
}: {
    gameType: string;
    sectionLabel: string;
    mods: RegisteredMod[];
    onDelete: (modId: number) => void;
}) {
    const addForm = useForm({
        mod_id: '',
        name: '',
    });

    function submitAdd(e: React.FormEvent) {
        e.preventDefault();
        addForm.post(storeRegisteredMod.url(gameType), {
            preserveScroll: true,
            onSuccess: () => addForm.reset(),
        });
    }

    return (
        <TabsContent value={`registered-${gameType}`} className="space-y-4">
            {/* Add Form */}
            <form
                onSubmit={submitAdd}
                className="flex max-w-2xl items-end gap-4"
            >
                <div className="flex-1 space-y-1">
                    <Label>Mod ID</Label>
                    <Input
                        value={addForm.data.mod_id}
                        onChange={(e) =>
                            addForm.setData('mod_id', e.target.value)
                        }
                        placeholder="e.g. 591AF5BDA9F7CE8B"
                        required
                    />
                    {addForm.errors.mod_id && (
                        <p className="text-sm text-destructive">
                            {addForm.errors.mod_id}
                        </p>
                    )}
                </div>
                <div className="flex-1 space-y-1">
                    <Label>Name</Label>
                    <Input
                        value={addForm.data.name}
                        onChange={(e) =>
                            addForm.setData('name', e.target.value)
                        }
                        placeholder="e.g. My Mod"
                        required
                    />
                    {addForm.errors.name && (
                        <p className="text-sm text-destructive">
                            {addForm.errors.name}
                        </p>
                    )}
                </div>
                <Button type="submit" disabled={addForm.processing}>
                    <Plus className="mr-2 size-4" />
                    Add Mod
                </Button>
            </form>

            {mods.length === 0 ? (
                <Alert>
                    <AlertDescription>
                        No {sectionLabel.toLowerCase()} registered. Add them
                        using the form above.
                    </AlertDescription>
                </Alert>
            ) : (
                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-left text-xs text-muted-foreground">
                                <th className="px-4 py-3">Mod ID</th>
                                <th className="px-4 py-3">Name</th>
                                <th className="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {mods.map((mod) => (
                                <tr
                                    key={mod.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {mod.mod_id}
                                    </td>
                                    <td className="px-4 py-3">{mod.name}</td>
                                    <td className="px-4 py-3">
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => onDelete(mod.id)}
                                        >
                                            <Trash2 className="mr-2 size-4" />
                                            Delete
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </TabsContent>
    );
}

function ModRow({
    mod,
    isSelected,
    onToggleSelect,
    onDelete,
    isLogExpanded,
    onToggleLog,
}: {
    mod: WorkshopMod;
    isSelected: boolean;
    onToggleSelect: () => void;
    onDelete: () => void;
    isLogExpanded: boolean;
    onToggleLog: () => void;
}) {
    const isActive =
        mod.installation_status === 'installing' ||
        mod.installation_status === 'queued';

    return (
        <>
            <tr className="border-b last:border-0">
                <td className="px-4 py-3">
                    <Checkbox
                        checked={isSelected}
                        onCheckedChange={onToggleSelect}
                        disabled={isActive}
                    />
                </td>
                <td className="px-4 py-3 font-mono text-xs">
                    {mod.workshop_id}
                </td>
                <td className="px-4 py-3">
                    <span className="font-medium">
                        {mod.name || `Mod #${mod.workshop_id}`}
                    </span>
                </td>
                <td className="px-4 py-3 text-muted-foreground">
                    {mod.file_size ? formatBytes(mod.file_size) : '\u2014'}
                </td>
                <td className="px-4 py-3">
                    <div className="flex items-center gap-1.5">
                        {isActive ? (
                            <div className="flex items-center gap-2">
                                <Badge
                                    variant={installStatusVariant(
                                        mod.installation_status,
                                    )}
                                >
                                    {mod.installation_status}
                                </Badge>
                                <Progress
                                    value={mod.progress_pct}
                                    className="h-1.5 w-20"
                                />
                                <span className="text-xs text-muted-foreground">
                                    {mod.progress_pct}%
                                </span>
                            </div>
                        ) : (
                            <>
                                <Badge
                                    variant={installStatusVariant(
                                        mod.installation_status,
                                    )}
                                >
                                    {mod.installation_status}
                                </Badge>
                                {mod.is_outdated && (
                                    <Badge
                                        variant="outline"
                                        className="border-amber-500/50 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-950/50 dark:text-amber-300"
                                    >
                                        <AlertTriangle className="mr-1 size-3" />
                                        Update available
                                    </Badge>
                                )}
                            </>
                        )}
                    </div>
                </td>
                <td className="px-4 py-3 text-xs text-muted-foreground">
                    {mod.steam_updated_at
                        ? new Date(mod.steam_updated_at).toLocaleDateString()
                        : '\u2014'}
                </td>
                <td className="px-4 py-3 text-xs text-muted-foreground">
                    {mod.installed_at
                        ? new Date(mod.installed_at).toLocaleDateString()
                        : '\u2014'}
                </td>
                <td className="px-4 py-3">
                    <div className="flex items-center gap-1">
                        {isActive && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={onToggleLog}
                                title="Toggle download log"
                            >
                                <Terminal className="size-4" />
                            </Button>
                        )}
                        {mod.installation_status === 'failed' && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    router.post(
                                        retry.url(mod.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                title="Retry download"
                            >
                                <RefreshCw className="size-4" />
                            </Button>
                        )}
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={onDelete}
                            disabled={isActive}
                            title="Delete mod"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                </td>
            </tr>
            {isLogExpanded && isActive && (
                <tr>
                    <td colSpan={8} className="bg-muted/30 p-3">
                        <LogViewer
                            channel={`mod-download.${mod.id}`}
                            event="ModDownloadOutput"
                            trackProgress
                            maxHeight="max-h-40"
                            label="Download Output"
                        />
                    </td>
                </tr>
            )}
        </>
    );
}
