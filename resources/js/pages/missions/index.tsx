import { Head, router } from '@inertiajs/react';
import type { AxiosProgressEvent } from 'axios';
import { Download, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { formatBytes } from '@/lib/utils';
import {
    index as missionsIndex,
    store,
    download,
    destroy,
} from '@/routes/missions';
import type { BreadcrumbItem, Mission } from '@/types';

type Props = {
    missions: Mission[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Missions', href: missionsIndex() },
];

export default function MissionsIndex({ missions }: Props) {
    const [deletingFilename, setDeletingFilename] = useState<string | null>(
        null,
    );
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState<AxiosProgressEvent | null>(null);

    function handleUpload(files: FileList | null) {
        if (!files || files.length === 0) return;

        router.post(
            store.url(),
            { missions: Array.from(files) },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onProgress: (p) => {
                    if (p) setProgress(p);
                },
                onFinish: () => {
                    setUploading(false);
                    setProgress(null);
                },
                onSuccess: () => {
                    if (fileInputRef.current) fileInputRef.current.value = '';
                },
            },
        );
    }

    function handleDelete() {
        if (deletingFilename === null) return;
        router.delete(destroy.url(deletingFilename), {
            preserveScroll: true,
            onSuccess: () => setDeletingFilename(null),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Missions" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Missions"
                        description="Upload and manage mission PBO files."
                    />
                    <div className="flex items-center gap-3">
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".pbo"
                            multiple
                            className="hidden"
                            onChange={(e) => handleUpload(e.target.files)}
                        />
                        <Button
                            onClick={() => fileInputRef.current?.click()}
                            disabled={uploading}
                        >
                            <Upload className="mr-2 size-4" />
                            {uploading ? 'Uploading...' : 'Upload Missions'}
                        </Button>
                        {progress && (
                            <div className="flex w-48 items-center gap-2">
                                <Progress
                                    value={
                                        progress.progress
                                            ? progress.progress * 100
                                            : 0
                                    }
                                    className="h-2"
                                />
                                <span className="text-xs font-medium text-muted-foreground">
                                    {progress.progress
                                        ? Math.round(progress.progress * 100)
                                        : 0}
                                    %
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                <Alert>
                    <AlertDescription>
                        Arma 3 missions are .pbo files placed in the server's
                        MPMissions folder. Reforger scenarios and DayZ maps are
                        configured differently.
                    </AlertDescription>
                </Alert>

                {missions.length === 0 ? (
                    <Alert>
                        <AlertDescription>
                            No mission files found. Upload .pbo files to get
                            started.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <div className="space-y-2">
                        {missions.map((mission) => (
                            <div
                                key={mission.name}
                                className="flex items-center justify-between rounded-lg border p-3"
                            >
                                <div>
                                    <span className="font-mono font-medium">
                                        {mission.name}
                                    </span>
                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                        {formatBytes(mission.size)} &middot;{' '}
                                        {new Date(
                                            mission.modified_at,
                                        ).toLocaleDateString()}
                                    </div>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button size="sm" variant="ghost" asChild>
                                        <a
                                            href={download.url(mission.name)}
                                            download
                                        >
                                            <Download className="size-4" />
                                        </a>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            setDeletingFilename(mission.name)
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

            <ConfirmDeleteDialog
                open={deletingFilename !== null}
                onOpenChange={(open) => !open && setDeletingFilename(null)}
                onConfirm={handleDelete}
                title="Delete Mission"
                description={`Are you sure you want to delete "${deletingFilename}"?`}
            />
        </AppLayout>
    );
}
