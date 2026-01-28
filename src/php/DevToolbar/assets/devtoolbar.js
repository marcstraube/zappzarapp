(function() {
    'use strict';

    /**
     * StorageManager - Handles localStorage persistence for DevToolbar
     *
     * Stores up to 50 lightweight metadata entries and 20 full request data entries.
     * Implements LRU eviction and automatic quota management.
     */
    const StorageManager = {
        CONFIG_KEY: 'devToolbar.config',
        META_KEY: 'devToolbar.meta',
        DATA_PREFIX: 'devToolbar.req_',
        MAX_METADATA: 50,
        MAX_FULL_DATA: 20,
        MIN_SAFE_ENTRIES: 5,

        // In-memory fallback for private browsing
        memoryStore: {
            meta: [],
            requests: {}
        },
        useMemoryFallback: false,

        /**
         * Initialize storage, handle migration, store current request
         */
        init() {
            // Test localStorage availability
            if (!this.isLocalStorageAvailable()) {
                console.warn('[DevToolbar] localStorage unavailable, using in-memory storage');
                this.useMemoryFallback = true;
            }

            // Handle one-time migration from session storage
            this.handleMigration();

            // Store current request data
            if (window.__DEV_TOOLBAR_DATA__) {
                const { id, metadata, tabs } = window.__DEV_TOOLBAR_DATA__;
                this.storeRequest(id, metadata, tabs);
            }
        },

        /**
         * Check if localStorage is available
         */
        isLocalStorageAvailable() {
            try {
                const testKey = '__devToolbarTest__';
                localStorage.setItem(testKey, '1');
                localStorage.removeItem(testKey);
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Handle one-time migration from session storage
         */
        handleMigration() {
            const config = this.getConfig();

            if (config.migrated) {
                return; // Already migrated
            }

            // Check for migration data injected by PHP
            if (window.__DEV_TOOLBAR_MIGRATION__ && Array.isArray(window.__DEV_TOOLBAR_MIGRATION__)) {
                console.log('[DevToolbar] Migrating', window.__DEV_TOOLBAR_MIGRATION__.length, 'requests from session');

                window.__DEV_TOOLBAR_MIGRATION__.forEach(request => {
                    this.storeRequest(request.id, request.metadata, request.tabs);
                });

                console.log('[DevToolbar] Migration completed');
            }

            // Mark as migrated
            config.migrated = true;
            this.setConfig(config);
        },

        /**
         * Store request with quota handling
         *
         * @param {string} id Request ID
         * @param {Object} metadata Lightweight metadata
         * @param {Object} tabs Full tab HTML content
         */
        storeRequest(id, metadata, tabs) {
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
                if (metaArray.length > this.MAX_METADATA) {
                    metaArray.length = this.MAX_METADATA;
                }

                localStorage.setItem(this.META_KEY, JSON.stringify(metaArray));

                // Store full data
                const fullData = { metadata, tabs };
                localStorage.setItem(this.DATA_PREFIX + id, JSON.stringify(fullData));

                // Enforce quota limits
                this.enforceQuotaLimits();

            } catch (e) {
                if (e.name === 'QuotaExceededError') {
                    console.warn('[DevToolbar] Quota exceeded, evicting oldest entries');
                    this.evictOldest();

                    // Retry
                    try {
                        const metaArray = this.getMetadata();
                        metaArray.unshift(metadata);
                        if (metaArray.length > this.MAX_METADATA) {
                            metaArray.length = this.MAX_METADATA;
                        }
                        localStorage.setItem(this.META_KEY, JSON.stringify(metaArray));
                        localStorage.setItem(this.DATA_PREFIX + id, JSON.stringify({ metadata, tabs }));
                    } catch (retryError) {
                        console.error('[DevToolbar] Failed to store after eviction:', retryError);
                    }
                } else {
                    console.error('[DevToolbar] Storage error:', e);
                }
            }
        },

        /**
         * Store in memory (private browsing fallback)
         */
        storeInMemory(id, metadata, tabs) {
            this.memoryStore.meta.unshift(metadata);
            if (this.memoryStore.meta.length > this.MAX_METADATA) {
                this.memoryStore.meta.length = this.MAX_METADATA;
            }

            this.memoryStore.requests[id] = { metadata, tabs };

            // Evict old full data
            const ids = this.memoryStore.meta.map(m => m.id);
            const keysToKeep = ids.slice(0, this.MAX_FULL_DATA);

            for (const key in this.memoryStore.requests) {
                if (!keysToKeep.includes(key)) {
                    delete this.memoryStore.requests[key];
                }
            }
        },

        /**
         * Retrieve full request data
         *
         * @param {string} id Request ID
         * @return {Object|null} Request data or null
         */
        getRequest(id) {
            try {
                if (this.useMemoryFallback) {
                    return this.memoryStore.requests[id] || null;
                }

                const data = localStorage.getItem(this.DATA_PREFIX + id);
                return data ? JSON.parse(data) : null;
            } catch (e) {
                console.error('[DevToolbar] Failed to retrieve request:', e);
                return null;
            }
        },

        /**
         * Get all metadata entries
         *
         * @return {Array} Array of metadata objects
         */
        getMetadata() {
            try {
                if (this.useMemoryFallback) {
                    return this.memoryStore.meta;
                }

                const data = localStorage.getItem(this.META_KEY);
                return data ? JSON.parse(data) : [];
            } catch (e) {
                console.error('[DevToolbar] Failed to retrieve metadata:', e);
                return [];
            }
        },

        /**
         * Enforce quota limits - keep only MAX_FULL_DATA entries
         */
        enforceQuotaLimits() {
            if (this.useMemoryFallback) {
                return;
            }

            const metaArray = this.getMetadata();
            const fullDataIds = metaArray.slice(0, this.MAX_FULL_DATA).map(m => m.id);

            // Find all stored request keys
            const keysToDelete = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith(this.DATA_PREFIX)) {
                    const id = key.substring(this.DATA_PREFIX.length);
                    if (!fullDataIds.includes(id)) {
                        keysToDelete.push(key);
                    }
                }
            }

            // Delete old entries
            keysToDelete.forEach(key => {
                try {
                    localStorage.removeItem(key);
                } catch (e) {
                    console.error('[DevToolbar] Failed to remove key:', key, e);
                }
            });

            if (keysToDelete.length > 0) {
                console.log('[DevToolbar] Evicted', keysToDelete.length, 'old request entries');
            }
        },

        /**
         * Emergency eviction - delete oldest entries until under MIN_SAFE_ENTRIES
         */
        evictOldest() {
            if (this.useMemoryFallback) {
                return;
            }

            const metaArray = this.getMetadata();

            // Keep only MIN_SAFE_ENTRIES newest metadata
            if (metaArray.length > this.MIN_SAFE_ENTRIES) {
                metaArray.length = this.MIN_SAFE_ENTRIES;
                localStorage.setItem(this.META_KEY, JSON.stringify(metaArray));
            }

            const idsToKeep = metaArray.slice(0, this.MIN_SAFE_ENTRIES).map(m => m.id);

            // Delete all request data except the safe entries
            const keysToDelete = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith(this.DATA_PREFIX)) {
                    const id = key.substring(this.DATA_PREFIX.length);
                    if (!idsToKeep.includes(id)) {
                        keysToDelete.push(key);
                    }
                }
            }

            keysToDelete.forEach(key => {
                try {
                    localStorage.removeItem(key);
                } catch (e) {
                    console.error('[DevToolbar] Failed to remove key during eviction:', key, e);
                }
            });

            console.log('[DevToolbar] Emergency eviction: removed', keysToDelete.length, 'entries');
        },

        /**
         * Clear all DevToolbar data
         */
        clear() {
            if (this.useMemoryFallback) {
                this.memoryStore = { meta: [], requests: {} };
                return;
            }

            try {
                const keysToDelete = [];
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && (key.startsWith('devToolbar.') || key.startsWith(this.DATA_PREFIX))) {
                        keysToDelete.push(key);
                    }
                }

                keysToDelete.forEach(key => localStorage.removeItem(key));
                console.log('[DevToolbar] Cleared all data');
            } catch (e) {
                console.error('[DevToolbar] Failed to clear data:', e);
            }
        },

        /**
         * Get config object
         */
        getConfig() {
            if (this.useMemoryFallback) {
                return { migrated: false };
            }

            try {
                const config = localStorage.getItem(this.CONFIG_KEY);
                return config ? JSON.parse(config) : { migrated: false };
            } catch (e) {
                return { migrated: false };
            }
        },

        /**
         * Set config object
         */
        setConfig(config) {
            if (this.useMemoryFallback) {
                return;
            }

            try {
                localStorage.setItem(this.CONFIG_KEY, JSON.stringify(config));
            } catch (e) {
                console.error('[DevToolbar] Failed to save config:', e);
            }
        }
    };

    const DevToolbar = {
        miniBar: null,
        panel: null,
        currentTab: 'request',
        historyTabInitialized: false,
        isLoadingRequest: false,
        isViewingHistoricalRequest: false,
        currentHistoricalRequestId: null, // Track which historical request is currently displayed
        currentRequestData: null, // Store initial request data for restoration
        originalBadgeCounts: null, // Store original badge counts for restoration

        init() {
            console.log('[DevToolbar] Initializing...');

            // Initialize storage FIRST - migrate and store current request
            StorageManager.init();

            // Restore last active tab from localStorage
            const savedTab = localStorage.getItem('devtoolbar_active_tab');
            if (savedTab) {
                this.currentTab = savedTab;
                console.log('[DevToolbar] Restored active tab:', savedTab);
            }

            this.miniBar = document.querySelector('.dev-toolbar-mini');
            this.panel = document.querySelector('.dev-toolbar-panel');

            if (!this.miniBar || !this.panel) {
                console.error('[DevToolbar] Mini bar or panel not found');
                return;
            }

            // Debug: Check all badges in DOM
            const allBadges = document.querySelectorAll('.dev-toolbar-panel-tab-badge');
            console.log('[DevToolbar] Found', allBadges.length, 'badges in DOM:',
                Array.from(allBadges).map(b => ({
                    parent: b.closest('.dev-toolbar-panel-tab')?.dataset.tab,
                    value: b.textContent
                }))
            );

            // Store current request data and badge counts for restoration
            this.storeCurrentRequestData();
            if (window.__DEV_TOOLBAR_DATA__ && window.__DEV_TOOLBAR_DATA__.metadata) {
                this.originalBadgeCounts = window.__DEV_TOOLBAR_DATA__.metadata.badge_counts;
                console.log('[DevToolbar] Stored original badge counts:', this.originalBadgeCounts);
            }

            // Update badge with count from localStorage
            this.updateRequestBadge();

            // Restore state from localStorage
            this.restoreMaximizedState();
            this.restoreOpenState();

            this.attachEventListeners();
            this.setActiveTab(this.currentTab);
            this.initRequestSwitcher(); // Request switcher is always visible

            console.log('[DevToolbar] Initialization complete');
        },

        attachEventListeners() {
            this.miniBar.addEventListener('click', () => {
                this.togglePanel();
            });

            document.querySelectorAll('.dev-toolbar-panel-tab').forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabName = e.target.closest('.dev-toolbar-panel-tab').dataset.tab;
                    this.setActiveTab(tabName);
                });
            });

            const closeBtn = document.querySelector('.dev-toolbar-panel-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.closePanel();
                });
            }

            const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
            if (maximizeBtn) {
                maximizeBtn.addEventListener('click', () => {
                    this.toggleMaximize();
                });
            }

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.panel.classList.contains('open')) {
                    this.closePanel();
                }
            });

            // Prevent background scrolling when hovering over non-scrollable areas
            this.preventBackgroundScroll();

            // Attach alert dismiss handlers
            this.attachAlertHandlers();

            // Attach Xdebug debugging controls
            this.attachXdebugHandlers();
        },

        /**
         * Prevent background page scrolling when mouse is over DevToolbar
         */
        preventBackgroundScroll() {
            // Block all scroll events on entire panel from propagating to background
            if (this.panel) {
                this.panel.addEventListener('wheel', (e) => {
                    e.stopPropagation();

                    // Allow internal scrolling in content area
                    const content = document.querySelector('.dev-toolbar-panel-content');
                    if (content && content.contains(e.target)) {
                        const atTop = content.scrollTop === 0;
                        const atBottom = content.scrollTop + content.clientHeight >= content.scrollHeight;

                        // Prevent scroll bounce at content boundaries
                        if ((atTop && e.deltaY < 0) || (atBottom && e.deltaY > 0)) {
                            e.preventDefault();
                        }
                    } else {
                        // Non-scrollable area (header, alerts, etc.) - block completely
                        e.preventDefault();
                    }
                }, { passive: false });
            }
        },

        attachAlertHandlers() {
            // Dismiss all alerts
            const dismissAllBtn = document.querySelector('.dev-toolbar-alerts-dismiss');
            if (dismissAllBtn) {
                dismissAllBtn.addEventListener('click', () => {
                    const alertsContainer = document.querySelector('.dev-toolbar-alerts');
                    if (alertsContainer) {
                        alertsContainer.classList.add('dismissed');
                    }
                });
            }

            // Dismiss individual alerts
            document.querySelectorAll('.dev-toolbar-alert-close').forEach(closeBtn => {
                closeBtn.addEventListener('click', (e) => {
                    const alert = e.target.closest('.dev-toolbar-alert');
                    if (alert) {
                        alert.classList.add('dismissed');

                        // After animation, check if all alerts are dismissed
                        setTimeout(() => {
                            const alertsContainer = document.querySelector('.dev-toolbar-alerts');
                            const remainingAlerts = alertsContainer?.querySelectorAll('.dev-toolbar-alert:not(.dismissed)');

                            if (remainingAlerts && remainingAlerts.length === 0) {
                                // All alerts dismissed, hide container
                                alertsContainer.classList.add('dismissed');
                            }
                        }, 300);
                    }
                });
            });
        },

        attachXdebugHandlers() {
            // Handle Xdebug enable buttons
            document.querySelectorAll('[data-action="xdebug-enable"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const ide = e.target.dataset.ide || 'PHPSTORM';
                    this.enableXdebug(ide);
                });
            });

            // Handle Xdebug disable button
            const disableBtn = document.querySelector('[data-action="xdebug-disable"]');
            if (disableBtn) {
                disableBtn.addEventListener('click', () => {
                    this.disableXdebug();
                });
            }
        },

        enableXdebug(ideKey) {
            // Set XDEBUG_SESSION cookie
            document.cookie = `XDEBUG_SESSION=${ideKey}; path=/; max-age=3600`;

            console.log(`[Xdebug] Enabled with IDE key: ${ideKey}`);
            this.showNotification(`Xdebug enabled for ${ideKey}. Reload page to activate.`, 'success');

            // Reload page to reflect new state
            setTimeout(() => {
                location.reload();
            }, 1500);
        },

        disableXdebug() {
            // Remove XDEBUG_SESSION cookie
            document.cookie = 'XDEBUG_SESSION=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';

            console.log('[Xdebug] Disabled');
            this.showNotification('Xdebug disabled', 'success');

            // Reload page to reflect new state
            setTimeout(() => {
                location.reload();
            }, 1500);
        },

        /**
         * Render Xdebug controls dynamically based on current state
         *
         * This ensures that Xdebug status always reflects the CURRENT state,
         * even when viewing historical requests with stored tab content.
         */
        renderXdebugControls() {
            const container = document.getElementById('dev-toolbar-request-controls-container');
            if (!container) {
                return; // Container not found (not on REQUEST tab)
            }

            // Get Xdebug configuration from server-injected data
            const xdebugConfig = window.__XDEBUG_CONFIG__ || { enabled: false };
            const xdebugEnabled = xdebugConfig.enabled;

            // Check current cookie status
            const cookies = document.cookie.split(';').reduce((acc, cookie) => {
                const [key, value] = cookie.trim().split('=');
                acc[key] = value;
                return acc;
            }, {});

            const xdebugActive = 'XDEBUG_SESSION' in cookies;
            const xdebugSessionName = cookies['XDEBUG_SESSION'] || '';

            // Build HTML
            let html = '<div class="dev-toolbar-request-status">';

            if (xdebugEnabled) {
                const statusClass = xdebugActive ? 'active' : 'inactive';
                const statusText = xdebugActive ? `Xdebug: ${xdebugSessionName}` : 'Xdebug: Off';
                const statusIcon = xdebugActive ? '●' : '○';

                html += `<div class="dev-toolbar-xdebug-compact dev-toolbar-xdebug-compact-${statusClass}">
                    <span class="dev-toolbar-xdebug-indicator">${statusIcon}</span>
                    <span class="dev-toolbar-xdebug-label">${this.escapeHtml(statusText)}</span>
                </div>`;
            } else {
                html += `<div class="dev-toolbar-xdebug-compact dev-toolbar-xdebug-compact-disabled">
                    <span class="dev-toolbar-xdebug-label">Xdebug: Not Installed</span>
                </div>`;
            }

            html += '</div>'; // .dev-toolbar-request-status

            html += '<div class="dev-toolbar-request-actions">';

            if (xdebugEnabled) {
                if (xdebugActive) {
                    html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-disable" title="Disable Xdebug step debugging">
                        ⏹ Disable
                    </button>`;
                } else {
                    html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="PHPSTORM" title="Enable Xdebug for PhpStorm">
                        ▶ PhpStorm
                    </button>`;
                    html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="VSCODE" title="Enable Xdebug for VSCode">
                        ▶ VSCode
                    </button>`;
                }
            }

            html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="export-current" title="Export current request as JSON">
                ⬇ Export
            </button>`;

            html += '</div>'; // .dev-toolbar-request-actions

            // Inject HTML
            container.innerHTML = html;

            // Re-attach event listeners
            this.attachXdebugHandlers();

            console.log('[Xdebug] Controls rendered, status:', xdebugActive ? 'active' : 'inactive');
        },

        togglePanel() {
            if (this.panel.classList.contains('open')) {
                this.closePanel();
            } else {
                this.openPanel();
            }
        },

        openPanel() {
            this.panel.classList.add('open');

            // Save state to localStorage
            try {
                localStorage.setItem('devToolbar.open', '1');
            } catch (e) {
                console.warn('DevToolbar: Could not save open state', e);
            }
        },

        closePanel() {
            this.panel.classList.remove('open');

            // Save state to localStorage
            try {
                localStorage.setItem('devToolbar.open', '0');
            } catch (e) {
                console.warn('DevToolbar: Could not save open state', e);
            }
        },

        toggleMaximize() {
            this.panel.classList.toggle('maximized');
            const isMaximized = this.panel.classList.contains('maximized');

            // Save state to localStorage
            try {
                localStorage.setItem('devToolbar.maximized', isMaximized ? '1' : '0');
            } catch (e) {
                console.warn('DevToolbar: Could not save maximized state', e);
            }

            // Update maximize button icon
            const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
            if (maximizeBtn) {
                maximizeBtn.textContent = isMaximized ? '⛶' : '⛶';
                maximizeBtn.title = isMaximized ? 'Restore' : 'Maximize';
            }
        },

        restoreMaximizedState() {
            try {
                const isMaximized = localStorage.getItem('devToolbar.maximized') === '1';
                if (isMaximized) {
                    this.panel.classList.add('maximized');

                    // Update button on next tick to ensure DOM is ready
                    setTimeout(() => {
                        const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
                        if (maximizeBtn) {
                            maximizeBtn.title = 'Restore';
                        }
                    }, 0);
                }
            } catch (e) {
                console.warn('DevToolbar: Could not restore maximized state', e);
            }
        },

        restoreOpenState() {
            try {
                const isOpen = localStorage.getItem('devToolbar.open') === '1';
                if (isOpen) {
                    // Add 'open' class without triggering save again
                    this.panel.classList.add('open');
                }
            } catch (e) {
                console.warn('DevToolbar: Could not restore open state', e);
            }
        },

        setActiveTab(tabName) {
            this.currentTab = tabName;
            console.log('Setting active tab:', tabName);

            // Save to localStorage for persistence across reloads
            localStorage.setItem('devtoolbar_active_tab', tabName);

            // Update tab buttons
            const tabButtons = document.querySelectorAll('.dev-toolbar-panel-tab');
            console.log('Found tab buttons:', tabButtons.length);

            tabButtons.forEach(tab => {
                if (tab.dataset.tab === tabName) {
                    tab.classList.add('active');
                    console.log('Activated tab button:', tabName);
                } else {
                    tab.classList.remove('active');
                }
            });

            // Update tab panes
            const panes = document.querySelectorAll('.dev-toolbar-panel-tab-pane');
            console.log('Found panes:', panes.length);

            let activatedPane = false;
            panes.forEach(pane => {
                const paneTab = pane.dataset.tab;
                console.log('Pane:', paneTab, 'Target:', tabName, 'Match:', paneTab === tabName);

                if (paneTab === tabName) {
                    pane.classList.add('active');
                    activatedPane = true;
                    console.log('✓ Activated pane for', tabName);
                } else {
                    pane.classList.remove('active');
                }
            });

            if (!activatedPane) {
                console.error('Warning: No pane activated for tab:', tabName);
            }

            // Render Xdebug controls when REQUEST tab becomes active
            // This ensures controls always show current state, not historical
            if (tabName === 'request') {
                setTimeout(() => this.renderXdebugControls(), 50);
            }

            // Initialize HISTORY tab when it becomes active
            if (tabName === 'history') {
                // Use setTimeout to ensure DOM is fully updated
                setTimeout(() => this.initHistoryTab(), 50);
            }
        },

        // HISTORY TAB: Filtering and Export
        initHistoryTab() {
            const methodFilter = document.getElementById('history-filter-method');
            const statusFilter = document.getElementById('history-filter-status');
            const uriFilter = document.getElementById('history-filter-uri');
            const minTimeFilter = document.getElementById('history-filter-min-time');
            const resetBtn = document.getElementById('history-filter-reset');

            if (!methodFilter) {
                console.log('History tab elements not found, skipping init');
                return; // Tab not active
            }

            // Prevent multiple initializations
            if (this.historyTabInitialized) {
                console.log('History tab already initialized, skipping');
                return;
            }

            console.log('Initializing History tab filters');
            this.historyTabInitialized = true;

            // Render history data from localStorage FIRST
            this.renderHistoryTabData();

            // Attach export button listeners
            this.attachHistoryExportListeners();

            // Attach filter listeners
            [methodFilter, statusFilter, uriFilter, minTimeFilter].forEach(el => {
                el?.addEventListener('input', () => this.filterHistoryRequests());
            });

            resetBtn?.addEventListener('click', () => this.resetHistoryFilters());

            // Export controls
            document.getElementById('history-export-json')?.addEventListener('click',
                () => this.exportHistoryAsJSON());
            document.getElementById('history-export-csv')?.addEventListener('click',
                () => this.exportHistoryAsCSV());
            document.getElementById('history-clear')?.addEventListener('click',
                () => this.clearHistory());
        },

        filterHistoryRequests() {
            const filters = {
                method: document.getElementById('history-filter-method')?.value || '',
                status: document.getElementById('history-filter-status')?.value || '',
                uri: document.getElementById('history-filter-uri')?.value.toLowerCase() || '',
                minTime: parseFloat(document.getElementById('history-filter-min-time')?.value || '0')
            };

            const items = document.querySelectorAll('.dev-toolbar-history-item');
            let visibleCount = 0;

            items.forEach(item => {
                const matches = this.historyItemMatches(item, filters);
                item.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });

            this.updateHistoryListTitle(visibleCount);
        },

        historyItemMatches(item, filters) {
            if (filters.method && item.dataset.method !== filters.method) return false;
            if (filters.status && !item.dataset.status.startsWith(filters.status)) return false;
            if (filters.uri && !item.dataset.uri.toLowerCase().includes(filters.uri)) return false;
            if (filters.minTime > 0 && parseFloat(item.dataset.time) < filters.minTime) return false;
            return true;
        },

        updateHistoryListTitle(visibleCount) {
            const title = document.getElementById('history-list-title');
            if (title) {
                const totalCount = document.querySelectorAll('.dev-toolbar-history-item').length;
                title.textContent = `Request History (${visibleCount} of ${totalCount})`;
            }
        },

        resetHistoryFilters() {
            ['method', 'status', 'uri', 'min-time'].forEach(id => {
                const el = document.getElementById(`history-filter-${id}`);
                if (el) el.value = '';
            });
            this.filterHistoryRequests();
        },

        exportHistoryAsJSON() {
            const data = this.collectVisibleHistoryData();
            const json = JSON.stringify({
                toolbar_version: '2.1.0',
                export_time: new Date().toISOString(),
                requests: data
            }, null, 2);

            this.downloadFile(json, `devtoolbar-history-${Date.now()}.json`, 'application/json');
        },

        exportHistoryAsCSV() {
            const data = this.collectVisibleHistoryData();
            let csv = 'Timestamp,Method,URI,Status,Time (ms),Memory (MB),Queries\n';

            data.forEach(item => {
                const timestamp = new Date(item.timestamp * 1000).toISOString();
                const uri = `"${item.uri.replace(/"/g, '""')}"`;
                csv += `${timestamp},${item.method},${uri},${item.status},${item.time},${item.memory},${item.query_count}\n`;
            });

            this.downloadFile(csv, `devtoolbar-history-${Date.now()}.csv`, 'text/csv');
        },

        downloadFile(content, filename, mimeType) {
            const blob = new Blob([content], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        },

        collectVisibleHistoryData() {
            const items = document.querySelectorAll('.dev-toolbar-history-item:not([style*="display: none"])');
            return Array.from(items).map(item => {
                const memoryText = item.querySelector('.dev-toolbar-history-meta-item:nth-child(3)')?.textContent || '';
                const memoryMatch = memoryText.match(/[\d.]+/);
                const memory = memoryMatch ? parseFloat(memoryMatch[0]) : 0;

                const queriesText = item.querySelector('.dev-toolbar-history-meta-item:nth-child(4)')?.textContent || '';
                const queriesMatch = queriesText.match(/\d+/);
                const queryCount = queriesMatch ? parseInt(queriesMatch[0], 10) : 0;

                return {
                    method: item.dataset.method,
                    uri: item.dataset.uri,
                    status: parseInt(item.dataset.status, 10),
                    time: parseFloat(item.dataset.time),
                    memory: memory,
                    query_count: queryCount,
                    timestamp: this.parseTimeAgo(item.querySelector('.dev-toolbar-history-time-ago')?.textContent || '')
                };
            });
        },

        parseTimeAgo(text) {
            const match = text.match(/(\d+)([smhd])/);
            if (!match) return Math.floor(Date.now() / 1000);

            const value = parseInt(match[1], 10);
            const multipliers = { s: 1, m: 60, h: 3600, d: 86400 };
            return Math.floor(Date.now() / 1000) - (value * (multipliers[match[2]] || 1));
        },

        /**
         * Render HISTORY tab data from localStorage
         */
        renderHistoryTabData() {
            console.log('[HISTORY Tab] Rendering HISTORY tab data');

            const metaArray = StorageManager.getMetadata();
            console.log('[HISTORY Tab] Found', metaArray.length, 'requests in metadata');

            // Update statistics
            const stats = this.calculateHistoryStats(metaArray);

            const totalEl = document.querySelector('[data-history-stat="total"]');
            const avgTimeEl = document.querySelector('[data-history-stat="avg_time"]');
            const avgMemoryEl = document.querySelector('[data-history-stat="avg_memory"]');
            const avgQueriesEl = document.querySelector('[data-history-stat="avg_queries"]');
            const fastestEl = document.querySelector('[data-history-stat="fastest"]');
            const slowestEl = document.querySelector('[data-history-stat="slowest"]');

            if (totalEl) totalEl.textContent = metaArray.length;
            if (avgTimeEl) avgTimeEl.textContent = stats.avg_time.toFixed(0) + 'ms';
            if (avgMemoryEl) avgMemoryEl.textContent = stats.avg_memory.toFixed(1) + 'MB';
            if (avgQueriesEl) avgQueriesEl.textContent = stats.avg_queries.toFixed(1);
            if (fastestEl) fastestEl.textContent = stats.fastest_time.toFixed(0) + 'ms';
            if (slowestEl) slowestEl.textContent = stats.slowest_time.toFixed(0) + 'ms';

            // Update request count
            const countEl = document.getElementById('history-list-count');
            if (countEl) {
                countEl.textContent = metaArray.length;
            }

            // Render response time trends (sparkline)
            this.renderTrends(metaArray);

            // Render request list
            const listContainer = document.getElementById('history-request-list-container');
            if (!listContainer) {
                console.warn('[localStorage] History list container not found');
                return;
            }

            if (metaArray.length === 0) {
                listContainer.innerHTML = '<p style="color: #888;">No request history yet. Reload the page to see requests.</p>';
                return;
            }

            let html = '';

            metaArray.forEach(request => {
                const statusIcon = this.getStatusIcon(request.status);
                const timeAgo = this.timeAgo(request.timestamp);
                const fullTimestamp = this.formatTimestamp(request.timestamp);

                // Performance class
                let perfClass = '';
                if (request.time > 500) {
                    perfClass = 'slow';
                } else if (request.time > 200) {
                    perfClass = 'warning';
                }

                html += `<div class="dev-toolbar-history-item ${perfClass}"
                          data-method="${this.escapeHtml(request.method)}"
                          data-uri="${this.escapeHtml(request.uri)}"
                          data-status="${request.status}"
                          data-time="${request.time}"
                          data-request-id="${this.escapeHtml(request.id)}">
                        <div class="dev-toolbar-history-item-header">
                            <span class="dev-toolbar-history-icon">${statusIcon}</span>
                            <span class="dev-toolbar-history-method">${this.escapeHtml(request.method)}</span>
                            <span class="dev-toolbar-history-uri">${this.escapeHtml(request.uri)}</span>
                            <span class="dev-toolbar-history-time-ago" title="${fullTimestamp}">${timeAgo}</span>
                            <button class="dev-toolbar-history-item-export"
                                    data-request-id="${this.escapeHtml(request.id)}"
                                    title="Export this request">⬇</button>
                        </div>
                        <div class="dev-toolbar-history-item-meta">
                            <span class="dev-toolbar-history-meta-item">Status: ${request.status}</span>
                            <span class="dev-toolbar-history-meta-item">Time: ${request.time.toFixed(0)}ms</span>
                            <span class="dev-toolbar-history-meta-item">Memory: ${(request.memory / 1024 / 1024).toFixed(1)}MB</span>
                            <span class="dev-toolbar-history-meta-item">Queries: ${request.query_count}</span>
                        </div>
                    </div>`;
            });

            listContainer.innerHTML = html;
            console.log('[localStorage] Rendered', metaArray.length, 'requests in HISTORY tab');

            // Attach export listeners after rendering
            setTimeout(() => this.attachHistoryExportListeners(), 50);
        },

        clearHistory() {
            if (!confirm('Clear all request history? This cannot be undone.')) return;

            // Clear localStorage instead of calling server
            StorageManager.clear();

            // Reload page to reset everything
            location.reload();
        },

        // REQUEST SWITCHER: AJAX Navigation
        initRequestSwitcher() {
            const switcher = document.querySelector('.dev-toolbar-request-switcher');
            if (!switcher) return;

            const toggle = switcher.querySelector('.dev-toolbar-request-switcher-toggle');
            const dropdown = switcher.querySelector('.dev-toolbar-request-switcher-dropdown');

            // Populate dropdown from localStorage
            this.populateRequestSwitcher();

            // Toggle dropdown
            toggle?.addEventListener('click', (e) => {
                e.stopPropagation();
                switcher.classList.toggle('open');
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!switcher.contains(e.target)) {
                    switcher.classList.remove('open');
                }
            });

            console.log('[Switcher] Attaching click handler to dropdown');

            // Handle item clicks
            dropdown?.addEventListener('click', (e) => {
                console.log('[Switcher] Dropdown clicked, target:', e.target);
                const item = e.target.closest('.dev-toolbar-request-switcher-item');
                if (!item) {
                    console.log('[Switcher] Click but no item found');
                    return;
                }

                console.log('[Switcher] Item clicked:', item.dataset);

                if (item.dataset.action === 'view-history') {
                    console.log('[Switcher] Opening HISTORY tab');
                    this.setActiveTab('history');
                    switcher.classList.remove('open');
                    return;
                }

                if (item.dataset.action === 'current') {
                    switcher.classList.remove('open');

                    // Check if we're currently viewing a historical request
                    if (this.isViewingHistoricalRequest) {
                        // Restore current request data without reloading page
                        this.restoreCurrentRequest();
                    } else {
                        console.log('Already viewing current request');
                    }
                    return;
                }

                const requestId = item.dataset.requestId;

                if (requestId) {
                    this.loadHistoricalRequest(requestId);
                }
            });

        },

        async loadHistoricalRequest(requestId) {
            // Prevent multiple simultaneous loads
            if (this.isLoadingRequest) {
                console.log('Already loading a request, ignoring');
                return;
            }

            this.isLoadingRequest = true;
            const switcher = document.querySelector('.dev-toolbar-request-switcher');
            switcher?.classList.add('loading');

            console.log('[localStorage] Loading historical request:', requestId);

            try {
                // Load from localStorage instead of AJAX
                const requestData = StorageManager.getRequest(requestId);

                if (!requestData) {
                    throw new Error('Request not found in localStorage');
                }

                console.log('[localStorage] Retrieved request data from localStorage');

                // Reconstruct tabs HTML from stored data
                let tabsHtml = '';
                for (const [tabName, content] of Object.entries(requestData.tabs)) {
                    tabsHtml += `<div class="dev-toolbar-panel-tab-pane" data-tab="${tabName}">${content}</div>`;
                }

                if (!tabsHtml) {
                    throw new Error('No tab content found in stored data');
                }

                // Replace tab content
                const contentContainer = document.querySelector('.dev-toolbar-panel-content');
                if (contentContainer) {
                    // Remember current tab before replacing content
                    const currentTab = this.currentTab;
                    console.log('Current tab before replace:', currentTab);

                    // Replace content
                    contentContainer.innerHTML = tabsHtml;

                    // Mark that we're viewing a historical request
                    this.isViewingHistoricalRequest = true;
                    this.currentHistoricalRequestId = requestId;

                    // Re-populate dropdown to update highlighting
                    this.populateRequestSwitcher();

                    // Reset history tab initialization flag (so it can be re-initialized)
                    this.historyTabInitialized = false;

                    // Store badge counts for later update
                    const badgeCounts = requestData.metadata?.badge_counts;

                    // Wait for DOM to update before re-attaching listeners and activating tab
                    setTimeout(() => {
                        // Re-initialize tab switching for new content
                        this.attachTabEventListeners();

                        // Restore the active tab (re-activate it to show correct pane)
                        console.log('Restoring tab:', currentTab);
                        this.setActiveTab(currentTab);

                        // Update tab badges AFTER listeners are attached and tab is active
                        if (badgeCounts) {
                            this.updateTabBadges(badgeCounts);
                        }

                        // Show success feedback
                        console.log('[localStorage] Successfully loaded historical request');
                        this.showNotification('Historical request loaded (<10ms)', 'success');
                    }, 50);

                    // Update switcher label and badge immediately
                    const toggle = switcher?.querySelector('.dev-toolbar-request-switcher-toggle');
                    if (toggle) {
                        toggle.dataset.current = requestId;

                        // Update label to show we're viewing history
                        const label = toggle.querySelector('.dev-toolbar-request-switcher-label');
                        if (label) {
                            label.textContent = 'Request (Historical)';
                            label.style.color = '#f59e0b'; // Orange to indicate historical
                        }
                    }
                } else {
                    throw new Error('Content container not found');
                }
            } catch (error) {
                console.error('[localStorage] Failed to load request:', error);
                this.showNotification('Failed: ' + error.message, 'error');
            } finally {
                this.isLoadingRequest = false;
                switcher?.classList.remove('loading');
                switcher?.classList.remove('open');
            }
        },

        attachTabEventListeners() {
            // Re-attach tab click listeners (needed after AJAX content replacement)
            console.log('Re-attaching tab event listeners');
            const tabButtons = document.querySelectorAll('.dev-toolbar-panel-tab');
            console.log('Found', tabButtons.length, 'tab buttons');

            tabButtons.forEach(tab => {
                // Clone and replace to remove old listeners
                const newTab = tab.cloneNode(true);
                tab.parentNode.replaceChild(newTab, tab);

                // Add new listener
                newTab.addEventListener('click', () => {
                    console.log('Tab clicked:', newTab.dataset.tab);
                    this.setActiveTab(newTab.dataset.tab);
                });
            });

            // Re-render Xdebug controls with current state (historical data may have stale state)
            // Then re-attach event handlers
            this.renderXdebugControls();
        },

        showNotification(message, type = 'info') {
            // Simple notification system
            const notification = document.createElement('div');
            notification.className = `dev-toolbar-notification dev-toolbar-notification-${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        },

        /**
         * Store current request data for later restoration
         */
        storeCurrentRequestData() {
            const contentContainer = document.querySelector('.dev-toolbar-panel-content');
            if (contentContainer) {
                this.currentRequestData = contentContainer.innerHTML;
            }
        },

        /**
         * Restore current request data (return from historical view)
         */
        restoreCurrentRequest() {
            console.log('[localStorage] Restoring current request');

            const contentContainer = document.querySelector('.dev-toolbar-panel-content');
            if (contentContainer && this.currentRequestData) {
                const currentTab = this.currentTab;

                // Restore original content
                contentContainer.innerHTML = this.currentRequestData;

                // Reset flags
                this.isViewingHistoricalRequest = false;
                this.currentHistoricalRequestId = null;
                this.historyTabInitialized = false;

                // Re-populate dropdown to update highlighting
                this.populateRequestSwitcher();

                // Store badge counts for later update
                const originalBadges = this.originalBadgeCounts;

                // Re-attach listeners and restore tab
                setTimeout(() => {
                    this.attachTabEventListeners();
                    this.setActiveTab(currentTab);

                    // Restore original badge counts AFTER listeners are attached and tab is active
                    if (originalBadges) {
                        this.updateTabBadges(originalBadges);
                    }

                    this.showNotification('Returned to current request', 'success');
                }, 50);

                // Update switcher label
                const switcher = document.querySelector('.dev-toolbar-request-switcher');
                const toggle = switcher?.querySelector('.dev-toolbar-request-switcher-toggle');
                if (toggle) {
                    toggle.dataset.current = 'current';

                    const label = toggle.querySelector('.dev-toolbar-request-switcher-label');
                    if (label) {
                        label.textContent = 'Request';
                        label.style.color = ''; // Reset color
                    }
                }
            }
        },

        /**
         * Update HISTORY tab badge with count from localStorage
         * Note: REQUEST tab has no badge (always shows current request)
         * Other tabs (QUERIES, LOGS, EXCEPTIONS) have static badges from PHP collectors
         */
        updateRequestBadge() {
            const metaArray = StorageManager.getMetadata();
            console.log('[Badge] Updating HISTORY badge with', metaArray.length, 'requests');

            // Update HISTORY badge (only tab that needs JavaScript update)
            const historyBadge = document.querySelector('.dev-toolbar-panel-tab[data-tab="history"] .dev-toolbar-panel-tab-badge');
            if (historyBadge) {
                historyBadge.textContent = metaArray.length;
                console.log('[Badge] HISTORY badge updated to:', metaArray.length);
            } else {
                console.warn('[Badge] HISTORY badge element not found');
            }
        },

        /**
         * Update all tab badges based on badge counts
         *
         * @param {Object} badgeCounts - Badge counts for each tab (e.g., {queries: 5, exceptions: 2})
         */
        updateTabBadges(badgeCounts) {
            if (!badgeCounts) {
                console.warn('[Badge] No badge counts provided');
                return;
            }

            console.log('[Badge] Updating tab badges with counts:', badgeCounts);

            for (const [tabName, count] of Object.entries(badgeCounts)) {
                const tab = document.querySelector(`.dev-toolbar-panel-tab[data-tab="${tabName}"]`);
                if (!tab) continue;

                let badge = tab.querySelector('.dev-toolbar-panel-tab-badge');

                // Special handling for history tab - always has badge
                if (tabName === 'history') {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'dev-toolbar-panel-tab-badge';
                        tab.appendChild(badge);
                    }
                    // History badge is updated separately by updateRequestBadge()
                    continue;
                }

                // For other tabs: show badge if count > 0, hide if count === 0
                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'dev-toolbar-panel-tab-badge';
                        tab.appendChild(badge);
                    }
                    badge.textContent = count;
                    badge.style.display = '';
                } else if (badge) {
                    // Hide badge if count is 0
                    badge.style.display = 'none';
                }
            }

            console.log('[Badge] Tab badges updated');
        },

        /**
         * Populate request switcher dropdown from localStorage
         */
        populateRequestSwitcher() {
            const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');
            if (!dropdown) return;

            console.log('[localStorage] Populating request switcher from localStorage');

            // Get metadata from storage
            const metaArray = StorageManager.getMetadata();

            // Build dropdown HTML
            let html = '';

            // Current request (highlighted if active)
            const isCurrentActive = !this.isViewingHistoricalRequest;
            html += `<div class="dev-toolbar-request-switcher-item ${isCurrentActive ? 'active' : ''}" data-action="current">
                <span class="dev-toolbar-request-icon">●</span>
                <span class="dev-toolbar-request-label">Current Request</span>
            </div>`;

            // Recent requests - only show those with full data (MAX_FULL_DATA = 20)
            if (metaArray.length > 0) {
                html += '<div class="dev-toolbar-request-switcher-divider"></div>';

                // Show up to MAX_FULL_DATA (20) recent requests - these have full tab data
                const recentRequests = metaArray.slice(0, StorageManager.MAX_FULL_DATA);

                recentRequests.forEach(request => {
                    const statusIcon = this.getStatusIcon(request.status);
                    const isActive = this.currentHistoricalRequestId === request.id;

                    html += `<div class="dev-toolbar-request-switcher-item ${isActive ? 'active' : ''}"
                                 data-request-id="${this.escapeHtml(request.id)}">
                            <span class="dev-toolbar-request-icon">${statusIcon}</span>
                            <span class="dev-toolbar-request-method">${this.escapeHtml(request.method)}</span>
                            <span class="dev-toolbar-request-uri">${this.escapeHtml(request.uri)}</span>
                            <span class="dev-toolbar-request-time">${Math.round(request.time)}ms</span>
                        </div>`;
                });

                html += '<div class="dev-toolbar-request-switcher-divider"></div>';
            }

            // View all history link
            html += `<div class="dev-toolbar-request-switcher-item" data-action="view-history">
                <span class="dev-toolbar-request-icon">📋</span>
                <span class="dev-toolbar-request-label">View All History →</span>
            </div>`;

            // Replace dropdown content
            dropdown.innerHTML = html;

            console.log('[localStorage] Populated', metaArray.length, 'requests in switcher');
        },

        /**
         * Get status icon for HTTP status code
         */
        getStatusIcon(status) {
            if (status >= 200 && status < 300) return '✓';
            if (status >= 300 && status < 400) return '↻';
            if (status >= 400 && status < 500) return '⚠';
            if (status >= 500) return '✗';
            return '○';
        },

        /**
         * Escape HTML special characters
         */
        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        /**
         * Render request history from localStorage
         */
        renderRequestHistory() {
            console.log('[REQUEST Tab] Rendering request history');

            const historyList = document.getElementById('dev-toolbar-history-list');
            if (!historyList) {
                console.warn('[REQUEST Tab] History list container not found');
                return;
            }

            const metaArray = StorageManager.getMetadata();
            console.log('[REQUEST Tab] Found', metaArray.length, 'requests in metadata');
            console.log('[REQUEST Tab] Memory fallback active:', StorageManager.useMemoryFallback);

            if (metaArray.length === 0) {
                historyList.innerHTML = '<p style="color: #888;">No request history yet. Reload the page to see requests.</p>';
                return;
            }

            // Calculate statistics
            const stats = this.calculateHistoryStats(metaArray);

            // Update count
            const countEl = document.getElementById('dev-toolbar-history-count');
            if (countEl) {
                countEl.textContent = metaArray.length;
            }

            // Update statistics safely
            const avgTimeEl = document.querySelector('[data-stat="avg_time"]');
            const avgMemoryEl = document.querySelector('[data-stat="avg_memory"]');
            const avgQueriesEl = document.querySelector('[data-stat="avg_queries"]');
            const slowestTimeEl = document.querySelector('[data-stat="slowest_time"]');
            const fastestTimeEl = document.querySelector('[data-stat="fastest_time"]');

            if (avgTimeEl) avgTimeEl.textContent = stats.avg_time.toFixed(2);
            if (avgMemoryEl) avgMemoryEl.textContent = stats.avg_memory.toFixed(2);
            if (avgQueriesEl) avgQueriesEl.textContent = stats.avg_queries.toFixed(1);
            if (slowestTimeEl) slowestTimeEl.textContent = stats.slowest_time.toFixed(2);
            if (fastestTimeEl) fastestTimeEl.textContent = stats.fastest_time.toFixed(2);

            // Render request list (show max 10 recent)
            let html = '';

            const recentRequests = metaArray.slice(0, 10);

            recentRequests.forEach(request => {
                const statusIcon = this.getStatusIcon(request.status);
                const timeAgo = this.timeAgo(request.timestamp);
                const fullTimestamp = this.formatTimestamp(request.timestamp);

                // Determine performance class
                let perfClass = '';
                if (request.time > 1000) {
                    perfClass = 'slow';
                } else if (request.time > 500) {
                    perfClass = 'warning';
                }

                html += `<div class="dev-toolbar-request-history-item ${perfClass}">
                    <div class="dev-toolbar-request-history-header">
                        ${statusIcon} <strong>${this.escapeHtml(request.method)}</strong>
                        <span class="dev-toolbar-request-uri">${this.escapeHtml(request.uri)}</span>
                        <span class="dev-toolbar-request-time-ago" title="${fullTimestamp}">${timeAgo}</span>
                    </div>
                    <div class="dev-toolbar-request-history-meta">
                        <span>${request.status}</span> •
                        <span>${request.time.toFixed(2)}ms</span> •
                        <span>${(request.memory / 1024 / 1024).toFixed(2)}MB</span> •
                        <span>${request.query_count} queries</span>
                    </div>
                </div>`;
            });

            historyList.innerHTML = html;
            console.log('[localStorage] Rendered', recentRequests.length, 'requests in history list');
        },

        /**
         * Calculate statistics from metadata array
         */
        calculateHistoryStats(metaArray) {
            if (metaArray.length === 0) {
                return {
                    avg_time: 0,
                    avg_memory: 0,
                    avg_queries: 0,
                    slowest_time: 0,
                    fastest_time: 0
                };
            }

            let totalTime = 0;
            let totalMemory = 0;
            let totalQueries = 0;
            let slowest = 0;
            let fastest = Infinity;

            metaArray.forEach(req => {
                totalTime += req.time;
                totalMemory += req.memory;
                totalQueries += req.query_count;
                if (req.time > slowest) slowest = req.time;
                if (req.time < fastest) fastest = req.time;
            });

            return {
                avg_time: totalTime / metaArray.length,
                avg_memory: (totalMemory / metaArray.length) / 1024 / 1024, // Convert to MB
                avg_queries: totalQueries / metaArray.length,
                slowest_time: slowest,
                fastest_time: fastest === Infinity ? 0 : fastest
            };
        },

        /**
         * Generate ASCII sparkline from numeric values
         *
         * Creates a visual representation using Unicode block characters (▁▂▃▄▅▆▇█)
         * that scales proportionally to the value range.
         *
         * @param {Array<number>} values - Numeric values to visualize
         * @returns {string} Sparkline string (empty if input is empty)
         */
        generateSparkline(values) {
            if (!values || values.length === 0) {
                return '';
            }

            const ticks = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
            const min = Math.min(...values);
            const max = Math.max(...values);
            const range = max - min;

            // If all values are equal, use middle tick
            if (range === 0) {
                return ticks[3].repeat(values.length);
            }

            let sparkline = '';
            values.forEach(value => {
                const normalized = (value - min) / range;
                const index = Math.min(7, Math.floor(normalized * 8));
                sparkline += ticks[index];
            });

            return sparkline;
        },

        /**
         * Render response time trends with sparkline
         *
         * @param {Array<Object>} metaArray - Request metadata
         */
        renderTrends(metaArray) {
            const trendSection = document.getElementById('dev-toolbar-history-trends-section');
            if (!trendSection) {
                console.warn('[HISTORY Tab] Trend section not found');
                return;
            }

            const sparklineEl = trendSection.querySelector('.dev-toolbar-history-sparkline');
            if (!sparklineEl) {
                console.warn('[HISTORY Tab] Sparkline element not found');
                return;
            }

            if (metaArray.length === 0) {
                // Hide trend section if no data
                trendSection.style.display = 'none';
                console.log('[HISTORY Tab] No data available, hiding trends');
                return;
            }

            // Extract time values (limit to last 50 for readability)
            const timeValues = metaArray.slice(-50).map(req => req.time);

            // Generate sparkline
            const sparkline = this.generateSparkline(timeValues);

            // Update sparkline display
            sparklineEl.textContent = sparkline;

            // Show trend section
            trendSection.style.display = '';

            console.log('[HISTORY Tab] Rendered sparkline for', timeValues.length, 'requests:', sparkline);
        },

        /**
         * Format timestamp as "time ago"
         */
        timeAgo(timestamp) {
            const seconds = Math.floor(Date.now() / 1000 - timestamp);

            if (seconds < 60) return `${seconds}s ago`;
            if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
            if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
            return `${Math.floor(seconds / 86400)}d ago`;
        },

        /**
         * Format timestamp as readable date/time string
         *
         * @param {number} timestamp Unix timestamp (seconds)
         * @return {string} Formatted date/time
         */
        formatTimestamp(timestamp) {
            const date = new Date(timestamp * 1000);

            // Format: "2026-01-28 15:42:35"
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');

            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        },

        /**
         * Attach export button listeners in HISTORY tab
         */
        attachHistoryExportListeners() {
            // Remove old listeners to prevent duplicates
            const exportButtons = document.querySelectorAll('.dev-toolbar-history-item-export');
            exportButtons.forEach(btn => {
                // Clone node to remove old listeners
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);

                // Add new listener
                newBtn.addEventListener('click', (e) => {
                    e.stopPropagation(); // Prevent triggering other click handlers
                    const requestId = newBtn.dataset.requestId;
                    if (requestId) {
                        this.exportRequest(requestId);
                    }
                });
            });

            // Also attach to REQUEST tab export button
            const requestExportBtn = document.querySelector('[data-action="export-current"]');
            if (requestExportBtn) {
                // Clone to remove old listeners
                const newExportBtn = requestExportBtn.cloneNode(true);
                requestExportBtn.parentNode.replaceChild(newExportBtn, requestExportBtn);

                newExportBtn.addEventListener('click', () => {
                    this.exportCurrentRequest();
                });
            }
        },

        /**
         * Export a specific request by ID from localStorage
         */
        exportRequest(requestId) {
            console.log('[Export] Exporting request:', requestId);

            const requestData = StorageManager.getRequest(requestId);
            if (!requestData) {
                alert('Request data not found in localStorage');
                return;
            }

            const data = {
                toolbar_version: '2.1.0',
                export_time: new Date().toISOString(),
                request_id: requestId,
                metadata: requestData.metadata,
                html_data: requestData.tabs
            };

            const json = JSON.stringify(data, null, 2);
            this.downloadFile(json, `devtoolbar-${requestId}.json`, 'application/json');
        },

        /**
         * Export the currently visible request
         */
        exportCurrentRequest() {
            const currentId = document.querySelector('.dev-toolbar-request-switcher-toggle')?.dataset.current || 'current';

            // Collect current request data from visible tab content
            const allData = {};
            const panes = document.querySelectorAll('.dev-toolbar-panel-tab-pane');

            panes.forEach(pane => {
                const tabName = pane.dataset.tab;
                allData[tabName] = pane.innerHTML;
            });

            const data = {
                toolbar_version: '2.1.0',
                export_time: new Date().toISOString(),
                request_id: currentId,
                html_data: allData
            };

            const json = JSON.stringify(data, null, 2);
            this.downloadFile(json, `devtoolbar-${currentId}.json`, 'application/json');
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => DevToolbar.init());
    } else {
        DevToolbar.init();
    }
})();