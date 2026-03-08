import { router } from '@inertiajs/react';
import axios from 'axios';
import { Check, ChevronDown, Loader2, RefreshCw, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    reforgerScenarios,
    reloadReforgerScenarios,
} from '@/actions/App/Http/Controllers/ServerController';
import BackupSection from '@/components/servers/backup-section';
import DifficultySettingsSection from '@/components/servers/difficulty-settings-section';
import NetworkSettingsSection from '@/components/servers/network-settings-section';
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
import { gameTypeLabel } from '@/lib/utils';
import { update } from '@/routes/servers';
import type { GameInstall, ModPreset, Server } from '@/types';

type ReforgerScenario = {
    value: string;
    name: string;
    isOfficial: boolean;
};

type ServerEditPanelProps = {
    server: Server;
    presets: ModPreset[];
    gameInstalls: GameInstall[];
    onCancel: () => void;
};

type EditData = {
    name: string;
    port: number;
    query_port: number;
    max_players: number;
    password: string;
    admin_password: string;
    description: string;
    active_preset_id: string;
    game_install_id: string;
    additional_params: string;
    verify_signatures: boolean;
    allowed_file_patching: boolean;
    battle_eye: boolean;
    persistent: boolean;
    von_enabled: boolean;
    additional_server_options: string;
    auto_restart: boolean;
    // difficulty
    reduced_damage: boolean;
    group_indicators: number;
    friendly_tags: number;
    enemy_tags: number;
    detected_mines: number;
    commands: number;
    waypoints: number;
    tactical_ping: number;
    weapon_info: number;
    stance_indicator: number;
    stamina_bar: boolean;
    weapon_crosshair: boolean;
    vision_aid: boolean;
    third_person_view: number;
    camera_shake: boolean;
    score_table: boolean;
    death_messages: boolean;
    von_id: boolean;
    map_content: boolean;
    auto_report: boolean;
    ai_level_preset: number;
    skill_ai: string;
    precision_ai: string;
    // reforger
    scenario_id: string;
    third_person_view_enabled: boolean;
    max_fps: number;
    cross_platform: boolean;
    // network
    max_msg_send: number;
    max_size_guaranteed: number;
    max_size_nonguaranteed: number;
    min_bandwidth: string;
    max_bandwidth: string;
    min_error_to_send: string;
    min_error_to_send_near: string;
    max_packet_size: number;
    max_custom_file_size: number;
    terrain_grid: string;
    view_distance: number;
};

function buildEditData(server: Server): EditData {
    const diff = server.difficulty_settings;
    const net = server.network_settings;
    const rfg = server.reforger_settings;

    return {
        name: server.name,
        port: server.port,
        query_port: server.query_port,
        max_players: server.max_players,
        password: server.password ?? '',
        admin_password: server.admin_password ?? '',
        description: server.description ?? '',
        active_preset_id: server.active_preset_id?.toString() ?? '',
        game_install_id: server.game_install_id?.toString() ?? '',
        additional_params: server.additional_params ?? '',
        verify_signatures: server.verify_signatures,
        allowed_file_patching: server.allowed_file_patching,
        battle_eye: server.battle_eye,
        persistent: server.persistent,
        von_enabled: server.von_enabled,
        additional_server_options: server.additional_server_options ?? '',
        auto_restart: server.auto_restart ?? false,
        // reforger
        scenario_id: rfg?.scenario_id ?? '',
        third_person_view_enabled: rfg?.third_person_view_enabled ?? true,
        max_fps: rfg?.max_fps ?? 60,
        cross_platform: rfg?.cross_platform ?? false,
        // difficulty defaults
        reduced_damage: diff?.reduced_damage ?? false,
        group_indicators: diff?.group_indicators ?? 2,
        friendly_tags: diff?.friendly_tags ?? 2,
        enemy_tags: diff?.enemy_tags ?? 0,
        detected_mines: diff?.detected_mines ?? 2,
        commands: diff?.commands ?? 2,
        waypoints: diff?.waypoints ?? 2,
        tactical_ping: diff?.tactical_ping ?? 3,
        weapon_info: diff?.weapon_info ?? 2,
        stance_indicator: diff?.stance_indicator ?? 2,
        stamina_bar: diff?.stamina_bar ?? true,
        weapon_crosshair: diff?.weapon_crosshair ?? true,
        vision_aid: diff?.vision_aid ?? false,
        third_person_view: diff?.third_person_view ?? 1,
        camera_shake: diff?.camera_shake ?? true,
        score_table: diff?.score_table ?? true,
        death_messages: diff?.death_messages ?? true,
        von_id: diff?.von_id ?? true,
        map_content: diff?.map_content ?? true,
        auto_report: diff?.auto_report ?? false,
        ai_level_preset: diff?.ai_level_preset ?? 1,
        skill_ai: diff?.skill_ai ?? '0.50',
        precision_ai: diff?.precision_ai ?? '0.50',
        // network defaults
        max_msg_send: net?.max_msg_send ?? 128,
        max_size_guaranteed: net?.max_size_guaranteed ?? 512,
        max_size_nonguaranteed: net?.max_size_nonguaranteed ?? 256,
        min_bandwidth: net?.min_bandwidth ?? '131072',
        max_bandwidth: net?.max_bandwidth ?? '10000000000',
        min_error_to_send: net?.min_error_to_send ?? '0.0010',
        min_error_to_send_near: net?.min_error_to_send_near ?? '0.0100',
        max_packet_size: net?.max_packet_size ?? 1400,
        max_custom_file_size: net?.max_custom_file_size ?? 0,
        terrain_grid: net?.terrain_grid ?? '25.0000',
        view_distance: net?.view_distance ?? 0,
    };
}

