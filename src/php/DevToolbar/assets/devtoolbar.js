/* DevToolbar - Generated browser bundle - DO NOT EDIT MANUALLY */
"use strict";
(() => {
  // DevToolbar/storage/StorageConfig.ts
  var MAX_METADATA = 50;
  var MAX_FULL_DATA = 20;
  var MIN_SAFE_ENTRIES = 5;
  var CONFIG_KEY = "devToolbar.config";
  var META_KEY = "devToolbar.meta";
  var DATA_PREFIX = "devToolbar.req_";

  // DevToolbar/storage/StorageManager.ts
  var StorageManagerClass = class {
    constructor() {
      this.useMemoryFallback = false;
      this.memoryStore = {
        meta: [],
        requests: {}
      };
    }
    /**
     * Initialize storage, handle migration, store current request
     */
    init() {
      if (!this.isLocalStorageAvailable()) {
        console.warn("[DevToolbar] localStorage unavailable, using in-memory storage");
        this.useMemoryFallback = true;
      }
      this.handleMigration();
      const win = window;
      if (win.__DEV_TOOLBAR_DATA__) {
        const { id, metadata, tabs } = win.__DEV_TOOLBAR_DATA__;
        this.storeRequest(id, metadata, tabs);
      }
    }
    /**
     * Check if localStorage is available
     * Returns false in private browsing mode or when disabled
     */
    isLocalStorageAvailable() {
      try {
        const testKey = "__devToolbarTest__";
        localStorage.setItem(testKey, "1");
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
    handleMigration() {
      const config = this.getConfig();
      if (config.migrated) {
        return;
      }
      const win = window;
      if (win.__DEV_TOOLBAR_MIGRATION__ && Array.isArray(win.__DEV_TOOLBAR_MIGRATION__)) {
        console.log("[DevToolbar] Migrating", win.__DEV_TOOLBAR_MIGRATION__.length, "requests from session");
        win.__DEV_TOOLBAR_MIGRATION__.forEach((request) => {
          this.storeRequest(request.id, request.metadata, request.tabs);
        });
        console.log("[DevToolbar] Migration completed");
      }
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
    storeRequest(id, metadata, tabs) {
      console.log("[StorageManager] Storing request:", id, "useMemoryFallback:", this.useMemoryFallback);
      try {
        if (this.useMemoryFallback) {
          this.storeInMemory(id, metadata, tabs);
          console.log("[StorageManager] Stored in memory, total:", this.memoryStore.meta.length);
          return;
        }
        const metaArray = this.getMetadata();
        metaArray.unshift(metadata);
        if (metaArray.length > MAX_METADATA) {
          metaArray.length = MAX_METADATA;
        }
        localStorage.setItem(META_KEY, JSON.stringify(metaArray));
        const fullData = { id, metadata, tabs };
        localStorage.setItem(DATA_PREFIX + id, JSON.stringify(fullData));
        this.enforceQuotaLimits();
      } catch (e) {
        if (e.name === "QuotaExceededError") {
          console.warn("[DevToolbar] Quota exceeded, evicting oldest entries");
          this.evictOldest();
          try {
            const metaArray = this.getMetadata();
            metaArray.unshift(metadata);
            if (metaArray.length > MAX_METADATA) {
              metaArray.length = MAX_METADATA;
            }
            localStorage.setItem(META_KEY, JSON.stringify(metaArray));
            localStorage.setItem(DATA_PREFIX + id, JSON.stringify({ id, metadata, tabs }));
          } catch (retryError) {
            console.error("[DevToolbar] Failed to store after eviction:", retryError);
          }
        } else {
          console.error("[DevToolbar] Storage error:", e);
        }
      }
    }
    /**
     * Store in memory (private browsing fallback)
     */
    storeInMemory(id, metadata, tabs) {
      this.memoryStore.meta.unshift(metadata);
      if (this.memoryStore.meta.length > MAX_METADATA) {
        this.memoryStore.meta.length = MAX_METADATA;
      }
      this.memoryStore.requests[id] = { id, metadata, tabs };
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
    getRequest(id) {
      try {
        if (this.useMemoryFallback) {
          return this.memoryStore.requests[id] || null;
        }
        const data = localStorage.getItem(DATA_PREFIX + id);
        return data ? JSON.parse(data) : null;
      } catch (e) {
        console.error("[DevToolbar] Failed to retrieve request:", e);
        return null;
      }
    }
    /**
     * Get all metadata entries
     *
     * @return Array of metadata objects
     */
    getMetadata() {
      try {
        if (this.useMemoryFallback) {
          return this.memoryStore.meta;
        }
        const data = localStorage.getItem(META_KEY);
        return data ? JSON.parse(data) : [];
      } catch (e) {
        console.error("[DevToolbar] Failed to retrieve metadata:", e);
        return [];
      }
    }
    /**
     * Enforce quota limits - keep only MAX_FULL_DATA entries
     */
    enforceQuotaLimits() {
      if (this.useMemoryFallback) {
        return;
      }
      const metaArray = this.getMetadata();
      const fullDataIds = metaArray.slice(0, MAX_FULL_DATA).map((m) => m.id);
      const keysToDelete = [];
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (key && key.startsWith(DATA_PREFIX)) {
          const id = key.substring(DATA_PREFIX.length);
          if (!fullDataIds.includes(id)) {
            keysToDelete.push(key);
          }
        }
      }
      keysToDelete.forEach((key) => {
        try {
          localStorage.removeItem(key);
        } catch (e) {
          console.error("[DevToolbar] Failed to remove key:", key, e);
        }
      });
      if (keysToDelete.length > 0) {
        console.log("[DevToolbar] Evicted", keysToDelete.length, "old request entries");
      }
    }
    /**
     * Emergency eviction - delete oldest entries until under MIN_SAFE_ENTRIES
     */
    evictOldest() {
      if (this.useMemoryFallback) {
        return;
      }
      const metaArray = this.getMetadata();
      if (metaArray.length > MIN_SAFE_ENTRIES) {
        metaArray.length = MIN_SAFE_ENTRIES;
        localStorage.setItem(META_KEY, JSON.stringify(metaArray));
      }
      const idsToKeep = metaArray.slice(0, MIN_SAFE_ENTRIES).map((m) => m.id);
      const keysToDelete = [];
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
          console.error("[DevToolbar] Failed to remove key during eviction:", key, e);
        }
      });
      console.log("[DevToolbar] Emergency eviction: removed", keysToDelete.length, "entries");
    }
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
          if (key && (key.startsWith("devToolbar.") || key.startsWith(DATA_PREFIX))) {
            keysToDelete.push(key);
          }
        }
        keysToDelete.forEach((key) => localStorage.removeItem(key));
        console.log("[DevToolbar] Cleared all data");
      } catch (e) {
        console.error("[DevToolbar] Failed to clear data:", e);
      }
    }
    /**
     * Get config object
     */
    getConfig() {
      if (this.useMemoryFallback) {
        return { migrated: false };
      }
      try {
        const config = localStorage.getItem(CONFIG_KEY);
        return config ? JSON.parse(config) : { migrated: false };
      } catch (e) {
        return { migrated: false };
      }
    }
    /**
     * Set config object
     */
    setConfig(config) {
      if (this.useMemoryFallback) {
        return;
      }
      try {
        localStorage.setItem(CONFIG_KEY, JSON.stringify(config));
      } catch (e) {
        console.error("[DevToolbar] Failed to save config:", e);
      }
    }
  };
  var StorageManager = new StorageManagerClass();

  // DevToolbar/ui/TabManager.ts
  var TabManager = class {
    constructor() {
      this.currentTab = "request";
      this.STORAGE_KEY = "devtoolbar_active_tab";
    }
    /**
     * Initialize tab manager with optional saved tab
     */
    init() {
      const savedTab = localStorage.getItem(this.STORAGE_KEY);
      if (savedTab) {
        this.currentTab = savedTab;
        console.log("[DevToolbar] Restored active tab:", savedTab);
      }
    }
    /**
     * Get current active tab
     */
    getCurrentTab() {
      return this.currentTab;
    }
    /**
     * Set active tab and update UI
     *
     * @param tabName - Tab identifier (e.g., 'request', 'history', 'queries')
     * @param onActivate - Optional callback after tab activation
     */
    setActiveTab(tabName, onActivate) {
      this.currentTab = tabName;
      console.log("[TabManager] Setting active tab:", tabName);
      localStorage.setItem(this.STORAGE_KEY, tabName);
      this.updateTabButtons(tabName);
      const activated = this.updateTabPanes(tabName);
      if (!activated) {
        console.error("[TabManager] Warning: No pane activated for tab:", tabName);
      }
      if (onActivate) {
        onActivate(tabName);
      }
    }
    /**
     * Update tab button active states
     */
    updateTabButtons(activeTabName) {
      const tabButtons = document.querySelectorAll(".dev-toolbar-panel-tab");
      console.log("[TabManager] Found tab buttons:", tabButtons.length);
      tabButtons.forEach((tab) => {
        const tabElement = tab;
        if (tabElement.dataset.tab === activeTabName) {
          tabElement.classList.add("active");
          console.log("[TabManager] Activated tab button:", activeTabName);
        } else {
          tabElement.classList.remove("active");
        }
      });
    }
    /**
     * Update tab pane active states
     *
     * @returns True if a pane was activated
     */
    updateTabPanes(activeTabName) {
      const panes = document.querySelectorAll(".dev-toolbar-panel-tab-pane");
      console.log("[TabManager] Found panes:", panes.length);
      let activatedPane = false;
      panes.forEach((pane) => {
        const paneElement = pane;
        const paneTab = paneElement.dataset.tab;
        if (paneTab === activeTabName) {
          paneElement.classList.add("active");
          activatedPane = true;
          console.log("[TabManager] \u2713 Activated pane for", activeTabName);
        } else {
          paneElement.classList.remove("active");
        }
      });
      return activatedPane;
    }
    /**
     * Update badge counts for tabs
     *
     * @param badgeCounts - Badge counts for each tab (e.g., {queries: 5, exceptions: 2})
     */
    updateBadgeCounts(badgeCounts) {
      if (!badgeCounts) {
        return;
      }
      console.log("[TabManager] Updating badge counts:", badgeCounts);
      Object.entries(badgeCounts).forEach(([tabName, count]) => {
        const badge = document.querySelector(
          `.dev-toolbar-panel-tab[data-tab="${tabName}"] .dev-toolbar-panel-tab-badge`
        );
        if (badge) {
          badge.textContent = String(count);
          console.log(`[TabManager] Updated ${tabName} badge to:`, count);
        }
      });
    }
    /**
     * Update history tab badge with count from metadata
     *
     * @param count - Number of history entries
     */
    updateHistoryBadge(count) {
      const historyBadge = document.querySelector(
        '.dev-toolbar-panel-tab[data-tab="history"] .dev-toolbar-panel-tab-badge'
      );
      if (historyBadge) {
        historyBadge.textContent = String(count);
        console.log("[TabManager] HISTORY badge updated to:", count);
      } else {
        console.warn("[TabManager] HISTORY badge element not found");
      }
    }
  };

  // DevToolbar/utils/timeUtils.ts
  function timeAgo(timestamp) {
    const seconds = Math.floor(Date.now() / 1e3 - timestamp);
    if (seconds < 60) return `${seconds}s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
  }

  // DevToolbar/ui/RequestSwitcher.ts
  var RequestSwitcher = class {
    constructor() {
      this.isLoadingRequest = false;
      this.isViewingHistoricalRequest = false;
      this.currentHistoricalRequestId = null;
      this.currentRequestData = null;
      this.originalBadgeCounts = null;
    }
    /**
     * Initialize request switcher
     *
     * @param onRequestLoad - Callback when request is loaded (for tab re-initialization)
     */
    init(onRequestLoad) {
      const switcher = document.querySelector(".dev-toolbar-request-switcher");
      if (!switcher) return;
      const toggle = switcher.querySelector(".dev-toolbar-request-switcher-toggle");
      const dropdown = switcher.querySelector(".dev-toolbar-request-switcher-dropdown");
      this.storeCurrentRequestData();
      if (typeof window !== "undefined" && "window" in globalThis) {
        const win = window;
        if (win.__DEV_TOOLBAR_DATA__ && win.__DEV_TOOLBAR_DATA__.metadata) {
          this.originalBadgeCounts = win.__DEV_TOOLBAR_DATA__.metadata.badge_counts;
          console.log("[RequestSwitcher] Stored original badge counts:", this.originalBadgeCounts);
        }
      }
      this.populateSwitcher();
      toggle?.addEventListener("click", (e) => {
        e.stopPropagation();
        switcher.classList.toggle("open");
      });
      document.addEventListener("click", (e) => {
        if (!switcher.contains(e.target)) {
          switcher.classList.remove("open");
        }
      });
      dropdown?.addEventListener("click", (e) => {
        const item = e.target.closest(".dev-toolbar-request-switcher-item");
        if (!item) return;
        console.log("[RequestSwitcher] Item clicked:", item.dataset);
        if (item.dataset.action === "view-history") {
          console.log("[RequestSwitcher] Opening HISTORY tab");
          if (onRequestLoad) {
            onRequestLoad("history");
          }
          switcher.classList.remove("open");
          return;
        }
        if (item.dataset.action === "current") {
          switcher.classList.remove("open");
          if (this.isViewingHistoricalRequest) {
            this.restoreCurrentRequest(onRequestLoad);
          } else {
            console.log("[RequestSwitcher] Already viewing current request");
          }
          return;
        }
        const requestId = item.dataset.requestId;
        if (requestId) {
          this.loadHistoricalRequest(requestId, onRequestLoad);
        }
      });
      console.log("[RequestSwitcher] Initialized");
    }
    /**
     * Check if currently viewing a historical request
     */
    isViewingHistory() {
      return this.isViewingHistoricalRequest;
    }
    /**
     * Get currently viewed historical request ID
     */
    getCurrentHistoricalRequestId() {
      return this.currentHistoricalRequestId;
    }
    /**
     * Populate switcher dropdown from localStorage
     */
    populateSwitcher() {
      const dropdown = document.querySelector(".dev-toolbar-request-switcher-dropdown");
      if (!dropdown) return;
      const metaArray = StorageManager.getMetadata();
      console.log("[RequestSwitcher] Populating with", metaArray.length, "requests");
      let html = "";
      const isCurrentActive = !this.isViewingHistoricalRequest;
      html += `<div class="dev-toolbar-request-switcher-item ${isCurrentActive ? "active" : ""}" data-action="current">
            <span class="dev-toolbar-request-switcher-item-indicator">\u25CF</span>
            <span class="dev-toolbar-request-switcher-item-label">Current Request</span>
        </div>`;
      const recentRequests = metaArray.slice(0, 5);
      if (recentRequests.length > 0) {
        html += '<div class="dev-toolbar-request-switcher-separator">Recent</div>';
        recentRequests.forEach((meta) => {
          const isActive = this.isViewingHistoricalRequest && this.currentHistoricalRequestId === meta.id;
          const statusClass = this.getStatusClass(meta.statusCode);
          const time = timeAgo(meta.timestamp);
          html += `<div class="dev-toolbar-request-switcher-item ${isActive ? "active" : ""}" data-request-id="${meta.id}">
                    <span class="dev-toolbar-request-switcher-item-method">${meta.method}</span>
                    <span class="dev-toolbar-request-switcher-item-status status-${statusClass}">${meta.statusCode}</span>
                    <span class="dev-toolbar-request-switcher-item-uri" title="${this.escapeHtml(meta.uri)}">${this.escapeHtml(meta.uri)}</span>
                    <span class="dev-toolbar-request-switcher-item-time">${time}</span>
                </div>`;
        });
      }
      if (metaArray.length > 5) {
        html += `<div class="dev-toolbar-request-switcher-item dev-toolbar-request-switcher-item-action" data-action="view-history">
                <span class="dev-toolbar-request-switcher-item-label">View all ${metaArray.length} requests \u2192</span>
            </div>`;
      } else if (metaArray.length > 0) {
        html += `<div class="dev-toolbar-request-switcher-item dev-toolbar-request-switcher-item-action" data-action="view-history">
                <span class="dev-toolbar-request-switcher-item-label">View history \u2192</span>
            </div>`;
      }
      dropdown.innerHTML = html;
    }
    /**
     * Load historical request from localStorage
     */
    async loadHistoricalRequest(requestId, onRequestLoad) {
      if (this.isLoadingRequest) {
        console.log("[RequestSwitcher] Already loading a request, ignoring");
        return;
      }
      this.isLoadingRequest = true;
      const switcher = document.querySelector(".dev-toolbar-request-switcher");
      switcher?.classList.add("loading");
      console.log("[RequestSwitcher] Loading historical request:", requestId);
      try {
        const requestData = StorageManager.getRequest(requestId);
        if (!requestData) {
          throw new Error("Request not found in localStorage");
        }
        const tabsHtml = this.buildTabsHTML(requestData);
        const contentContainer = document.querySelector(".dev-toolbar-panel-content");
        if (!contentContainer) {
          throw new Error("Content container not found");
        }
        contentContainer.innerHTML = tabsHtml;
        this.isViewingHistoricalRequest = true;
        this.currentHistoricalRequestId = requestId;
        this.populateSwitcher();
        this.updateSwitcherLabel(requestId, true);
        if (onRequestLoad) {
          onRequestLoad(requestId);
        }
        console.log("[RequestSwitcher] Successfully loaded historical request");
      } catch (error) {
        console.error("[RequestSwitcher] Failed to load request:", error);
      } finally {
        this.isLoadingRequest = false;
        switcher?.classList.remove("loading");
        switcher?.classList.remove("open");
      }
    }
    /**
     * Restore current request
     */
    restoreCurrentRequest(onRequestLoad) {
      console.log("[RequestSwitcher] Restoring current request");
      const contentContainer = document.querySelector(".dev-toolbar-panel-content");
      if (!contentContainer || !this.currentRequestData) {
        return;
      }
      contentContainer.innerHTML = this.currentRequestData;
      this.isViewingHistoricalRequest = false;
      this.currentHistoricalRequestId = null;
      this.populateSwitcher();
      this.updateSwitcherLabel("current", false);
      if (onRequestLoad) {
        onRequestLoad("current");
      }
      console.log("[RequestSwitcher] Restored current request");
    }
    /**
     * Store current request data for restoration
     */
    storeCurrentRequestData() {
      const contentContainer = document.querySelector(".dev-toolbar-panel-content");
      if (contentContainer) {
        this.currentRequestData = contentContainer.innerHTML;
      }
    }
    /**
     * Build tabs HTML from request data
     */
    buildTabsHTML(requestData) {
      let html = "";
      for (const [tabName, content] of Object.entries(requestData.tabs)) {
        html += `<div class="dev-toolbar-panel-tab-pane" data-tab="${tabName}">${content}</div>`;
      }
      return html;
    }
    /**
     * Update switcher label
     */
    updateSwitcherLabel(requestId, isHistorical) {
      const switcher = document.querySelector(".dev-toolbar-request-switcher");
      const toggle = switcher?.querySelector(".dev-toolbar-request-switcher-toggle");
      if (!toggle) return;
      toggle.dataset.current = requestId;
      const label = toggle.querySelector(".dev-toolbar-request-switcher-label");
      if (label) {
        label.textContent = isHistorical ? "Request (Historical)" : "Request";
        label.style.color = isHistorical ? "#f59e0b" : "";
      }
    }
    /**
     * Get status class for status code
     */
    getStatusClass(statusCode) {
      if (statusCode >= 200 && statusCode < 300) return "success";
      if (statusCode >= 300 && statusCode < 400) return "redirect";
      if (statusCode >= 400 && statusCode < 500) return "client-error";
      if (statusCode >= 500) return "server-error";
      return "unknown";
    }
    /**
     * Escape HTML
     */
    escapeHtml(text) {
      const map = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
      };
      return text.replace(/[&<>"']/g, (char) => map[char] || char);
    }
    /**
     * Get original badge counts
     */
    getOriginalBadgeCounts() {
      return this.originalBadgeCounts;
    }
  };

  // DevToolbar/ui/XdebugControls.ts
  var XdebugControls = class {
    constructor() {
      this.CONTAINER_ID = "dev-toolbar-request-controls-container";
    }
    /**
     * Render Xdebug controls based on current state
     *
     * Reads configuration from window.__XDEBUG_CONFIG__ and cookies
     */
    render() {
      const container = document.getElementById(this.CONTAINER_ID);
      if (!container) {
        return;
      }
      const xdebugConfig = this.getXdebugConfig();
      const xdebugEnabled = xdebugConfig.enabled;
      const { xdebugActive, xdebugSessionName } = this.getXdebugStatus();
      const html = this.buildControlsHTML(xdebugEnabled, xdebugActive, xdebugSessionName);
      container.innerHTML = html;
      console.log("[Xdebug] Controls rendered, status:", xdebugActive ? "active" : "inactive");
    }
    /**
     * Get Xdebug configuration from window global
     */
    getXdebugConfig() {
      if (typeof window !== "undefined" && "window" in globalThis) {
        const win = window;
        return win.__XDEBUG_CONFIG__ || { enabled: false, mode: "off" };
      }
      return { enabled: false, mode: "off" };
    }
    /**
     * Get Xdebug session status from cookies
     */
    getXdebugStatus() {
      const cookies = this.parseCookies();
      const xdebugActive = "XDEBUG_SESSION" in cookies;
      const xdebugSessionName = cookies["XDEBUG_SESSION"] || "";
      return { xdebugActive, xdebugSessionName };
    }
    /**
     * Parse document.cookie into key-value pairs
     */
    parseCookies() {
      if (typeof document === "undefined") {
        return {};
      }
      return document.cookie.split(";").reduce((acc, cookie) => {
        const [key, value] = cookie.trim().split("=");
        if (key) {
          acc[key] = value || "";
        }
        return acc;
      }, {});
    }
    /**
     * Build controls HTML
     */
    buildControlsHTML(xdebugEnabled, xdebugActive, xdebugSessionName) {
      let html = '<div class="dev-toolbar-request-status">';
      if (xdebugEnabled) {
        const statusClass = xdebugActive ? "active" : "inactive";
        const statusText = xdebugActive ? `Xdebug: ${xdebugSessionName}` : "Xdebug: Off";
        const statusIcon = xdebugActive ? "\u25CF" : "\u25CB";
        html += `<div class="dev-toolbar-xdebug-compact dev-toolbar-xdebug-compact-${statusClass}">
                <span class="dev-toolbar-xdebug-indicator">${statusIcon}</span>
                <span class="dev-toolbar-xdebug-label">${this.escapeHtml(statusText)}</span>
            </div>`;
      } else {
        html += `<div class="dev-toolbar-xdebug-compact dev-toolbar-xdebug-compact-disabled">
                <span class="dev-toolbar-xdebug-label">Xdebug: Not Installed</span>
            </div>`;
      }
      html += "</div>";
      html += '<div class="dev-toolbar-request-actions">';
      if (xdebugEnabled) {
        if (xdebugActive) {
          html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-disable" title="Disable Xdebug step debugging">
                    \u23F9 Disable
                </button>`;
        } else {
          html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="PHPSTORM" title="Enable Xdebug for PhpStorm">
                    \u25B6 PhpStorm
                </button>`;
          html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="VSCODE" title="Enable Xdebug for VSCode">
                    \u25B6 VSCode
                </button>`;
        }
      }
      html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="export-current" title="Export current request as JSON">
            \u2B07 Export
        </button>`;
      html += "</div>";
      return html;
    }
    /**
     * Escape HTML special characters
     */
    escapeHtml(text) {
      const map = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
      };
      return text.replace(/[&<>"']/g, (char) => map[char] || char);
    }
    /**
     * Enable Xdebug debugging for specific IDE
     *
     * @param ide - IDE identifier (PHPSTORM, VSCODE)
     */
    enableXdebug(ide) {
      console.log(`[Xdebug] Enabling Xdebug for ${ide}`);
      document.cookie = `XDEBUG_SESSION=${ide}; path=/; max-age=3600`;
      this.render();
      window.location.reload();
    }
    /**
     * Disable Xdebug debugging
     */
    disableXdebug() {
      console.log("[Xdebug] Disabling Xdebug");
      document.cookie = "XDEBUG_SESSION=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT";
      this.render();
      window.location.reload();
    }
  };

  // DevToolbar/utils/exportUtils.ts
  function exportRequestAsJson(requestId, requestData) {
    return {
      toolbar_version: "2.1.0",
      export_time: (/* @__PURE__ */ new Date()).toISOString(),
      request_id: requestId,
      metadata: requestData.metadata,
      html_data: requestData.tabs
    };
  }
  function downloadJson(content, filename) {
    const json = typeof content === "string" ? content : JSON.stringify(content, null, 2);
    const blob = new Blob([json], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  }

  // DevToolbar/ui/DevToolbarUI.ts
  var DevToolbarUI = class {
    constructor() {
      this.miniBar = null;
      this.panel = null;
      this.historyTabInitialized = false;
      this.tabManager = new TabManager();
      this.requestSwitcher = new RequestSwitcher();
      this.xdebugControls = new XdebugControls();
    }
    /**
     * Initialize DevToolbar UI
     */
    init() {
      console.log("[DevToolbar] Initializing UI...");
      StorageManager.init();
      this.tabManager.init();
      this.miniBar = document.querySelector(".dev-toolbar-mini");
      this.panel = document.querySelector(".dev-toolbar-panel");
      if (!this.miniBar || !this.panel) {
        console.error("[DevToolbar] Mini bar or panel not found");
        return;
      }
      this.updateHistoryBadge();
      this.restoreState();
      this.attachEventListeners();
      this.requestSwitcher.init((requestId) => this.handleRequestLoad(requestId));
      this.tabManager.setActiveTab(
        this.tabManager.getCurrentTab(),
        (tabName) => this.handleTabActivate(tabName)
      );
      console.log("[DevToolbar] UI initialization complete");
    }
    /**
     * Attach all event listeners
     */
    attachEventListeners() {
      this.miniBar?.addEventListener("click", () => {
        this.togglePanel();
      });
      document.querySelectorAll(".dev-toolbar-panel-tab").forEach((tab) => {
        tab.addEventListener("click", (e) => {
          const tabName = e.target.closest(".dev-toolbar-panel-tab")?.getAttribute("data-tab");
          if (tabName) {
            this.tabManager.setActiveTab(tabName, (name) => this.handleTabActivate(name));
          }
        });
      });
      const closeBtn = document.querySelector(".dev-toolbar-panel-close");
      closeBtn?.addEventListener("click", () => {
        this.closePanel();
      });
      const maximizeBtn = document.querySelector(".dev-toolbar-panel-maximize");
      maximizeBtn?.addEventListener("click", () => {
        this.toggleMaximize();
      });
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && this.panel?.classList.contains("open")) {
          this.closePanel();
        }
      });
      this.preventBackgroundScroll();
      this.attachAlertHandlers();
      this.attachXdebugHandlers();
    }
    /**
     * Handle tab activation
     */
    handleTabActivate(tabName) {
      console.log("[DevToolbarUI] Tab activated:", tabName);
      if (tabName === "request") {
        setTimeout(() => this.xdebugControls.render(), 50);
        setTimeout(() => this.attachXdebugHandlers(), 100);
      }
      if (tabName === "history") {
        setTimeout(() => this.initHistoryTab(), 50);
      }
    }
    /**
     * Handle request load (historical or current)
     */
    handleRequestLoad(requestId) {
      console.log("[DevToolbarUI] Request loaded:", requestId);
      this.historyTabInitialized = false;
      setTimeout(() => {
        this.reattachTabListeners();
        const currentTab = this.tabManager.getCurrentTab();
        this.tabManager.setActiveTab(currentTab, (name) => this.handleTabActivate(name));
        if (requestId !== "current" && requestId !== "history") {
          const requestData = StorageManager.getRequest(requestId);
          if (requestData?.metadata?.badge_counts) {
            this.tabManager.updateBadgeCounts(requestData.metadata.badge_counts);
          }
        } else {
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
    reattachTabListeners() {
      console.log("[DevToolbarUI] Re-attaching tab event listeners");
      const tabButtons = document.querySelectorAll(".dev-toolbar-panel-tab");
      console.log("[DevToolbarUI] Found", tabButtons.length, "tab buttons");
      tabButtons.forEach((tab) => {
        const newTab = tab.cloneNode(true);
        tab.parentNode?.replaceChild(newTab, tab);
        newTab.addEventListener("click", () => {
          const tabName = newTab.dataset.tab;
          if (tabName) {
            console.log("[DevToolbarUI] Tab clicked:", tabName);
            this.tabManager.setActiveTab(tabName, (name) => this.handleTabActivate(name));
          }
        });
      });
      this.xdebugControls.render();
      this.attachXdebugHandlers();
    }
    /**
     * Toggle panel open/close
     */
    togglePanel() {
      if (this.panel?.classList.contains("open")) {
        this.closePanel();
      } else {
        this.openPanel();
      }
    }
    /**
     * Open panel
     */
    openPanel() {
      this.panel?.classList.add("open");
      try {
        localStorage.setItem("devToolbar.open", "1");
      } catch (e) {
        console.warn("[DevToolbar] Could not save open state", e);
      }
    }
    /**
     * Close panel
     */
    closePanel() {
      this.panel?.classList.remove("open");
      try {
        localStorage.setItem("devToolbar.open", "0");
      } catch (e) {
        console.warn("[DevToolbar] Could not save open state", e);
      }
    }
    /**
     * Toggle maximize
     */
    toggleMaximize() {
      this.panel?.classList.toggle("maximized");
      const isMaximized = this.panel?.classList.contains("maximized");
      try {
        localStorage.setItem("devToolbar.maximized", isMaximized ? "1" : "0");
      } catch (e) {
        console.warn("[DevToolbar] Could not save maximized state", e);
      }
      const maximizeBtn = document.querySelector(".dev-toolbar-panel-maximize");
      if (maximizeBtn) {
        maximizeBtn.setAttribute("title", isMaximized ? "Restore" : "Maximize");
      }
    }
    /**
     * Restore panel state from localStorage
     */
    restoreState() {
      try {
        const isMaximized = localStorage.getItem("devToolbar.maximized") === "1";
        if (isMaximized) {
          this.panel?.classList.add("maximized");
        }
        const isOpen = localStorage.getItem("devToolbar.open") === "1";
        if (isOpen) {
          this.panel?.classList.add("open");
        }
      } catch (e) {
        console.warn("[DevToolbar] Could not restore state", e);
      }
    }
    /**
     * Prevent background page scrolling
     */
    preventBackgroundScroll() {
      this.panel?.addEventListener(
        "wheel",
        (e) => {
          e.stopPropagation();
          const content = document.querySelector(".dev-toolbar-panel-content");
          if (content && content.contains(e.target)) {
            const atTop = content.scrollTop === 0;
            const atBottom = content.scrollTop + content.clientHeight >= content.scrollHeight;
            if (atTop && e.deltaY < 0 || atBottom && e.deltaY > 0) {
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
    attachAlertHandlers() {
      const dismissAllBtn = document.querySelector(".dev-toolbar-alerts-dismiss");
      dismissAllBtn?.addEventListener("click", () => {
        const alertsContainer = document.querySelector(".dev-toolbar-alerts");
        alertsContainer?.classList.add("dismissed");
      });
      document.querySelectorAll(".dev-toolbar-alert-close").forEach((closeBtn) => {
        closeBtn.addEventListener("click", (e) => {
          const alert = e.target.closest(".dev-toolbar-alert");
          alert?.classList.add("dismissed");
          setTimeout(() => {
            const alertsContainer = document.querySelector(".dev-toolbar-alerts");
            const remainingAlerts = alertsContainer?.querySelectorAll(".dev-toolbar-alert:not(.dismissed)");
            if (remainingAlerts && remainingAlerts.length === 0) {
              alertsContainer?.classList.add("dismissed");
            }
          }, 300);
        });
      });
    }
    /**
     * Attach Xdebug control handlers
     */
    attachXdebugHandlers() {
      document.querySelectorAll('[data-action="xdebug-enable"]').forEach((btn) => {
        btn.addEventListener("click", (e) => {
          const ide = e.target.dataset.ide || "PHPSTORM";
          this.xdebugControls.enableXdebug(ide);
        });
      });
      const disableBtn = document.querySelector('[data-action="xdebug-disable"]');
      disableBtn?.addEventListener("click", () => {
        this.xdebugControls.disableXdebug();
      });
      const exportBtn = document.querySelector('[data-action="export-current"]');
      exportBtn?.addEventListener("click", () => {
        this.exportCurrentRequest();
      });
    }
    /**
     * Export current request as JSON
     */
    exportCurrentRequest() {
      const win = window;
      const toolbarData = win.__DEV_TOOLBAR_DATA__;
      if (!toolbarData) {
        console.error("[DevToolbar] No request data available");
        return;
      }
      const exportData = exportRequestAsJson(toolbarData.id, toolbarData);
      const filename = `devtoolbar-request-${toolbarData.id}-${Date.now()}.json`;
      downloadJson(exportData, filename);
      console.log("[DevToolbar] Exported current request");
    }
    /**
     * Update history badge
     */
    updateHistoryBadge() {
      const metaArray = StorageManager.getMetadata();
      this.tabManager.updateHistoryBadge(metaArray.length);
    }
    /**
     * Initialize history tab (placeholder for full implementation)
     */
    initHistoryTab() {
      if (this.historyTabInitialized) {
        console.log("[DevToolbar] History tab already initialized");
        return;
      }
      console.log("[DevToolbar] Initializing History tab");
      this.historyTabInitialized = true;
    }
  };

  // DevToolbar/index.ts
  function initDevToolbar() {
    const toolbar = new DevToolbarUI();
    toolbar.init();
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDevToolbar);
  } else {
    initDevToolbar();
  }
})();
//# sourceMappingURL=devtoolbar.js.map
