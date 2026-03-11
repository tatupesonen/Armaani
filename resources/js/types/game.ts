// Server type is a discriminated union generated from game handler schemas.
// Narrow on `server.game_type` to access game-specific settings.
// Regenerate with: php artisan game:generate-types
export type {
    Server,
    ServerBase,
    Arma3Settings,
    ReforgerSettings,
    Arma3Server,
    ReforgerServer,
    DayzServer,
} from './generated';
import type { Server } from './generated';

export type GameType = string;

export type ServerStatus =
    | 'stopped'
    | 'starting'
    | 'booting'
    | 'downloading_mods'
    | 'running'
    | 'stopping'
    | 'crashed';

export type InstallationStatus =
    | 'queued'
    | 'installing'
    | 'installed'
    | 'failed';

export type GameInstall = {
    id: number;
    game_type: GameType;
    name: string;
    branch: string;
    build_id: string | null;
    installation_status: InstallationStatus;
    progress_pct: number;
    disk_size_bytes: number | null;
    installation_path?: string;
    installed_at: string | null;
    created_at: string;
    updated_at: string;
    servers?: Server[];
};

export type WorkshopMod = {
    id: number;
    game_type: GameType;
    workshop_id: number;
    name: string;
    file_size: number | null;
    installation_status: InstallationStatus;
    progress_pct: number;
    installed_at: string | null;
    steam_updated_at: string | null;
    created_at: string;
    updated_at: string;
    is_outdated?: boolean;
    presets?: ModPreset[];
};

export type ReforgerMod = {
    id: number;
    mod_id: string;
    name: string;
    created_at: string;
    updated_at: string;
    presets?: ModPreset[];
};

export type ModPreset = {
    id: number;
    game_type: GameType;
    name: string;
    created_at: string;
    updated_at: string;
    mods?: WorkshopMod[];
    mods_count?: number;
    total_mod_count?: number;
    servers_count?: number;
};

export type SteamAccount = {
    id: number;
    username: string;
    mod_download_batch_size: number;
    created_at: string;
    updated_at: string;
};

export type ServerBackup = {
    id: number;
    server_id: number;
    name: string;
    file_size: number | null;
    is_automatic: boolean;
    data: string | null;
    created_at: string;
    updated_at: string;
    server?: Server;
};

// Game-specific settings types are dynamic — the schema-driven renderer reads
// from the server object using the `source` key defined in each handler's
// settingsSchema(). These types exist only for backward compatibility and
// developer convenience; the frontend does not branch on them.

export type ModSection = {
    type: 'workshop' | 'registered';
    label: string;
    relationship: string;
    formField: string;
};

export type GameModSections = {
    gameType: GameType;
    gameLabel: string;
    sections: ModSection[];
};

export type RegisteredMod = {
    id: number;
    mod_id: string;
    name: string;
    [key: string]: unknown;
};

export type GameTypeInfo = {
    value: GameType;
    label: string;
    branches: string[];
    defaultName: string;
};

// --- Settings Schema Types ---

export type SettingsFieldOption = {
    value: string;
    label: string;
};

export type SettingsField = {
    key?: string;
    label?: string;
    type:
        | 'toggle'
        | 'number'
        | 'text'
        | 'textarea'
        | 'segmented'
        | 'separator'
        | 'custom';
    default?: string | number | boolean;
    description?: string;
    source?: string;
    halfWidth?: boolean;
    options?: SettingsFieldOption[];
    min?: number;
    max?: number;
    step?: number;
    storeAsString?: boolean;
    inputMode?: string;
    placeholder?: string;
    required?: boolean;
    rows?: number;
    component?: string;
};

export type SettingsPreset = {
    label: string;
    variant: 'ghost' | 'default';
    icon: 'reset' | 'zap';
    values: Record<string, string | number | boolean>;
};

export type SettingsFieldGroup = {
    columns?: number;
    fields: SettingsField[];
};

export type SettingsSection = {
    title?: string;
    description?: string;
    collapsible?: boolean;
    showOnCreate?: boolean;
    createLabel?: string;
    source?: string;
    layout?: 'columns' | 'rows';
    advanced?: boolean;
    presets?: SettingsPreset[];
    fields?: SettingsField[];
    groups?: SettingsFieldGroup[];
};

export type ServerGameTypeOption = {
    value: GameType;
    label: string;
    defaultPort: number;
    defaultQueryPort: number;
    supportsHeadlessClients: boolean;
    supportsWorkshopMods: boolean;
    supportsMissionUpload: boolean;
    supportsAutoRestart: boolean;
    settingsSchema: SettingsSection[];
    modSections: ModSection[];
};

export type Mission = {
    name: string;
    size: number;
    modified_at: string;
};

export type DashboardServerStats = {
    total: number;
    running: number;
    stopped: number;
};

export type DashboardGameInstallStats = {
    total: number;
    installed: number;
    disk_size: number;
};

export type DashboardModStats = {
    total: number;
    installed: number;
    total_size: number;
};

export type DashboardQueueStats = {
    pending: number;
    failed: number;
};

export type DiskUsage = {
    total: number;
    used: number;
    free: number;
    percent: number;
};

export type MemoryUsage = {
    total: number;
    used: number;
    free: number;
    percent: number;
};

export type CpuInfo = {
    load_1: number;
    load_5: number;
    load_15: number;
    cores: number;
    percent: number;
};
