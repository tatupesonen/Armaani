import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, X } from 'lucide-react';
import { Fragment, useState } from 'react';
import '@/components/servers/custom-components';
import BackupSection from '@/components/servers/backup-section';
import GameSettingsRenderer, {
    buildEditDataFromSchema,
    getAdvancedFields,
} from '@/components/servers/game-settings-renderer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/servers';
import type { GameInstall, ModPreset, Server, SettingsSection } from '@/types';

type ServerEditPanelProps = {
    server: Server;
    presets: ModPreset[];
    gameInstalls: GameInstall[];
    settingsSchema: SettingsSection[];
    supportsAutoRestart: boolean;
    onCancel: () => void;
};

function buildEditData(
    server: Server,
    schema: SettingsSection[],
): Record<string, unknown> {
    return {
        // Universal fields
        name: server.name,
        port: server.port,
        max_players: server.max_players,
        auto_restart: server.auto_restart,
        description: server.description ?? '',
        active_preset_id: server.active_preset_id?.toString() ?? '',
        game_install_id: server.game_install_id?.toString() ?? '',
        // Schema-driven fields (reads from server + related settings via source)
        ...buildEditDataFromSchema(
            server as unknown as Record<string, unknown>,
            schema,
        ),
    };
}

export default function ServerEditPanel({
    server,
    presets,
    gameInstalls,
    settingsSchema,
    supportsAutoRestart,
    onCancel,
}: ServerEditPanelProps) {
    const { gameTypeLabels } = usePage().props;
    const [data, setData] = useState<Record<string, unknown>>(() =>
        buildEditData(server, settingsSchema),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [advancedOpen, setAdvancedOpen] = useState(false);

    const filteredInstalls = gameInstalls.filter(
        (gi) => gi.game_type === server.game_type,
    );
    const filteredPresets = presets.filter(
        (p) => p.game_type === server.game_type,
    );

    const advancedFields = getAdvancedFields(settingsSchema);

    function set(key: string, value: unknown) {
        setData((prev) => ({ ...prev, [key]: value }));
    }

    function batchSet(values: Record<string, unknown>) {
        setData((prev) => ({ ...prev, ...values }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);

        const payload: Record<string, unknown> = {
            ...data,
            active_preset_id: (data.active_preset_id as string) || null,
            game_install_id: Number(data.game_install_id),
            password: (data.password as string) || null,
            description: (data.description as string) || null,
            additional_params: (data.additional_params as string) || null,
        };

        router.put(
            update.url(server.id),
            payload as Record<string, string | number | boolean | null>,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setErrors({});
                },
                onError: (errs) => setErrors(errs),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div className="border-t bg-muted/30 p-4">
            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Game type badge (read-only) */}
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">Game Type:</span>
                    <Badge variant="outline">
                        {gameTypeLabels[server.game_type] ?? server.game_type}
                    </Badge>
                </div>

                {/* Universal Settings */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label>Server Name</Label>
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
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label>Description</Label>
                        <Textarea
                            value={data.description as string}
                            onChange={(e) => set('description', e.target.value)}
                            rows={2}
                        />
                    </div>
                    {filteredPresets.length > 0 && (
                        <div className="space-y-2">
                            <Label>Mod Preset</Label>
                            <Select
                                value={
                                    (data.active_preset_id as string) || 'none'
                                }
                                onValueChange={(v) =>
                                    set(
                                        'active_preset_id',
                                        v === 'none' ? '' : v,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="None" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
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
                </div>

                {/* Auto-restart toggle (only for games with crash detection) */}
                {supportsAutoRestart && (
                    <div className="flex items-center gap-3">
                        <Switch
                            id="auto_restart"
                            checked={data.auto_restart as boolean}
                            onCheckedChange={(v) => set('auto_restart', v)}
                        />
                        <Label htmlFor="auto_restart">
                            Auto-Restart on Crash
                        </Label>
                    </div>
                )}

                {/* Schema-driven game-specific sections */}
                <GameSettingsRenderer
                    schema={settingsSchema}
                    data={data}
                    errors={errors}
                    onChange={set}
                    onBatchChange={batchSet}
                    serverId={server.id}
                />

                {/* Advanced (schema-driven) */}
                {advancedFields.length > 0 && (
                    <div className="rounded-lg border">
                        <button
                            type="button"
                            onClick={() => setAdvancedOpen(!advancedOpen)}
                            className="flex w-full items-center gap-3 px-4 py-3 text-left"
                        >
                            <div className="flex-1">
                                <span className="text-base font-semibold">
                                    Advanced
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    Additional launch parameters and server
                                    configuration.
                                </span>
                            </div>
                            <ChevronDown
                                className={`size-4 text-muted-foreground transition-transform duration-200 ${advancedOpen ? 'rotate-180' : ''}`}
                            />
                        </button>
                        {advancedOpen && (
                            <div className="space-y-4 border-t px-4 py-4">
                                {advancedFields.map((field, fi) => (
                                    <Fragment key={field.key ?? `adv-${fi}`}>
                                        <div className="space-y-2">
                                            <Label>{field.label}</Label>
                                            <Textarea
                                                value={
                                                    (data[
                                                        field.key!
                                                    ] as string) ?? ''
                                                }
                                                onChange={(e) =>
                                                    set(
                                                        field.key!,
                                                        e.target.value,
                                                    )
                                                }
                                                rows={field.rows ?? 2}
                                                placeholder={field.placeholder}
                                            />
                                        </div>
                                    </Fragment>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Backups */}
                {server.supports_backups !== false && (
                    <BackupSection server={server} />
                )}

                {/* Save / Cancel */}
                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={processing}>
                        <Check className="mr-2 size-4" />
                        Save
                    </Button>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        <X className="mr-2 size-4" />
                        Cancel
                    </Button>
                </div>
            </form>
        </div>
    );
}
