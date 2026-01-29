/**
 * StorageManager - Handles localStorage persistence for DevToolbar
 *
 * Stores up to 50 lightweight metadata entries and 20 full request data entries.
 * Implements LRU eviction and automatic quota management.
 * Falls back to in-memory storage when localStorage is unavailable (private browsing).
 */

import type {
    RequestMetadata,
    RequestData,
    DevToolbarConfig,
    DevToolbarWindow,
    DevToolbarMigration,
} from '../types/index.js';

import {
    MAX_METADATA,
    MAX_FULL_DATA,
    MIN_SAFE_ENTRIES,
    CONFIG_KEY,
    META_KEY,
    DATA_PREFIX,
} from './StorageConfig.js';

/**
 * In-memory storage fallback structure
 */
interface MemoryStore {
    meta: RequestMetadata[];
    requests: Record<string, RequestData>;
}

/**
 * StorageManager singleton class
 */
class StorageManagerClass {
    private useMemoryFallback = false;
    private memoryStore: MemoryStore = {
        meta: [],
        requests: {},
    };

    /**
     * Initialize storage, handle migration, store current request
     */
    init(): void {
        // Test localStorage availability
        if (!this.isLocalStorageAvailable()) {
            console.warn('[DevToolbar] localStorage unavailable, using in-memory storage');
            this.useMemoryFallback = true;
        }

        // Handle one-time migration from session storage
        this.handleMigration();

        // Store current request data
        const win = window as DevToolbarWindow;
        if (win.__DEV_TOOLBAR_DATA__) {
            const { id, metadata, tabs } = win.__DEV_TOOLBAR_DATA__;
            this.storeRequest(id, metadata, tabs);
        }
    }

    /**
     * Check if localStorage is available
     * Returns false in private browsing mode or when disabled
     */
    isLocalStorageAvailable(): boolean {
        try {
            const testKey = '__devToolbarTest__';
            localStorage.setItem(testKey, '1');
            localStorage.removeItem(testKey);
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Handle one-time migration from session storage
     * Migration data is injected by PHP via window.__DEV_TOOLBAR_MIGRATION__
     */
    handleMigration(): void {
        const config = this.getConfig();

        if (config.migrated) {
            return; // Already migrated
        }

        // Check for migration data injected by PHP
        const win = window as DevToolbarWindow;
        if (win.__DEV_TOOLBAR_MIGRATION__ && Array.isArray(win.__DEV_TOOLBAR_MIGRATION__)) {
            console.log('[DevToolbar] Migrating', win.__DEV_TOOLBAR_MIGRATION__.length, 'requests from session');

            win.__DEV_TOOLBAR_MIGRATION__.forEach((request: DevToolbarMigration) => {
                this.storeRequest(request.id, request.metadata, request.tabs);
            });

            console.log('[DevToolbar] Migration completed');
        }

        // Mark as migrated
        config.migrated = true;
        this.setConfig(config);
    }

    /**
     * Store request with quota handling
     *
     * @param id Request ID
     * @param metadata Lightweight metadata
     * @param tabs Full tab HTML content
     */
    storeRequest(id: string, metadata: RequestMetadata, tabs: Record<string, string>): void {
        console.log('[StorageManager] Storing request:', id, 'useMemoryFallback:', this.useMemoryFallback);

        try {
            if (this.useMemoryFallback) {
                this.storeInMemory(id, metadata, tabs);
                console.log('[StorageManager] Stored in memory, total:', this.memoryStore.meta.length);
                return;
            }

            // Store metadata
            const metaArray = this.getMetadata();
            metaArray.unshift(metadata); // Add to beginning (newest first)

            // Enforce metadata limit
            if (metaArray.length > MAX_METADATA) {
                metaArray.length = MAX_METADATA;
            }

            localStorage.setItem(META_KEY, JSON.stringify(metaArray));

            // Store full data
            const fullData: RequestData = { id, metadata, tabs };
            localStorage.setItem(DATA_PREFIX + id, JSON.stringify(fullData));

            // Enforce quota limits
            this.enforceQuotaLimits();
        } catch (e) {
            if ((e as Error).name === 'QuotaExceededError') {
                console.warn('[DevToolbar] Quota exceeded, evicting oldest entries');
                this.evictOldest();

                // Retry
                try {
                    const metaArray = this.getMetadata();
                    metaArray.unshift(metadata);
                    if (metaArray.length > MAX_METADATA) {
                        metaArray.length = MAX_METADATA;
                    }
                    localStorage.setItem(META_KEY, JSON.stringify(metaArray));
                    localStorage.setItem(DATA_PREFIX + id, JSON.stringify({ id, metadata, tabs }));
                } catch (retryError) {
                    console.error('[DevToolbar] Failed to store after eviction:', retryError);
                }
            } else {
                console.error('[DevToolbar] Storage error:', e);
            }
        }
    }

    /**
     * Store in memory (private browsing fallback)
     */
    private storeInMemory(id: string, metadata: RequestMetadata, tabs: Record<string, string>): void {
        this.memoryStore.meta.unshift(metadata);
        if (this.memoryStore.meta.length > MAX_METADATA) {
            this.memoryStore.meta.length = MAX_METADATA;
        }

        this.memoryStore.requests[id] = { id, metadata, tabs };

        // Evict old full data
        const ids = this.memoryStore.meta.map((m) => m.id);
        const keysToKeep = ids.slice(0, MAX_FULL_DATA);

        for (const key in this.memoryStore.requests) {
            if (!keysToKeep.includes(key)) {
                delete this.memoryStore.requests[key];
            }
        }
    }

    /**
     * Retrieve full request data
     *
     * @param id Request ID
     * @return Request data or null
     */
    getRequest(id: string): RequestData | null {
        try {
            if (this.useMemoryFallback) {
                return this.memoryStore.requests[id] || null;
            }

            const data = localStorage.getItem(DATA_PREFIX + id);
            return data ? (JSON.parse(data) as RequestData) : null;
        } catch (e) {
            console.error('[DevToolbar] Failed to retrieve request:', e);
            return null;
        }
    }

    /**
     * Get all metadata entries
     *
     * @return Array of metadata objects
     */
    getMetadata(): RequestMetadata[] {
        try {
            if (this.useMemoryFallback) {
                return this.memoryStore.meta;
            }

            const data = localStorage.getItem(META_KEY);
            return data ? (JSON.parse(data) as RequestMetadata[]) : [];
        } catch (e) {
            console.error('[DevToolbar] Failed to retrieve metadata:', e);
            return [];
        }
    }

    /**
     * Enforce quota limits - keep only MAX_FULL_DATA entries
     */
    enforceQuotaLimits(): void {
        if (this.useMemoryFallback) {
            return;
        }

        const metaArray = this.getMetadata();
        const fullDataIds = metaArray.slice(0, MAX_FULL_DATA).map((m) => m.id);

        // Find all stored request keys
        const keysToDelete: string[] = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith(DATA_PREFIX)) {
                const id = key.substring(DATA_PREFIX.length);
                if (!fullDataIds.includes(id)) {
                    keysToDelete.push(key);
                }
            }
        }

        // Delete old entries
        keysToDelete.forEach((key) => {
            try {
                localStorage.removeItem(key);
            } catch (e) {
                console.error('[DevToolbar] Failed to remove key:', key, e);
            }
        });

        if (keysToDelete.length > 0) {
            console.log('[DevToolbar] Evicted', keysToDelete.length, 'old request entries');
        }
    }

