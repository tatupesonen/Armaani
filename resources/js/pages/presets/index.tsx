import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import Heading from '@/components/heading';
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
import AppLayout from '@/layouts/app-layout';
import {
    index as presetsIndex,
    create,
    edit,
    destroy,
    importMethod,
} from '@/routes/presets';
import type { BreadcrumbItem, ModPreset } from '@/types';

type Props = {
    presets: ModPreset[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mod Presets', href: presetsIndex() },
];

export default function PresetsIndex({ presets }: Props) {
    const { gameTypeLabels } = usePage().props;
    const [deletingPresetId, setDeletingPresetId] = useState<number | null>(
        null,
    );
    const [showImportModal, setShowImportModal] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    function handleDelete() {
        if (deletingPresetId === null) return;
        router.delete(destroy.url(deletingPresetId), {
            preserveScroll: true,
            onSuccess: () => setDeletingPresetId(null),
        });
    }

    function handleImport(e: React.FormEvent) {
        e.preventDefault();
        const file = fileInputRef.current?.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('import_file', file);

        router.post(importMethod.url(), formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setShowImportModal(false);
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mod Presets" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Mod Presets"
                        description="Organize mods into reusable presets for your servers."
                    />
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setShowImportModal(true)}
                        >
                            <FileUp className="mr-2 size-4" />
                            Import
                        </Button>
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="mr-2 size-4" />
                                New Preset
                            </Link>
                        </Button>
                    </div>
                </div>

                {presets.length === 0 ? (
                    <Alert>
                        <AlertDescription>
                            No mod presets yet. Create one or import from an
                            Arma 3 Launcher HTML export.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <div className="space-y-2">
                        {presets.map((preset) => (
                            <div
                                key={preset.id}
                                className="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {preset.name}
                                        </span>
                                        <Badge variant="outline">
                                            {gameTypeLabels[preset.game_type] ??
                                                preset.game_type}
                                        </Badge>
                                    </div>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        {(preset.total_mod_count ?? 0) > 0
                                            ? `${preset.total_mod_count} mod(s)`
                                            : 'No mods'}
                                    </p>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button size="sm" variant="ghost" asChild>
                                        <Link href={edit.url(preset.id)}>
                                            <Pencil className="size-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            setDeletingPresetId(preset.id)
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Import Modal */}
            <Dialog open={showImportModal} onOpenChange={setShowImportModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Import Preset</DialogTitle>
                        <DialogDescription>
                            Upload an Arma 3 Launcher HTML preset export file.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleImport} className="space-y-4">
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".html,.htm"
                            className="block w-full text-sm"
                            required
                        />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowImportModal(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">
                                <FileUp className="mr-2 size-4" />
                                Import
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <ConfirmDeleteDialog
                open={deletingPresetId !== null}
                onOpenChange={(open) => !open && setDeletingPresetId(null)}
                onConfirm={handleDelete}
                title="Delete Preset"
                description="Are you sure? This will not delete the mods themselves."
            />
        </AppLayout>
    );
}
