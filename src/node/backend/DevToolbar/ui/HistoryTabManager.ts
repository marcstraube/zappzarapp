/**
 * HistoryTabManager - Manages History tab rendering and interactions
 *
 * Handles:
 * - Rendering request history list from localStorage
 * - Filtering by method, status, URI, time
 * - Exporting history as JSON/CSV
 * - Statistics calculation
 * - Sparkline trends
 */

import { StorageManager } from '../storage/StorageManager';
import { timeAgo, formatTimestamp, generateSparkline } from '../utils/timeUtils';
import { downloadFile, exportRequestAsJson } from '../utils/exportUtils';
import { ClearHistoryDialog } from './ClearHistoryDialog';
import type { RequestMetadata } from '../types';
import { debug, warn, error as logError } from '../utils/logger.js';

/**
 * HistoryTabManager for managing history tab
 */
export class HistoryTabManager {
  private initialized: boolean = false;
  private clearHistoryDialog: ClearHistoryDialog;

  constructor() {
    this.clearHistoryDialog = new ClearHistoryDialog();
  }

  /**
   * Initialize history tab
   */
  init(): void {
    if (this.initialized) {
      debug('[HistoryTabManager] Already initialized');
      return;
    }

    debug('[HistoryTabManager] Initializing History tab');

    // Render history data from localStorage
    this.renderHistoryData();

    // Attach filter listeners
    this.attachFilterListeners();

    // Attach export listeners
    this.attachExportListeners();

    this.initialized = true;
  }

  /**
   * Reset initialization flag (for re-initialization after request load)
   */
  reset(): void {
    this.initialized = false;
  }

  /**
   * Render history data from localStorage
   */
  private renderHistoryData(): void {
    const metaArray = StorageManager.getMetadata();
    debug('[HistoryTabManager] Found', metaArray.length, 'requests');

    // Update statistics
    this.updateStatistics(metaArray);

    // Update request count
    const countEl = document.getElementById('history-list-count');
    if (countEl) {
      countEl.textContent = String(metaArray.length);
    }

    // Render trends sparkline
    this.renderTrends(metaArray);

    // Render request list
    this.renderRequestList(metaArray);
  }

  /**
   * Update statistics display
   */
  private updateStatistics(metaArray: RequestMetadata[]): void {
    const stats = this.calculateStats(metaArray);

    const statsMap: Record<string, string> = {
      total: String(stats.total),
      avg_time: `${stats.avgTime.toFixed(0)}ms`,
      avg_memory: `${stats.avgMemory.toFixed(1)}MB`,
      avg_queries: stats.avgQueries.toFixed(1),
      fastest: `${stats.fastest.toFixed(0)}ms`,
      slowest: `${stats.slowest.toFixed(0)}ms`,
    };

    Object.entries(statsMap).forEach(([stat, value]) => {
      const el = document.querySelector(`[data-history-stat="${stat}"]`);
      if (el) {
        el.textContent = value;
      }
    });
  }

  /**
   * Calculate statistics from metadata
   */
  private calculateStats(metaArray: RequestMetadata[]): {
    total: number;
    avgTime: number;
    avgMemory: number;
    avgQueries: number;
    fastest: number;
    slowest: number;
  } {
    if (metaArray.length === 0) {
      return {
        total: 0,
        avgTime: 0,
        avgMemory: 0,
        avgQueries: 0,
        fastest: 0,
        slowest: 0,
      };
    }

    const times = metaArray.map((r) => r.time);
    const memories = metaArray.map((r) => r.memory / 1024 / 1024); // Convert to MB
    const queries = metaArray.map((r) => r.query_count);

    return {
      total: metaArray.length,
      avgTime: times.reduce((a, b) => a + b, 0) / times.length,
      avgMemory: memories.reduce((a, b) => a + b, 0) / memories.length,
      avgQueries: queries.reduce((a, b) => a + b, 0) / queries.length,
      fastest: Math.min(...times),
      slowest: Math.max(...times),
    };
  }

  /**
   * Render trends sparkline
   */
  private renderTrends(metaArray: RequestMetadata[]): void {
    const trendsEl = document.querySelector('.dev-toolbar-history-sparkline');
    if (!trendsEl) return;

    const times = metaArray
      .slice(0, 20)
      .reverse()
      .map((r) => r.time);
    const sparkline = generateSparkline(times);

    trendsEl.textContent = sparkline;
  }

