/**
 * DevToolbar Type Definitions
 *
 * TypeScript interfaces for DevToolbar data structures, window globals,
 * and storage models.
 */

/**
 * Request metadata stored for history navigation
 * Lightweight entries (up to 50 stored)
 *
 * Note: Field names match PHP's extractMetadata() output in DataInjectionRenderer
 */
export interface RequestMetadata {
    id: string;
    method: string;
    uri: string;
    status: number;           // PHP: status_code
    timestamp: number;
    time: number;             // PHP: execution_time (in ms)
    memory: number;           // PHP: memory_peak (in bytes)
    query_count: number;      // PHP: query count from queries collector
    badge_counts?: Record<string, number>; // PHP: badge counts per tab
}

/**
 * Full request data with tab content
 * Heavy entries (up to 20 stored)
 */
export interface RequestData {
    id: string;
    metadata: RequestMetadata;
    tabs: Record<string, string>;
}

/**
 * DevToolbar configuration stored in localStorage
 */
export interface DevToolbarConfig {
    migrated: boolean;
    version?: string;
}

/**
 * Xdebug configuration injected by PHP
 */
export interface XdebugConfig {
    enabled: boolean;
    mode: string;
    idekey: string;
    client_host: string;
    client_port: number;
}

/**
 * Main DevToolbar data injected by PHP via window global
 */
export interface DevToolbarData {
    id: string;
    metadata: RequestMetadata;
    tabs: Record<string, string>;
}

/**
 * Migration data for session storage transition
 */
export interface DevToolbarMigration extends RequestData {}

/**
 * Extended Window interface with DevToolbar globals
 */
export interface DevToolbarWindow extends Window {
    __DEV_TOOLBAR_DATA__?: DevToolbarData;
    __XDEBUG_CONFIG__?: XdebugConfig;
    __DEV_TOOLBAR_MIGRATION__?: DevToolbarMigration[];
}

/**
 * Storage entry format for in-memory fallback
 */
export interface StorageEntry {
    metadata: RequestMetadata;
    fullData?: RequestData;
}

/**
 * Type guard to check if window has DevToolbar data
 */
export function isDevToolbarWindow(win: Window): win is DevToolbarWindow {
    return '__DEV_TOOLBAR_DATA__' in win;
}

/**
 * Type guard to check if window has Xdebug config
 */
export function hasXdebugConfig(win: Window): win is DevToolbarWindow & { __XDEBUG_CONFIG__: XdebugConfig } {
    return '__XDEBUG_CONFIG__' in win && win.__XDEBUG_CONFIG__ !== undefined;
}

/**
 * Tab names used in DevToolbar
 */
export type TabName = 'request' | 'database' | 'performance' | 'xdebug' | 'history';

/**
 * Event types for DevToolbar
 */
export type DevToolbarEventType = 'tab-change' | 'request-load' | 'storage-clear';
