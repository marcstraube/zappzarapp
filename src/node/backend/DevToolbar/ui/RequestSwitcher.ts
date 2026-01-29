/**
 * RequestSwitcher - Manages request history navigation
 *
 * Handles the request switcher dropdown:
 * - Populating dropdown from localStorage metadata
 * - Loading historical requests
 * - Restoring current request
 * - Updating switcher label and highlighting
 */

import { StorageManager } from '../storage/StorageManager';
import { timeAgo } from '../utils/timeUtils';
import type { RequestData } from '../types';

/**
 * RequestSwitcher for navigating between current and historical requests
 */
export class RequestSwitcher {
    private isLoadingRequest: boolean = false;
    private isViewingHistoricalRequest: boolean = false;
    private currentHistoricalRequestId: string | null = null;
    private currentRequestData: string | null = null;
    private originalBadgeCounts: Record<string, number> | null = null;

    /**
     * Initialize request switcher
     *
     * @param onRequestLoad - Callback when request is loaded (for tab re-initialization)
     */
    init(onRequestLoad?: (requestId: string) => void): void {
        const switcher = document.querySelector('.dev-toolbar-request-switcher');
        if (!switcher) return;

        const toggle = switcher.querySelector('.dev-toolbar-request-switcher-toggle');
        const dropdown = switcher.querySelector('.dev-toolbar-request-switcher-dropdown');

        // Store current request data for restoration
        this.storeCurrentRequestData();

        // Store original badge counts
        if (typeof window !== 'undefined' && 'window' in globalThis) {
            const win = window as any;
            if (win.__DEV_TOOLBAR_DATA__ && win.__DEV_TOOLBAR_DATA__.metadata) {
                this.originalBadgeCounts = win.__DEV_TOOLBAR_DATA__.metadata.badge_counts;
                console.log('[RequestSwitcher] Stored original badge counts:', this.originalBadgeCounts);
            }
        }

        // Populate dropdown from localStorage
        this.populateSwitcher();

        // Toggle dropdown
        toggle?.addEventListener('click', (e) => {
            e.stopPropagation();
            switcher.classList.toggle('open');
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!switcher.contains(e.target as Node)) {
                switcher.classList.remove('open');
            }
        });

        // Handle item clicks
        dropdown?.addEventListener('click', (e) => {
            const item = (e.target as HTMLElement).closest('.dev-toolbar-request-switcher-item') as HTMLElement;
            if (!item) return;

            console.log('[RequestSwitcher] Item clicked:', item.dataset);

            // Handle special actions
            if (item.dataset.action === 'view-history') {
                console.log('[RequestSwitcher] Opening HISTORY tab');
                if (onRequestLoad) {
                    onRequestLoad('history');
                }
                switcher.classList.remove('open');
                return;
            }

            if (item.dataset.action === 'current') {
                switcher.classList.remove('open');
                if (this.isViewingHistoricalRequest) {
                    this.restoreCurrentRequest(onRequestLoad);
                } else {
                    console.log('[RequestSwitcher] Already viewing current request');
                }
                return;
            }

            const requestId = item.dataset.requestId;
            if (requestId) {
                this.loadHistoricalRequest(requestId, onRequestLoad);
            }
        });

