import { ChevronDown, RotateCcw, Zap } from 'lucide-react';
import { Fragment, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type {
    SettingsField,
    SettingsFieldGroup,
    SettingsPreset,
    SettingsSection,
} from '@/types';

// Registry for custom field components (e.g., scenario-picker)
type CustomComponentProps = {
    field: SettingsField;
    value: unknown;
    onChange: (key: string, value: unknown) => void;
    error?: string;
    serverId?: number;
};

const customComponents: Record<
    string,
    React.ComponentType<CustomComponentProps>
> = {};

export function registerCustomComponent(
    name: string,
    component: React.ComponentType<CustomComponentProps>,
): void {
    customComponents[name] = component;
}

// --- Field Renderers ---

function ToggleField({
    field,
    value,
    onChange,
}: {
    field: SettingsField;
    value: unknown;
    onChange: (key: string, value: unknown) => void;
}) {
    return (
        <div className="flex items-center gap-2">
            <Switch
                checked={value as boolean}
                onCheckedChange={(v) => onChange(field.key!, v)}
            />
            <Label>{field.label}</Label>
        </div>
    );
}

function NumberField({
    field,
    value,
    onChange,
    error,
}: {
    field: SettingsField;
    value: unknown;
    onChange: (key: string, value: unknown) => void;
    error?: string;
}) {
    return (
        <div className="space-y-1.5">
            <Label className="text-xs">{field.label}</Label>
            <Input
                type="number"
                min={field.min}
                max={field.max}
                step={field.step}
                value={value as string | number}
                onChange={(e) =>
                    onChange(
                        field.key!,
                        field.storeAsString
                            ? e.target.value
                            : Number(e.target.value),
                    )
                }
            />
            {field.description && (
                <p className="text-xs text-muted-foreground">
                    {field.description}
                </p>
            )}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

function TextField({
    field,
    value,
    onChange,
    error,
}: {
    field: SettingsField;
    value: unknown;
    onChange: (key: string, value: unknown) => void;
    error?: string;
}) {
    return (
        <div className="space-y-1.5">
            <Label className="text-xs">{field.label}</Label>
            <Input
                inputMode={
                    field.inputMode as
                        | 'decimal'
                        | 'text'
                        | 'numeric'
                        | undefined
                }
                value={(value as string) ?? ''}
                onChange={(e) => onChange(field.key!, e.target.value)}
                placeholder={field.placeholder}
                required={field.required}
            />
            {field.description && (
                <p className="text-xs text-muted-foreground">
                    {field.description}
                </p>
            )}
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}

function TextareaField({
    field,
    value,
    onChange,
}: {
    field: SettingsField;
    value: unknown;
    onChange: (key: string, value: unknown) => void;
}) {
    return (
        <div className="space-y-2">
            <Label>{field.label}</Label>
            <Textarea
                value={(value as string) ?? ''}
                onChange={(e) => onChange(field.key!, e.target.value)}
                rows={field.rows ?? 3}
                placeholder={field.placeholder}
            />
        </div>
    );
}

function SegmentedField({
    field,
    value,
    onChange,
}: {
    field: SettingsField;
    value: unknown;
    onChange: (key: string, value: unknown) => void;
}) {
    return (
        <div className="space-y-1.5">
            <Label className="text-xs">{field.label}</Label>
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                value={String(value)}
                onValueChange={(v) => v && onChange(field.key!, Number(v))}
                className="w-full"
            >
                {field.options?.map((opt) => (
                    <ToggleGroupItem
                        key={opt.value}
                        value={opt.value}
                        className="flex-1 text-xs"
                    >
                        {opt.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
        </div>
    );
}

// --- Field Dispatcher ---

function renderField(
    field: SettingsField,
    data: Record<string, unknown>,
    onChange: (key: string, value: unknown) => void,
    errors: Record<string, string>,
    serverId?: number,
): React.ReactNode {
    if (field.type === 'separator') {
        return <Separator />;
    }

    if (field.type === 'custom') {
        const Component = field.component
            ? customComponents[field.component]
            : undefined;
        if (!Component) {
            return null;
        }
        return (
            <Component
                field={field}
                value={data[field.key!]}
                onChange={onChange}
                error={field.key ? errors[field.key] : undefined}
                serverId={serverId}
            />
        );
    }

    const value = field.key ? data[field.key] : undefined;
    const error = field.key ? errors[field.key] : undefined;

    switch (field.type) {
        case 'toggle':
            return (
                <ToggleField field={field} value={value} onChange={onChange} />
            );
        case 'number':
            return (
                <NumberField
                    field={field}
                    value={value}
                    onChange={onChange}
                    error={error}
                />
            );
        case 'text':
            return (
                <TextField
                    field={field}
                    value={value}
                    onChange={onChange}
                    error={error}
                />
            );
        case 'textarea':
            return (
                <TextareaField
                    field={field}
                    value={value}
                    onChange={onChange}
                />
            );
        case 'segmented':
            return (
                <SegmentedField
                    field={field}
                    value={value}
                    onChange={onChange}
                />
            );
        default:
            return null;
    }
}

// --- Layout: Render fields with halfWidth grouping ---

function renderFieldList(
    fields: SettingsField[],
    data: Record<string, unknown>,
    onChange: (key: string, value: unknown) => void,
    errors: Record<string, string>,
    serverId?: number,
): React.ReactNode[] {
    const elements: React.ReactNode[] = [];
    let i = 0;

    while (i < fields.length) {
        const field = fields[i];

        // Group consecutive halfWidth fields into a grid row
        if (field.halfWidth) {
            const halfWidthFields: SettingsField[] = [];
            while (i < fields.length && fields[i].halfWidth) {
                halfWidthFields.push(fields[i]);
                i++;
            }
            elements.push(
                <div
                    key={halfWidthFields.map((f) => f.key ?? 'hw').join('-')}
                    className={`grid gap-3 grid-cols-${String(halfWidthFields.length)}`}
                >
                    {halfWidthFields.map((f) => (
                        <Fragment key={f.key ?? f.type}>
                            {renderField(f, data, onChange, errors, serverId)}
                        </Fragment>
                    ))}
                </div>,
            );
            continue;
        }

        elements.push(
            <Fragment key={field.key ?? `${field.type}-${i}`}>
                {renderField(field, data, onChange, errors, serverId)}
            </Fragment>,
        );
        i++;
    }

    return elements;
}

// --- Preset Buttons ---

function PresetButtons({
    presets,
    onBatchChange,
}: {
    presets: SettingsPreset[];
    onBatchChange: (values: Record<string, unknown>) => void;
}) {
    return (
        <div className="flex items-center gap-2">
            {presets.map((preset) => (
                <Button
                    key={preset.label}
                    type="button"
                    size="sm"
                    variant={preset.variant}
                    onClick={() => onBatchChange(preset.values)}
                >
                    {preset.icon === 'reset' && (
                        <RotateCcw className="mr-2 size-3" />
                    )}
                    {preset.icon === 'zap' && <Zap className="mr-2 size-3" />}
                    {preset.label}
                </Button>
            ))}
        </div>
    );
}

// --- Section Renderers ---

function renderColumnsLayout(
    groups: SettingsFieldGroup[],
    data: Record<string, unknown>,
    onChange: (key: string, value: unknown) => void,
    errors: Record<string, string>,
    serverId?: number,
): React.ReactNode {
    return (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            {groups.map((group, gi) => (
                <div key={gi} className="space-y-3">
                    {renderFieldList(
                        group.fields,
                        data,
                        onChange,
                        errors,
                        serverId,
                    )}
                </div>
            ))}
        </div>
    );
}

function renderRowsLayout(
    groups: SettingsFieldGroup[],
    data: Record<string, unknown>,
    onChange: (key: string, value: unknown) => void,
    errors: Record<string, string>,
    serverId?: number,
): React.ReactNode {
    return (
        <div className="space-y-4">
            {groups.map((group, gi) => {
                const cols = group.columns ?? 1;
                const gridClass =
                    cols === 1
                        ? ''
                        : cols === 2
                          ? 'grid grid-cols-1 gap-4 md:grid-cols-2'
                          : 'grid grid-cols-1 gap-4 md:grid-cols-3';
                return gridClass ? (
                    <div key={gi} className={gridClass}>
                        {group.fields.map((field, fi) => (
                            <Fragment key={field.key ?? `${field.type}-${fi}`}>
                                {renderField(
                                    field,
                                    data,
                                    onChange,
                                    errors,
                                    serverId,
                                )}
                            </Fragment>
                        ))}
                    </div>
                ) : (
                    <Fragment key={gi}>
                        {group.fields.map((field, fi) => (
                            <Fragment key={field.key ?? `${field.type}-${fi}`}>
                                {renderField(
                                    field,
                                    data,
                                    onChange,
                                    errors,
                                    serverId,
                                )}
                            </Fragment>
                        ))}
                    </Fragment>
                );
            })}
        </div>
    );
}

function CollapsibleSection({
    section,
    children,
    presets,
    onBatchChange,
}: {
    section: SettingsSection;
    children: React.ReactNode;
    presets?: SettingsPreset[];
    onBatchChange: (values: Record<string, unknown>) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="rounded-lg border">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="flex w-full items-center gap-3 px-4 py-3 text-left"
            >
                <div className="flex-1">
                    <span className="text-base font-semibold">
                        {section.title}
                    </span>
                    {section.description && (
                        <span className="block text-xs text-muted-foreground">
                            {section.description}
                        </span>
                    )}
                </div>
                <ChevronDown
                    className={`size-4 text-muted-foreground transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
                />
            </button>

            {open && (
                <div className="space-y-4 border-t px-4 py-4">
                    {presets && presets.length > 0 && (
                        <PresetButtons
                            presets={presets}
                            onBatchChange={onBatchChange}
                        />
                    )}
                    {children}
                </div>
            )}
        </div>
    );
}

function FlatSection({
    section,
    children,
}: {
    section: SettingsSection;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-3 rounded-lg border p-3">
            {section.title && (
                <p className="text-sm font-medium">{section.title}</p>
            )}
            {children}
        </div>
    );
}

// --- Main Renderer ---

type GameSettingsRendererProps = {
    schema: SettingsSection[];
    data: Record<string, unknown>;
    errors: Record<string, string>;
    onChange: (key: string, value: unknown) => void;
    onBatchChange: (values: Record<string, unknown>) => void;
    serverId?: number;
    mode?: 'edit' | 'create';
};

export default function GameSettingsRenderer({
    schema,
    data,
    errors,
    onChange,
    onBatchChange,
    serverId,
    mode = 'edit',
}: GameSettingsRendererProps) {
    // Filter sections based on mode
    const visibleSections = schema.filter((section) => {
        if (section.advanced) {
            return false; // Advanced fields handled separately
        }
        if (mode === 'create') {
            return section.showOnCreate === true;
        }
        return true;
    });

    return (
        <>
            {visibleSections.map((section, si) => {
                const title =
                    mode === 'create' && section.createLabel
                        ? section.createLabel
                        : section.title;
                const displaySection = { ...section, title };

                const content = section.groups ? (
                    section.layout === 'columns' ? (
                        renderColumnsLayout(
                            section.groups,
                            data,
                            onChange,
                            errors,
                            serverId,
                        )
                    ) : (
                        renderRowsLayout(
                            section.groups,
                            data,
                            onChange,
                            errors,
                            serverId,
                        )
                    )
                ) : section.fields ? (
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                        {section.fields.map((field, fi) => (
                            <Fragment key={field.key ?? `${field.type}-${fi}`}>
                                {renderField(
                                    field,
                                    data,
                                    onChange,
                                    errors,
                                    serverId,
                                )}
                            </Fragment>
                        ))}
                    </div>
                ) : null;

                if (section.collapsible) {
                    return (
                        <CollapsibleSection
                            key={si}
                            section={displaySection}
                            presets={section.presets}
                            onBatchChange={onBatchChange}
                        >
                            {content}
                        </CollapsibleSection>
                    );
                }

                return (
                    <FlatSection key={si} section={displaySection}>
                        {content}
                    </FlatSection>
                );
            })}
        </>
    );
}

// --- Utility: Extract all fields from a schema (for building form defaults) ---

export function getAllSchemaFields(schema: SettingsSection[]): SettingsField[] {
    const fields: SettingsField[] = [];

    for (const section of schema) {
        if (section.fields) {
            fields.push(
                ...section.fields.filter(
                    (f) => f.type !== 'separator' && f.key,
                ),
            );
        }
        if (section.groups) {
            for (const group of section.groups) {
                fields.push(
                    ...group.fields.filter(
                        (f) => f.type !== 'separator' && f.key,
                    ),
                );
            }
        }
    }

    return fields;
}

export function getSchemaDefaults(
    schema: SettingsSection[],
    onlyCreate = false,
): Record<string, unknown> {
    const defaults: Record<string, unknown> = {};

    for (const section of schema) {
        if (onlyCreate && !section.showOnCreate) {
            continue;
        }
        const fields = section.fields
            ? section.fields
            : section.groups
              ? section.groups.flatMap((g) => g.fields)
              : [];

        for (const field of fields) {
            if (field.type === 'separator' || !field.key) {
                continue;
            }
            defaults[field.key] = field.default;
        }
    }

    return defaults;
}

export function getAdvancedFields(schema: SettingsSection[]): SettingsField[] {
    const fields: SettingsField[] = [];

    for (const section of schema) {
        if (!section.advanced) {
            continue;
        }
        if (section.fields) {
            fields.push(
                ...section.fields.filter(
                    (f) => f.type !== 'separator' && f.key,
                ),
            );
        }
    }

    return fields;
}

export function buildEditDataFromSchema(
    server: Record<string, unknown>,
    schema: SettingsSection[],
): Record<string, unknown> {
    const data: Record<string, unknown> = {};

    for (const section of schema) {
        const fields = section.fields
            ? section.fields
            : section.groups
              ? section.groups.flatMap((g) => g.fields)
              : [];

        for (const field of fields) {
            if (field.type === 'separator' || !field.key) {
                continue;
            }

            const source = field.source ?? section.source;
            let value: unknown;

            if (!source || source === 'server') {
                value = server[field.key];
            } else {
                const sourceObj = server[source] as
                    | Record<string, unknown>
                    | undefined;
                value = sourceObj?.[field.key];
            }

            data[field.key] = value ?? field.default;
        }
    }

    return data;
}
