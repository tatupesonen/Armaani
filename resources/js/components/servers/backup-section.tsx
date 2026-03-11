import { router } from '@inertiajs/react';
import {
    ChevronDown,
    Download,
    RotateCcw,
    Save,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import ConfirmDeleteDialog from '@/components/confirm-delete-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBytes } from '@/lib/utils';
import {
    store as createBackup,
    destroy as deleteBackup,
    download,
    restore,
    upload,
} from '@/routes/servers/backups';
import type { Server, ServerBackup } from '@/types';

type BackupSectionProps = {
    server: Server;
};

export default function BackupSection({ server }: BackupSectionProps) {
    const [open, setOpen] = useState(false);
    const [restoringBackupId, setRestoringBackupId] = useState<number | null>(
        null,
    );
    const [backupName, setBackupName] = useState('');
    const [uploadName, setUploadName] = useState('');
    const [uploading, setUploading] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const backups = server.backups ?? [];
    const isStopped = server.status === 'stopped';

    function handleCreateBackup() {
        router.post(
            createBackup.url(server.id),
            { backup_name: backupName || undefined },
            {
                preserveScroll: true,
                onSuccess: () => setBackupName(''),
            },
        );
    }

    function handleUpload() {
        const file = fileInputRef.current?.files?.[0];
        if (!file) return;

        setUploading(true);

        const formData = new FormData();
        formData.append('backup_file', file);
        if (uploadName) {
            formData.append('backup_name', uploadName);
        }

        router.post(upload.url(server.id), formData as never, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setUploadName('');
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
            onFinish: () => setUploading(false),
        });
    }

    function handleRestore() {
        if (restoringBackupId === null) return;
        router.post(
            restore.url(restoringBackupId),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setRestoringBackupId(null),
            },
        );
    }

    function handleDelete(backupId: number) {
        router.delete(deleteBackup.url(backupId), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <div className="rounded-lg border">
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className="flex w-full items-center gap-3 px-4 py-3 text-left"
                >
                    <div className="flex-1">
                        <span className="text-base font-semibold">
                            State Backups
                        </span>
                        <span className="block text-xs text-muted-foreground">
                            Back up and restore the server profile state file.
                        </span>
                    </div>
                    <ChevronDown
                        className={`size-4 text-muted-foreground transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
                    />
                </button>

                {open && (
                    <div className="border-t px-4 py-4">
                        {/* Create backup */}
                        <div className="mb-4 flex items-end gap-3">
                            <div className="max-w-xs flex-1 space-y-1.5">
                                <Label className="text-xs">Backup Name</Label>
                                <Input
                                    value={backupName}
                                    onChange={(e) =>
                                        setBackupName(e.target.value)
                                    }
                                    placeholder="Optional label"
                                    className="h-8"
                                />
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                onClick={handleCreateBackup}
                                disabled={!isStopped}
                            >
                                <Save className="mr-2 size-3" />
                                Backup Current State
                            </Button>
                        </div>

                        {/* Upload backup */}
                        <div className="mb-6 flex items-end gap-3">
                            <div className="max-w-xs flex-1 space-y-1.5">
                                <Label className="text-xs">Upload Name</Label>
                                <Input
                                    value={uploadName}
                                    onChange={(e) =>
                                        setUploadName(e.target.value)
                                    }
                                    placeholder="Optional label"
                                    className="h-8"
                                />
                            </div>
                            <div className="max-w-xs flex-1 space-y-1.5">
                                <Label className="text-xs">Backup File</Label>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    className="block w-full cursor-pointer text-sm text-muted-foreground file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-semibold hover:file:bg-muted/80"
                                />
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                onClick={handleUpload}
                                disabled={uploading}
                            >
                                <Upload className="mr-2 size-3" />
                                Upload
                            </Button>
                        </div>

                        {/* Backup list */}
                        {backups.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No backups yet.
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-lg border">
                                <table className="min-w-full divide-y">
                                    <thead className="bg-muted/50">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground uppercase">
                                                Name
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground uppercase">
                                                Date
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground uppercase">
                                                Size
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground uppercase">
                                                Type
                                            </th>
                                            <th className="px-3 py-2 text-right text-xs font-medium text-muted-foreground uppercase">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {backups.map((backup: ServerBackup) => (
                                            <tr key={backup.id}>
                                                <td className="px-3 py-2 text-sm">
                                                    {backup.name || 'Unnamed'}
                                                </td>
                                                <td className="px-3 py-2 text-sm text-muted-foreground">
                                                    {new Date(
                                                        backup.created_at,
                                                    ).toLocaleDateString(
                                                        'en-US',
                                                        {
                                                            month: 'short',
                                                            day: 'numeric',
                                                            year: 'numeric',
                                                            hour: 'numeric',
                                                            minute: '2-digit',
                                                        },
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 font-mono text-sm text-muted-foreground">
                                                    {backup.file_size
                                                        ? formatBytes(
                                                              backup.file_size,
                                                          )
                                                        : '-'}
                                                </td>
                                                <td className="px-3 py-2 text-sm">
                                                    <Badge
                                                        variant={
                                                            backup.is_automatic
                                                                ? 'secondary'
                                                                : 'default'
                                                        }
                                                    >
                                                        {backup.is_automatic
                                                            ? 'Auto'
                                                            : 'Manual'}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-7"
                                                            asChild
                                                        >
                                                            <a
                                                                href={download.url(
                                                                    backup.id,
                                                                )}
                                                            >
                                                                <Download className="size-3" />
                                                            </a>
                                                        </Button>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-7"
                                                            onClick={() =>
                                                                setRestoringBackupId(
                                                                    backup.id,
                                                                )
                                                            }
                                                            disabled={
                                                                !isStopped
                                                            }
                                                        >
                                                            <RotateCcw className="size-3" />
                                                        </Button>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-7"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    backup.id,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-3" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Restore confirmation */}
            <ConfirmDeleteDialog
                open={restoringBackupId !== null}
                onOpenChange={(o) => !o && setRestoringBackupId(null)}
                onConfirm={handleRestore}
                title="Restore Backup"
                description="Are you sure you want to restore this backup? This will overwrite the server's current profile state. The server must be stopped."
                confirmLabel="Restore"
                confirmVariant="default"
            />
        </>
    );
}
