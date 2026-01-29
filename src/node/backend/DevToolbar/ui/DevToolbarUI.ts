/**
 * DevToolbarUI - Main UI controller
 *
 * Orchestrates all DevToolbar UI components:
 * - Panel open/close/maximize
 * - Tab management (via TabManager)
 * - Request switching (via RequestSwitcher)
 * - Xdebug controls (via XdebugControls)
 * - Event listeners and keyboard shortcuts
 */

import { StorageManager } from '../storage/StorageManager';
import { TabManager } from './TabManager';
import { RequestSwitcher } from './RequestSwitcher';
import { XdebugControls } from './XdebugControls';
import { HistoryTabManager } from './HistoryTabManager';
import { exportRequestAsJson, downloadJson } from '../utils/exportUtils';

/**
 * DevToolbarUI singleton - main controller
 */
export class DevToolbarUI {
    private miniBar: HTMLElement | null = null;
    private panel: HTMLElement | null = null;

    private tabManager: TabManager;
    private requestSwitcher: RequestSwitcher;
    private xdebugControls: XdebugControls;
    private historyTabManager: HistoryTabManager;

    constructor() {
        this.tabManager = new TabManager();
        this.requestSwitcher = new RequestSwitcher();
        this.xdebugControls = new XdebugControls();
        this.historyTabManager = new HistoryTabManager();
    }

    /**
     * Initialize DevToolbar UI
     */
    init(): void {
        console.log('[DevToolbar] Initializing UI...');

        // Initialize storage FIRST - migrate and store current request
        StorageManager.init();

        // Initialize tab manager
        this.tabManager.init();

        // Find DOM elements
        this.miniBar = document.querySelector('.dev-toolbar-mini');
        this.panel = document.querySelector('.dev-toolbar-panel');

        if (!this.miniBar || !this.panel) {
            console.error('[DevToolbar] Mini bar or panel not found');
            return;
        }

        // Update history badge with count from localStorage
        this.updateHistoryBadge();

        // Restore panel state from localStorage
        this.restoreState();

        // Attach event listeners
        this.attachEventListeners();

        // Initialize request switcher
        this.requestSwitcher.init((requestId) => this.handleRequestLoad(requestId));

        // Set active tab (will trigger tab-specific initialization)
        this.tabManager.setActiveTab(this.tabManager.getCurrentTab(), (tabName) =>
            this.handleTabActivate(tabName)
        );

        console.log('[DevToolbar] UI initialization complete');
    }

