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
  var DEFAULT_MINIBAR_LABELS = [
    "branding"
  ];
  var DEFAULT_BRANCH_COLORS = {
    feat: "#3b82f6",
    // Blue
    fix: "#f59e0b",
    // Orange
    hotfix: "#ef4444",
    // Red
    chore: "#6b7280",
    // Gray
    default: "#10b981"
    // Green
  };
  var DEFAULT_TOGGLE_SHORTCUT = {
    key: "D",
    ctrlKey: true,
    shiftKey: true,
    altKey: false,
    metaKey: false
  };

  // DevToolbar/utils/logger.ts
  var isDev = true;
  function devLog(...args) {
    if (isDev) {
      console.log(...args);
    }
  }
  function debug(...args) {
    devLog(...args);
  }
  function warn(...args) {
    console.warn(...args);
  }
  function error(...args) {
    console.error(...args);
  }

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
     * Initialize storage and store current request
     */
    init() {
      if (!this.isLocalStorageAvailable()) {
        warn("[DevToolbar] localStorage unavailable, using in-memory storage");
        this.useMemoryFallback = true;
      }
      const win = window;
      if (win.__DEV_TOOLBAR_DATA__ != null) {
        const { id, metadata, tabs, raw_data } = win.__DEV_TOOLBAR_DATA__;
        this.storeRequest(id, metadata, tabs, raw_data);
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
      } catch {
        return false;
      }
    }
    /**
     * Store request with quota handling
     *
     * @param id Request ID
     * @param metadata Lightweight metadata
     * @param tabs Full tab HTML content
     * @param rawData Optional structured collector data for export
     */
    storeRequest(id, metadata, tabs, rawData) {
      debug("[StorageManager] Storing request:", id, "useMemoryFallback:", this.useMemoryFallback);
      try {
        if (this.useMemoryFallback) {
          this.storeInMemory(id, metadata, tabs, rawData);
          debug("[StorageManager] Stored in memory, total:", this.memoryStore.meta.length);
          return;
        }
        const metaArray = this.getMetadata();
        metaArray.unshift(metadata);
        if (metaArray.length > MAX_METADATA) {
          metaArray.length = MAX_METADATA;
        }
        localStorage.setItem(META_KEY, JSON.stringify(metaArray));
        const fullData = { id, metadata, tabs, raw_data: rawData };
        localStorage.setItem(DATA_PREFIX + id, JSON.stringify(fullData));
        this.enforceQuotaLimits();
      } catch (e) {
        if (e.name === "QuotaExceededError") {
          warn("[DevToolbar] Quota exceeded, evicting oldest entries");
          this.evictOldest();
          try {
            const metaArray = this.getMetadata();
            metaArray.unshift(metadata);
            if (metaArray.length > MAX_METADATA) {
              metaArray.length = MAX_METADATA;
            }
            localStorage.setItem(META_KEY, JSON.stringify(metaArray));
            localStorage.setItem(
              DATA_PREFIX + id,
              JSON.stringify({ id, metadata, tabs, raw_data: rawData })
            );
          } catch (retryError) {
            error("[DevToolbar] Failed to store after eviction:", retryError);
          }
        } else {
          error("[DevToolbar] Storage error:", e);
        }
      }
    }
    /**
     * Store in memory (private browsing fallback)
     */
    storeInMemory(id, metadata, tabs, rawData) {
      this.memoryStore.meta.unshift(metadata);
      if (this.memoryStore.meta.length > MAX_METADATA) {
        this.memoryStore.meta.length = MAX_METADATA;
      }
      this.memoryStore.requests[id] = { id, metadata, tabs, raw_data: rawData };
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
          return this.memoryStore.requests[id] ?? null;
        }
        const data = localStorage.getItem(DATA_PREFIX + id);
        return data !== null ? JSON.parse(data) : null;
      } catch (error2) {
        error("[DevToolbar] Failed to retrieve request:", error2);
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
        return data !== null ? JSON.parse(data) : [];
      } catch (error2) {
        error("[DevToolbar] Failed to retrieve metadata:", error2);
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
        if (key?.startsWith(DATA_PREFIX)) {
          const id = key.substring(DATA_PREFIX.length);
          if (!fullDataIds.includes(id)) {
            keysToDelete.push(key);
          }
        }
      }
      keysToDelete.forEach((key) => {
        try {
          localStorage.removeItem(key);
        } catch (error2) {
          error("[DevToolbar] Failed to remove key:", key, error2);
        }
      });
      if (keysToDelete.length > 0) {
        debug("[DevToolbar] Evicted", keysToDelete.length, "old request entries");
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
        if (key?.startsWith(DATA_PREFIX)) {
          const id = key.substring(DATA_PREFIX.length);
          if (!idsToKeep.includes(id)) {
            keysToDelete.push(key);
          }
        }
      }
      keysToDelete.forEach((key) => {
        try {
          localStorage.removeItem(key);
        } catch (error2) {
          error("[DevToolbar] Failed to remove key during eviction:", key, error2);
        }
      });
      debug("[DevToolbar] Emergency eviction: removed", keysToDelete.length, "entries");
    }
    /**
     * Clear history data only (preserve settings and UI state)
     *
     * Removes:
     * - devToolbar.meta (metadata array)
     * - devToolbar.req_* (request data)
     *
     * Preserves:
     * - devToolbar.config (settings including minibarLabel, branchColors)
     * - devtoolbar_active_tab (current tab state)
     * - xdebug cookie (stored in document.cookie, not localStorage)
     */
    clear() {
      if (this.useMemoryFallback) {
        this.memoryStore = { meta: [], requests: {} };
        return;
      }
      try {
        localStorage.removeItem(META_KEY);
        const keysToDelete = [];
        for (let i = 0; i < localStorage.length; i++) {
          const key = localStorage.key(i);
          if (key?.startsWith(DATA_PREFIX)) {
            keysToDelete.push(key);
          }
        }
        keysToDelete.forEach((key) => localStorage.removeItem(key));
        debug("[DevToolbar] Cleared history data, preserved settings");
      } catch (error2) {
        error("[DevToolbar] Failed to clear data:", error2);
      }
    }
    /**
     * Get config object
     */
    getConfig() {
      if (this.useMemoryFallback) {
        return {};
      }
      try {
        const config = localStorage.getItem(CONFIG_KEY);
        return config !== null ? JSON.parse(config) : {};
      } catch {
        return {};
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
      } catch (error2) {
        error("[DevToolbar] Failed to save config:", error2);
      }
    }
    /**
     * Get active minibar labels
     */
    getMinibarLabels() {
      const config = this.getConfig();
      return config.minibarLabels ?? DEFAULT_MINIBAR_LABELS;
    }
    /**
     * Set active minibar labels
     */
    setMinibarLabels(labels) {
      const config = this.getConfig();
      this.setConfig({ ...config, minibarLabels: labels });
    }
    /**
     * Get branch color configuration
     */
    getBranchColors() {
      const config = this.getConfig();
      return config.branchColors ?? DEFAULT_BRANCH_COLORS;
    }
    /**
     * Set branch color configuration
     */
    setBranchColors(colors) {
      const config = this.getConfig();
      this.setConfig({ ...config, branchColors: colors });
    }
    /**
     * Get keyboard shortcut for toggling toolbar
     */
    getToggleShortcut() {
      const config = this.getConfig();
      return config.toggleShortcut ?? DEFAULT_TOGGLE_SHORTCUT;
    }
    /**
     * Set keyboard shortcut for toggling toolbar
     */
    setToggleShortcut(shortcut) {
      const config = this.getConfig();
      this.setConfig({ ...config, toggleShortcut: shortcut });
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
      if (savedTab != null) {
        this.currentTab = savedTab;
        debug("[DevToolbar] Restored active tab:", savedTab);
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
      debug("[TabManager] Setting active tab:", tabName);
      localStorage.setItem(this.STORAGE_KEY, tabName);
      this.updateTabButtons(tabName);
      const activated = this.updateTabPanes(tabName);
      if (!activated) {
        error("[TabManager] Warning: No pane activated for tab:", tabName);
      }
      if (onActivate != null) {
        onActivate(tabName);
      }
    }
    /**
     * Update tab button active states
     */
    updateTabButtons(activeTabName) {
      const tabButtons = document.querySelectorAll(".dev-toolbar-panel-tab");
      debug("[TabManager] Found tab buttons:", tabButtons.length);
      tabButtons.forEach((tab) => {
        const tabElement = tab;
        if (tabElement.dataset.tab === activeTabName) {
          tabElement.classList.add("active");
          debug("[TabManager] Activated tab button:", activeTabName);
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
      debug("[TabManager] Found panes:", panes.length);
      let activatedPane = false;
      panes.forEach((pane) => {
        const paneElement = pane;
        const paneTab = paneElement.dataset.tab;
        if (paneTab === activeTabName) {
          paneElement.classList.add("active");
          activatedPane = true;
          debug("[TabManager] \u2713 Activated pane for", activeTabName);
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
      if (badgeCounts == null) {
        return;
      }
      debug("[TabManager] Updating badge counts:", badgeCounts);
      Object.entries(badgeCounts).forEach(([tabName, count]) => {
        const badge = document.querySelector(
          `.dev-toolbar-panel-tab[data-tab="${tabName}"] .dev-toolbar-panel-tab-badge`
        );
        if (badge != null) {
          badge.textContent = String(count);
          debug(`[TabManager] Updated ${tabName} badge to:`, count);
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
      if (historyBadge != null) {
        historyBadge.textContent = String(count);
        debug("[TabManager] HISTORY badge updated to:", count);
      } else {
        warn("[TabManager] HISTORY badge element not found");
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
  function formatTimestamp(timestamp) {
    const date = new Date(timestamp * 1e3);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    const hours = String(date.getHours()).padStart(2, "0");
    const minutes = String(date.getMinutes()).padStart(2, "0");
    const seconds = String(date.getSeconds()).padStart(2, "0");
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
  }
  function generateSparkline(values) {
    if (!values || values.length === 0) {
      return "";
    }
    const ticks = ["\u2581", "\u2582", "\u2583", "\u2584", "\u2585", "\u2586", "\u2587", "\u2588"];
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min;
    if (range === 0) {
      return (ticks[3] ?? "\u2584").repeat(values.length);
    }
    let sparkline = "";
    values.forEach((value) => {
      const normalized = (value - min) / range;
      const index = Math.min(7, Math.floor(normalized * 8));
      sparkline += ticks[index] ?? "\u2581";
    });
    return sparkline;
  }

  // DevToolbar/utils/uiHelpers.ts
  function escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;"
    };
    return text.replace(/[&<>"']/g, (char) => map[char] ?? char);
  }
  function createEscapeKeyHandler(onClose) {
    const escHandler = (e) => {
      if (e.key === "Escape") {
        e.preventDefault();
        e.stopImmediatePropagation();
        onClose();
        cleanup();
      }
    };
    const cleanup = () => {
      document.removeEventListener("keydown", escHandler, true);
    };
    document.addEventListener("keydown", escHandler, true);
    return cleanup;
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
        if (win.__DEV_TOOLBAR_DATA__?.metadata != null) {
          this.originalBadgeCounts = win.__DEV_TOOLBAR_DATA__.metadata.badge_counts ?? null;
          debug("[RequestSwitcher] Stored original badge counts:", this.originalBadgeCounts);
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
        const target = e.target;
        const element = target.closest(".dev-toolbar-request-switcher-item");
        if (!(element instanceof HTMLElement)) return;
        debug("[RequestSwitcher] Item clicked:", element.dataset);
        if (element.dataset.action === "view-history") {
          debug("[RequestSwitcher] Opening HISTORY tab");
          if (onRequestLoad != null) {
            onRequestLoad("history");
          }
          switcher.classList.remove("open");
          return;
        }
        if (element.dataset.action === "current") {
          switcher.classList.remove("open");
          if (this.isViewingHistoricalRequest) {
            this.restoreCurrentRequest(onRequestLoad);
          } else {
            debug("[RequestSwitcher] Already viewing current request");
          }
          return;
        }
        const requestId = element.dataset.requestId;
        if (requestId != null) {
          void this.loadHistoricalRequest(requestId, onRequestLoad);
        }
      });
      debug("[RequestSwitcher] Initialized");
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
      debug("[RequestSwitcher] Populating with", metaArray.length, "requests");
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
          const statusClass = this.getStatusClass(meta.status);
          const time = timeAgo(meta.timestamp);
          html += `<div class="dev-toolbar-request-switcher-item ${isActive ? "active" : ""}" data-request-id="${meta.id}">
                    <span class="dev-toolbar-request-switcher-item-method">${meta.method}</span>
                    <span class="dev-toolbar-request-switcher-item-status status-${statusClass}">${meta.status}</span>
                    <span class="dev-toolbar-request-switcher-item-uri" title="${escapeHtml(meta.uri)}">${escapeHtml(meta.uri)}</span>
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
    loadHistoricalRequest(requestId, onRequestLoad) {
      if (this.isLoadingRequest) {
        debug("[RequestSwitcher] Already loading a request, ignoring");
        return;
      }
      this.isLoadingRequest = true;
      const switcher = document.querySelector(".dev-toolbar-request-switcher");
      switcher?.classList.add("loading");
      debug("[RequestSwitcher] Loading historical request:", requestId);
      try {
        const requestData = StorageManager.getRequest(requestId);
        if (requestData == null) {
          throw new Error("Request not found in localStorage");
        }
        const tabsHtml = this.buildTabsHTML(requestData);
        const contentContainer = document.querySelector(".dev-toolbar-panel-content");
        if (contentContainer == null) {
          throw new Error("Content container not found");
        }
        contentContainer.innerHTML = tabsHtml;
        this.isViewingHistoricalRequest = true;
        this.currentHistoricalRequestId = requestId;
        this.populateSwitcher();
        this.updateSwitcherLabel(requestId, true);
        if (onRequestLoad != null) {
          onRequestLoad(requestId);
        }
        debug("[RequestSwitcher] Successfully loaded historical request");
      } catch (error2) {
        error("[RequestSwitcher] Failed to load request:", error2);
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
      debug("[RequestSwitcher] Restoring current request");
      const contentContainer = document.querySelector(".dev-toolbar-panel-content");
      if (contentContainer == null || this.currentRequestData == null) {
        return;
      }
      contentContainer.innerHTML = this.currentRequestData;
      this.isViewingHistoricalRequest = false;
      this.currentHistoricalRequestId = null;
      this.populateSwitcher();
      this.updateSwitcherLabel("current", false);
      if (onRequestLoad != null) {
        onRequestLoad("current");
      }
      debug("[RequestSwitcher] Restored current request");
    }
    /**
     * Store current request data for restoration
     */
    storeCurrentRequestData() {
      const contentContainer = document.querySelector(".dev-toolbar-panel-content");
      if (contentContainer != null) {
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
      const toggle = switcher?.querySelector(
        ".dev-toolbar-request-switcher-toggle"
      );
      if (toggle == null) return;
      toggle.dataset.current = requestId;
      const label = toggle.querySelector(".dev-toolbar-request-switcher-label");
      if (label != null) {
        label.textContent = isHistorical ? "Request (Historical)" : "Request";
        label.style.color = isHistorical ? "#f59e0b" : "";
      }
    }
    /**
     * Get status class for status code
     */
    getStatusClass(status) {
      if (status >= 200 && status < 300) return "success";
      if (status >= 300 && status < 400) return "redirect";
      if (status >= 400 && status < 500) return "client-error";
      if (status >= 500) return "server-error";
      return "unknown";
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
      container.innerHTML = this.buildControlsHTML(xdebugEnabled, xdebugActive, xdebugSessionName);
      debug("[Xdebug] Controls rendered, status:", xdebugActive ? "active" : "inactive");
    }
    /**
     * Get Xdebug configuration from window global
     */
    getXdebugConfig() {
      if (typeof window !== "undefined" && "window" in globalThis) {
        const win = window;
        return win.__XDEBUG_CONFIG__ ?? {
          enabled: false,
          mode: "off",
          idekey: "",
          client_host: "",
          client_port: 0
        };
      }
      return {
        enabled: false,
        mode: "off",
        idekey: "",
        client_host: "",
        client_port: 0
      };
    }
    /**
     * Get Xdebug session status from cookies
     */
    getXdebugStatus() {
      const cookies = this.parseCookies();
      const xdebugActive = "XDEBUG_SESSION" in cookies;
      const xdebugSessionName = cookies["XDEBUG_SESSION"] ?? "";
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
        if (key != null && key !== "") {
          acc[key] = value ?? "";
        }
        return acc;
      }, {});
    }
    /**
     * Build controls HTML
     */
    buildControlsHTML(xdebugEnabled, xdebugActive, xdebugSessionName) {
      const statusSection = this.buildStatusSection(xdebugEnabled, xdebugActive, xdebugSessionName);
      const actionsSection = this.buildActionsSection(xdebugEnabled, xdebugActive);
      return `${statusSection}${actionsSection}`;
    }
    /**
     * Build status section HTML
     */
    buildStatusSection(xdebugEnabled, xdebugActive, xdebugSessionName) {
      if (!xdebugEnabled) {
        return `<div class="dev-toolbar-request-status">
                <div class="dev-toolbar-xdebug-compact dev-toolbar-xdebug-compact-disabled">
                  <span class="dev-toolbar-xdebug-label">Xdebug: Not Installed</span>
                </div>
              </div>`;
      }
      const statusClass = xdebugActive ? "active" : "inactive";
      const statusText = xdebugActive ? `Xdebug: ${xdebugSessionName}` : "Xdebug: Off";
      const statusIcon = xdebugActive ? "\u25CF" : "\u25CB";
      return `<div class="dev-toolbar-request-status">
              <div class="dev-toolbar-xdebug-compact dev-toolbar-xdebug-compact-${statusClass}">
                <span class="dev-toolbar-xdebug-indicator">${statusIcon}</span>
                <span class="dev-toolbar-xdebug-label">${escapeHtml(statusText)}</span>
              </div>
            </div>`;
    }
    /**
     * Build actions section HTML
     */
    buildActionsSection(xdebugEnabled, xdebugActive) {
      const xdebugButtons = this.buildXdebugButtons(xdebugEnabled, xdebugActive);
      return `<div class="dev-toolbar-request-actions">
              ${xdebugButtons}
              <button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="export-current" title="Export current request as JSON">
                \u2B07 Export
              </button>
            </div>`;
    }
    /**
     * Build Xdebug control buttons
     */
    buildXdebugButtons(xdebugEnabled, xdebugActive) {
      if (!xdebugEnabled) {
        return "";
      }
      if (xdebugActive) {
        return `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-disable" title="Disable Xdebug step debugging">
                \u23F9 Disable
              </button>`;
      }
      return `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="PHPSTORM" title="Enable Xdebug for PhpStorm">
              \u25B6 PhpStorm
            </button>
            <button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="VSCODE" title="Enable Xdebug for VSCode">
              \u25B6 VSCode
            </button>`;
    }
    /**
     * Enable Xdebug debugging for specific IDE
     *
     * @param ide - IDE identifier (PHPSTORM, VSCODE)
     */
    enableXdebug(ide) {
      debug(`[Xdebug] Enabling Xdebug for ${ide}`);
      document.cookie = `XDEBUG_SESSION=${ide}; path=/; max-age=3600`;
      this.render();
      window.location.reload();
    }
    /**
     * Disable Xdebug debugging
     */
    disableXdebug() {
      debug("[Xdebug] Disabling Xdebug");
      document.cookie = "XDEBUG_SESSION=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT";
      this.render();
      window.location.reload();
    }
  };

  // DevToolbar/utils/exportUtils.ts
  function exportRequestAsJson(requestId, requestData) {
    const { badge_counts: _badge_counts, ...exportMetadata } = requestData.metadata;
    return {
      toolbar_version: "2.1.0",
      export_time: (/* @__PURE__ */ new Date()).toISOString(),
      request_id: requestId,
      metadata: exportMetadata,
      data: requestData.raw_data
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
  function downloadFile(content, filename, mimeType = "application/json") {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  }

  // DevToolbar/ui/ClearHistoryDialog.ts
  var ClearHistoryDialog = class {
    constructor() {
      this.modal = null;
      this.isOpen = false;
      this.onConfirm = null;
      this.escKeyCleanup = null;
    }
    /**
     * Open dialog with confirmation callback
     */
    open(onConfirm) {
      if (this.isOpen) {
        return;
      }
      this.onConfirm = onConfirm;
      this.createModal();
      this.showModal();
      this.attachModalHandlers();
      this.isOpen = true;
    }
    /**
     * Close dialog
     */
    close() {
      if (!this.isOpen) {
        return;
      }
      this.hideModal();
      this.removeModal();
      this.isOpen = false;
      this.onConfirm = null;
    }
    /**
     * Create modal HTML structure
     */
    createModal() {
      const modalHTML = `
            <div class="dev-toolbar-modal-overlay" id="dev-toolbar-clear-history-overlay">
                <div class="dev-toolbar-modal dev-toolbar-clear-history-modal">
                    <div class="dev-toolbar-modal-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 1.5rem;">\u{1F5D1}\uFE0F</span>
                            <h3>Clear Request History</h3>
                        </div>
                        <button class="dev-toolbar-modal-close" title="Close">\xD7</button>
                    </div>

                    <div class="dev-toolbar-modal-content">
                        <!-- Warning -->
                        <div class="dev-toolbar-clear-warning">
                            <strong>\u26A0\uFE0F Warning:</strong> This action cannot be undone.
                        </div>

                        <!-- What will be deleted -->
                        <div class="dev-toolbar-clear-section">
                            <h4>Will be deleted:</h4>
                            <ul class="dev-toolbar-clear-list">
                                <li>All request metadata (timestamps, URIs, methods)</li>
                                <li>All request details (queries, performance data)</li>
                                <li>History statistics and trends</li>
                            </ul>
                        </div>

                        <!-- What will be preserved -->
                        <div class="dev-toolbar-clear-section">
                            <h4>Will be preserved:</h4>
                            <ul class="dev-toolbar-clear-list dev-toolbar-clear-list-preserved">
                                <li>DevToolbar settings (minibar labels, colors)</li>
                                <li>Active tab selection</li>
                                <li>Xdebug cookie settings</li>
                            </ul>
                        </div>
                    </div>

                    <div class="dev-toolbar-modal-footer">
                        <button class="dev-toolbar-btn dev-toolbar-btn-secondary" id="clear-history-cancel">
                            Cancel
                        </button>
                        <button class="dev-toolbar-btn dev-toolbar-btn-danger" id="clear-history-confirm">
                            \u{1F5D1}\uFE0F Clear History
                        </button>
                    </div>
                </div>
            </div>
        `;
      const container = document.createElement("div");
      container.innerHTML = modalHTML;
      const modalElement = container.firstElementChild;
      if (modalElement != null) {
        document.body.appendChild(modalElement);
      }
      this.modal = document.getElementById("dev-toolbar-clear-history-overlay");
    }
    /**
     * Show modal (fade in)
     */
    showModal() {
      if (this.modal != null) {
        this.modal.style.display = "flex";
        void this.modal.offsetHeight;
        this.modal.style.opacity = "1";
      }
    }
    /**
     * Hide modal (fade out)
     */
    hideModal() {
      if (this.modal != null) {
        this.modal.style.opacity = "0";
      }
    }
    /**
     * Remove modal from DOM
     */
    removeModal() {
      if (this.modal != null) {
        if (this.escKeyCleanup != null) {
          this.escKeyCleanup();
          this.escKeyCleanup = null;
        }
        setTimeout(() => {
          this.modal?.remove();
          this.modal = null;
        }, 200);
      }
    }
    /**
     * Attach event handlers to modal
     */
    attachModalHandlers() {
      if (this.modal == null) {
        return;
      }
      const confirmBtn = this.modal.querySelector("#clear-history-confirm");
      confirmBtn?.addEventListener("click", () => {
        if (this.onConfirm != null) {
          this.onConfirm();
        }
        this.close();
      });
      const cancelBtn = this.modal.querySelector("#clear-history-cancel");
      cancelBtn?.addEventListener("click", () => this.close());
      const closeBtn = this.modal.querySelector(".dev-toolbar-modal-close");
      closeBtn?.addEventListener("click", () => this.close());
      this.escKeyCleanup = createEscapeKeyHandler(() => this.close());
      this.modal.addEventListener("click", (e) => {
        if (e.target === this.modal) {
          this.close();
        }
      });
    }
  };

  // DevToolbar/ui/MessageDialog.ts
  var MessageDialog = class {
    constructor() {
      this.modal = null;
      this.isOpen = false;
      this.escKeyCleanup = null;
    }
    /**
     * Open dialog with message
     */
    open(options) {
      if (this.isOpen) {
        return;
      }
      this.createModal(options);
      this.showModal();
      this.attachModalHandlers();
      this.isOpen = true;
    }
    /**
     * Close dialog
     */
    close() {
      if (!this.isOpen) {
        return;
      }
      this.hideModal();
      this.removeModal();
      this.isOpen = false;
    }
    /**
     * Get icon and color for message type
     */
    getTypeConfig(type) {
      const configs = {
        error: { icon: "\u274C", color: "#ef4444" },
        warning: { icon: "\u26A0\uFE0F", color: "#f59e0b" },
        info: { icon: "\u2139\uFE0F", color: "#3b82f6" },
        success: { icon: "\u2705", color: "#10b981" }
      };
      return configs[type] || configs.info;
    }
    /**
     * Create modal HTML structure
     */
    createModal(options) {
      const type = options.type || "info";
      const title = options.title || this.getDefaultTitle(type);
      const okButtonText = options.okButtonText || "OK";
      const { icon, color } = this.getTypeConfig(type);
      const modalHTML = `
            <div class="dev-toolbar-modal-overlay" id="dev-toolbar-message-overlay">
                <div class="dev-toolbar-modal dev-toolbar-message-modal">
                    <div class="dev-toolbar-modal-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 1.5rem;">${icon}</span>
                            <h3 style="color: ${color};">${this.escapeHtml(title)}</h3>
                        </div>
                        <button class="dev-toolbar-modal-close" title="Close">\xD7</button>
                    </div>

                    <div class="dev-toolbar-modal-content">
                        <p style="white-space: pre-wrap; margin: 0;">${this.escapeHtml(options.message)}</p>
                    </div>

                    <div class="dev-toolbar-modal-footer">
                        <button class="dev-toolbar-btn dev-toolbar-btn-primary" id="message-dialog-ok">
                            ${this.escapeHtml(okButtonText)}
                        </button>
                    </div>
                </div>
            </div>
        `;
      const container = document.createElement("div");
      container.innerHTML = modalHTML;
      const modalElement = container.firstElementChild;
      if (modalElement != null) {
        document.body.appendChild(modalElement);
      }
      this.modal = document.getElementById("dev-toolbar-message-overlay");
    }
    /**
     * Get default title for message type
     */
    getDefaultTitle(type) {
      const titles = {
        error: "Error",
        warning: "Warning",
        info: "Information",
        success: "Success"
      };
      return titles[type] || "Information";
    }
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    }
    /**
     * Show modal (fade in)
     */
    showModal() {
      if (this.modal != null) {
        this.modal.style.display = "flex";
        void this.modal.offsetHeight;
        this.modal.style.opacity = "1";
      }
    }
    /**
     * Hide modal (fade out)
     */
    hideModal() {
      if (this.modal != null) {
        this.modal.style.opacity = "0";
      }
    }
    /**
     * Remove modal from DOM
     */
    removeModal() {
      if (this.modal != null) {
        if (this.escKeyCleanup != null) {
          this.escKeyCleanup();
          this.escKeyCleanup = null;
        }
        setTimeout(() => {
          this.modal?.remove();
          this.modal = null;
        }, 200);
      }
    }
    /**
     * Attach event handlers to modal
     */
    attachModalHandlers() {
      if (this.modal == null) {
        return;
      }
      const okBtn = this.modal.querySelector("#message-dialog-ok");
      okBtn?.addEventListener("click", () => this.close());
      const closeBtn = this.modal.querySelector(".dev-toolbar-modal-close");
      closeBtn?.addEventListener("click", () => this.close());
      this.escKeyCleanup = createEscapeKeyHandler(() => this.close());
      this.modal.addEventListener("click", (e) => {
        if (e.target === this.modal) {
          this.close();
        }
      });
    }
  };
  function showMessage(options) {
    const dialog = new MessageDialog();
    dialog.open(options);
  }
  function showError(message, title) {
    showMessage({ type: "error", title, message });
  }

  // DevToolbar/ui/HistoryTabManager.ts
  var HistoryTabManager = class {
    constructor() {
      this.initialized = false;
      this.clearHistoryDialog = new ClearHistoryDialog();
    }
    /**
     * Initialize history tab
     */
    init() {
      if (this.initialized) {
        debug("[HistoryTabManager] Already initialized");
        return;
      }
      debug("[HistoryTabManager] Initializing History tab");
      this.renderHistoryData();
      this.attachFilterListeners();
      this.attachExportListeners();
      this.initialized = true;
    }
    /**
     * Reset initialization flag (for re-initialization after request load)
     */
    reset() {
      this.initialized = false;
    }
    /**
     * Render history data from localStorage
     */
    renderHistoryData() {
      const metaArray = StorageManager.getMetadata();
      debug("[HistoryTabManager] Found", metaArray.length, "requests");
      this.updateStatistics(metaArray);
      const countEl = document.getElementById("history-list-count");
      if (countEl != null) {
        countEl.textContent = String(metaArray.length);
      }
      this.renderTrends(metaArray);
      this.renderRequestList(metaArray);
    }
    /**
     * Update statistics display
     */
    updateStatistics(metaArray) {
      const stats = this.calculateStats(metaArray);
      const statsMap = {
        total: String(stats.total),
        avg_time: `${stats.avgTime.toFixed(0)}ms`,
        avg_memory: `${stats.avgMemory.toFixed(1)}MB`,
        avg_queries: stats.avgQueries.toFixed(1),
        fastest: `${stats.fastest.toFixed(0)}ms`,
        slowest: `${stats.slowest.toFixed(0)}ms`
      };
      Object.entries(statsMap).forEach(([stat, value]) => {
        const el = document.querySelector(`[data-history-stat="${stat}"]`);
        if (el != null) {
          el.textContent = value;
        }
      });
    }
    /**
     * Calculate statistics from metadata
     */
    calculateStats(metaArray) {
      if (metaArray.length === 0) {
        return {
          total: 0,
          avgTime: 0,
          avgMemory: 0,
          avgQueries: 0,
          fastest: 0,
          slowest: 0
        };
      }
      const times = metaArray.map((r) => r.time);
      const memories = metaArray.map((r) => r.memory / 1024 / 1024);
      const queries = metaArray.map((r) => r.query_count);
      return {
        total: metaArray.length,
        avgTime: times.reduce((a, b) => a + b, 0) / times.length,
        avgMemory: memories.reduce((a, b) => a + b, 0) / memories.length,
        avgQueries: queries.reduce((a, b) => a + b, 0) / queries.length,
        fastest: Math.min(...times),
        slowest: Math.max(...times)
      };
    }
    /**
     * Render trends sparkline
     */
    renderTrends(metaArray) {
      const trendsEl = document.querySelector(".dev-toolbar-history-sparkline");
      if (!trendsEl) return;
      const times = metaArray.slice(0, 20).reverse().map((r) => r.time);
      trendsEl.textContent = generateSparkline(times);
    }
    /**
     * Render request list
     */
    renderRequestList(metaArray) {
      const listContainer = document.getElementById("history-request-list-container");
      if (!listContainer) {
        warn("[HistoryTabManager] List container not found");
        return;
      }
      if (metaArray.length === 0) {
        listContainer.innerHTML = '<p style="color: #888;">No request history yet. Reload the page to see requests.</p>';
        return;
      }
      let html = "";
      metaArray.forEach((request) => {
        const statusIcon = this.getStatusIcon(request.status);
        const timeAgoText = timeAgo(request.timestamp);
        const fullTimestamp = formatTimestamp(request.timestamp);
        const perfClass = request.time > 500 ? "slow" : request.time > 200 ? "warning" : "";
        html += `<div class="dev-toolbar-history-item ${perfClass}"
                      data-method="${escapeHtml(request.method)}"
                      data-uri="${escapeHtml(request.uri)}"
                      data-status="${request.status}"
                      data-time="${request.time}"
                      data-request-id="${escapeHtml(request.id)}">
                    <div class="dev-toolbar-history-item-header">
                        <span class="dev-toolbar-history-icon">${statusIcon}</span>
                        <span class="dev-toolbar-history-method">${escapeHtml(request.method)}</span>
                        <span class="dev-toolbar-history-uri">${escapeHtml(request.uri)}</span>
                        <span class="dev-toolbar-history-time-ago" title="${fullTimestamp}">${timeAgoText}</span>
                        <button class="dev-toolbar-history-item-export"
                                data-request-id="${escapeHtml(request.id)}"
                                title="Export this request">\u2B07</button>
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
      debug("[HistoryTabManager] Rendered", metaArray.length, "requests");
      setTimeout(() => this.attachItemExportListeners(), 50);
    }
    /**
     * Get status icon for status code
     */
    getStatusIcon(statusCode) {
      if (statusCode >= 200 && statusCode < 300) return "\u2713";
      if (statusCode >= 300 && statusCode < 400) return "\u2192";
      if (statusCode >= 400 && statusCode < 500) return "\u26A0";
      if (statusCode >= 500) return "\u2717";
      return "?";
    }
    /**
     * Attach filter listeners
     */
    attachFilterListeners() {
      const methodFilter = document.getElementById(
        "history-filter-method"
      );
      const statusFilter = document.getElementById(
        "history-filter-status"
      );
      const uriFilter = document.getElementById("history-filter-uri");
      const minTimeFilter = document.getElementById(
        "history-filter-min-time"
      );
      const resetBtn = document.getElementById("history-filter-reset");
      [methodFilter, statusFilter, uriFilter, minTimeFilter].forEach((el) => {
        if (el != null) {
          el.addEventListener("input", () => this.filterRequests());
        }
      });
      resetBtn?.addEventListener("click", () => this.resetFilters());
    }
    /**
     * Filter requests based on current filter values
     */
    filterRequests() {
      const methodEl = document.getElementById("history-filter-method");
      const statusEl = document.getElementById("history-filter-status");
      const uriEl = document.getElementById("history-filter-uri");
      const minTimeEl = document.getElementById("history-filter-min-time");
      const filters = {
        method: methodEl ? methodEl.value : "",
        status: statusEl ? statusEl.value : "",
        uri: uriEl ? uriEl.value.toLowerCase() : "",
        minTime: parseFloat(minTimeEl ? minTimeEl.value : "0")
      };
      const items = document.querySelectorAll(".dev-toolbar-history-item");
      let visibleCount = 0;
      items.forEach((item) => {
        const matches = this.itemMatchesFilters(item, filters);
        item.style.display = matches ? "" : "none";
        if (matches) visibleCount++;
      });
      this.updateListTitle(visibleCount, items.length);
    }
    /**
     * Check if item matches filters
     */
    itemMatchesFilters(item, filters) {
      return (!filters.method || item.dataset.method === filters.method) && (!filters.status || item.dataset.status?.startsWith(filters.status) === true) && (!filters.uri || item.dataset.uri?.toLowerCase().includes(filters.uri) === true) && (filters.minTime <= 0 || parseFloat(item.dataset.time ?? "0") >= filters.minTime);
    }
    /**
     * Update list title with counts
     */
    updateListTitle(visibleCount, totalCount) {
      const title = document.getElementById("history-list-title");
      if (title != null) {
        title.textContent = `Request History (${visibleCount} of ${totalCount})`;
      }
    }
    /**
     * Reset filters
     */
    resetFilters() {
      ["method", "status", "uri", "min-time"].forEach((id) => {
        const el = document.getElementById(`history-filter-${id}`);
        if (el instanceof HTMLInputElement) {
          el.value = "";
        }
      });
      this.filterRequests();
    }
    /**
     * Attach export listeners
     */
    attachExportListeners() {
      document.getElementById("history-export-json")?.addEventListener("click", () => this.exportAsJSON());
      document.getElementById("history-export-csv")?.addEventListener("click", () => this.exportAsCSV());
      document.getElementById("history-clear")?.addEventListener("click", () => this.clearHistory());
    }
    /**
     * Attach individual item export listeners
     */
    attachItemExportListeners() {
      document.querySelectorAll(".dev-toolbar-history-item-export").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.stopPropagation();
          const requestId = btn.dataset.requestId;
          if (requestId != null) {
            this.exportRequest(requestId);
          }
        });
      });
    }
    /**
     * Export single request
     *
     * Exports structured collector data only.
     */
    exportRequest(requestId) {
      const requestData = StorageManager.getRequest(requestId);
      if (requestData == null) {
        error("[HistoryTabManager] Request not found:", requestId);
        showError("Request not found in history.", "Export Failed");
        return;
      }
      const exportData = exportRequestAsJson(requestId, requestData);
      const filename = `devtoolbar-request-${requestId}-${Date.now()}.json`;
      downloadFile(JSON.stringify(exportData, null, 2), filename, "application/json");
    }
    /**
     * Export visible history as JSON
     */
    exportAsJSON() {
      const data = this.collectVisibleData();
      const json = JSON.stringify(
        {
          toolbar_version: "2.1.0",
          export_time: (/* @__PURE__ */ new Date()).toISOString(),
          requests: data
        },
        null,
        2
      );
      downloadFile(json, `devtoolbar-history-${Date.now()}.json`, "application/json");
    }
    /**
     * Export visible history as CSV
     */
    exportAsCSV() {
      const data = this.collectVisibleData();
      let csv = "Timestamp,Method,URI,Status,Time (ms),Memory (MB),Queries\n";
      data.forEach((item) => {
        const timestamp = new Date(item.timestamp * 1e3).toISOString();
        const uri = `"${item.uri.replace(/"/g, '""')}"`;
        csv += `${timestamp},${item.method},${uri},${item.status},${item.time},${item.memory},${item.query_count}
`;
      });
      downloadFile(csv, `devtoolbar-history-${Date.now()}.csv`, "text/csv");
    }
    /**
     * Collect visible request data
     */
    collectVisibleData() {
      const items = document.querySelectorAll(
        '.dev-toolbar-history-item:not([style*="display: none"])'
      );
      return Array.from(items).map((item) => {
        const el = item;
        const memoryText = item.querySelector(".dev-toolbar-history-meta-item:nth-child(3)")?.textContent ?? "";
        const memoryMatch = memoryText.match(/[\d.]+/);
        const memory = memoryMatch ? parseFloat(memoryMatch[0]) : 0;
        const queriesText = item.querySelector(".dev-toolbar-history-meta-item:nth-child(4)")?.textContent ?? "";
        const queriesMatch = queriesText.match(/\d+/);
        const queryCount = queriesMatch ? parseInt(queriesMatch[0], 10) : 0;
        const timeAgoText = item.querySelector(".dev-toolbar-history-time-ago")?.textContent ?? "";
        const timestamp = this.parseTimeAgo(timeAgoText);
        return {
          method: el.dataset.method ?? "",
          uri: el.dataset.uri ?? "",
          status: parseInt(el.dataset.status ?? "0", 10),
          time: parseFloat(el.dataset.time ?? "0"),
          memory,
          query_count: queryCount,
          timestamp
        };
      });
    }
    /**
     * Parse "time ago" text to timestamp
     */
    parseTimeAgo(text) {
      const match = text.match(/(\d+)([smhd])/);
      if (!match?.[1] || !match[2]) return Math.floor(Date.now() / 1e3);
      const value = parseInt(match[1], 10);
      const multipliers = { s: 1, m: 60, h: 3600, d: 86400 };
      const multiplier = multipliers[match[2]];
      if (multiplier === void 0) return Math.floor(Date.now() / 1e3);
      return Math.floor(Date.now() / 1e3) - value * multiplier;
    }
    /**
     * Clear history
     */
    clearHistory() {
      this.clearHistoryDialog.open(() => {
        StorageManager.clear();
        window.location.reload();
      });
    }
  };

  // DevToolbar/ui/SettingsManager.ts
  var SettingsManager = class {
    constructor() {
      this.modal = null;
      this.isOpen = false;
      this.escKeyCleanup = null;
      this.currentShortcut = null;
    }
    /**
     * Open settings modal
     */
    open() {
      if (this.isOpen) {
        return;
      }
      this.createModal();
      this.showModal();
      this.attachModalHandlers();
      this.isOpen = true;
    }
    /**
     * Close settings modal
     */
    close() {
      if (!this.isOpen) {
        return;
      }
      this.hideModal();
      this.removeModal();
      this.isOpen = false;
    }
    /**
     * Create modal HTML structure
     */
    createModal() {
      const currentLabels = StorageManager.getMinibarLabels();
      const currentColors = StorageManager.getBranchColors();
      const currentShortcut = StorageManager.getToggleShortcut();
      const modalHTML = `
            <div class="dev-toolbar-modal-overlay" id="dev-toolbar-settings-overlay">
                <div class="dev-toolbar-modal">
                    <div class="dev-toolbar-modal-header">
                        <h3>DevToolbar Settings</h3>
                        <button class="dev-toolbar-modal-close" title="Close">\xD7</button>
                    </div>

                    <div class="dev-toolbar-modal-content">
                        <!-- Minibar Label Selection -->
                        <div class="dev-toolbar-settings-group">
                            <label>Minibar Labels (multiple selection)</label>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 0.875rem;">
                                Select which labels to display in the minibar (left to right order)
                            </p>
                            <div class="dev-toolbar-settings-checkboxes">
                                ${this.buildCheckboxOption("branding", "\u26A1 Branding", "Show lightning bolt icon", currentLabels)}
                                ${this.buildCheckboxOption("branch", "Git Branch", "Show current git branch name", currentLabels)}
                                ${this.buildCheckboxOption("route", "Current Route", "Show HTTP method and URI", currentLabels)}
                                ${this.buildCheckboxOption("request-id", "Request ID", "Show unique request identifier", currentLabels)}
                            </div>
                        </div>

                        <!-- Branch Color Configuration -->
                        <div class="dev-toolbar-settings-group">
                            <label>Git Branch Colors</label>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 0.875rem;">
                                Customize colors for different branch types when using branch display mode
                            </p>
                            <div class="dev-toolbar-settings-colors">
                                ${this.buildColorInput("feat", "feat/* branches", currentColors.feat)}
                                ${this.buildColorInput("fix", "fix/* branches", currentColors.fix)}
                                ${this.buildColorInput("hotfix", "hotfix/* branches", currentColors.hotfix)}
                                ${this.buildColorInput("chore", "chore/* branches", currentColors.chore)}
                                ${this.buildColorInput("default", "Other branches", currentColors.default)}
                            </div>
                        </div>

                        <!-- Keyboard Shortcut Configuration -->
                        <div class="dev-toolbar-settings-group">
                            <label>Toggle Keyboard Shortcut</label>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 0.875rem;">
                                Keyboard shortcut to open/close the DevToolbar. Click in the box and press your desired key combination.
                            </p>
                            <div class="dev-toolbar-settings-shortcut">
                                <input
                                    type="text"
                                    id="shortcut-input"
                                    readonly
                                    placeholder="Press keys..."
                                    value="${this.formatShortcut(currentShortcut)}"
                                    style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-family: monospace; background: #f9fafb; cursor: pointer;"
                                >
                                <p style="margin: 8px 0 0 0; color: #6b7280; font-size: 0.75rem;">
                                    Current: <strong>${this.formatShortcut(currentShortcut)}</strong> |
                                    <a href="#" id="reset-shortcut" style="color: #3b82f6; text-decoration: none;">Reset to Ctrl+Shift+D</a>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="dev-toolbar-modal-footer">
                        <button class="dev-toolbar-btn dev-toolbar-btn-secondary" id="settings-cancel">
                            Cancel
                        </button>
                        <button class="dev-toolbar-btn dev-toolbar-btn-primary" id="settings-save">
                            Save & Reload
                        </button>
                    </div>
                </div>
            </div>
        `;
      const container = document.createElement("div");
      container.innerHTML = modalHTML;
      const modalElement = container.firstElementChild;
      if (modalElement != null) {
        document.body.appendChild(modalElement);
      }
      this.modal = document.getElementById("dev-toolbar-settings-overlay");
    }
    /**
     * Build checkbox option HTML
     */
    buildCheckboxOption(value, title, description, currentValues) {
      const checked = currentValues.includes(value) ? "checked" : "";
      return `
            <label class="dev-toolbar-settings-checkbox-item">
                <input type="checkbox" name="minibar-label" value="${escapeHtml(value)}" ${checked}>
                <div>
                    <strong>${escapeHtml(title)}</strong>
                    <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 0.875rem;">
                        ${escapeHtml(description)}
                    </p>
                </div>
            </label>
        `;
    }
    /**
     * Build color input HTML
     */
    buildColorInput(type, label, value) {
      return `
            <div class="dev-toolbar-settings-color-item">
                <label for="color-${escapeHtml(type)}">${escapeHtml(label)}</label>
                <input type="color" id="color-${escapeHtml(type)}" name="color-${escapeHtml(type)}" value="${escapeHtml(value)}">
            </div>
        `;
    }
    /**
     * Format shortcut for display
     */
    formatShortcut(shortcut) {
      const parts = [];
      if (shortcut.ctrlKey) parts.push("Ctrl");
      if (shortcut.shiftKey) parts.push("Shift");
      if (shortcut.altKey) parts.push("Alt");
      if (shortcut.metaKey) parts.push(navigator.platform.includes("Mac") ? "Cmd" : "Win");
      parts.push(shortcut.key);
      return parts.join("+");
    }
    /**
     * Show modal (fade in)
     */
    showModal() {
      if (this.modal != null) {
        this.modal.style.display = "flex";
        void this.modal.offsetHeight;
        this.modal.style.opacity = "1";
      }
    }
    /**
     * Hide modal (fade out)
     */
    hideModal() {
      if (this.modal != null) {
        this.modal.style.opacity = "0";
      }
    }
    /**
     * Remove modal from DOM
     */
    removeModal() {
      if (this.modal != null) {
        if (this.escKeyCleanup != null) {
          this.escKeyCleanup();
          this.escKeyCleanup = null;
        }
        setTimeout(() => {
          this.modal?.remove();
          this.modal = null;
        }, 200);
      }
    }
    /**
     * Attach event handlers to modal
     */
    attachModalHandlers() {
      if (this.modal == null) {
        return;
      }
      this.currentShortcut = StorageManager.getToggleShortcut();
      const shortcutInput = this.modal.querySelector("#shortcut-input");
      shortcutInput?.addEventListener("keydown", (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (["Control", "Shift", "Alt", "Meta"].includes(e.key)) {
          return;
        }
        this.currentShortcut = {
          key: e.key,
          ctrlKey: e.ctrlKey,
          shiftKey: e.shiftKey,
          altKey: e.altKey,
          metaKey: e.metaKey
        };
        shortcutInput.value = this.formatShortcut(this.currentShortcut);
        debug("[Settings] Captured shortcut:", this.currentShortcut);
      });
      const resetLink = this.modal.querySelector("#reset-shortcut");
      resetLink?.addEventListener("click", (e) => {
        e.preventDefault();
        this.currentShortcut = { ...DEFAULT_TOGGLE_SHORTCUT };
        if (shortcutInput != null) {
          shortcutInput.value = this.formatShortcut(this.currentShortcut);
        }
      });
      const saveBtn = this.modal.querySelector("#settings-save");
      saveBtn?.addEventListener("click", () => this.saveSettings());
      const cancelBtn = this.modal.querySelector("#settings-cancel");
      cancelBtn?.addEventListener("click", () => this.close());
      const closeBtn = this.modal.querySelector(".dev-toolbar-modal-close");
      closeBtn?.addEventListener("click", () => this.close());
      this.escKeyCleanup = createEscapeKeyHandler(() => this.close());
      this.modal.addEventListener("click", (e) => {
        if (e.target === this.modal) {
          this.close();
        }
      });
    }
    /**
     * Save settings and reload page
     */
    saveSettings() {
      const selectedLabels = this.getSelectedLabels();
      const branchColors = this.getBranchColors();
      if (selectedLabels.length === 0) {
        error("[Settings] No labels selected");
        return;
      }
      StorageManager.setMinibarLabels(selectedLabels);
      StorageManager.setBranchColors(branchColors);
      if (this.currentShortcut != null) {
        StorageManager.setToggleShortcut(this.currentShortcut);
      }
      this.saveSettingsToCookies(selectedLabels, branchColors);
      debug("[Settings] Saved:", { labels: selectedLabels, colors: branchColors });
      window.location.reload();
    }
    /**
     * Save settings to cookies for PHP access
     */
    saveSettingsToCookies(labels, colors) {
      const labelsJson = JSON.stringify(labels);
      document.cookie = `devbar_labels=${encodeURIComponent(labelsJson)}; path=/; max-age=31536000`;
      const colorsJson = JSON.stringify(colors);
      document.cookie = `devbar_colors=${encodeURIComponent(colorsJson)}; path=/; max-age=31536000`;
      debug("[Settings] Cookies set:", {
        labels: `devbar_labels=${encodeURIComponent(labelsJson)}`,
        colors: `devbar_colors=${encodeURIComponent(colorsJson)}`,
        allCookies: document.cookie
      });
    }
    /**
     * Get selected minibar labels from form
     */
    getSelectedLabels() {
      if (this.modal == null) {
        return [];
      }
      const selectedCheckboxes = this.modal.querySelectorAll(
        'input[name="minibar-label"]:checked'
      );
      const labels = [];
      selectedCheckboxes.forEach((checkbox) => {
        labels.push(checkbox.value);
      });
      return labels;
    }
    /**
     * Get branch colors from form
     */
    getBranchColors() {
      return {
        feat: this.getColorValue("feat") ?? DEFAULT_BRANCH_COLORS.feat,
        fix: this.getColorValue("fix") ?? DEFAULT_BRANCH_COLORS.fix,
        hotfix: this.getColorValue("hotfix") ?? DEFAULT_BRANCH_COLORS.hotfix,
        chore: this.getColorValue("chore") ?? DEFAULT_BRANCH_COLORS.chore,
        default: this.getColorValue("default") ?? DEFAULT_BRANCH_COLORS.default
      };
    }
    /**
     * Get color value from color input
     */
    getColorValue(type) {
      if (this.modal == null) {
        return null;
      }
      const input = this.modal.querySelector(`input[name="color-${type}"]`);
      return input?.value ?? null;
    }
  };

  // DevToolbar/ui/DevToolbarUI.ts
  var DevToolbarUI = class {
    constructor() {
      this.miniBar = null;
      this.panel = null;
      this.tabManager = new TabManager();
      this.requestSwitcher = new RequestSwitcher();
      this.xdebugControls = new XdebugControls();
      this.historyTabManager = new HistoryTabManager();
      this.settingsManager = new SettingsManager();
    }
    /**
     * Initialize DevToolbar UI
     */
    init() {
      debug("[DevToolbar] Initializing UI...");
      StorageManager.init();
      this.tabManager.init();
      this.miniBar = document.querySelector(".dev-toolbar-mini");
      this.panel = document.querySelector(".dev-toolbar-panel");
      if (!this.miniBar || !this.panel) {
        error("[DevToolbar] Mini bar or panel not found");
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
      debug("[DevToolbar] UI initialization complete");
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
          if (tabName != null) {
            this.tabManager.setActiveTab(tabName, (name) => this.handleTabActivate(name));
          }
        });
      });
      const closeBtn = document.querySelector(".dev-toolbar-panel-close");
      closeBtn?.addEventListener("click", () => {
        this.closePanel();
      });
      const settingsBtn = document.querySelector(".dev-toolbar-panel-settings");
      settingsBtn?.addEventListener("click", () => {
        this.settingsManager.open();
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
      document.addEventListener("keydown", (e) => {
        const shortcut = StorageManager.getToggleShortcut();
        const ctrlMatch = (shortcut.ctrlKey ?? false) === e.ctrlKey;
        const shiftMatch = (shortcut.shiftKey ?? false) === e.shiftKey;
        const altMatch = (shortcut.altKey ?? false) === e.altKey;
        const metaMatch = (shortcut.metaKey ?? false) === e.metaKey;
        const keyMatch = e.key.toUpperCase() === shortcut.key.toUpperCase();
        if (keyMatch && ctrlMatch && shiftMatch && altMatch && metaMatch) {
          e.preventDefault();
          this.togglePanel();
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
      debug("[DevToolbarUI] Tab activated:", tabName);
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
      debug("[DevToolbarUI] Request loaded:", requestId);
      if (requestId === "history") {
        this.tabManager.setActiveTab("history", (name) => this.handleTabActivate(name));
        return;
      }
      this.historyTabManager.reset();
      setTimeout(() => {
        this.reattachTabListeners();
        const currentTab = this.tabManager.getCurrentTab();
        this.tabManager.setActiveTab(currentTab, (name) => this.handleTabActivate(name));
        if (requestId !== "current") {
          const requestData = StorageManager.getRequest(requestId);
          if (requestData?.metadata.badge_counts != null) {
            this.tabManager.updateBadgeCounts(requestData.metadata.badge_counts);
          }
        } else {
          const originalBadges = this.requestSwitcher.getOriginalBadgeCounts();
          if (originalBadges != null) {
            this.tabManager.updateBadgeCounts(originalBadges);
          }
        }
      }, 50);
    }
    /**
     * Re-attach tab listeners after content replacement
     */
    reattachTabListeners() {
      debug("[DevToolbarUI] Re-attaching tab event listeners");
      const tabButtons = document.querySelectorAll(".dev-toolbar-panel-tab");
      debug("[DevToolbarUI] Found", tabButtons.length, "tab buttons");
      tabButtons.forEach((tab) => {
        const newTab = tab.cloneNode(true);
        tab.parentNode?.replaceChild(newTab, tab);
        newTab.addEventListener("click", () => {
          const tabName = newTab.dataset.tab;
          if (tabName != null) {
            debug("[DevToolbarUI] Tab clicked:", tabName);
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
        warn("[DevToolbar] Could not save open state", e);
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
        warn("[DevToolbar] Could not save open state", e);
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
        warn("[DevToolbar] Could not save maximized state", e);
      }
      const maximizeBtn = document.querySelector(".dev-toolbar-panel-maximize");
      if (maximizeBtn != null) {
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
        warn("[DevToolbar] Could not restore state", e);
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
          if (content?.contains(e.target)) {
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
            const remainingAlerts = alertsContainer?.querySelectorAll(
              ".dev-toolbar-alert:not(.dismissed)"
            );
            if (remainingAlerts?.length === 0) {
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
          const ide = e.target.dataset.ide ?? "PHPSTORM";
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
        error("[DevToolbar] No request data available");
        showError("No request data available for export.", "Export Failed");
        return;
      }
      const exportData = exportRequestAsJson(toolbarData.id, toolbarData);
      const filename = `devtoolbar-request-${toolbarData.id}-${Date.now()}.json`;
      downloadJson(exportData, filename);
      debug("[DevToolbar] Exported current request");
    }
    /**
     * Update history badge
     */
    updateHistoryBadge() {
      const metaArray = StorageManager.getMetadata();
      this.tabManager.updateHistoryBadge(metaArray.length);
    }
    /**
     * Initialize history tab
     */
    initHistoryTab() {
      this.historyTabManager.init();
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
