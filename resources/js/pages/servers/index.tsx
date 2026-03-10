import { Head, router, usePoll } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import CreateServerDialog from '@/components/servers/create-server-dialog';
import ServerCard from '@/components/servers/server-card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import echo from '@/echo';
import AppLayout from '@/layouts/app-layout';
import { index as serversIndex, destroy } from '@/routes/servers';
import type {
    BreadcrumbItem,
    GameInstall,
    ModPreset,
    Server,
    ServerGameTypeOption,
} from '@/types';

type Props = {
    servers: Server[];
    presets: ModPreset[];
    gameInstalls: GameInstall[];
    gameTypes: ServerGameTypeOption[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Servers', href: serversIndex() },
];

export default function ServersIndex({
    servers,
    presets,
    gameInstalls,
    gameTypes,
}: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [deletingServerId, setDeletingServerId] = useState<number | null>(
        null,
    );

    const hasActiveServers = servers.some((s) => s.status !== 'stopped');

    // Poll for status updates when servers are active
    usePoll(5000, { only: ['servers'] }, { autoStart: hasActiveServers });

    // Listen for instant server status changes via WebSocket
    useEffect(() => {
        const channel = echo
            .private('servers')
            .listen('ServerStatusChanged', () => {
                router.reload({ only: ['servers'] });
            });

        return () => {
            channel.stopListening('ServerStatusChanged');
            echo.leave('private-servers');
        };
    }, []);

    function handleDelete() {
        if (deletingServerId === null) return;
        router.delete(destroy.url(deletingServerId), {
            onSuccess: () => setDeletingServerId(null),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Servers" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Servers"
                        description="Manage your server instances."
                    />
                    <Button onClick={() => setShowCreateModal(true)}>
                        <Plus className="mr-2 size-4" />
                        New Server
                    </Button>
                </div>

                {servers.length === 0 ? (
                    <Alert>
                        <AlertDescription>
                            No servers configured yet. Create your first server
                            to get started.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <div className="space-y-4">
                        {servers.map((server) => {
                            const gt = gameTypes.find(
                                (g) => g.value === server.game_type,
                            );

                            return (
                                <ServerCard
                                    key={server.id}
                                    server={server}
                                    presets={presets}
                                    gameInstalls={gameInstalls}
                                    settingsSchema={gt?.settingsSchema ?? []}
                                    supportsHeadlessClients={
                                        gt?.supportsHeadlessClients ?? false
                                    }
                                    onDelete={(id) => setDeletingServerId(id)}
                                />
                            );
                        })}
                    </div>
                )}
            </div>

            <CreateServerDialog
                open={showCreateModal}
                onOpenChange={setShowCreateModal}
                gameTypes={gameTypes}
                gameInstalls={gameInstalls}
                presets={presets}
            />

            {/* Delete Confirmation */}
            <AlertDialog
                open={deletingServerId !== null}
                onOpenChange={(open) => !open && setDeletingServerId(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Server</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            variant="destructive"
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
