import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { index as presetsIndex, store } from '@/routes/presets';
import type { BreadcrumbItem, ReforgerMod, WorkshopMod } from '@/types';

type GameTypeOption = {
    value: string;
    label: string;
    supportsWorkshopMods: boolean;
};

type Props = {
    gameTypes: GameTypeOption[];
    workshopMods: WorkshopMod[];
    reforgerMods: ReforgerMod[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mod Presets', href: presetsIndex() },
    { title: 'Create', href: '#' },
];

export default function PresetCreate({
    gameTypes,
    workshopMods,
    reforgerMods,
}: Props) {
    const form = useForm({
        game_type: 'arma3',
        name: '',
        mod_ids: [] as number[],
        reforger_mod_ids: [] as number[],
    });

    const isReforger = form.data.game_type === 'reforger';
    const availableMods = isReforger
        ? []
        : workshopMods.filter((m) => m.game_type === form.data.game_type);
    const availableReforgerMods = isReforger ? reforgerMods : [];

    function toggleMod(modId: number) {
        const ids = form.data.mod_ids.includes(modId)
            ? form.data.mod_ids.filter((id) => id !== modId)
            : [...form.data.mod_ids, modId];
        form.setData('mod_ids', ids);
    }

    function toggleReforgerMod(modId: number) {
        const ids = form.data.reforger_mod_ids.includes(modId)
            ? form.data.reforger_mod_ids.filter((id) => id !== modId)
            : [...form.data.reforger_mod_ids, modId];
        form.setData('reforger_mod_ids', ids);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(store.url());
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Preset" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Create Mod Preset"
                    description="Select mods to include in this preset."
                />

                <form onSubmit={submit} className="max-w-2xl space-y-6">
                    <div className="space-y-2">
                        <Label>Game Type</Label>
                        <Select
                            value={form.data.game_type}
                            onValueChange={(v) => {
                                form.setData({
                                    ...form.data,
                                    game_type: v,
                                    mod_ids: [],
                                    reforger_mod_ids: [],
                                });
                            }}
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
                        <Label>Preset Name</Label>
                        <Input
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                            placeholder="My Preset"
                        />
                        {form.errors.name && (
                            <p className="text-sm text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </div>

                    {!isReforger && (
                        <div className="space-y-2">
                            <Label>
                                Workshop Mods ({form.data.mod_ids.length}{' '}
                                selected)
                            </Label>
                            {availableMods.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No workshop mods available for this game
                                    type.
                                </p>
                            ) : (
                                <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border p-2">
                                    {availableMods.map((mod) => (
                                        <label
                                            key={mod.id}
                                            className="flex cursor-pointer items-center gap-2 rounded p-1.5 hover:bg-muted"
                                        >
                                            <Checkbox
                                                checked={form.data.mod_ids.includes(
                                                    mod.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleMod(mod.id)
                                                }
                                            />
                                            <span className="text-sm">
                                                {mod.name ||
                                                    `Mod #${mod.workshop_id}`}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {isReforger && (
                        <div className="space-y-2">
                            <Label>
                                Reforger Mods (
                                {form.data.reforger_mod_ids.length} selected)
                            </Label>
                            {availableReforgerMods.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No Reforger mods available.
                                </p>
                            ) : (
                                <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border p-2">
                                    {availableReforgerMods.map((mod) => (
                                        <label
                                            key={mod.id}
                                            className="flex cursor-pointer items-center gap-2 rounded p-1.5 hover:bg-muted"
                                        >
                                            <Checkbox
                                                checked={form.data.reforger_mod_ids.includes(
                                                    mod.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleReforgerMod(mod.id)
                                                }
                                            />
                                            <span className="text-sm">
                                                {mod.name}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Create Preset
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.history.back()}
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