    /**
     * Emergency eviction - delete oldest entries until under MIN_SAFE_ENTRIES
     */
    private evictOldest(): void {
        if (this.useMemoryFallback) {
            return;
        }

        const metaArray = this.getMetadata();

        // Keep only MIN_SAFE_ENTRIES newest metadata
        if (metaArray.length > MIN_SAFE_ENTRIES) {
            metaArray.length = MIN_SAFE_ENTRIES;
            localStorage.setItem(META_KEY, JSON.stringify(metaArray));
        }

        const idsToKeep = metaArray.slice(0, MIN_SAFE_ENTRIES).map((m) => m.id);

        // Delete all request data except the safe entries
        const keysToDelete: string[] = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith(DATA_PREFIX)) {
                const id = key.substring(DATA_PREFIX.length);
                if (!idsToKeep.includes(id)) {
                    keysToDelete.push(key);
                }
            }
        }

        keysToDelete.forEach((key) => {
            try {
                localStorage.removeItem(key);
            } catch (e) {
                console.error('[DevToolbar] Failed to remove key during eviction:', key, e);
            }
        });

        console.log('[DevToolbar] Emergency eviction: removed', keysToDelete.length, 'entries');
    }

    /**
     * Clear all DevToolbar data
     */
    clear(): void {
        if (this.useMemoryFallback) {
            this.memoryStore = { meta: [], requests: {} };
            return;
        }

        try {
            const keysToDelete: string[] = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && (key.startsWith('devToolbar.') || key.startsWith(DATA_PREFIX))) {
                    keysToDelete.push(key);
                }
            }

            keysToDelete.forEach((key) => localStorage.removeItem(key));
            console.log('[DevToolbar] Cleared all data');
        } catch (e) {
            console.error('[DevToolbar] Failed to clear data:', e);
        }
    }

    /**
     * Get config object
     */
    getConfig(): DevToolbarConfig {
        if (this.useMemoryFallback) {
            return { migrated: false };
        }

        try {
            const config = localStorage.getItem(CONFIG_KEY);
            return config ? (JSON.parse(config) as DevToolbarConfig) : { migrated: false };
        } catch (e) {
            return { migrated: false };
        }
    }

    /**
     * Set config object
     */
    setConfig(config: DevToolbarConfig): void {
        if (this.useMemoryFallback) {
            return;
        }

        try {
            localStorage.setItem(CONFIG_KEY, JSON.stringify(config));
        } catch (e) {
            console.error('[DevToolbar] Failed to save config:', e);
        }
    }
}

// Export singleton instance
export const StorageManager = new StorageManagerClass();
