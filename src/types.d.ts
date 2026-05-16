declare global {
    interface Window {
        FastCloudWP: FastCloudData
        __fastcloudwpI18n?: Record<string, string[]>
    }
}

export interface Settings {
    enabled: boolean;
    autosync: boolean;
    delete_media: boolean;
    remove_original: boolean;
}

export interface Storage {
    total: number;
    used: number;
    free: number;
    exceeded: boolean;
    percent_used: number;
    last_sync: string | null;
}

export interface State {
    connected: boolean;
    uuid: string | null;
    name: string | null;
    short_id: string | null;
    settings: Settings;
    storage: Storage;
    sitekey: string;
    domain: string;
    cdn: string;
    custom_domain: string;
}

export interface Statistics {
    total: number;
    queued: number;
    offloaded: number;
    deleted: number;
    pending_delete: number
    missing: number;
    offloaded_progress: number;
    deleted_progress: number;
    quota_exceeded: number;
}

export type LogLevel = 'debug' | 'info' | 'success' | 'warning' | 'error';

export interface Log {
    id: number;
    level: LogLevel;
    source: string;
    message: string;
    context: Record<string, unknown> | null;
    created_at: string;
}

export interface FastCloudData {
    isSaving: boolean;
    nonce: string;
    state: State;
    statistics: Statistics;
    logs: Log[];
}

export interface NavLink {
    to: string;
    title: string;
    active?: boolean;
}

export interface ApiStateResponse {
    success: boolean;
    state: FastCloudData;
    error?: string;
}

export interface ApiSettingsResponse {
    success: boolean;
    settings: Settings;
}

export interface ApiOffloadBatchResponse {
    success: boolean;
    queued: number;
    left: number;
    quota_exceeded: number;
    done: boolean;
}

export interface ApiFreeSpaceResponse {
    success: boolean;
    deleted: number;
    failed: number;
    remaining: number;
    done?: boolean;
}
