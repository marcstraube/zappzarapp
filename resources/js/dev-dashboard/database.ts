/**
 * DevDashboard Database page
 *
 * Backup list rendering builds elements via DomHelper so untrusted API
 * values (filenames, timestamps) never pass through innerHTML.
 */

import { DomHelper } from '@zappzarapp/browser-utils/html';
import { createDashboardApi } from './shared/api';
import { copyCmd } from './shared/copy';
import { initTabs } from './shared/tabs';

interface Backup {
  filename: string;
  timestamp: string;
  size: string;
  sizeBytes?: number;
  age: string;
  encrypted?: boolean;
  dbType: string;
  dbName: string;
}

interface BackupListResponse {
  success?: boolean;
  backups?: Backup[];
}

interface BackupActionResponse {
  success?: boolean;
  message?: string;
}

interface BackupStats {
  count: number;
  totalSize: string;
  oldestDate: string;
  newestDate: string;
}

const listApi = createDashboardApi();
// Creating or restoring a backup dumps/loads the whole database — allow far
// more than the default lookup timeout.
const backupApi = createDashboardApi(300_000);

function showNotification(type: 'success' | 'error', message: string): void {
  const notification = DomHelper.create(
    'div',
    { class: `dev-notification dev-notification--${type}` },
    message
  );
  document.body.appendChild(notification);

  setTimeout(() => {
    notification.classList.add('dev-notification--closing');
    setTimeout(() => notification.remove(), 300);
  }, 4000);
}

function formatBytes(bytes: number): string {
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let value = bytes;
  let factor = 0;
  while (value >= 1024 && factor < units.length - 1) {
    value /= 1024;
    factor++;
  }
  return `${value.toFixed(2)} ${units[factor]}`;
}

function updateBackupStats(stats: BackupStats): void {
  const set = (id: string, text: string): void => {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  };
  set('stat-count', String(stats.count));
  set('stat-size', stats.totalSize);
  set('stat-newest', stats.newestDate);
  set('stat-oldest', stats.oldestDate);
}

function buildBackupItem(backup: Backup): HTMLElement {
  const title = DomHelper.create(
    'h3',
    { class: 'font-medium text-gray-900 mb-1' },
    backup.filename
  );

  const meta = DomHelper.create('p', { class: 'text-sm text-gray-600' });
  const badge =
    backup.encrypted === true
      ? DomHelper.create('span', { class: 'badge badge-green ml-2' }, 'Encrypted')
      : DomHelper.create('span', { class: 'badge badge-gray ml-2' }, 'Unencrypted');
  DomHelper.append(meta, `${backup.timestamp} • ${backup.size} • ${backup.age} `, badge);

  const dbInfo = DomHelper.create(
    'p',
    { class: 'text-xs text-gray-500 mt-1' },
    `DB: ${backup.dbType} / ${backup.dbName}`
  );

  const info = DomHelper.create('div', { style: 'flex: 1;' });
  DomHelper.append(info, title, meta, dbInfo);

  const restoreBtn = DomHelper.create(
    'button',
    { class: 'btn btn-primary text-sm', 'data-action': 'restore-backup' },
    'Restore'
  );
  restoreBtn.dataset.filename = backup.filename;

  const deleteBtn = DomHelper.create(
    'button',
    { class: 'btn btn-danger text-sm', 'data-action': 'delete-backup' },
    'Delete'
  );
  deleteBtn.dataset.filename = backup.filename;

  const buttons = DomHelper.create('div', { class: 'flex gap-2', style: 'flex-shrink: 0;' });
  DomHelper.append(buttons, restoreBtn, deleteBtn);

  const row = DomHelper.create('div', { class: 'flex items-start justify-between gap-4' });
  DomHelper.append(row, info, buttons);

  const item = DomHelper.create('div', {
    class: 'border border-gray-200 rounded-lg p-4 mb-3 hover:bg-gray-50',
  });
  item.appendChild(row);
  return item;
}

function updateBackupList(backups: Backup[]): void {
  const container = document.getElementById('backup-list');
  if (!container) return;

  DomHelper.clear(container);

  if (backups.length === 0) {
    container.appendChild(
      DomHelper.create(
        'p',
        { class: 'text-gray-600 text-center py-8' },
        'No backups found. Create your first backup above.'
      )
    );
    updateBackupStats({ count: 0, totalSize: '0 B', oldestDate: 'N/A', newestDate: 'N/A' });
    return;
  }

  backups.forEach((backup) => container.appendChild(buildBackupItem(backup)));

  const totalBytes = backups.reduce((sum, b) => sum + (b.sizeBytes ?? 0), 0);
  updateBackupStats({
    count: backups.length,
    totalSize: formatBytes(totalBytes),
    oldestDate: backups[backups.length - 1]?.timestamp ?? 'N/A',
    newestDate: backups[0]?.timestamp ?? 'N/A',
  });
}

