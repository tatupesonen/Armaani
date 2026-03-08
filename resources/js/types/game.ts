export type GameType = 'arma3' | 'reforger' | 'dayz';

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

export type Server = {
    id: number;
    game_type: GameType;
    name: string;
    port: number;
    query_port: number;
    max_players: number;
    password: string | null;
    admin_password: string | null;
    description: string | null;
    active_preset_id: number | null;
    game_install_id: number | null;
    status: ServerStatus;
    auto_restart: boolean;
    additional_params: string | null;
    verify_signatures: boolean;
    allowed_file_patching: boolean;
    battle_eye: boolean;
    persistent: boolean;
    von_enabled: boolean;
    additional_server_options: string | null;
    supports_backups?: boolean;
    profiles_path?: string;
    created_at: string;
    updated_at: string;
    game_install?: GameInstall;
    active_preset?: ModPreset;
    backups?: ServerBackup[];
    difficulty_settings?: DifficultySettings;
    network_settings?: NetworkSettings;
    reforger_settings?: ReforgerSettings;
    dayz_settings?: DayZSettings;
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
    reforger_mods?: ReforgerMod[];
    mods_count?: number;
    reforger_mods_count?: number;
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

export type DifficultySettings = {
    id: number;
    server_id: number;
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
};

export type NetworkSettings = {
    id: number;
    server_id: number;
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

export type ReforgerSettings = {
    id: number;
    server_id: number;
    scenario_id: string | null;
    third_person_view_enabled: boolean;
    max_fps: number;
    cross_platform: boolean;
};

export type DayZSettings = {
    id: number;
    server_id: number;
    [key: string]: unknown;
};

export type GameTypeInfo = {
    value: GameType;
    label: string;
    branches: string[];
    defaultName: string;
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
