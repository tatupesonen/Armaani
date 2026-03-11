import { router } from '@inertiajs/react';
import { useState } from 'react';
import '@/components/servers/custom-components';
import GameSettingsRenderer, {
    getSchemaDefaults,
} from '@/components/servers/game-settings-renderer';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/servers';
import type { GameInstall, ModPreset, ServerGameTypeOption } from '@/types';

type CreateServerDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    gameTypes: ServerGameTypeOption[];
    gameInstalls: GameInstall[];
    presets: ModPreset[];
};

function buildFormDefaults(
    gt: ServerGameTypeOption,
    gameInstalls: GameInstall[],
): Record<string, unknown> {
    const defaultInstall = gameInstalls.find((gi) => gi.game_type === gt.value);

    return {
        game_type: gt.value,
        name: '',
        port: gt.defaultPort,
        max_players: 32,
        game_install_id: defaultInstall?.id?.toString() ?? '',
        active_preset_id: '',
        ...getSchemaDefaults(gt.settingsSchema, true),
    };
}

export default function CreateServerDialog({
    open,
    onOpenChange,
    gameTypes,
    gameInstalls,
    presets,
}: CreateServerDialogProps) {
    const firstGt = gameTypes[0] ?? null;
    const [data, setData] = useState<Record<string, unknown>>(() =>
        firstGt ? buildFormDefaults(firstGt, gameInstalls) : {},
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const currentGt = gameTypes.find((g) => g.value === data.game_type);
    const filteredInstalls = gameInstalls.filter(
        (gi) => gi.game_type === data.game_type,
    );
    const filteredPresets = presets.filter(
        (p) => p.game_type === data.game_type,
    );

    function onGameTypeChange(value: string) {
        const gt = gameTypes.find((g) => g.value === value);
        if (!gt) return;
        setData(buildFormDefaults(gt, gameInstalls));
        setErrors({});
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);

        const payload: Record<string, unknown> = {
            ...data,
            active_preset_id: (data.active_preset_id as string) || null,
            game_install_id: Number(data.game_install_id),
        };

        router.post(
            store.url(),
            payload as Record<string, string | number | boolean | null>,
            {
                onSuccess: () => {
                    setErrors({});
                    onOpenChange(false);
                },
                onError: (errs) => setErrors(errs),
                onFinish: () => setProcessing(false),
            },
        );
    }

    function handleOpenChange(isOpen: boolean) {
        if (isOpen) {
            setData(buildFormDefaults(firstGt, gameInstalls));
            setErrors({});
        }
        onOpenChange(isOpen);
    }

    function set(key: string, value: unknown) {
        setData((prev) => ({ ...prev, [key]: value }));
    }

    function batchSet(values: Record<string, unknown>) {
        setData((prev) => ({ ...prev, ...values }));
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>New Server</DialogTitle>
                    <DialogDescription>
                        Configure and create a new game server instance.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Game</Label>
                        <Select
                            value={data.game_type as string}
                            onValueChange={onGameTypeChange}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {gameTypes.map((gt) => (
                                    <SelectItem key={gt.value} value={gt.value}>
                                        {gt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>Name</Label>
                        <Input
                            value={data.name as string}
                            onChange={(e) => set('name', e.target.value)}
                            required
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label>Game Port</Label>
                        <Input
                            type="number"
                            value={data.port as number}
                            onChange={(e) => {
                                const port = Number(e.target.value);
                                setData((prev) => ({
                                    ...prev,
                                    port,
                                    ...('query_port' in prev
                                        ? { query_port: port + 1 }
                                        : {}),
                                }));
                            }}
                            required
                        />
                        {errors.port && (
                            <p className="text-sm text-destructive">
                                {errors.port}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label>Max Players</Label>
                        <Input
                            type="number"
                            value={data.max_players as number}
                            onChange={(e) =>
                                set('max_players', Number(e.target.value))
                            }
                            required
                        />
                    </div>

                    <div className="space-y-2">
                        <Label>Game Install</Label>
                        <Select
                            value={data.game_install_id as string}
                            onValueChange={(v) => set('game_install_id', v)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select install..." />
                            </SelectTrigger>
                            <SelectContent>
                                {filteredInstalls.map((gi) => (
                                    <SelectItem
                                        key={gi.id}
                                        value={gi.id.toString()}
                                    >
                                        {gi.name} ({gi.branch})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.game_install_id && (
                            <p className="text-sm text-destructive">
                                {errors.game_install_id}
                            </p>
                        )}
                    </div>

                    {filteredPresets.length > 0 && (
                        <div className="space-y-2">
                            <Label>Mod Preset (optional)</Label>
                            <Select
                                value={data.active_preset_id as string}
                                onValueChange={(v) =>
                                    set('active_preset_id', v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="None" />
                                </SelectTrigger>
                                <SelectContent>
                                    {filteredPresets.map((p) => (
                                        <SelectItem
                                            key={p.id}
                                            value={p.id.toString()}
                                        >
                                            {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {/* Schema-driven create sections (e.g., Server Rules for Arma 3) */}
                    {currentGt && (
                        <GameSettingsRenderer
                            schema={currentGt.settingsSchema}
                            data={data}
                            errors={errors}
                            onChange={set}
                            onBatchChange={batchSet}
                            mode="create"
                        />
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Create Server
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