    /**
     * Attach all event listeners
     */
    private attachEventListeners(): void {
        // Mini bar toggle
        this.miniBar?.addEventListener('click', () => {
            this.togglePanel();
        });

        // Tab clicks
        document.querySelectorAll('.dev-toolbar-panel-tab').forEach((tab) => {
            tab.addEventListener('click', (e) => {
                const tabName = (e.target as HTMLElement).closest('.dev-toolbar-panel-tab')?.getAttribute('data-tab');
                if (tabName) {
                    this.tabManager.setActiveTab(tabName, (name) => this.handleTabActivate(name));
                }
            });
        });

        // Close button
        const closeBtn = document.querySelector('.dev-toolbar-panel-close');
        closeBtn?.addEventListener('click', () => {
            this.closePanel();
        });

        // Maximize button
        const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
        maximizeBtn?.addEventListener('click', () => {
            this.toggleMaximize();
        });

        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.panel?.classList.contains('open')) {
                this.closePanel();
            }
        });

        // Prevent background scrolling
        this.preventBackgroundScroll();

        // Attach alert handlers
        this.attachAlertHandlers();

        // Attach Xdebug handlers
        this.attachXdebugHandlers();
    }

    /**
     * Handle tab activation
     */
    private handleTabActivate(tabName: string): void {
        console.log('[DevToolbarUI] Tab activated:', tabName);

        // Render Xdebug controls when REQUEST tab becomes active
        if (tabName === 'request') {
            setTimeout(() => this.xdebugControls.render(), 50);
            setTimeout(() => this.attachXdebugHandlers(), 100);
        }

        // Initialize HISTORY tab when it becomes active
        if (tabName === 'history') {
            setTimeout(() => this.initHistoryTab(), 50);
        }
    }

    /**
     * Handle request load (historical or current)
     */
    private handleRequestLoad(requestId: string): void {
        console.log('[DevToolbarUI] Request loaded:', requestId);

        // Special case: Switch to history tab
        if (requestId === 'history') {
            this.tabManager.setActiveTab('history', (name) => this.handleTabActivate(name));
            return;
        }

        // Reset history tab so it re-initializes on next activation
        this.historyTabManager.reset();

        // Re-attach tab event listeners
        setTimeout(() => {
            this.reattachTabListeners();

            // Restore active tab
            const currentTab = this.tabManager.getCurrentTab();
            this.tabManager.setActiveTab(currentTab, (name) => this.handleTabActivate(name));

            // Update tab badges
            if (requestId !== 'current') {
                const requestData = StorageManager.getRequest(requestId);
                if (requestData?.metadata?.badge_counts) {
                    this.tabManager.updateBadgeCounts(requestData.metadata.badge_counts);
                }
            } else {
                // Restore original badge counts
                const originalBadges = this.requestSwitcher.getOriginalBadgeCounts();
                if (originalBadges) {
                    this.tabManager.updateBadgeCounts(originalBadges);
                }
            }
        }, 50);
    }

    /**
     * Re-attach tab listeners after content replacement
     */
    private reattachTabListeners(): void {
        console.log('[DevToolbarUI] Re-attaching tab event listeners');

        const tabButtons = document.querySelectorAll('.dev-toolbar-panel-tab');
        console.log('[DevToolbarUI] Found', tabButtons.length, 'tab buttons');

        tabButtons.forEach((tab) => {
            // Clone and replace to remove old listeners
            const newTab = tab.cloneNode(true) as HTMLElement;
            tab.parentNode?.replaceChild(newTab, tab);

            // Add new listener
            newTab.addEventListener('click', () => {
                const tabName = newTab.dataset.tab;
                if (tabName) {
                    console.log('[DevToolbarUI] Tab clicked:', tabName);
                    this.tabManager.setActiveTab(tabName, (name) => this.handleTabActivate(name));
                }
            });
        });

        // Re-render Xdebug controls
        this.xdebugControls.render();
        this.attachXdebugHandlers();
    }

    /**
     * Toggle panel open/close
     */
    private togglePanel(): void {
        if (this.panel?.classList.contains('open')) {
            this.closePanel();
        } else {
            this.openPanel();
        }
    }

    /**
     * Open panel
     */
    private openPanel(): void {
        this.panel?.classList.add('open');
        try {
            localStorage.setItem('devToolbar.open', '1');
        } catch (e) {
            console.warn('[DevToolbar] Could not save open state', e);
        }
    }

    /**
     * Close panel
     */
    private closePanel(): void {
        this.panel?.classList.remove('open');
        try {
            localStorage.setItem('devToolbar.open', '0');
        } catch (e) {
            console.warn('[DevToolbar] Could not save open state', e);
        }
    }

    /**
     * Toggle maximize
     */
    private toggleMaximize(): void {
        this.panel?.classList.toggle('maximized');
        const isMaximized = this.panel?.classList.contains('maximized');

        try {
            localStorage.setItem('devToolbar.maximized', isMaximized ? '1' : '0');
        } catch (e) {
            console.warn('[DevToolbar] Could not save maximized state', e);
        }

        // Update maximize button
        const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
        if (maximizeBtn) {
            maximizeBtn.setAttribute('title', isMaximized ? 'Restore' : 'Maximize');
        }
    }

    /**
     * Restore panel state from localStorage
     */
    private restoreState(): void {
        try {
            const isMaximized = localStorage.getItem('devToolbar.maximized') === '1';
            if (isMaximized) {
                this.panel?.classList.add('maximized');
            }

            const isOpen = localStorage.getItem('devToolbar.open') === '1';
            if (isOpen) {
                this.panel?.classList.add('open');
            }
        } catch (e) {
            console.warn('[DevToolbar] Could not restore state', e);
        }
    }

    /**
     * Prevent background page scrolling
     */
    private preventBackgroundScroll(): void {
        this.panel?.addEventListener(
            'wheel',
            (e) => {
                e.stopPropagation();

                const content = document.querySelector('.dev-toolbar-panel-content');
                if (content && content.contains(e.target as Node)) {
                    const atTop = content.scrollTop === 0;
                    const atBottom = content.scrollTop + content.clientHeight >= content.scrollHeight;

                    if ((atTop && e.deltaY < 0) || (atBottom && e.deltaY > 0)) {
                        e.preventDefault();
                    }
                } else {
                    e.preventDefault();
                }
            },
            { passive: false }
        );
    }

    /**
     * Attach alert dismiss handlers
     */
    private attachAlertHandlers(): void {
        const dismissAllBtn = document.querySelector('.dev-toolbar-alerts-dismiss');
        dismissAllBtn?.addEventListener('click', () => {
            const alertsContainer = document.querySelector('.dev-toolbar-alerts');
            alertsContainer?.classList.add('dismissed');
        });

        document.querySelectorAll('.dev-toolbar-alert-close').forEach((closeBtn) => {
            closeBtn.addEventListener('click', (e) => {
                const alert = (e.target as HTMLElement).closest('.dev-toolbar-alert');
                alert?.classList.add('dismissed');

                setTimeout(() => {
                    const alertsContainer = document.querySelector('.dev-toolbar-alerts');
                    const remainingAlerts = alertsContainer?.querySelectorAll('.dev-toolbar-alert:not(.dismissed)');

                    if (remainingAlerts && remainingAlerts.length === 0) {
                        alertsContainer?.classList.add('dismissed');
                    }
                }, 300);
            });
        });
    }

    /**
     * Attach Xdebug control handlers
     */
    private attachXdebugHandlers(): void {
        document.querySelectorAll('[data-action="xdebug-enable"]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                const ide = (e.target as HTMLElement).dataset.ide || 'PHPSTORM';
                this.xdebugControls.enableXdebug(ide);
            });
        });

        const disableBtn = document.querySelector('[data-action="xdebug-disable"]');
        disableBtn?.addEventListener('click', () => {
            this.xdebugControls.disableXdebug();
        });

        const exportBtn = document.querySelector('[data-action="export-current"]');
        exportBtn?.addEventListener('click', () => {
            this.exportCurrentRequest();
        });
    }

    /**
     * Export current request as JSON
     */
    private exportCurrentRequest(): void {
        const win = window as any;
        const toolbarData = win.__DEV_TOOLBAR_DATA__;

        if (!toolbarData) {
            console.error('[DevToolbar] No request data available');
            alert('No request data available for export.');
            return;
        }

        try {
            const exportData = exportRequestAsJson(toolbarData.id, toolbarData);
            const filename = `devtoolbar-request-${toolbarData.id}-${Date.now()}.json`;

            downloadJson(exportData, filename);
            console.log('[DevToolbar] Exported current request');
        } catch (error) {
            console.error('[DevToolbar] Export failed:', error);
            alert('Export failed: ' + (error as Error).message);
        }
    }

    /**
     * Update history badge
     */
    private updateHistoryBadge(): void {
        const metaArray = StorageManager.getMetadata();
        console.log('[DevToolbarUI] Updating history badge. Metadata count:', metaArray.length);
        console.log('[DevToolbarUI] Metadata entries:', metaArray.map(m => m.id));
        this.tabManager.updateHistoryBadge(metaArray.length);
    }

    /**
     * Initialize history tab
     */
    private initHistoryTab(): void {
        this.historyTabManager.init();
    }
}