        console.log('[RequestSwitcher] Initialized');
    }

    /**
     * Check if currently viewing a historical request
     */
    isViewingHistory(): boolean {
        return this.isViewingHistoricalRequest;
    }

    /**
     * Get currently viewed historical request ID
     */
    getCurrentHistoricalRequestId(): string | null {
        return this.currentHistoricalRequestId;
    }

    /**
     * Populate switcher dropdown from localStorage
     */
    populateSwitcher(): void {
        const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');
        if (!dropdown) return;

        const metaArray = StorageManager.getMetadata();
        console.log('[RequestSwitcher] Populating with', metaArray.length, 'requests');

        let html = '';

        // Current request (always first)
        const isCurrentActive = !this.isViewingHistoricalRequest;
        html += `<div class="dev-toolbar-request-switcher-item ${isCurrentActive ? 'active' : ''}" data-action="current">
            <span class="dev-toolbar-request-switcher-item-indicator">●</span>
            <span class="dev-toolbar-request-switcher-item-label">Current Request</span>
        </div>`;

        // Recent requests (latest 5)
        const recentRequests = metaArray.slice(0, 5);
        if (recentRequests.length > 0) {
            html += '<div class="dev-toolbar-request-switcher-separator">Recent</div>';
            recentRequests.forEach((meta) => {
                const isActive = this.isViewingHistoricalRequest && this.currentHistoricalRequestId === meta.id;
                const statusClass = this.getStatusClass(meta.statusCode);
                const time = timeAgo(meta.timestamp);

                html += `<div class="dev-toolbar-request-switcher-item ${isActive ? 'active' : ''}" data-request-id="${meta.id}">
                    <span class="dev-toolbar-request-switcher-item-method">${meta.method}</span>
                    <span class="dev-toolbar-request-switcher-item-status status-${statusClass}">${meta.statusCode}</span>
                    <span class="dev-toolbar-request-switcher-item-uri" title="${this.escapeHtml(meta.uri)}">${this.escapeHtml(meta.uri)}</span>
                    <span class="dev-toolbar-request-switcher-item-time">${time}</span>
                </div>`;
            });
        }

        // View all history link
        if (metaArray.length > 5) {
            html += `<div class="dev-toolbar-request-switcher-item dev-toolbar-request-switcher-item-action" data-action="view-history">
                <span class="dev-toolbar-request-switcher-item-label">View all ${metaArray.length} requests →</span>
            </div>`;
        } else if (metaArray.length > 0) {
            html += `<div class="dev-toolbar-request-switcher-item dev-toolbar-request-switcher-item-action" data-action="view-history">
                <span class="dev-toolbar-request-switcher-item-label">View history →</span>
            </div>`;
        }

        dropdown.innerHTML = html;
    }

    /**
     * Load historical request from localStorage
     */
    async loadHistoricalRequest(requestId: string, onRequestLoad?: (requestId: string) => void): Promise<void> {
        if (this.isLoadingRequest) {
            console.log('[RequestSwitcher] Already loading a request, ignoring');
            return;
        }

        this.isLoadingRequest = true;
        const switcher = document.querySelector('.dev-toolbar-request-switcher');
        switcher?.classList.add('loading');

        console.log('[RequestSwitcher] Loading historical request:', requestId);

        try {
            const requestData = StorageManager.getRequest(requestId);

            if (!requestData) {
                throw new Error('Request not found in localStorage');
            }

            // Reconstruct tabs HTML
            const tabsHtml = this.buildTabsHTML(requestData);

            // Replace tab content
            const contentContainer = document.querySelector('.dev-toolbar-panel-content');
            if (!contentContainer) {
                throw new Error('Content container not found');
            }

            contentContainer.innerHTML = tabsHtml;

            // Mark as viewing historical request
            this.isViewingHistoricalRequest = true;
            this.currentHistoricalRequestId = requestId;

            // Re-populate dropdown to update highlighting
            this.populateSwitcher();

            // Update switcher label
            this.updateSwitcherLabel(requestId, true);

            // Notify callback
            if (onRequestLoad) {
                onRequestLoad(requestId);
            }

            console.log('[RequestSwitcher] Successfully loaded historical request');
        } catch (error) {
            console.error('[RequestSwitcher] Failed to load request:', error);
        } finally {
            this.isLoadingRequest = false;
            switcher?.classList.remove('loading');
            switcher?.classList.remove('open');
        }
    }

    /**
     * Restore current request
     */
    restoreCurrentRequest(onRequestLoad?: (requestId: string) => void): void {
        console.log('[RequestSwitcher] Restoring current request');

        const contentContainer = document.querySelector('.dev-toolbar-panel-content');
        if (!contentContainer || !this.currentRequestData) {
            return;
        }

        // Restore original content
        contentContainer.innerHTML = this.currentRequestData;

        // Reset flags
        this.isViewingHistoricalRequest = false;
        this.currentHistoricalRequestId = null;

        // Re-populate dropdown
        this.populateSwitcher();

        // Update switcher label
        this.updateSwitcherLabel('current', false);

        // Notify callback
        if (onRequestLoad) {
            onRequestLoad('current');
        }

        console.log('[RequestSwitcher] Restored current request');
    }

    /**
     * Store current request data for restoration
     */
    private storeCurrentRequestData(): void {
        const contentContainer = document.querySelector('.dev-toolbar-panel-content');
        if (contentContainer) {
            this.currentRequestData = contentContainer.innerHTML;
        }
    }

    /**
     * Build tabs HTML from request data
     */
    private buildTabsHTML(requestData: RequestData): string {
        let html = '';
        for (const [tabName, content] of Object.entries(requestData.tabs)) {
            html += `<div class="dev-toolbar-panel-tab-pane" data-tab="${tabName}">${content}</div>`;
        }
        return html;
    }

    /**
     * Update switcher label
     */
    private updateSwitcherLabel(requestId: string, isHistorical: boolean): void {
        const switcher = document.querySelector('.dev-toolbar-request-switcher');
        const toggle = switcher?.querySelector('.dev-toolbar-request-switcher-toggle') as HTMLElement;
        if (!toggle) return;

        toggle.dataset.current = requestId;

        const label = toggle.querySelector('.dev-toolbar-request-switcher-label');
        if (label) {
            label.textContent = isHistorical ? 'Request (Historical)' : 'Request';
            (label as HTMLElement).style.color = isHistorical ? '#f59e0b' : '';
        }
    }

    /**
     * Get status class for status code
     */
    private getStatusClass(statusCode: number): string {
        if (statusCode >= 200 && statusCode < 300) return 'success';
        if (statusCode >= 300 && statusCode < 400) return 'redirect';
        if (statusCode >= 400 && statusCode < 500) return 'client-error';
        if (statusCode >= 500) return 'server-error';
        return 'unknown';
    }

    /**
     * Escape HTML
     */
    private escapeHtml(text: string): string {
        const map: Record<string, string> = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };
        return text.replace(/[&<>"']/g, (char) => map[char] || char);
    }

    /**
     * Get original badge counts
     */
    getOriginalBadgeCounts(): Record<string, number> | null {
        return this.originalBadgeCounts;
    }
}
