/**
 * DevDashboard Logs page
 *
 * Tab navigation, copy buttons and the API interceptor come from the shared
 * dev-dashboard modules; formatDateResult (modified-date display) and Result
 * (explicit error handling) from @zappzarapp/browser-utils.
 */

import { Result } from '@zappzarapp/browser-utils/core';
import { formatDateResult } from '@zappzarapp/browser-utils/intl';
import { createDashboardApi } from './shared/api';
import { copyCmd } from './shared/copy';
import { initTabs, switchTab } from './shared/tabs';

interface LogApiResponse {
  error?: string;
  content?: string;
  size?: number;
  modified?: number;
  lines?: number;
}

const logApi = createDashboardApi();

let currentLogFile = '';

function openLogModal(filename: string): void {
  currentLogFile = filename;
  const modal = document.getElementById('logModal');
  const title = document.getElementById('logModalTitle');
  if (modal) modal.style.display = 'block';
  if (title) title.textContent = filename;
  document.body.style.overflow = 'hidden';
  void loadLogContent(filename);
}

function closeLogModal(): void {
  const modal = document.getElementById('logModal');
  if (modal) modal.style.display = 'none';
  document.body.style.overflow = '';
  currentLogFile = '';
}

function refreshLog(): void {
  if (currentLogFile) {
    void loadLogContent(currentLogFile);
  }
}

function formatModified(modifiedSeconds: number): string {
  const date = new Date(modifiedSeconds * 1000);
  return Result.unwrapOr(
    formatDateResult(date, undefined, { dateStyle: 'medium', timeStyle: 'medium' }),
    date.toISOString()
  );
}

async function loadLogContent(filename: string): Promise<void> {
  const contentEl = document.getElementById('logModalContent');
  const metaEl = document.getElementById('logModalMeta');
  const linesSelect = document.getElementById('logLinesSelect');
  if (!contentEl || !metaEl) return;

  const lines = linesSelect instanceof HTMLSelectElement ? linesSelect.value : '100';

  contentEl.textContent = 'Loading...';
  contentEl.style.color = '#4ade80';

  try {
    const response = await logApi.get(
      `/_dev/api/logs?file=${encodeURIComponent(filename)}&lines=${lines}`
    );
    const data = (await response.json()) as LogApiResponse;

    if (data.error !== undefined) {
      contentEl.textContent = `Error: ${data.error}`;
      contentEl.style.color = '#f87171';
      metaEl.textContent = '';
    } else {
      contentEl.textContent =
        data.content !== undefined && data.content !== '' ? data.content : '(empty file)';
      contentEl.style.color = '#4ade80';
      const sizeKb = ((data.size ?? 0) / 1024).toFixed(1);
      const modified = formatModified(data.modified ?? 0);
      metaEl.textContent = `${sizeKb} KB • Last modified: ${modified} • Showing last ${String(data.lines ?? lines)} lines`;
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    contentEl.textContent = `Failed to load log: ${message}`;
    contentEl.style.color = '#f87171';
    metaEl.textContent = '';
  }
}

function init(): void {
  initTabs();

  // Event delegation for data-action clicks (CSP-compliant)
  document.addEventListener('click', (e) => {
    const target =
      e.target instanceof Element ? e.target.closest<HTMLElement>('[data-action]') : null;
    if (!target) return;

    switch (target.dataset.action) {
      case 'copy-cmd':
        if (target.dataset.cmd !== undefined) void copyCmd(target.dataset.cmd, target);
        break;
      case 'open-log':
        if (target.dataset.logfile !== undefined) openLogModal(target.dataset.logfile);
        break;
      case 'close-log':
        closeLogModal();
        break;
      case 'refresh-log':
        refreshLog();
        break;
    }
  });

  document.getElementById('logLinesSelect')?.addEventListener('change', refreshLog);

  document.addEventListener('keydown', (e) => {
    const modal = document.getElementById('logModal');
    if (e.key === 'Escape' && modal !== null && modal.style.display !== 'none') {
      closeLogModal();
    }
  });
}

init();

// Exported for unit tests (happy-dom); the page itself only needs init()
export { switchTab, copyCmd, openLogModal, closeLogModal, formatModified, loadLogContent };