async function loadBackups(): Promise<void> {
  try {
    const response = await listApi.get('/_dev/api/backup/list');
    const result = (await response.json()) as BackupListResponse;

    if (result.success === true) {
      updateBackupList(result.backups ?? []);
    } else {
      showNotification('error', 'Failed to load backups');
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    showNotification('error', `Failed to load backups: ${message}`);
  }
}

async function createBackup(): Promise<void> {
  const retentionInput = document.getElementById('retention-input');
  const encryptInput = document.getElementById('encrypt-input');
  const retention = retentionInput instanceof HTMLInputElement ? retentionInput.value : '';
  const encrypt = encryptInput instanceof HTMLInputElement && encryptInput.checked;
  const btn = document.getElementById('btn-create-backup');
  if (!(btn instanceof HTMLButtonElement)) return;

  const confirmMsg = encrypt
    ? 'Create an encrypted database backup? This may take a few moments.\n\nNote: You will need BACKUP_ENCRYPTION_KEY to restore this backup.'
    : 'Create an unencrypted database backup? This may take a few moments.';

  if (!confirm(confirmMsg)) return;

  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Creating...';

  try {
    const params = new URLSearchParams();
    if (retention) params.append('retention', retention);
    if (encrypt) params.append('encrypt', 'true');

    const url = params.toString()
      ? `/_dev/api/backup/create?${params.toString()}`
      : '/_dev/api/backup/create';
    const response = await backupApi.post(url);
    const result = (await response.json()) as BackupActionResponse;

    if (result.success === true) {
      showNotification('success', result.message ?? 'Backup created');
      void loadBackups();
    } else {
      showNotification('error', result.message ?? 'Failed to create backup');
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    showNotification('error', `Failed to create backup: ${message}`);
  } finally {
    btn.disabled = false;
    btn.textContent = originalText;
  }
}

async function restoreBackup(filename: string, btn: HTMLButtonElement): Promise<void> {
  const confirmed = confirm(
    '⚠️ WARNING: This will OVERWRITE the current database!\n\n' +
      `Restoring: ${filename}\n\n` +
      'This action cannot be undone. Continue?'
  );
  if (!confirmed) return;

  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Restoring...';

  try {
    const response = await backupApi.post(
      '/_dev/api/backup/restore',
      JSON.stringify({ filename }),
      {
        headers: { 'Content-Type': 'application/json' },
      }
    );
    const result = (await response.json()) as BackupActionResponse;

    if (result.success === true) {
      showNotification('success', result.message ?? 'Backup restored');
    } else {
      showNotification('error', result.message ?? 'Failed to restore backup');
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    showNotification('error', `Failed to restore backup: ${message}`);
  } finally {
    btn.disabled = false;
    btn.textContent = originalText;
  }
}

async function deleteBackup(filename: string): Promise<void> {
  if (!confirm(`Delete backup: ${filename}?\n\nThis action cannot be undone.`)) return;

  try {
    const response = await listApi.post('/_dev/api/backup/delete', JSON.stringify({ filename }), {
      headers: { 'Content-Type': 'application/json' },
    });
    const result = (await response.json()) as BackupActionResponse;

    if (result.success === true) {
      showNotification('success', result.message ?? 'Backup deleted');
      void loadBackups();
    } else {
      showNotification('error', result.message ?? 'Failed to delete backup');
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    showNotification('error', `Failed to delete backup: ${message}`);
  }
}

function init(): void {
  // Load the backup list lazily when its tab is shown (also covers the
  // initial hash restore).
  initTabs((tabId) => {
    if (tabId === 'backups') void loadBackups();
  });

  // Event delegation for data-action clicks (CSP-compliant)
  document.addEventListener('click', (e) => {
    const target =
      e.target instanceof Element ? e.target.closest<HTMLElement>('[data-action]') : null;
    if (!target) return;

    switch (target.dataset.action) {
      case 'create-backup':
        void createBackup();
        break;
      case 'load-backups':
        void loadBackups();
        break;
      case 'copy-cmd':
        if (target.dataset.cmd !== undefined) void copyCmd(target.dataset.cmd, target);
        break;
      case 'restore-backup':
        if (target.dataset.filename !== undefined && target instanceof HTMLButtonElement) {
          void restoreBackup(target.dataset.filename, target);
        }
        break;
      case 'delete-backup':
        if (target.dataset.filename !== undefined) void deleteBackup(target.dataset.filename);
        break;
    }
  });
}

init();

// Exported for unit tests (happy-dom); the page itself only needs init()
export {
  showNotification,
  formatBytes,
  updateBackupList,
  loadBackups,
  createBackup,
  restoreBackup,
  deleteBackup,
};
