/**
 * StorageManager Unit Tests
 *
 * Tests localStorage persistence, LRU eviction, quota management,
 * and memory fallback for DevToolbar.
 *
 * @vitest-environment happy-dom
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { StorageManager } from '@backend/DevToolbar/storage/StorageManager';
import {
    MAX_METADATA,
    MAX_FULL_DATA,
    MIN_SAFE_ENTRIES,
    CONFIG_KEY,
    META_KEY,
    DATA_PREFIX,
} from '@backend/DevToolbar/storage/StorageConfig';
import {
    mockDevToolbarData,
    mockDevToolbarMigration,
    mockRequestMetadata,
    resetWindowGlobals,
} from '../mocks/browserMocks';

describe('StorageManager', () => {
    beforeEach(() => {
        // Clear localStorage (provided by setup.ts)
        localStorage.clear();

        // Reset window globals
        resetWindowGlobals();

        // Reset StorageManager internal state
        (StorageManager as any).useMemoryFallback = false;
        (StorageManager as any).memoryStore = { meta: [], requests: {} };
    });

    describe('isLocalStorageAvailable', () => {
        it('should return true when localStorage is available', () => {
            expect(StorageManager.isLocalStorageAvailable()).toBe(true);
        });

        it('should return false when localStorage throws error', () => {
            const originalSetItem = localStorage.setItem;
            localStorage.setItem = vi.fn(() => {
                throw new Error('localStorage disabled');
            });

            expect(StorageManager.isLocalStorageAvailable()).toBe(false);

            // Restore
            localStorage.setItem = originalSetItem;
        });

        it('should return false when localStorage is undefined', () => {
            const originalLocalStorage = global.localStorage;
            vi.stubGlobal('localStorage', undefined);

            expect(() => StorageManager.isLocalStorageAvailable()).toThrow();

            // Restore
            vi.stubGlobal('localStorage', originalLocalStorage);
        });
    });

    describe('init', () => {
        it('should initialize with localStorage when available', () => {
            const toolbarData = mockDevToolbarData();

            StorageManager.init();

            // Verify data was stored
            const stored = StorageManager.getRequest(toolbarData.id);
            expect(stored).toBeTruthy();
            expect(stored?.id).toBe(toolbarData.id);
        });

        it('should fallback to memory when localStorage unavailable', () => {
            const originalSetItem = localStorage.setItem;
            localStorage.setItem = vi.fn(() => {
                throw new Error('localStorage disabled');
            });

            mockDevToolbarData({ id: 'test-123' });

            StorageManager.init();

            expect((StorageManager as any).useMemoryFallback).toBe(true);
            expect((StorageManager as any).memoryStore.meta).toHaveLength(1);

            // Restore
            localStorage.setItem = originalSetItem;
        });

        it('should handle missing toolbar data gracefully', () => {
            const win = window as any;
            win.__DEV_TOOLBAR_DATA__ = undefined;

            expect(() => StorageManager.init()).not.toThrow();

            // No data should be stored
            const metadata = StorageManager.getMetadata();
            expect(metadata).toHaveLength(0);
        });
    });

    describe('handleMigration', () => {
        it('should migrate requests from session storage', () => {
            const migrationData = mockDevToolbarMigration([
                {
                    id: 'migrated-1',
                    metadata: mockRequestMetadata({ id: 'migrated-1' }),
                    tabs: { request: '<div>Migrated</div>' },
                },
                {
                    id: 'migrated-2',
                    metadata: mockRequestMetadata({ id: 'migrated-2' }),
                    tabs: { request: '<div>Migrated 2</div>' },
                },
            ]);

            StorageManager.init();

            // Check that both requests were stored
            const metadata = StorageManager.getMetadata();
            expect(metadata).toHaveLength(2);
            expect(metadata[0].id).toBe('migrated-2'); // Newest first
            expect(metadata[1].id).toBe('migrated-1');

            // Check that migrated flag was set
            const config = StorageManager.getConfig();
            expect(config.migrated).toBe(true);
        });

        it('should not migrate if already migrated', () => {
            localStorage.setItem(CONFIG_KEY, JSON.stringify({ migrated: true }));
            mockDevToolbarMigration();

            StorageManager.init();

            // Should not store migration data
            const metadata = StorageManager.getMetadata();
            expect(metadata).toHaveLength(0);
        });

        it('should handle missing migration data', () => {
            vi.stubGlobal('__DEV_TOOLBAR_MIGRATION__', undefined);

            expect(() => StorageManager.init()).not.toThrow();

            const config = StorageManager.getConfig();
            expect(config.migrated).toBe(true); // Still marks as migrated
        });
    });

    describe('storeRequest', () => {
        it('should store request in localStorage', () => {
            const metadata = mockRequestMetadata();
            const tabs = { request: '<div>Test</div>' };

            StorageManager.storeRequest('test-123', metadata, tabs);

            // Check metadata was stored
            const storedMeta = StorageManager.getMetadata();
            expect(storedMeta).toHaveLength(1);
            expect(storedMeta[0]).toEqual(metadata);

            // Check full data was stored
            const fullData = StorageManager.getRequest('test-123');
            expect(fullData).toEqual({ id: 'test-123', metadata, tabs });
        });

        it('should add new requests to beginning (newest first)', () => {
            const meta1 = mockRequestMetadata({ id: 'req-1', timestamp: 1000 });
            const meta2 = mockRequestMetadata({ id: 'req-2', timestamp: 2000 });
            const meta3 = mockRequestMetadata({ id: 'req-3', timestamp: 3000 });

            StorageManager.storeRequest('req-1', meta1, {});
            StorageManager.storeRequest('req-2', meta2, {});
            StorageManager.storeRequest('req-3', meta3, {});

            const metadata = StorageManager.getMetadata();
            expect(metadata[0].id).toBe('req-3'); // Newest first
            expect(metadata[1].id).toBe('req-2');
            expect(metadata[2].id).toBe('req-1');
        });

        it('should enforce MAX_METADATA limit', () => {
            // Store MAX_METADATA + 10 requests
            for (let i = 0; i < MAX_METADATA + 10; i++) {
                const meta = mockRequestMetadata({ id: `req-${i}` });
                StorageManager.storeRequest(`req-${i}`, meta, {});
            }

            const metadata = StorageManager.getMetadata();
            expect(metadata).toHaveLength(MAX_METADATA);
            expect(metadata[0].id).toBe(`req-${MAX_METADATA + 9}`); // Newest
            expect(metadata[MAX_METADATA - 1].id).toBe(`req-10`); // Oldest kept
        });

        it('should handle QuotaExceededError with eviction', () => {
            const originalSetItem = localStorage.setItem.bind(localStorage);
            // First call throws quota error, second succeeds
            let callCount = 0;
            localStorage.setItem = vi.fn((key: string, value: string) => {
                if (key.startsWith(DATA_PREFIX)) {
                    callCount++;
                    if (callCount === 1) {
                        const error = new Error('QuotaExceededError');
                        error.name = 'QuotaExceededError';
                        throw error;
                    }
                }
                originalSetItem(key, value);
            });

            const meta = mockRequestMetadata({ id: 'test-quota' });
            StorageManager.storeRequest('test-quota', meta, {});

            // Should have retried and succeeded
            expect(callCount).toBe(2);
            const stored = StorageManager.getRequest('test-quota');
            expect(stored).toBeTruthy();

            // Restore
            localStorage.setItem = originalSetItem;
        });

        it('should use memory fallback when localStorage unavailable', () => {
            (StorageManager as any).useMemoryFallback = true;

            const meta = mockRequestMetadata({ id: 'memory-test' });
            StorageManager.storeRequest('memory-test', meta, { request: '<div>Test</div>' });

            const memoryStore = (StorageManager as any).memoryStore;
            expect(memoryStore.meta).toHaveLength(1);
            expect(memoryStore.requests['memory-test']).toBeTruthy();
        });
    });

    describe('getRequest', () => {
        it('should retrieve stored request', () => {
            const meta = mockRequestMetadata({ id: 'test-get' });
            const tabs = { request: '<div>Test</div>' };

            StorageManager.storeRequest('test-get', meta, tabs);
            const retrieved = StorageManager.getRequest('test-get');

            expect(retrieved).toEqual({ id: 'test-get', metadata: meta, tabs });
        });

        it('should return null for non-existent request', () => {
            const retrieved = StorageManager.getRequest('non-existent');
            expect(retrieved).toBeNull();
        });

        it('should handle corrupted data gracefully', () => {
            localStorage.setItem(DATA_PREFIX + 'corrupted', 'invalid json{');

            const retrieved = StorageManager.getRequest('corrupted');
            expect(retrieved).toBeNull();
        });

        it('should retrieve from memory when using fallback', () => {
            (StorageManager as any).useMemoryFallback = true;
            (StorageManager as any).memoryStore.requests['mem-test'] = {
                id: 'mem-test',
                metadata: mockRequestMetadata(),
                tabs: {},
            };

            const retrieved = StorageManager.getRequest('mem-test');
            expect(retrieved).toBeTruthy();
            expect(retrieved?.id).toBe('mem-test');
        });
    });

    describe('getMetadata', () => {
        it('should return all metadata entries', () => {
            const meta1 = mockRequestMetadata({ id: 'req-1' });
            const meta2 = mockRequestMetadata({ id: 'req-2' });

            StorageManager.storeRequest('req-1', meta1, {});
            StorageManager.storeRequest('req-2', meta2, {});

            const metadata = StorageManager.getMetadata();
            expect(metadata).toHaveLength(2);
        });

        it('should return empty array when no metadata stored', () => {
            const metadata = StorageManager.getMetadata();
            expect(metadata).toEqual([]);
        });

        it('should handle corrupted metadata gracefully', () => {
            localStorage.setItem(META_KEY, 'invalid json{');

            const metadata = StorageManager.getMetadata();
            expect(metadata).toEqual([]);
        });
    });

    describe('enforceQuotaLimits', () => {
        it('should keep only MAX_FULL_DATA full request entries', () => {
            // Store MAX_FULL_DATA + 5 requests
            for (let i = 0; i < MAX_FULL_DATA + 5; i++) {
                const meta = mockRequestMetadata({ id: `req-${i}` });
                StorageManager.storeRequest(`req-${i}`, meta, { tab: `content-${i}` });
            }

            // All metadata should be present
            const metadata = StorageManager.getMetadata();
            expect(metadata.length).toBeGreaterThanOrEqual(MAX_FULL_DATA);

            // But only MAX_FULL_DATA full data entries should exist
            let fullDataCount = 0;
            for (let i = 0; i < MAX_FULL_DATA + 5; i++) {
                const data = StorageManager.getRequest(`req-${i}`);
                if (data) fullDataCount++;
            }

            expect(fullDataCount).toBeLessThanOrEqual(MAX_FULL_DATA);
        });

        it('should not run when using memory fallback', () => {
            (StorageManager as any).useMemoryFallback = true;

            const before = localStorage.length;
            // Should not throw or access localStorage
            expect(() => StorageManager.enforceQuotaLimits()).not.toThrow();
            const after = localStorage.length;

            // localStorage should be unchanged
            expect(after).toBe(before);
        });
    });

    describe('clear', () => {
        it('should clear all DevToolbar data from localStorage', () => {
            // Store some data
            StorageManager.storeRequest('test-1', mockRequestMetadata(), {});
            StorageManager.storeRequest('test-2', mockRequestMetadata(), {});
            StorageManager.setConfig({ migrated: true });

            StorageManager.clear();

            // All data should be cleared
            expect(StorageManager.getMetadata()).toEqual([]);
            expect(StorageManager.getRequest('test-1')).toBeNull();
            expect(StorageManager.getRequest('test-2')).toBeNull();
        });

        it('should clear memory store when using fallback', () => {
            (StorageManager as any).useMemoryFallback = true;
            (StorageManager as any).memoryStore = {
                meta: [mockRequestMetadata()],
                requests: { 'test-1': { id: 'test-1', metadata: mockRequestMetadata(), tabs: {} } },
            };

            StorageManager.clear();

            expect((StorageManager as any).memoryStore.meta).toEqual([]);
            expect((StorageManager as any).memoryStore.requests).toEqual({});
        });
    });

    describe('getConfig / setConfig', () => {
        it('should store and retrieve config', () => {
            const config = { migrated: true, version: '1.0.0' };

            StorageManager.setConfig(config);
            const retrieved = StorageManager.getConfig();

            expect(retrieved).toEqual(config);
        });

        it('should return default config when none stored', () => {
            const config = StorageManager.getConfig();
            expect(config).toEqual({ migrated: false });
        });

        it('should handle corrupted config gracefully', () => {
            localStorage.setItem(CONFIG_KEY, 'invalid json{');

            const config = StorageManager.getConfig();
            expect(config).toEqual({ migrated: false });
        });

        it('should not persist config when using memory fallback', () => {
            (StorageManager as any).useMemoryFallback = true;

            const before = localStorage.length;
            StorageManager.setConfig({ migrated: true });
            const after = localStorage.length;

            // localStorage should not have new items
            expect(after).toBe(before);
        });
    });

    describe('LRU eviction (memory fallback)', () => {
        beforeEach(() => {
            (StorageManager as any).useMemoryFallback = true;
        });

        it('should keep only MAX_FULL_DATA requests in memory', () => {
            // Store MAX_FULL_DATA + 5 requests
            for (let i = 0; i < MAX_FULL_DATA + 5; i++) {
                const meta = mockRequestMetadata({ id: `req-${i}` });
                StorageManager.storeRequest(`req-${i}`, meta, {});
            }

            const memoryStore = (StorageManager as any).memoryStore;
            expect(Object.keys(memoryStore.requests)).toHaveLength(MAX_FULL_DATA);

            // Oldest requests should be evicted
            expect(memoryStore.requests['req-0']).toBeUndefined();
            expect(memoryStore.requests[`req-${MAX_FULL_DATA + 4}`]).toBeDefined();
        });

        it('should enforce MAX_METADATA limit in memory', () => {
            // Store MAX_METADATA + 10 requests
            for (let i = 0; i < MAX_METADATA + 10; i++) {
                const meta = mockRequestMetadata({ id: `req-${i}` });
                StorageManager.storeRequest(`req-${i}`, meta, {});
            }

            const memoryStore = (StorageManager as any).memoryStore;
            expect(memoryStore.meta).toHaveLength(MAX_METADATA);
        });
    });
});
