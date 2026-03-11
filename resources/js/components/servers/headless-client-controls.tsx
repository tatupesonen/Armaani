import { router } from '@inertiajs/react';
import { Minus, Plus } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { status as serverStatus } from '@/routes/servers';
import { add, remove } from '@/routes/servers/headless-client';
import type { Server } from '@/types';

type HeadlessClientControlsProps = {
    server: Server;
};

export default function HeadlessClientControls({
    server,
}: HeadlessClientControlsProps) {
    const [hcCount, setHcCount] = useState(0);

    const fetchCount = useCallback(() => {
        fetch(serverStatus.url(server.id))
            .then((res) => res.json())
            .then((data) => setHcCount(data.headlessClientCount ?? 0))
            .catch((error) => {
                console.error('Failed to fetch headless client count:', error);
            });
    }, [server.id]);

    useEffect(() => {
        fetchCount();
    }, [fetchCount]);

    function handleAdd() {
        router.post(
            add.url(server.id),
            {},
            {
                preserveScroll: true,
                onSuccess: fetchCount,
            },
        );
    }

    function handleRemove() {
        router.post(
            remove.url(server.id),
            {},
            {
                preserveScroll: true,
                onSuccess: fetchCount,
            },
        );
    }

    return (
        <div className="flex items-center gap-2 border-t px-4 py-3">
            <span className="text-sm font-medium">Headless Clients</span>
            <Button
                size="icon"
                variant="ghost"
                className="size-6"
                onClick={handleRemove}
                disabled={hcCount < 1}
            >
                <Minus className="size-3" />
            </Button>
            <Badge variant={hcCount > 0 ? 'default' : 'secondary'}>
                {hcCount}
            </Badge>
            <Button
                size="icon"
                variant="ghost"
                className="size-6"
                onClick={handleAdd}
                disabled={hcCount >= 10}
            >
                <Plus className="size-3" />
            </Button>
        </div>
    );
}
