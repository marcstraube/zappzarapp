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
 * Stores: { version?: string, minibarLabels?: MinibarLabelType[], branchColors?: BranchColors }
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
 * Default minibar labels (active by default)
 * Shows lightning bolt (⚡) as branding by default
 */
export const DEFAULT_MINIBAR_LABELS: import('../types/DevToolbarTypes.js').MinibarLabelType[] = [
  'branding',
];

/**
 * Default branch colors for git branch types
 */
export const DEFAULT_BRANCH_COLORS: import('../types/DevToolbarTypes.js').BranchColors = {
  feat: '#3b82f6', // Blue
  fix: '#f59e0b', // Orange
  hotfix: '#ef4444', // Red
  chore: '#6b7280', // Gray
  default: '#10b981', // Green
};