export default function ServerEditPanel({
    server,
    presets,
    gameInstalls,
    onCancel,
}: ServerEditPanelProps) {
    const isArma3 = server.game_type === 'arma3';
    const isReforger = server.game_type === 'reforger';
    const [data, setData] = useState<EditData>(() => buildEditData(server));
    const [errors, setErrors] = useState<Partial<Record<string, string>>>({});
    const [processing, setProcessing] = useState(false);
    const [advancedOpen, setAdvancedOpen] = useState(false);

    // Reforger scenario autocomplete state
    const [scenarios, setScenarios] = useState<ReforgerScenario[]>([]);
    const [scenariosLoading, setScenariosLoading] = useState(false);
    const [scenariosLoaded, setScenariosLoaded] = useState(false);
    const [scenarioDropdownOpen, setScenarioDropdownOpen] = useState(false);
    const scenarioRef = useRef<HTMLDivElement>(null);

    const loadScenarios = useCallback(() => {
        if (scenariosLoaded || scenariosLoading || !server.id) {
            return;
        }
        setScenariosLoading(true);
        axios
            .get(reforgerScenarios.url(server.id))
            .then((res) => {
                setScenarios(res.data.scenarios ?? []);
                setScenariosLoaded(true);
            })
            .catch(() => setScenariosLoaded(true))
            .finally(() => setScenariosLoading(false));
    }, [scenariosLoaded, scenariosLoading, server.id]);

    const reloadScenarios = useCallback(() => {
        if (scenariosLoading || !server.id) {
            return;
        }
        setScenariosLoading(true);
        axios
            .post(reloadReforgerScenarios.url(server.id))
            .then((res) => {
                setScenarios(res.data.scenarios ?? []);
                setScenariosLoaded(true);
            })
            .catch(() => {})
            .finally(() => setScenariosLoading(false));
    }, [scenariosLoading, server.id]);

    // Close dropdown on outside click
    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (
                scenarioRef.current &&
                !scenarioRef.current.contains(e.target as Node)
            ) {
                setScenarioDropdownOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    const filteredInstalls = gameInstalls.filter(
        (gi) => gi.game_type === server.game_type,
    );
    const filteredPresets = presets.filter(
        (p) => p.game_type === server.game_type,
    );

    function set<K extends keyof EditData>(key: K, value: EditData[K]) {
        setData((prev) => ({ ...prev, [key]: value }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);

        const payload: Record<string, unknown> = {
            ...data,
            active_preset_id: data.active_preset_id || null,
            game_install_id: Number(data.game_install_id),
            password: data.password || null,
            admin_password: data.admin_password || null,
            description: data.description || null,
            additional_params: data.additional_params || null,
            additional_server_options: data.additional_server_options || null,
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
                        {gameTypeLabel(server.game_type)}
                    </Badge>
                </div>

                {/* Basic Settings */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label>Server Name</Label>
                        <Input
                            value={data.name}
                            onChange={(e) => set('name', e.target.value)}
                            required
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">
                                {errors.name}
                            </p>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>Game Port</Label>
                            <Input
                                type="number"
                                value={data.port}
                                onChange={(e) => {
                                    const port = Number(e.target.value);
                                    setData((prev) => ({
                                        ...prev,
                                        port,
                                        query_port: port + 1,
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
                            <Label>Query Port</Label>
                            <Input
                                type="number"
                                value={data.query_port}
                                onChange={(e) =>
                                    set('query_port', Number(e.target.value))
                                }
                                required
                            />
                            {errors.query_port && (
                                <p className="text-sm text-destructive">
                                    {errors.query_port}
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="space-y-2">
                        <Label>Max Players</Label>
                        <Input
                            type="number"
                            value={data.max_players}
                            onChange={(e) =>
                                set('max_players', Number(e.target.value))
                            }
                            required
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Password</Label>
                        <Input
                            value={data.password}
                            onChange={(e) => set('password', e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Admin Password</Label>
                        <Input
                            value={data.admin_password}
                            onChange={(e) =>
                                set('admin_password', e.target.value)
                            }
                        />
                    </div>
                </div>

                <div className="space-y-2">
                    <Label>Description</Label>
                    <Textarea
                        value={data.description}
                        onChange={(e) => set('description', e.target.value)}
                        rows={2}
                    />
                </div>

                <div className="grid grid-cols-1 items-center gap-4 md:grid-cols-3">
                    <div className="space-y-2">
                        <Label>Game Install</Label>
                        <Select
                            value={data.game_install_id}
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
                            <Label>Mod Preset</Label>
                            <Select
                                value={data.active_preset_id || 'none'}
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
                    <div className="mt-3 flex items-center gap-2">
                        <Switch
                            checked={data.auto_restart}
                            onCheckedChange={(v) => set('auto_restart', v)}
                        />
                        <Label>Auto-Restart on Crash</Label>
                    </div>
                </div>

                {/* Arma 3 Server Rules */}
                {isArma3 && (
                    <div className="space-y-3 rounded-lg border p-3">
                        <p className="text-sm font-medium">Server Rules</p>
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                            {(
                                [
                                    ['verify_signatures', 'Verify Signatures'],
                                    [
                                        'allowed_file_patching',
                                        'Allow File Patching',
                                    ],
                                    ['battle_eye', 'BattlEye Anti-Cheat'],
                                    ['von_enabled', 'Voice Over Network'],
                                    ['persistent', 'Persistent Server'],
                                ] as const
                            ).map(([key, label]) => (
                                <div
                                    key={key}
                                    className="flex items-center gap-2"
                                >
                                    <Switch
                                        checked={data[key] as boolean}
                                        onCheckedChange={(v) => set(key, v)}
                                    />
                                    <Label>{label}</Label>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Reforger Settings */}
                {isReforger && (
                    <div className="space-y-3 rounded-lg border p-3">
                        <p className="text-sm font-medium">Reforger Settings</p>
                        <div className="space-y-4">
                            <div className="space-y-2" ref={scenarioRef}>
                                <div className="flex items-center gap-2">
                                    <Label>Scenario ID</Label>
                                    <button
                                        type="button"
                                        onClick={reloadScenarios}
                                        disabled={scenariosLoading}
                                        className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs text-muted-foreground hover:text-foreground disabled:opacity-50"
                                        title="Reload scenarios from server"
                                    >
                                        <RefreshCw
                                            className={`size-3 ${scenariosLoading ? 'animate-spin' : ''}`}
                                        />
                                        Reload
                                    </button>
                                </div>
                                <div className="relative">
                                    <Input
                                        value={data.scenario_id}
                                        onChange={(e) => {
                                            set('scenario_id', e.target.value);
                                            setScenarioDropdownOpen(true);
                                        }}
                                        onFocus={() => {
                                            loadScenarios();
                                            if (scenariosLoaded) {
                                                setScenarioDropdownOpen(true);
                                            }
                                        }}
                                        placeholder="{ECC61978EDCC2B5A}Missions/23_Campaign.conf"
                                        required
                                    />
                                    {scenariosLoading && (
                                        <Loader2 className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                                    )}
                                    {scenarioDropdownOpen &&
                                        scenariosLoaded &&
                                        scenarios.length > 0 && (
                                            <div className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-popover shadow-md">
                                                {scenarios
                                                    .filter(
                                                        (s) =>
                                                            !data.scenario_id ||
                                                            s.value
                                                                .toLowerCase()
                                                                .includes(
                                                                    data.scenario_id.toLowerCase(),
                                                                ) ||
                                                            s.name
                                                                .toLowerCase()
                                                                .includes(
                                                                    data.scenario_id.toLowerCase(),
                                                                ),
                                                    )
                                                    .map((s) => (
                                                        <button
                                                            key={s.value}
                                                            type="button"
                                                            className="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-accent"
                                                            onClick={() => {
                                                                set(
                                                                    'scenario_id',
                                                                    s.value,
                                                                );
                                                                setScenarioDropdownOpen(
                                                                    false,
                                                                );
                                                            }}
                                                        >
                                                            <span className="font-medium">
                                                                {s.name}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {s.value}
                                                            </span>
                                                        </button>
                                                    ))}
                                            </div>
                                        )}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Select from available scenarios or enter a
                                    custom scenario ID.
                                </p>
                                {errors.scenario_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.scenario_id}
                                    </p>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={data.third_person_view_enabled}
                                        onCheckedChange={(v) =>
                                            set('third_person_view_enabled', v)
                                        }
                                    />
                                    <Label>Third Person View</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={data.battle_eye}
                                        onCheckedChange={(v) =>
                                            set('battle_eye', v)
                                        }
                                    />
                                    <Label>BattlEye Anti-Cheat</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={data.cross_platform}
                                        onCheckedChange={(v) =>
                                            set('cross_platform', v)
                                        }
                                    />
                                    <Label>Cross-Platform</Label>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
                                <div className="space-y-2">
                                    <Label>Max FPS</Label>
                                    <Input
                                        type="number"
                                        value={data.max_fps}
                                        onChange={(e) =>
                                            set(
                                                'max_fps',
                                                Number(e.target.value),
                                            )
                                        }
                                        min={10}
                                        max={240}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Recommended: 60-120. Limits server tick
                                        rate to prevent excessive CPU usage.
                                    </p>
                                    {errors.max_fps && (
                                        <p className="text-sm text-destructive">
                                            {errors.max_fps}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Difficulty Settings (Arma 3 only) */}
                {isArma3 && (
                    <DifficultySettingsSection
                        data={data}
                        onChange={(key, value) =>
                            setData((prev) => ({ ...prev, [key]: value }))
                        }
                    />
                )}

                {/* Network Settings (Arma 3 only) */}
                {isArma3 && (
                    <NetworkSettingsSection
                        data={data}
                        onChange={(key, value) =>
                            setData((prev) => ({ ...prev, [key]: value }))
                        }
                        onBatchChange={(values) =>
                            setData((prev) => ({ ...prev, ...values }))
                        }
                        errors={errors}
                    />
                )}

                {/* Advanced */}
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
                                Additional launch parameters and raw server.cfg
                                directives.
                            </span>
                        </div>
                        <ChevronDown
                            className={`size-4 text-muted-foreground transition-transform duration-200 ${advancedOpen ? 'rotate-180' : ''}`}
                        />
                    </button>
                    {advancedOpen && (
                        <div className="space-y-4 border-t px-4 py-4">
                            <div className="space-y-2">
                                <Label>Additional Launch Parameters</Label>
                                <Textarea
                                    value={data.additional_params}
                                    onChange={(e) =>
                                        set('additional_params', e.target.value)
                                    }
                                    rows={2}
                                    placeholder="-loadMissionToMemory -enableHT"
                                />
                            </div>
                            {isArma3 && (
                                <div className="space-y-2">
                                    <Label>Additional server.cfg Options</Label>
                                    <Textarea
                                        value={data.additional_server_options}
                                        onChange={(e) =>
                                            set(
                                                'additional_server_options',
                                                e.target.value,
                                            )
                                        }
                                        rows={3}
                                        placeholder="Raw config directives appended to server.cfg"
                                    />
                                </div>
                            )}
                        </div>
                    )}
                </div>

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
