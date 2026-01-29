/**
 * Test setup for DevToolbar browser environment
 *
 * Provides browser globals (localStorage, window, document) for unit tests.
 * This setup file runs before all tests in the suite.
 */

import { vi } from 'vitest';

// Note: This setup file provides a base localStorage implementation.
// Individual tests override this with mockLocalStorage() from browserMocks.ts
// which provides spies and more control.

// Create minimal but functional localStorage implementation
class LocalStorageMock {
    private store: Record<string, string> = {};

    getItem(key: string): string | null {
        return this.store[key] || null;
    }

    setItem(key: string, value: string): void {
        this.store[key] = String(value);
    }

    removeItem(key: string): void {
        delete this.store[key];
    }

    clear(): void {
        this.store = {};
    }

    get length(): number {
        return Object.keys(this.store).length;
    }

    key(index: number): string | null {
        const keys = Object.keys(this.store);
        return keys[index] !== undefined ? keys[index] : null;
    }
}

// Create localStorage instance
const localStorageInstance = new LocalStorageMock();

// Stub global browser APIs
vi.stubGlobal('localStorage', localStorageInstance);

// Create minimal window object
vi.stubGlobal('window', {
    localStorage: localStorageInstance,
    __DEV_TOOLBAR_DATA__: undefined,
    __XDEBUG_CONFIG__: undefined,
    __DEV_TOOLBAR_MIGRATION__: undefined,
});

// Create minimal document object
vi.stubGlobal('document', {
    querySelector: vi.fn(),
    querySelectorAll: vi.fn(() => []),
    createElement: vi.fn((tag: string) => ({
        tagName: tag.toUpperCase(),
        setAttribute: vi.fn(),
        getAttribute: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        closest: vi.fn(),
        classList: {
            add: vi.fn(),
            remove: vi.fn(),
            toggle: vi.fn(),
            contains: vi.fn(),
        },
        dataset: {},
        style: {},
        innerHTML: '',
        textContent: '',
        appendChild: vi.fn(),
        removeChild: vi.fn(),
    })),
});
