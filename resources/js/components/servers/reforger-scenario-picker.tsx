import axios from 'axios';
import { Loader2, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    scenarios as fetchScenarios,
    reloadScenarios as refreshScenarios,
} from '@/actions/App/Http/Controllers/ServerController';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SettingsField } from '@/types';

type ReforgerScenario = {
    value: string;
    name: string;
    isOfficial: boolean;
};

type ReforgerScenarioPickerProps = {
    field: SettingsField;
    value: unknown;
    onChange: (key: string, value: unknown) => void;
    error?: string;
    serverId?: number;
};

export default function ReforgerScenarioPicker({
    field,
    value,
    onChange,
    error,
    serverId,
}: ReforgerScenarioPickerProps) {
    const [scenarios, setScenarios] = useState<ReforgerScenario[]>([]);
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    const scenarioValue = (value as string) ?? '';

    const loadScenarios = useCallback(() => {
        if (loaded || loading || !serverId) {
            return;
        }
        setLoading(true);
        axios
            .get(fetchScenarios.url(serverId))
            .then((res) => {
                setScenarios(res.data.scenarios ?? []);
                setLoaded(true);
            })
            .catch((error) => {
                console.error('Failed to load scenarios:', error);
                setLoaded(true);
            })
            .finally(() => setLoading(false));
    }, [loaded, loading, serverId]);

    const reload = useCallback(() => {
        if (loading || !serverId) {
            return;
        }
        setLoading(true);
        axios
            .post(refreshScenarios.url(serverId))
            .then((res) => {
                setScenarios(res.data.scenarios ?? []);
                setLoaded(true);
            })
            .catch((error) => {
                console.error('Failed to reload scenarios:', error);
            })
            .finally(() => setLoading(false));
    }, [loading, serverId]);

    // Close dropdown on outside click
    useEffect(() => {
        function handleClick(e: MouseEvent) {
            if (
                containerRef.current &&
                !containerRef.current.contains(e.target as Node)
            ) {
                setDropdownOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    return (
        <div className="space-y-2" ref={containerRef}>
            <div className="flex items-center gap-2">
                <Label>Scenario ID</Label>
                <button
                    type="button"
                    onClick={reload}
                    disabled={loading}
                    className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs text-muted-foreground hover:text-foreground disabled:opacity-50"
                    title="Reload scenarios from server"
                >
                    <RefreshCw
                        className={`size-3 ${loading ? 'animate-spin' : ''}`}
                    />
                    Reload
                </button>
            </div>
            <div className="relative">
                <Input
                    value={scenarioValue}
                    onChange={(e) => {
                        onChange(field.key!, e.target.value);
                        setDropdownOpen(true);
                    }}
                    onFocus={() => {
                        loadScenarios();
                        if (loaded) {
                            setDropdownOpen(true);
                        }
                    }}
                    placeholder="{ECC61978EDCC2B5A}Missions/23_Campaign.conf"
                    required
                />
                {loading && (
                    <Loader2 className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                )}
                {dropdownOpen && loaded && scenarios.length > 0 && (
                    <div className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-popover shadow-md">
                        {scenarios
                            .filter(
                                (s) =>
                                    !scenarioValue ||
                                    s.value
                                        .toLowerCase()
                                        .includes(
                                            scenarioValue.toLowerCase(),
                                        ) ||
                                    s.name
                                        .toLowerCase()
                                        .includes(scenarioValue.toLowerCase()),
                            )
                            .map((s) => (
                                <button
                                    key={s.value}
                                    type="button"
                                    className="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-accent"
                                    onClick={() => {
                                        onChange(field.key!, s.value);
                                        setDropdownOpen(false);
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
                Select from available scenarios or enter a custom scenario ID.
            </p>
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
