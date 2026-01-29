/**
 * StorageManager Configuration Constants
 *
 * Defines storage limits and key naming conventions for DevToolbar persistence.
 */

/**
 * Maximum number of lightweight metadata entries to keep
 * Metadata includes: method, URI, status code, timestamp, duration, memory
 */
export const MAX_METADATA = 50;

/**
 * Maximum number of full request data entries to keep
 * Full data includes: metadata + all tab HTML content
 * LRU eviction keeps the most recent MAX_FULL_DATA entries
 */
export const MAX_FULL_DATA = 20;

/**
 * Minimum safe entries to keep during emergency quota eviction
 * When QuotaExceededError occurs, evict down to this many entries
 */
export const MIN_SAFE_ENTRIES = 5;

/**
 * localStorage key for DevToolbar configuration
 * Stores: { migrated: boolean, version?: string }
 */
export const CONFIG_KEY = 'devToolbar.config';

/**
 * localStorage key for metadata array
 * Stores: RequestMetadata[]
 */
export const META_KEY = 'devToolbar.meta';

/**
 * localStorage key prefix for full request data
 * Format: 'devToolbar.req_<requestId>'
 * Stores: { metadata: RequestMetadata, tabs: Record<string, string> }
 */
export const DATA_PREFIX = 'devToolbar.req_';

/**
 * localStorage key for last active tab
 * Stores: TabName (string)
 */
export const ACTIVE_TAB_KEY = 'devtoolbar_active_tab';
