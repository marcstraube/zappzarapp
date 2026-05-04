/* DevToolbar - Generated browser bundle - DO NOT EDIT MANUALLY */
"use strict";
(() => {
  var __defProp = Object.defineProperty;
  var __defNormalProp = (obj, key, value) => key in obj ? __defProp(obj, key, { enumerable: true, configurable: true, writable: true, value }) : obj[key] = value;
  var __publicField = (obj, key, value) => __defNormalProp(obj, typeof key !== "symbol" ? key + "" : key, value);

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
  var DEFAULT_THRESHOLDS = {
    time_ms: 1e3,
    // 1 second
    memory_mb: 50,
    // 50 MB
    query_count: 50,
    // 50 queries
    query_time_ms: 500,
    // 500ms total query time
    http_count: 10,
    // 10 HTTP requests
    http_time_ms: 1e3
    // 1 second total HTTP time
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/logging/LogLevel.js
  var LogLevel = {
    Debug: 0,
    Info: 1,
    Warn: 2,
    Error: 3,
    Silent: 4
  };
  function logLevelName(level) {
    switch (level) {
      case LogLevel.Debug:
        return "DEBUG";
      case LogLevel.Info:
        return "INFO";
      case LogLevel.Warn:
        return "WARN";
      case LogLevel.Error:
        return "ERROR";
      case LogLevel.Silent:
        return "SILENT";
    }
  }

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/logging/LoggerConfig.js
  var LoggerConfig = class _LoggerConfig {
    constructor(options) {
      __publicField(this, "level");
      __publicField(this, "prefix");
      __publicField(this, "timestamps");
      __publicField(this, "console");
      this.level = options.level;
      this.prefix = options.prefix;
      this.timestamps = options.timestamps;
      this.console = options.console;
    }
    static create(options = {}) {
      return new _LoggerConfig({
        level: options.level ?? LogLevel.Warn,
        prefix: options.prefix ?? "",
        timestamps: options.timestamps ?? false,
        console: options.console ?? globalThis.console
      });
    }
    static development(prefix) {
      return _LoggerConfig.create({
        level: LogLevel.Debug,
        prefix,
        timestamps: false
      });
    }
    static production(prefix) {
      return _LoggerConfig.create({
        level: LogLevel.Warn,
        prefix,
        timestamps: false
      });
    }
    static silent() {
      return _LoggerConfig.create({
        level: LogLevel.Silent
      });
    }
    withLevel(level) {
      return new _LoggerConfig({
        level,
        prefix: this.prefix,
        timestamps: this.timestamps,
        console: this.console
      });
    }
    withPrefix(prefix) {
      return new _LoggerConfig({
        level: this.level,
        prefix,
        timestamps: this.timestamps,
        console: this.console
      });
    }
    withTimestamps(enabled) {
      return new _LoggerConfig({
        level: this.level,
        prefix: this.prefix,
        timestamps: enabled,
        console: this.console
      });
    }
    withConsole(console) {
      return new _LoggerConfig({
        level: this.level,
        prefix: this.prefix,
        timestamps: this.timestamps,
        console
      });
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/logging/Logger.js
  var Logger = class _Logger {
    constructor(config) {
      __publicField(this, "config");
      this.config = config;
    }
    static create(options = {}) {
      return new _Logger(LoggerConfig.create(options));
    }
    static fromConfig(config) {
      return new _Logger(config);
    }
    static development(prefix) {
      return new _Logger(LoggerConfig.development(prefix));
    }
    static production(prefix) {
      return new _Logger(LoggerConfig.production(prefix));
    }
    static silent() {
      return new _Logger(LoggerConfig.silent());
    }
    get level() {
      return this.config.level;
    }
    get prefix() {
      return this.config.prefix;
    }
    isEnabled(level) {
      return level >= this.config.level;
    }
    withLevel(level) {
      return new _Logger(this.config.withLevel(level));
    }
    withPrefix(prefix) {
      return new _Logger(this.config.withPrefix(prefix));
    }
    withTimestamps(enabled) {
      return new _Logger(this.config.withTimestamps(enabled));
    }
    withConsole(console) {
      return new _Logger(this.config.withConsole(console));
    }
    debug(...args) {
      this.log(LogLevel.Debug, args);
    }
    info(...args) {
      this.log(LogLevel.Info, args);
    }
    warn(...args) {
      this.log(LogLevel.Warn, args);
    }
    error(...args) {
      this.log(LogLevel.Error, args);
    }
    log(level, args) {
      if (level < this.config.level) {
        return;
      }
      const formattedArgs = this.formatArgs(level, args);
      switch (level) {
        case LogLevel.Debug:
        case LogLevel.Info:
          this.config.console.log(...formattedArgs);
          break;
        case LogLevel.Warn:
          this.config.console.warn(...formattedArgs);
          break;
        case LogLevel.Error:
          this.config.console.error(...formattedArgs);
          break;
      }
    }
    formatArgs(level, args) {
      const parts = [];
      if (this.config.timestamps) {
        parts.push(`[${(/* @__PURE__ */ new Date()).toISOString()}]`);
      }
      if (this.config.prefix) {
        parts.push(this.config.prefix);
      }
      if (this.config.timestamps || level === LogLevel.Debug || level === LogLevel.Info) {
        parts.push(`[${logLevelName(level)}]`);
      }
      return [...parts, ...args];
    }
  };
  function createLogger(options = {}) {
    const logger = Logger.create(options);
    return {
      debug: (...args) => logger.debug(...args),
      info: (...args) => logger.info(...args),
      warn: (...args) => logger.warn(...args),
      error: (...args) => logger.error(...args)
    };
  }

  // DevToolbar/utils/logger.ts
  var { debug, info, warn, error } = createLogger({
    level: true ? LogLevel.Debug : LogLevel.Warn
  });

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
        const { id, metadata, tabs, json_data } = win.__DEV_TOOLBAR_DATA__;
        this.storeRequest(id, metadata, tabs, json_data);
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
     * @param jsonData Optional JSON collector data for export
     */
    storeRequest(id, metadata, tabs, jsonData) {
      debug("[StorageManager] Storing request:", id, "useMemoryFallback:", this.useMemoryFallback);
      try {
        if (this.useMemoryFallback) {
          this.storeInMemory(id, metadata, tabs, jsonData);
          debug("[StorageManager] Stored in memory, total:", this.memoryStore.meta.length);
          return;
        }
        const metaArray = this.getMetadata();
        metaArray.unshift(metadata);
        if (metaArray.length > MAX_METADATA) {
          metaArray.length = MAX_METADATA;
        }
        localStorage.setItem(META_KEY, JSON.stringify(metaArray));
        const fullData = { id, metadata, tabs, json_data: jsonData };
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
              JSON.stringify({ id, metadata, tabs, json_data: jsonData })
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
    storeInMemory(id, metadata, tabs, jsonData) {
      this.memoryStore.meta.unshift(metadata);
      if (this.memoryStore.meta.length > MAX_METADATA) {
        this.memoryStore.meta.length = MAX_METADATA;
      }
      this.memoryStore.requests[id] = { id, metadata, tabs, json_data: jsonData };
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
        if (key?.startsWith(DATA_PREFIX) === true) {
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
        if (key?.startsWith(DATA_PREFIX) === true) {
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
          if (key?.startsWith(DATA_PREFIX) === true) {
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
    /**
     * Get performance alert thresholds (merged with defaults)
     */
    getThresholds() {
      const config = this.getConfig();
      return { ...DEFAULT_THRESHOLDS, ...config.thresholds };
    }
    /**
     * Set custom performance alert thresholds
     */
    setThresholds(thresholds) {
      const config = this.getConfig();
      this.setConfig({ ...config, thresholds });
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

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/core/errors/BrowserUtilsError.js
  var BrowserUtilsError = class extends Error {
    constructor(message, cause) {
      super(message, cause !== void 0 ? { cause } : void 0);
      __publicField(this, "cause");
      this.name = this.constructor.name;
      this.cause = cause;
      Object.setPrototypeOf(this, new.target.prototype);
    }
    toFormattedString() {
      return `[${this.code}] ${this.message}`;
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/core/errors/ValidationError.js
  var ValidationError = class _ValidationError extends BrowserUtilsError {
    constructor(message, field, value, constraint) {
      super(message);
      __publicField(this, "code", "VALIDATION_ERROR");
      __publicField(this, "field");
      __publicField(this, "value");
      __publicField(this, "constraint");
      this.field = field;
      this.value = value;
      this.constraint = constraint;
    }
    static empty(field) {
      return new _ValidationError(`${field} cannot be empty`, field, "(empty)", "non-empty");
    }
    static containsForbiddenChars(field, value, chars) {
      const sanitized = _ValidationError.sanitizeForLog(value);
      return new _ValidationError(`${field} contains forbidden characters: ${chars}`, field, sanitized, `must not contain: ${chars}`);
    }
    static invalidFilename(filename, reason) {
      const sanitized = _ValidationError.sanitizeForLog(filename);
      return new _ValidationError(`Invalid filename: ${reason}`, "filename", sanitized, "valid filename");
    }
    static tooLong(field, value, maxLength) {
      return new _ValidationError(`${field} exceeds maximum length of ${maxLength}`, field, `(${value.length} chars)`, `max ${maxLength} chars`);
    }
    static invalidFormat(field, value, expectedFormat) {
      const sanitized = _ValidationError.sanitizeForLog(value);
      return new _ValidationError(`${field} has invalid format, expected: ${expectedFormat}`, field, sanitized, expectedFormat);
    }
    static insufficientComplexity(field, actual, required) {
      return new _ValidationError(`${field} must contain at least ${required} character classes (lowercase, uppercase, digits, special), got ${actual}`, field, "(hidden)", `at least ${required} of 4 character classes`);
    }
    static outOfRange(field, value, min, max) {
      return new _ValidationError(`${field} must be between ${min} and ${max}, got ${value}`, field, String(value), `${min} <= value <= ${max}`);
    }
    static sanitizeForLog(value, maxLength = 50) {
      const sanitized = value.replace(/\n/g, "\\n").replace(/\r/g, "\\r").replace(/\t/g, "\\t").replace(/[\x00-\x1f]/g, "?");
      if (sanitized.length > maxLength) {
        return sanitized.substring(0, maxLength) + "...";
      }
      return sanitized;
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/core/result/Result.js
  var Result = {
    ok(value) {
      return { _tag: "Ok", value };
    },
    err(error2) {
      return { _tag: "Err", error: error2 };
    },
    isOk(result) {
      return result._tag === "Ok";
    },
    isErr(result) {
      return result._tag === "Err";
    },
    unwrap(result) {
      if (result._tag === "Ok") {
        return result.value;
      }
      throw result.error;
    },
    unwrapOr(result, defaultValue) {
      if (result._tag === "Ok") {
        return result.value;
      }
      return defaultValue;
    },
    unwrapOrElse(result, fn) {
      if (result._tag === "Ok") {
        return result.value;
      }
      return fn(result.error);
    },
    unwrapErr(result) {
      if (result._tag === "Err") {
        return result.error;
      }
      throw new Error("Called unwrapErr on Ok value");
    },
    map(result, fn) {
      if (result._tag === "Ok") {
        return Result.ok(fn(result.value));
      }
      return result;
    },
    mapErr(result, fn) {
      if (result._tag === "Err") {
        return Result.err(fn(result.error));
      }
      return result;
    },
    flatMap(result, fn) {
      if (result._tag === "Ok") {
        return fn(result.value);
      }
      return result;
    },
    fromTry(fn, mapError) {
      try {
        return Result.ok(fn());
      } catch (e) {
        if (mapError) {
          return Result.err(mapError(e));
        }
        return Result.err(e);
      }
    },
    async fromPromise(promise, mapError) {
      try {
        const value = await promise;
        return Result.ok(value);
      } catch (e) {
        if (mapError) {
          return Result.err(mapError(e));
        }
        return Result.err(e);
      }
    },
    tap(result, fn) {
      if (result._tag === "Ok") {
        fn(result.value);
      }
      return result;
    },
    tapErr(result, fn) {
      if (result._tag === "Err") {
        fn(result.error);
      }
      return result;
    },
    match(result, handlers) {
      if (result._tag === "Ok") {
        return handlers.ok(result.value);
      }
      return handlers.err(result.error);
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/core/validation/StorageValidator.js
  var FORBIDDEN_KEY_CHARS = /[;\x00-\x1f]/;
  var MAX_LENGTHS = {
    storageKey: 128,
    storagePrefix: 32
  };
  var StorageValidator = {
    storageKey(key) {
      const result = StorageValidator.storageKeyResult(key);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    storageKeyResult(key) {
      if (!key) {
        return Result.err(ValidationError.empty("storageKey"));
      }
      if (key.length > MAX_LENGTHS.storageKey) {
        return Result.err(ValidationError.tooLong("storageKey", key, MAX_LENGTHS.storageKey));
      }
      if (FORBIDDEN_KEY_CHARS.test(key)) {
        return Result.err(ValidationError.containsForbiddenChars("storageKey", key, "semicolon, newline, control chars"));
      }
      return Result.ok(key);
    },
    storagePrefix(prefix) {
      const result = StorageValidator.storagePrefixResult(prefix);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    storagePrefixResult(prefix) {
      if (!prefix) {
        return Result.err(ValidationError.empty("storagePrefix"));
      }
      if (prefix.length > MAX_LENGTHS.storagePrefix) {
        return Result.err(ValidationError.tooLong("storagePrefix", prefix, MAX_LENGTHS.storagePrefix));
      }
      if (FORBIDDEN_KEY_CHARS.test(prefix)) {
        return Result.err(ValidationError.containsForbiddenChars("storagePrefix", prefix, "semicolon, newline, control chars"));
      }
      if (!/^[a-zA-Z][a-zA-Z0-9_-]*$/.test(prefix)) {
        return Result.err(ValidationError.invalidFormat("storagePrefix", prefix, "alphanumeric, starting with letter, may contain - or _"));
      }
      return Result.ok(prefix);
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/core/validation/CacheValidator.js
  var CACHE_KEY_PATTERN = /^[\w:.\-/]+$/;
  var MAX_CACHE_KEY_LENGTH = 256;
  var CacheValidator = {
    cacheKey(key) {
      const result = CacheValidator.cacheKeyResult(key);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    cacheKeyResult(key) {
      if (!key) {
        return Result.err(ValidationError.empty("cacheKey"));
      }
      if (key.length > MAX_CACHE_KEY_LENGTH) {
        return Result.err(ValidationError.tooLong("cacheKey", key, MAX_CACHE_KEY_LENGTH));
      }
      if (!CACHE_KEY_PATTERN.test(key)) {
        return Result.err(ValidationError.invalidFormat("cacheKey", key, "alphanumeric, may contain _ - : . /"));
      }
      return Result.ok(key);
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/core/validation/FilenameValidator.js
  var FORBIDDEN_FILENAME_CHARS = /[<>:"/\\|?*\x00-\x1f]/;
  var PATH_TRAVERSAL_PATTERN = /(?:^|[/\\])\.\.(?:[/\\]|$)/;
  var RESERVED_FILENAMES = /^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i;
  var MAX_FILENAME_LENGTH = 255;
  var MAX_MIMETYPE_LENGTH = 127;
  var FilenameValidator = {
    filename(filename) {
      const result = FilenameValidator.filenameResult(filename);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    filenameResult(filename) {
      if (!filename) {
        return Result.err(ValidationError.empty("filename"));
      }
      if (filename.length > MAX_FILENAME_LENGTH) {
        return Result.err(ValidationError.tooLong("filename", filename, MAX_FILENAME_LENGTH));
      }
      if (FORBIDDEN_FILENAME_CHARS.test(filename)) {
        return Result.err(ValidationError.containsForbiddenChars("filename", filename, '< > : " / \\ | ? * control chars'));
      }
      if (PATH_TRAVERSAL_PATTERN.test(filename)) {
        return Result.err(ValidationError.invalidFilename(filename, "path traversal detected"));
      }
      if (RESERVED_FILENAMES.test(filename)) {
        return Result.err(ValidationError.invalidFilename(filename, "reserved system name"));
      }
      if (filename.startsWith(".") || filename.startsWith(" ")) {
        return Result.err(ValidationError.invalidFilename(filename, "cannot start with dot or space"));
      }
      if (filename.endsWith(".") || filename.endsWith(" ")) {
        return Result.err(ValidationError.invalidFilename(filename, "cannot end with dot or space"));
      }
      return Result.ok(filename);
    },
    sanitizeFilename(filename, replacement = "_") {
      if (!filename) {
        return "download";
      }
      let sanitized = filename.replace(/[<>:"/\\|?*\x00-\x1f]/g, replacement).replace(/\.\./g, replacement).replace(/^[.\s]+|[.\s]+$/g, "");
      if (RESERVED_FILENAMES.test(sanitized)) {
        sanitized = `_${sanitized}`;
      }
      if (!sanitized) {
        return "download";
      }
      if (sanitized.length > MAX_FILENAME_LENGTH) {
        const ext = sanitized.lastIndexOf(".");
        if (ext > 0 && ext > sanitized.length - 10) {
          const extension = sanitized.substring(ext);
          const base = sanitized.substring(0, MAX_FILENAME_LENGTH - extension.length);
          sanitized = base + extension;
        } else {
          sanitized = sanitized.substring(0, MAX_FILENAME_LENGTH);
        }
      }
      return sanitized;
    },
    mimeType(mimeType) {
      const result = FilenameValidator.mimeTypeResult(mimeType);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    mimeTypeResult(mimeType) {
      if (!mimeType) {
        return Result.err(ValidationError.empty("mimeType"));
      }
      if (mimeType.length > MAX_MIMETYPE_LENGTH) {
        return Result.err(ValidationError.tooLong("mimeType", mimeType, MAX_MIMETYPE_LENGTH));
      }
      if (!/^[a-z]+\/[a-z0-9.+-]+(?:;\s*[a-z0-9-]+=[^\s;]+)*$/i.test(mimeType)) {
        return Result.err(ValidationError.invalidFormat("mimeType", mimeType, "type/subtype (e.g., text/plain)"));
      }
      return Result.ok(mimeType);
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/core/validation/CookieValidator.js
  var MAX_LENGTHS2 = {
    cookieName: 256,
    cookieValue: 4096
  };
  var COOKIE_NAME_FORBIDDEN = /[()<>@,;:\\"/[\]?={}\s\x00-\x1f\x7f]/;
  var COOKIE_VALUE_FORBIDDEN = /[;\x00-\x1f\x7f]/;
  var CookieValidator = {
    cookieName(name) {
      const result = CookieValidator.cookieNameResult(name);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    cookieNameResult(name) {
      if (!name) {
        return Result.err(ValidationError.empty("cookieName"));
      }
      if (name.length > MAX_LENGTHS2.cookieName) {
        return Result.err(ValidationError.tooLong("cookieName", name, MAX_LENGTHS2.cookieName));
      }
      if (COOKIE_NAME_FORBIDDEN.test(name)) {
        return Result.err(ValidationError.containsForbiddenChars("cookieName", name, "spaces, tabs, separators, control chars"));
      }
      return Result.ok(name);
    },
    cookieValue(value) {
      const result = CookieValidator.cookieValueResult(value);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    cookieValueResult(value) {
      if (value.length > MAX_LENGTHS2.cookieValue) {
        return Result.err(ValidationError.tooLong("cookieValue", value, MAX_LENGTHS2.cookieValue));
      }
      if (COOKIE_VALUE_FORBIDDEN.test(value)) {
        return Result.err(ValidationError.containsForbiddenChars("cookieValue", value, "semicolons, control chars"));
      }
      return Result.ok(value);
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/core/validation/UrlValidator.js
  var DEFAULT_SAFE_PROTOCOLS = [
    "http",
    "https",
    "ws",
    "wss",
    "mailto",
    "ftp",
    "ftps"
  ];
  function buildProtocolPattern(options) {
    const protocols = [...DEFAULT_SAFE_PROTOCOLS];
    if (options?.additionalProtocols) {
      for (const protocol of options.additionalProtocols) {
        if (/^[a-z][a-z0-9+.-]*$/i.test(protocol)) {
          protocols.push(protocol.toLowerCase());
        }
      }
    }
    const escaped = protocols.map((p) => p.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"));
    return new RegExp(`^(?:${escaped.join("|")}):`, "i");
  }
  var DEFAULT_SAFE_PATTERN = buildProtocolPattern();
  var UrlValidator = {
    DEFAULT_SAFE_PROTOCOLS,
    urlSafe(url, options) {
      const result = UrlValidator.urlSafeResult(url, options);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    urlSafeResult(url, options) {
      if (!url) {
        return Result.err(ValidationError.empty("url"));
      }
      const colonIndex = url.indexOf(":");
      if (colonIndex === -1) {
        return Result.ok(url);
      }
      const pattern = options?.additionalProtocols ? buildProtocolPattern(options) : DEFAULT_SAFE_PATTERN;
      if (!pattern.test(url)) {
        const allowedList = options?.additionalProtocols ? [...DEFAULT_SAFE_PROTOCOLS, ...options.additionalProtocols].join(", ") : DEFAULT_SAFE_PROTOCOLS.join(", ");
        return Result.err(ValidationError.invalidFormat("url", url, `allowed protocol (${allowedList})`));
      }
      return Result.ok(url);
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/core/validation/CommonValidator.js
  var MAX_CLIPBOARD_LENGTH = 1e7;
  var CommonValidator = {
    nonEmpty(field, value) {
      const result = CommonValidator.nonEmptyResult(field, value);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    nonEmptyResult(field, value) {
      if (!value) {
        return Result.err(ValidationError.empty(field));
      }
      return Result.ok(value);
    },
    numberInRange(field, value, min, max) {
      const result = CommonValidator.numberInRangeResult(field, value, min, max);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    numberInRangeResult(field, value, min, max) {
      if (value < min || value > max) {
        return Result.err(ValidationError.outOfRange(field, value, min, max));
      }
      return Result.ok(value);
    },
    positiveIntegerResult(field, value) {
      if (!Number.isInteger(value) || value < 1) {
        return Result.err(ValidationError.invalidFormat(field, String(value), "positive integer (>= 1)"));
      }
      return Result.ok(value);
    },
    clipboardText(text) {
      const result = CommonValidator.clipboardTextResult(text);
      if (Result.isErr(result)) {
        throw result.error;
      }
    },
    clipboardTextResult(text) {
      if (text.length > MAX_CLIPBOARD_LENGTH) {
        return Result.err(ValidationError.tooLong("clipboardText", text, MAX_CLIPBOARD_LENGTH));
      }
      return Result.ok(text);
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/core/validation/Validator.js
  var Validator = {
    storageKey: (key) => StorageValidator.storageKey(key),
    storageKeyResult: (key) => StorageValidator.storageKeyResult(key),
    storagePrefix: (prefix) => StorageValidator.storagePrefix(prefix),
    storagePrefixResult: (prefix) => StorageValidator.storagePrefixResult(prefix),
    cacheKey: (key) => CacheValidator.cacheKey(key),
    cacheKeyResult: (key) => CacheValidator.cacheKeyResult(key),
    filename: (filename) => FilenameValidator.filename(filename),
    filenameResult: (filename) => FilenameValidator.filenameResult(filename),
    sanitizeFilename: (filename, replacement) => FilenameValidator.sanitizeFilename(filename, replacement),
    mimeType: (mimeType) => FilenameValidator.mimeType(mimeType),
    mimeTypeResult: (mimeType) => FilenameValidator.mimeTypeResult(mimeType),
    cookieName: (name) => CookieValidator.cookieName(name),
    cookieNameResult: (name) => CookieValidator.cookieNameResult(name),
    cookieValue: (value) => CookieValidator.cookieValue(value),
    cookieValueResult: (value) => CookieValidator.cookieValueResult(value),
    urlSafe: (url) => UrlValidator.urlSafe(url),
    urlSafeResult: (url) => UrlValidator.urlSafeResult(url),
    nonEmpty: (field, value) => CommonValidator.nonEmpty(field, value),
    nonEmptyResult: (field, value) => CommonValidator.nonEmptyResult(field, value),
    numberInRange: (field, value, min, max) => CommonValidator.numberInRange(field, value, min, max),
    numberInRangeResult: (field, value, min, max) => CommonValidator.numberInRangeResult(field, value, min, max),
    positiveIntegerResult: (field, value) => CommonValidator.positiveIntegerResult(field, value),
    clipboardText: (text) => CommonValidator.clipboardText(text),
    clipboardTextResult: (text) => CommonValidator.clipboardTextResult(text)
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/html/HtmlEscaper.js
  var HTML_ENTITIES = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
    "`": "&#x60;"
  };
  var HTML_ESCAPE_PATTERN = /[&<>"'`]/g;
  var SAFE_ATTR_NAME = /^[a-zA-Z][a-zA-Z0-9_-]*$/;
  var DANGEROUS_ATTRS = /* @__PURE__ */ new Set([
    "onclick",
    "ondblclick",
    "onmousedown",
    "onmouseup",
    "onmouseover",
    "onmousemove",
    "onmouseout",
    "onmouseenter",
    "onmouseleave",
    "onkeydown",
    "onkeypress",
    "onkeyup",
    "onfocus",
    "onblur",
    "onsubmit",
    "onreset",
    "onselect",
    "onchange",
    "oninput",
    "onload",
    "onerror",
    "onabort",
    "onscroll",
    "onresize",
    "oncontextmenu",
    "ondrag",
    "ondragend",
    "ondragenter",
    "ondragleave",
    "ondragover",
    "ondragstart",
    "ondrop",
    "onanimationstart",
    "onanimationend",
    "onanimationiteration",
    "ontransitionend",
    "formaction",
    "action",
    "href",
    "src",
    "srcdoc",
    "xlink:href"
  ]);
  var HtmlEscaper = {
    escape(text) {
      if (!text) {
        return "";
      }
      return text.replace(HTML_ESCAPE_PATTERN, (char) => HTML_ENTITIES[char] ?? char);
    },
    truncate(text, maxLength = 100, suffix = "...") {
      if (!text) {
        return "";
      }
      const escaped = HtmlEscaper.escape(text);
      if (escaped.length <= maxLength) {
        return escaped;
      }
      return escaped.substring(0, maxLength - suffix.length) + suffix;
    },
    tag(tagName, attrs = {}, content) {
      if (!SAFE_ATTR_NAME.test(tagName)) {
        throw ValidationError.invalidFormat("tagName", tagName, "valid HTML tag name");
      }
      const attrParts = [];
      for (const [name, value] of Object.entries(attrs)) {
        if (DANGEROUS_ATTRS.has(name.toLowerCase())) {
          continue;
        }
        if (!SAFE_ATTR_NAME.test(name)) {
          continue;
        }
        if (typeof value === "boolean") {
          if (value) {
            attrParts.push(name);
          }
          continue;
        }
        const escapedValue = HtmlEscaper.escapeAttr(String(value));
        attrParts.push(`${name}="${escapedValue}"`);
      }
      const attrStr = attrParts.length > 0 ? " " + attrParts.join(" ") : "";
      if (content === null || content === void 0) {
        return `<${tagName}${attrStr} />`;
      }
      return `<${tagName}${attrStr}>${HtmlEscaper.escape(content)}</${tagName}>`;
    },
    escapeAttr(text) {
      if (!text) {
        return "";
      }
      return text.replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/'/g, "&#039;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/`/g, "&#x60;").replace(/\//g, "&#x2F;");
    },
    isSafeUrl(url) {
      if (!url) {
        return false;
      }
      const normalized = url.trim().toLowerCase();
      return !normalized.startsWith("javascript:") && !normalized.startsWith("data:") && !normalized.startsWith("vbscript:");
    },
    sanitizeUrl(url) {
      if (!HtmlEscaper.isSafeUrl(url)) {
        return "";
      }
      return HtmlEscaper.escapeAttr(url);
    }
  };

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
                    <span class="dev-toolbar-request-switcher-item-uri" title="${HtmlEscaper.escape(meta.uri)}">${HtmlEscaper.escape(meta.uri)}</span>
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
                <span class="dev-toolbar-xdebug-label">${HtmlEscaper.escape(statusText)}</span>
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
    if (!requestData.json_data) {
      throw new Error(`Cannot export request ${requestId}: No JSON data available`);
    }
    const { badge_counts: _badge_counts, ...exportMetadata } = requestData.metadata;
    return {
      toolbar_version: "2.1.0",
      export_time: (/* @__PURE__ */ new Date()).toISOString(),
      request_id: requestId,
      metadata: exportMetadata,
      data: requestData.json_data
    };
  }

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/download/DownloadOptions.js
  var DownloadOptions = class _DownloadOptions {
    constructor(filename, mimeType) {
      __publicField(this, "filename");
      __publicField(this, "mimeType");
      this.filename = filename;
      this.mimeType = mimeType;
    }
    static create(input) {
      let filename;
      if (input.sanitizeFilename === true) {
        filename = Validator.sanitizeFilename(input.filename);
      } else {
        Validator.filename(input.filename);
        filename = input.filename;
      }
      const mimeType = input.mimeType ?? "application/octet-stream";
      if (input.mimeType !== void 0 && input.mimeType !== "") {
        Validator.mimeType(input.mimeType);
      }
      return new _DownloadOptions(filename, mimeType);
    }
    static json(filename, sanitize = false) {
      const name = filename.endsWith(".json") ? filename : `${filename}.json`;
      return _DownloadOptions.create({
        filename: name,
        mimeType: "application/json",
        sanitizeFilename: sanitize
      });
    }
    static csv(filename, sanitize = false) {
      const name = filename.endsWith(".csv") ? filename : `${filename}.csv`;
      return _DownloadOptions.create({
        filename: name,
        mimeType: "text/csv",
        sanitizeFilename: sanitize
      });
    }
    static text(filename, sanitize = false) {
      const name = filename.endsWith(".txt") ? filename : `${filename}.txt`;
      return _DownloadOptions.create({
        filename: name,
        mimeType: "text/plain",
        sanitizeFilename: sanitize
      });
    }
    static html(filename, sanitize = false) {
      const name = filename.endsWith(".html") ? filename : `${filename}.html`;
      return _DownloadOptions.create({
        filename: name,
        mimeType: "text/html",
        sanitizeFilename: sanitize
      });
    }
    static binary(filename, mimeType = "application/octet-stream", sanitize = false) {
      return _DownloadOptions.create({
        filename,
        mimeType,
        sanitizeFilename: sanitize
      });
    }
    withFilename(filename, sanitize = false) {
      return _DownloadOptions.create({
        filename,
        mimeType: this.mimeType,
        sanitizeFilename: sanitize
      });
    }
    withMimeType(mimeType) {
      Validator.mimeType(mimeType);
      return new _DownloadOptions(this.filename, mimeType);
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/download/Downloader.js
  var Downloader = {
    download(content, options) {
      const blob = new Blob([content], { type: options.mimeType });
      Downloader.blob(blob, options.filename);
    },
    blob(content, filename) {
      const options = DownloadOptions.binary(filename);
      const blob = content instanceof Blob ? content : new Blob([content]);
      const url = URL.createObjectURL(blob);
      try {
        const anchor = document.createElement("a");
        anchor.href = url;
        anchor.download = options.filename;
        anchor.style.display = "none";
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
      } finally {
        URL.revokeObjectURL(url);
      }
    },
    json(content, filename, indent = 2) {
      const options = DownloadOptions.json(filename);
      const json = typeof content === "string" ? content : JSON.stringify(content, null, indent);
      Downloader.download(json, options);
    },
    csv(content, filename) {
      const options = DownloadOptions.csv(filename);
      Downloader.download(content, options);
    },
    text(content, filename) {
      const options = DownloadOptions.text(filename);
      Downloader.download(content, options);
    },
    html(content, filename) {
      const options = DownloadOptions.html(filename);
      Downloader.download(content, options);
    },
    withOptions(content, input) {
      const options = DownloadOptions.create(input);
      Downloader.download(content, options);
    },
    safe(content, filename, mimeType = "application/octet-stream") {
      const options = DownloadOptions.create({
        filename,
        mimeType,
        sanitizeFilename: true
      });
      Downloader.download(content, options);
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/keyboard/KeyboardShortcut.js
  var KeyboardShortcut = class _KeyboardShortcut {
    constructor(def) {
      __publicField(this, "key");
      __publicField(this, "ctrlKey");
      __publicField(this, "shiftKey");
      __publicField(this, "altKey");
      __publicField(this, "metaKey");
      this.key = def.key;
      this.ctrlKey = def.ctrlKey;
      this.shiftKey = def.shiftKey;
      this.altKey = def.altKey;
      this.metaKey = def.metaKey;
    }
    static create(def) {
      return new _KeyboardShortcut({
        key: def.key,
        ctrlKey: def.ctrlKey ?? false,
        shiftKey: def.shiftKey ?? false,
        altKey: def.altKey ?? false,
        metaKey: def.metaKey ?? false
      });
    }
    static key(key) {
      return _KeyboardShortcut.create({ key });
    }
    static ctrlKey(key) {
      return _KeyboardShortcut.create({ key, ctrlKey: true });
    }
    static ctrlShift(key) {
      return _KeyboardShortcut.create({ key, ctrlKey: true, shiftKey: true });
    }
    static altKey(key) {
      return _KeyboardShortcut.create({ key, altKey: true });
    }
    static metaKey(key) {
      return _KeyboardShortcut.create({ key, metaKey: true });
    }
    static escape() {
      return _KeyboardShortcut.create({ key: "Escape" });
    }
    static enter() {
      return _KeyboardShortcut.create({ key: "Enter" });
    }
    matches(event) {
      const keyMatches = event.key.length === 1 ? event.key.toUpperCase() === this.key.toUpperCase() : event.key === this.key;
      return keyMatches && event.ctrlKey === this.ctrlKey && event.shiftKey === this.shiftKey && event.altKey === this.altKey && event.metaKey === this.metaKey;
    }
    toString() {
      const parts = [];
      if (this.ctrlKey) {
        parts.push("Ctrl");
      }
      if (this.altKey) {
        parts.push("Alt");
      }
      if (this.shiftKey) {
        parts.push("Shift");
      }
      if (this.metaKey) {
        parts.push("Cmd");
      }
      parts.push(this.key.length === 1 ? this.key.toUpperCase() : this.key);
      return parts.join("+");
    }
    toMacString() {
      const parts = [];
      if (this.ctrlKey) {
        parts.push("\u2303");
      }
      if (this.altKey) {
        parts.push("\u2325");
      }
      if (this.shiftKey) {
        parts.push("\u21E7");
      }
      if (this.metaKey) {
        parts.push("\u2318");
      }
      parts.push(this.key.length === 1 ? this.key.toUpperCase() : this.key);
      return parts.join("");
    }
  };

  // ../../../node_modules/.pnpm/@zappzarapp+browser-utils@1.0.2/node_modules/@zappzarapp/browser-utils/dist/keyboard/ShortcutManager.js
  var ShortcutManager = {
    on(shortcut, handler, options = {}) {
      const kbd = shortcut instanceof KeyboardShortcut ? shortcut : KeyboardShortcut.create(shortcut);
      const { preventDefault = true, stopPropagation = false, stopImmediatePropagation = false, capture = false, once = false } = options;
      let isActive = true;
      const listener = (event) => {
        if (!isActive || !kbd.matches(event)) {
          return;
        }
        if (preventDefault) {
          event.preventDefault();
        }
        if (stopPropagation) {
          event.stopPropagation();
        }
        if (stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
        handler();
        if (once) {
          cleanup();
        }
      };
      const cleanup = () => {
        isActive = false;
        document.removeEventListener("keydown", listener, capture);
      };
      document.addEventListener("keydown", listener, capture);
      return cleanup;
    },
    onEscape(handler) {
      return ShortcutManager.on(KeyboardShortcut.escape(), handler, {
        capture: true,
        stopImmediatePropagation: true,
        once: true
      });
    },
    onEnter(handler, options = {}) {
      return ShortcutManager.on(KeyboardShortcut.enter(), handler, options);
    },
    createGroup() {
      return new ShortcutGroup();
    }
  };
  var ShortcutGroup = class {
    constructor() {
      __publicField(this, "cleanups", []);
    }
    add(shortcut, handler, options) {
      this.cleanups.push(ShortcutManager.on(shortcut, handler, options));
      return this;
    }
    addEscape(handler) {
      this.cleanups.push(ShortcutManager.onEscape(handler));
      return this;
    }
    cleanup() {
      for (const fn of this.cleanups) {
        fn();
      }
      this.cleanups.length = 0;
    }
    get size() {
      return this.cleanups.length;
    }
  };

  // DevToolbar/ui/BaseDialog.ts
  var BaseDialog = class {
    constructor() {
      this.modal = null;
      this.isOpen = false;
      this.escKeyCleanup = null;
    }
    /**
     * Close the dialog
     */
    close() {
      if (!this.isOpen) {
        return;
      }
      this.hideModal();
      this.removeModal();
      this.isOpen = false;
      this.onClose();
    }
    /**
     * Hook for subclasses to clean up on close
     */
    onClose() {
    }
    /**
     * Inject modal HTML into DOM and store reference
     */
    injectModal(html, overlayId) {
      const container = document.createElement("div");
      container.innerHTML = html;
      const modalElement = container.firstElementChild;
      if (modalElement != null) {
        document.body.appendChild(modalElement);
      }
      this.modal = document.getElementById(overlayId);
    }
    /**
     * Show modal with fade-in animation
     */
    showModal() {
      if (this.modal != null) {
        this.modal.style.display = "flex";
        void this.modal.offsetHeight;
        this.modal.style.opacity = "1";
      }
    }
    /**
     * Attach standard close handlers: × button, ESC key, overlay click
     *
     * Call this from subclass attachModalHandlers() after adding dialog-specific handlers.
     */
    attachCloseHandlers() {
      if (this.modal == null) {
        return;
      }
      const closeBtn = this.modal.querySelector(".dev-toolbar-modal-close");
      closeBtn?.addEventListener("click", () => this.close());
      this.escKeyCleanup = ShortcutManager.onEscape(() => this.close());
      this.modal.addEventListener("click", (e) => {
        if (e.target === this.modal) {
          this.close();
        }
      });
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
     * Remove modal from DOM with fade-out delay
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
  };

  // DevToolbar/ui/ClearHistoryDialog.ts
  var ClearHistoryDialog = class extends BaseDialog {
    constructor() {
      super(...arguments);
      this.onConfirm = null;
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
    onClose() {
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
      this.injectModal(modalHTML, "dev-toolbar-clear-history-overlay");
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
      this.attachCloseHandlers();
    }
  };

  // DevToolbar/ui/MessageDialog.ts
  var MessageDialog = class extends BaseDialog {
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
     * Get icon and color for message type
     */
    getTypeConfig(type) {
      const configs = {
        error: { icon: "\u274C", color: "#ef4444" },
        warning: { icon: "\u26A0\uFE0F", color: "#f59e0b" },
        info: { icon: "\u2139\uFE0F", color: "#3b82f6" },
        success: { icon: "\u2705", color: "#10b981" }
      };
      return configs[type];
    }
    /**
     * Create modal HTML structure
     */
    createModal(options) {
      const type = options.type ?? "info";
      const title = options.title ?? this.getDefaultTitle(type);
      const okButtonText = options.okButtonText ?? "OK";
      const { icon, color } = this.getTypeConfig(type);
      const modalHTML = `
            <div class="dev-toolbar-modal-overlay" id="dev-toolbar-message-overlay">
                <div class="dev-toolbar-modal dev-toolbar-message-modal">
                    <div class="dev-toolbar-modal-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 1.5rem;">${icon}</span>
                            <h3 style="color: ${color};">${HtmlEscaper.escape(title)}</h3>
                        </div>
                        <button class="dev-toolbar-modal-close" title="Close">\xD7</button>
                    </div>

                    <div class="dev-toolbar-modal-content">
                        <p style="white-space: pre-wrap; margin: 0;">${HtmlEscaper.escape(options.message)}</p>
                    </div>

                    <div class="dev-toolbar-modal-footer">
                        <button class="dev-toolbar-btn dev-toolbar-btn-primary" id="message-dialog-ok">
                            ${HtmlEscaper.escape(okButtonText)}
                        </button>
                    </div>
                </div>
            </div>
        `;
      this.injectModal(modalHTML, "dev-toolbar-message-overlay");
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
      return titles[type];
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
      this.attachCloseHandlers();
    }
  };
  function showMessage(options) {
    const dialog = new MessageDialog();
    dialog.open(options);
  }
  function showError(message, title) {
    showMessage({ type: "error", title, message });
  }

  // DevToolbar/ui/TrendCharts.ts
  var CHART_HEIGHT = 70;
  var CHART_PADDING_TOP = 8;
  var CHART_PADDING_BOTTOM = 20;
  var CHART_PADDING_LEFT = 50;
  var CHART_PADDING_RIGHT = 16;
  var METRICS = [
    {
      key: "time",
      label: "Time",
      unit: "ms",
      color: "#3b82f6",
      thresholdKey: "time_ms",
      extract: (r) => r.time,
      format: (v) => `${v.toFixed(0)}ms`
    },
    {
      key: "memory",
      label: "Memory",
      unit: "MB",
      color: "#10b981",
      thresholdKey: "memory_mb",
      extract: (r) => r.memory / 1024 / 1024,
      format: (v) => `${v.toFixed(1)}MB`
    },
    {
      key: "queries",
      label: "Queries",
      unit: "",
      color: "#f59e0b",
      thresholdKey: "query_count",
      extract: (r) => r.query_count,
      format: (v) => String(Math.round(v))
    }
  ];
  function renderTrendCharts(container, metaArray) {
    if (metaArray.length < 2) {
      container.innerHTML = '<p class="dev-toolbar-trends-empty">Need at least 2 requests to show trends.</p>';
      return;
    }
    const data = metaArray.slice(0, 50).reverse();
    const thresholds = StorageManager.getThresholds();
    const width = container.clientWidth || 500;
    let html = '<div class="dev-toolbar-trends-controls">';
    for (const metric of METRICS) {
      html += `<label class="dev-toolbar-trends-toggle">
      <input type="checkbox" data-trend-metric="${metric.key}" checked>
      <span class="dev-toolbar-trends-color" style="background:${metric.color}"></span>
      ${metric.label}
    </label>`;
    }
    html += "</div>";
    html += '<div class="dev-toolbar-trends-charts">';
    for (const metric of METRICS) {
      const values = data.map(metric.extract);
      const threshold = thresholds[metric.thresholdKey];
      const svg = buildChartSVG(metric, values, threshold, width);
      html += `<div class="dev-toolbar-trend-chart" data-trend-chart="${metric.key}">
      <div class="dev-toolbar-trend-chart-label">${metric.label}${metric.unit ? ` (${metric.unit})` : ""}</div>
      ${svg}
    </div>`;
    }
    html += "</div>";
    html += '<div class="dev-toolbar-trends-tooltip" id="dev-toolbar-trends-tooltip"></div>';
    container.innerHTML = html;
    attachChartInteractions(container, data);
  }
  function buildChartSVG(metric, values, threshold, containerWidth) {
    const plotWidth = containerWidth - CHART_PADDING_LEFT - CHART_PADDING_RIGHT;
    const plotHeight = CHART_HEIGHT - CHART_PADDING_TOP - CHART_PADDING_BOTTOM;
    const n = values.length;
    const min = Math.min(...values);
    const max = Math.max(...values);
    const rangeMax = Math.max(max, threshold) * 1.1;
    const rangeMin = Math.min(min, 0);
    const range = rangeMax - rangeMin || 1;
    const xStep = n > 1 ? plotWidth / (n - 1) : 0;
    const points = values.map((v, i) => {
      const x = CHART_PADDING_LEFT + i * xStep;
      const y = CHART_PADDING_TOP + plotHeight - (v - rangeMin) / range * plotHeight;
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(" ");
    const thresholdY = CHART_PADDING_TOP + plotHeight - (threshold - rangeMin) / range * plotHeight;
    const midVal = (rangeMin + rangeMax) / 2;
    const midY = CHART_PADDING_TOP + plotHeight / 2;
    let svg = `<svg class="dev-toolbar-trend-svg" data-metric="${metric.key}" width="100%" height="${CHART_HEIGHT}" viewBox="0 0 ${containerWidth} ${CHART_HEIGHT}" preserveAspectRatio="none">`;
    svg += `<line x1="${CHART_PADDING_LEFT}" y1="${CHART_PADDING_TOP}" x2="${CHART_PADDING_LEFT + plotWidth}" y2="${CHART_PADDING_TOP}" class="dev-toolbar-trend-grid"/>`;
    svg += `<line x1="${CHART_PADDING_LEFT}" y1="${midY}" x2="${CHART_PADDING_LEFT + plotWidth}" y2="${midY}" class="dev-toolbar-trend-grid"/>`;
    svg += `<line x1="${CHART_PADDING_LEFT}" y1="${CHART_PADDING_TOP + plotHeight}" x2="${CHART_PADDING_LEFT + plotWidth}" y2="${CHART_PADDING_TOP + plotHeight}" class="dev-toolbar-trend-grid"/>`;
    if (threshold > rangeMin && threshold < rangeMax) {
      svg += `<line x1="${CHART_PADDING_LEFT}" y1="${thresholdY.toFixed(1)}" x2="${CHART_PADDING_LEFT + plotWidth}" y2="${thresholdY.toFixed(1)}" class="dev-toolbar-trend-threshold" stroke="${metric.color}"/>`;
      svg += `<text x="${CHART_PADDING_LEFT + plotWidth + 2}" y="${thresholdY.toFixed(1)}" class="dev-toolbar-trend-threshold-label" fill="${metric.color}">${metric.format(threshold)}</text>`;
    }
    svg += `<text x="${CHART_PADDING_LEFT - 6}" y="${CHART_PADDING_TOP + 4}" class="dev-toolbar-trend-axis-label" text-anchor="end">${metric.format(rangeMax)}</text>`;
    svg += `<text x="${CHART_PADDING_LEFT - 6}" y="${midY + 4}" class="dev-toolbar-trend-axis-label" text-anchor="end">${metric.format(midVal)}</text>`;
    svg += `<text x="${CHART_PADDING_LEFT - 6}" y="${CHART_PADDING_TOP + plotHeight + 4}" class="dev-toolbar-trend-axis-label" text-anchor="end">${metric.format(rangeMin)}</text>`;
    svg += `<polyline points="${points}" class="dev-toolbar-trend-line" stroke="${metric.color}" fill="none"/>`;
    values.forEach((v, i) => {
      const x = CHART_PADDING_LEFT + i * xStep;
      const y = CHART_PADDING_TOP + plotHeight - (v - rangeMin) / range * plotHeight;
      svg += `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="3" class="dev-toolbar-trend-dot" fill="${metric.color}" data-index="${i}"/>`;
    });
    svg += `<line x1="0" y1="${CHART_PADDING_TOP}" x2="0" y2="${CHART_PADDING_TOP + plotHeight}" class="dev-toolbar-trend-crosshair" style="display:none"/>`;
    svg += `<circle cx="0" cy="0" r="4" class="dev-toolbar-trend-highlight" fill="${metric.color}" style="display:none"/>`;
    svg += `<text x="0" y="0" class="dev-toolbar-trend-value-label" fill="${metric.color}" style="display:none"></text>`;
    svg += "</svg>";
    return svg;
  }
  function attachChartInteractions(container, data) {
    const svgs = container.querySelectorAll(".dev-toolbar-trend-svg");
    const tooltip = container.querySelector("#dev-toolbar-trends-tooltip");
    const n = data.length;
    container.querySelectorAll("[data-trend-metric]").forEach((checkbox) => {
      checkbox.addEventListener("change", () => {
        const metric = checkbox.dataset.trendMetric;
        const chart = container.querySelector(`[data-trend-chart="${metric}"]`);
        if (chart) {
          chart.style.display = checkbox.checked ? "" : "none";
        }
      });
    });
    svgs.forEach((svg) => {
      svg.addEventListener("mousemove", (e) => {
        const rect = svg.getBoundingClientRect();
        const svgWidth = rect.width;
        const scaleX = svgWidth > 0 ? svg.viewBox.baseVal.width / svgWidth : 1;
        const mouseX = (e.clientX - rect.left) * scaleX;
        const plotWidth = svg.viewBox.baseVal.width - CHART_PADDING_LEFT - CHART_PADDING_RIGHT;
        const xStep = n > 1 ? plotWidth / (n - 1) : 0;
        const relX = mouseX - CHART_PADDING_LEFT;
        const index = Math.round(relX / (xStep || 1));
        if (index < 0 || index >= n) {
          hideCrosshairs(svgs);
          if (tooltip) tooltip.style.display = "none";
          return;
        }
        const xPos = CHART_PADDING_LEFT + index * xStep;
        showCrosshairs(svgs, xPos, index, data[index]);
        showTooltip(tooltip, data[index], e, container);
      });
      svg.addEventListener("mouseleave", () => {
        hideCrosshairs(svgs);
        if (tooltip) tooltip.style.display = "none";
      });
    });
  }
  function showCrosshairs(svgs, x, index, request) {
    svgs.forEach((svg) => {
      const crosshair = svg.querySelector(".dev-toolbar-trend-crosshair");
      if (crosshair) {
        crosshair.setAttribute("x1", x.toFixed(1));
        crosshair.setAttribute("x2", x.toFixed(1));
        crosshair.style.display = "";
      }
      const metricKey = svg.dataset.metric;
      const metric = METRICS.find((m) => m.key === metricKey);
      if (!metric) return;
      const dot = svg.querySelector(".dev-toolbar-trend-highlight");
      const label = svg.querySelector(".dev-toolbar-trend-value-label");
      const dataDot = svg.querySelector(
        `.dev-toolbar-trend-dot[data-index="${index}"]`
      );
      if (dot && dataDot) {
        const cy = dataDot.getAttribute("cy") ?? "0";
        dot.setAttribute("cx", x.toFixed(1));
        dot.setAttribute("cy", cy);
        dot.style.display = "";
      }
      if (label) {
        const value = metric.extract(request);
        const cy = dataDot?.getAttribute("cy") ?? "0";
        const labelY = parseFloat(cy) - 8;
        label.setAttribute("x", x.toFixed(1));
        label.setAttribute("y", labelY.toFixed(1));
        label.textContent = metric.format(value);
        label.style.display = "";
      }
    });
  }
  function hideCrosshairs(svgs) {
    svgs.forEach((svg) => {
      const crosshair = svg.querySelector(".dev-toolbar-trend-crosshair");
      if (crosshair) {
        crosshair.style.display = "none";
      }
      const dot = svg.querySelector(".dev-toolbar-trend-highlight");
      if (dot) dot.style.display = "none";
      const label = svg.querySelector(".dev-toolbar-trend-value-label");
      if (label) label.style.display = "none";
    });
  }
  function showTooltip(tooltip, request, event, container) {
    if (!tooltip) return;
    const time = request.time.toFixed(0);
    const memory = (request.memory / 1024 / 1024).toFixed(1);
    const queries = request.query_count;
    tooltip.innerHTML = `<strong>${request.method} ${request.uri}</strong><br>
    Time: ${time}ms | Memory: ${memory}MB | Queries: ${queries}`;
    tooltip.style.display = "block";
    const containerRect = container.getBoundingClientRect();
    const x = event.clientX - containerRect.left;
    const y = event.clientY - containerRect.top;
    tooltip.style.left = `${x + 12}px`;
    tooltip.style.top = `${y - 10}px`;
    const tooltipRect = tooltip.getBoundingClientRect();
    if (tooltipRect.right > containerRect.right) {
      tooltip.style.left = `${x - tooltipRect.width - 12}px`;
    }
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
     * Render performance trend charts
     */
    renderTrends(metaArray) {
      const container = document.getElementById("dev-toolbar-trends-container");
      if (!container) return;
      renderTrendCharts(container, metaArray);
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
                      data-method="${HtmlEscaper.escape(request.method)}"
                      data-uri="${HtmlEscaper.escape(request.uri)}"
                      data-status="${request.status}"
                      data-time="${request.time}"
                      data-request-id="${HtmlEscaper.escape(request.id)}">
                    <div class="dev-toolbar-history-item-header">
                        <span class="dev-toolbar-history-icon">${statusIcon}</span>
                        <span class="dev-toolbar-history-method">${HtmlEscaper.escape(request.method)}</span>
                        <span class="dev-toolbar-history-uri">${HtmlEscaper.escape(request.uri)}</span>
                        <span class="dev-toolbar-history-time-ago" title="${fullTimestamp}">${timeAgoText}</span>
                        <button class="dev-toolbar-history-item-export"
                                data-request-id="${HtmlEscaper.escape(request.id)}"
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
     * Exports JSON collector data only.
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
      Downloader.json(exportData, filename);
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
      Downloader.json(json, `devtoolbar-history-${Date.now()}.json`);
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
      Downloader.csv(csv, `devtoolbar-history-${Date.now()}.csv`);
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
      if (match?.[1] == null || match[2] == null) return Math.floor(Date.now() / 1e3);
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
  var SettingsManager = class extends BaseDialog {
    constructor() {
      super(...arguments);
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
     * Create modal HTML structure
     */
    createModal() {
      const currentLabels = StorageManager.getMinibarLabels();
      const currentColors = StorageManager.getBranchColors();
      const currentShortcut = StorageManager.getToggleShortcut();
      const currentThresholds = StorageManager.getThresholds();
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

                        <!-- Performance Thresholds -->
                        <div class="dev-toolbar-settings-group">
                            <label>Performance Alert Thresholds</label>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 0.875rem;">
                                Alerts trigger when values exceed these thresholds
                            </p>
                            <div class="dev-toolbar-settings-thresholds">
                                ${this.buildThresholdInput("time_ms", "Request Time", "ms", currentThresholds.time_ms)}
                                ${this.buildThresholdInput("memory_mb", "Memory Peak", "MB", currentThresholds.memory_mb)}
                                ${this.buildThresholdInput("query_count", "Query Count", "", currentThresholds.query_count)}
                                ${this.buildThresholdInput("query_time_ms", "Query Time (total)", "ms", currentThresholds.query_time_ms)}
                                ${this.buildThresholdInput("http_count", "HTTP Requests", "", currentThresholds.http_count)}
                                ${this.buildThresholdInput("http_time_ms", "HTTP Time (total)", "ms", currentThresholds.http_time_ms)}
                            </div>
                            <p style="margin: 8px 0 0 0; color: #6b7280; font-size: 0.75rem;">
                                <a href="#" id="reset-thresholds" style="color: #3b82f6; text-decoration: none;">Reset to Defaults</a>
                            </p>
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
      this.injectModal(modalHTML, "dev-toolbar-settings-overlay");
    }
    /**
     * Build checkbox option HTML
     */
    buildCheckboxOption(value, title, description, currentValues) {
      const checked = currentValues.includes(value) ? "checked" : "";
      return `
            <label class="dev-toolbar-settings-checkbox-item">
                <input type="checkbox" name="minibar-label" value="${HtmlEscaper.escape(value)}" ${checked}>
                <div>
                    <strong>${HtmlEscaper.escape(title)}</strong>
                    <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 0.875rem;">
                        ${HtmlEscaper.escape(description)}
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
                <label for="color-${HtmlEscaper.escape(type)}">${HtmlEscaper.escape(label)}</label>
                <input type="color" id="color-${HtmlEscaper.escape(type)}" name="color-${HtmlEscaper.escape(type)}" value="${HtmlEscaper.escape(value)}">
            </div>
        `;
    }
    /**
     * Build threshold number input HTML
     */
    buildThresholdInput(key, label, unit, value) {
      const suffix = unit !== "" ? ` <span style="color: #6b7280; font-size: 0.75rem;">${HtmlEscaper.escape(unit)}</span>` : "";
      return `
            <div class="dev-toolbar-settings-threshold-item">
                <label for="threshold-${HtmlEscaper.escape(key)}">${HtmlEscaper.escape(label)}${suffix}</label>
                <input type="number" id="threshold-${HtmlEscaper.escape(key)}" name="threshold-${HtmlEscaper.escape(key)}" value="${value}" min="0" step="1"
                    class="dev-toolbar-settings-threshold-input">
            </div>
        `;
    }
    /**
     * Format shortcut for display
     */
    formatShortcut(shortcut) {
      const parts = [];
      if (shortcut.ctrlKey === true) parts.push("Ctrl");
      if (shortcut.shiftKey === true) parts.push("Shift");
      if (shortcut.altKey === true) parts.push("Alt");
      if (shortcut.metaKey === true) {
        parts.push(/Mac|iPhone|iPad/.test(navigator.userAgent) ? "Cmd" : "Win");
      }
      parts.push(shortcut.key);
      return parts.join("+");
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
      const resetThresholds = this.modal.querySelector("#reset-thresholds");
      resetThresholds?.addEventListener("click", (e) => {
        e.preventDefault();
        const keys = Object.keys(DEFAULT_THRESHOLDS);
        for (const key of keys) {
          const input = this.modal?.querySelector(`#threshold-${key}`);
          if (input != null) {
            input.value = String(DEFAULT_THRESHOLDS[key]);
          }
        }
      });
      const saveBtn = this.modal.querySelector("#settings-save");
      saveBtn?.addEventListener("click", () => this.saveSettings());
      const cancelBtn = this.modal.querySelector("#settings-cancel");
      cancelBtn?.addEventListener("click", () => this.close());
      this.attachCloseHandlers();
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
      const thresholds = this.getThresholdValues();
      StorageManager.setThresholds(thresholds);
      this.saveSettingsToCookies(selectedLabels, branchColors, thresholds);
      debug("[Settings] Saved:", { labels: selectedLabels, colors: branchColors, thresholds });
      window.location.reload();
    }
    /**
     * Save settings to cookies for PHP access
     */
    saveSettingsToCookies(labels, colors, thresholds) {
      const labelsJson = JSON.stringify(labels);
      document.cookie = `devbar_labels=${encodeURIComponent(labelsJson)}; path=/; max-age=31536000`;
      const colorsJson = JSON.stringify(colors);
      document.cookie = `devbar_colors=${encodeURIComponent(colorsJson)}; path=/; max-age=31536000`;
      const thresholdsJson = JSON.stringify(thresholds);
      document.cookie = `devbar_thresholds=${encodeURIComponent(thresholdsJson)}; path=/; max-age=31536000`;
      debug("[Settings] Cookies set:", {
        labels: `devbar_labels=${encodeURIComponent(labelsJson)}`,
        colors: `devbar_colors=${encodeURIComponent(colorsJson)}`,
        thresholds: `devbar_thresholds=${encodeURIComponent(thresholdsJson)}`
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
    /**
     * Get threshold values from form
     */
    getThresholdValues() {
      const keys = Object.keys(DEFAULT_THRESHOLDS);
      const thresholds = {};
      for (const key of keys) {
        const input = this.modal?.querySelector(`#threshold-${key}`);
        if (input != null) {
          const value = parseInt(input.value, 10);
          if (!isNaN(value) && value >= 0) {
            thresholds[key] = value;
          }
        }
      }
      return thresholds;
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
        if (e.key === "Escape" && this.panel?.classList.contains("open") === true) {
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
      if (this.panel?.classList.contains("open") === true) {
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
      const isMaximized = this.panel?.classList.contains("maximized") === true;
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
          if (content?.contains(e.target) === true) {
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
      Downloader.json(exportData, filename);
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