  /**
   * Render request list
   */
  private renderRequestList(metaArray: RequestMetadata[]): void {
    const listContainer = document.getElementById('history-request-list-container');
    if (!listContainer) {
      warn('[HistoryTabManager] List container not found');
      return;
    }

    if (metaArray.length === 0) {
      listContainer.innerHTML =
        '<p style="color: #888;">No request history yet. Reload the page to see requests.</p>';
      return;
    }

    let html = '';

    metaArray.forEach((request) => {
      const statusIcon = this.getStatusIcon(request.status);
      const timeAgoText = timeAgo(request.timestamp);
      const fullTimestamp = formatTimestamp(request.timestamp);

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
                        <span class="dev-toolbar-history-time-ago" title="${fullTimestamp}">${timeAgoText}</span>
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
    debug('[HistoryTabManager] Rendered', metaArray.length, 'requests');

    // Attach export listeners after rendering
    setTimeout(() => this.attachItemExportListeners(), 50);
  }

  /**
   * Get status icon for status code
   */
  private getStatusIcon(statusCode: number): string {
    if (statusCode >= 200 && statusCode < 300) return '✓';
    if (statusCode >= 300 && statusCode < 400) return '→';
    if (statusCode >= 400 && statusCode < 500) return '⚠';
    if (statusCode >= 500) return '✗';
    return '?';
  }

  /**
   * Attach filter listeners
   */
  private attachFilterListeners(): void {
    const methodFilter = document.getElementById(
      'history-filter-method'
    ) as HTMLSelectElement | null;
    const statusFilter = document.getElementById(
      'history-filter-status'
    ) as HTMLSelectElement | null;
    const uriFilter = document.getElementById('history-filter-uri') as HTMLInputElement | null;
    const minTimeFilter = document.getElementById(
      'history-filter-min-time'
    ) as HTMLInputElement | null;
    const resetBtn = document.getElementById('history-filter-reset');

    [methodFilter, statusFilter, uriFilter, minTimeFilter].forEach((el) => {
      if (el) {
        el.addEventListener('input', () => this.filterRequests());
      }
    });

    resetBtn?.addEventListener('click', () => this.resetFilters());
  }

  /**
   * Filter requests based on current filter values
   */
  private filterRequests(): void {
    const methodEl = document.getElementById('history-filter-method') as HTMLSelectElement | null;
    const statusEl = document.getElementById('history-filter-status') as HTMLSelectElement | null;
    const uriEl = document.getElementById('history-filter-uri') as HTMLInputElement | null;
    const minTimeEl = document.getElementById('history-filter-min-time') as HTMLInputElement | null;

    const filters = {
      method: methodEl ? methodEl.value : '',
      status: statusEl ? statusEl.value : '',
      uri: uriEl ? uriEl.value.toLowerCase() : '',
      minTime: parseFloat(minTimeEl ? minTimeEl.value : '0'),
    };

    const items = document.querySelectorAll('.dev-toolbar-history-item');
    let visibleCount = 0;

    items.forEach((item) => {
      const matches = this.itemMatchesFilters(item as HTMLElement, filters);
      (item as HTMLElement).style.display = matches ? '' : 'none';
      if (matches) visibleCount++;
    });

    this.updateListTitle(visibleCount, items.length);
  }

  /**
   * Check if item matches filters
   */
  private itemMatchesFilters(
    item: HTMLElement,
    filters: { method: string; status: string; uri: string; minTime: number }
  ): boolean {
    if (filters.method && item.dataset.method !== filters.method) return false;
    if (filters.status && !item.dataset.status?.startsWith(filters.status)) return false;
    if (filters.uri && !item.dataset.uri?.toLowerCase().includes(filters.uri)) return false;
    if (filters.minTime > 0 && parseFloat(item.dataset.time || '0') < filters.minTime) return false;
    return true;
  }

  /**
   * Update list title with counts
   */
  private updateListTitle(visibleCount: number, totalCount: number): void {
    const title = document.getElementById('history-list-title');
    if (title) {
      title.textContent = `Request History (${visibleCount} of ${totalCount})`;
    }
  }

  /**
   * Reset filters
   */
  private resetFilters(): void {
    ['method', 'status', 'uri', 'min-time'].forEach((id) => {
      const el = document.getElementById(`history-filter-${id}`);
      if (el) el.value = '';
    });
    this.filterRequests();
  }

  /**
   * Attach export listeners
   */
  private attachExportListeners(): void {
    document
      .getElementById('history-export-json')
      ?.addEventListener('click', () => this.exportAsJSON());
    document
      .getElementById('history-export-csv')
      ?.addEventListener('click', () => this.exportAsCSV());
    document.getElementById('history-clear')?.addEventListener('click', () => this.clearHistory());
  }

  /**
   * Attach individual item export listeners
   */
  private attachItemExportListeners(): void {
    document.querySelectorAll('.dev-toolbar-history-item-export').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const requestId = (btn as HTMLElement).dataset.requestId;
        if (requestId) {
          this.exportRequest(requestId);
        }
      });
    });
  }

  /**
   * Export single request
   *
   * Exports structured collector data only.
   * Shows alert if data is not available (legacy request).
   */
  private exportRequest(requestId: string): void {
    const requestData = StorageManager.getRequest(requestId);
    if (!requestData) {
      logError('[HistoryTabManager] Request not found:', requestId);
      alert('Request not found in history.');
      return;
    }

    try {
      const exportData = exportRequestAsJson(requestId, requestData);
      const filename = `devtoolbar-request-${requestId}-${Date.now()}.json`;
      downloadFile(JSON.stringify(exportData, null, 2), filename, 'application/json');
    } catch (error) {
      logError('[HistoryTabManager] Export failed:', error);
      alert(
        'Cannot export this request: No structured data available.\n\n' +
          'This request was stored before structured data export was implemented.\n' +
          'Please reload the page to capture new requests with structured data.'
      );
    }
  }

  /**
   * Export visible history as JSON
   */
  private exportAsJSON(): void {
    const data = this.collectVisibleData();
    const json = JSON.stringify(
      {
        toolbar_version: '2.1.0',
        export_time: new Date().toISOString(),
        requests: data,
      },
      null,
      2
    );

    downloadFile(json, `devtoolbar-history-${Date.now()}.json`, 'application/json');
  }

  /**
   * Export visible history as CSV
   */
  private exportAsCSV(): void {
    const data = this.collectVisibleData();
    let csv = 'Timestamp,Method,URI,Status,Time (ms),Memory (MB),Queries\n';

    data.forEach((item) => {
      const timestamp = new Date(item.timestamp * 1000).toISOString();
      const uri = `"${item.uri.replace(/"/g, '""')}"`;
      csv += `${timestamp},${item.method},${uri},${item.status},${item.time},${item.memory},${item.query_count}\n`;
    });

    downloadFile(csv, `devtoolbar-history-${Date.now()}.csv`, 'text/csv');
  }

  /**
   * Collect visible request data
   */
  private collectVisibleData(): Array<{
    method: string;
    uri: string;
    status: number;
    time: number;
    memory: number;
    query_count: number;
    timestamp: number;
  }> {
    const items = document.querySelectorAll(
      '.dev-toolbar-history-item:not([style*="display: none"])'
    );
    return Array.from(items).map((item) => {
      const el = item as HTMLElement;
      const memoryText =
        item.querySelector('.dev-toolbar-history-meta-item:nth-child(3)')?.textContent || '';
      const memoryMatch = memoryText.match(/[\d.]+/);
      const memory = memoryMatch ? parseFloat(memoryMatch[0]) : 0;

      const queriesText =
        item.querySelector('.dev-toolbar-history-meta-item:nth-child(4)')?.textContent || '';
      const queriesMatch = queriesText.match(/\d+/);
      const queryCount = queriesMatch ? parseInt(queriesMatch[0], 10) : 0;

      const timeAgoText = item.querySelector('.dev-toolbar-history-time-ago')?.textContent || '';
      const timestamp = this.parseTimeAgo(timeAgoText);

      return {
        method: el.dataset.method || '',
        uri: el.dataset.uri || '',
        status: parseInt(el.dataset.status || '0', 10),
        time: parseFloat(el.dataset.time || '0'),
        memory,
        query_count: queryCount,
        timestamp,
      };
    });
  }

  /**
   * Parse "time ago" text to timestamp
   */
  private parseTimeAgo(text: string): number {
    const match = text.match(/(\d+)([smhd])/);
    if (!match) return Math.floor(Date.now() / 1000);

    const value = parseInt(match[1], 10);
    const multipliers: Record<string, number> = { s: 1, m: 60, h: 3600, d: 86400 };
    const multiplier = multipliers[match[2]];
    if (multiplier === undefined) return Math.floor(Date.now() / 1000);

    return Math.floor(Date.now() / 1000) - value * multiplier;
  }

  /**
   * Clear history
   */
  private clearHistory(): void {
    this.clearHistoryDialog.open(() => {
      StorageManager.clear();
      window.location.reload();
    });
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
}
