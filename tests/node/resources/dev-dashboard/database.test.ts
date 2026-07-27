// @vitest-environment happy-dom
// @vitest-environment-options {"url": "https://localhost:8443/_dev/database"}
/**
 * Tests for the DevDashboard database page module
 *
 * Runs in happy-dom; covers backup list rendering (DOM creation, no
 * innerHTML), the backup API actions (fetch mocked) and notifications.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
  showNotification,
  formatBytes,
  updateBackupList,
  loadBackups,
  createBackup,
  restoreBackup,
  deleteBackup,
} from '@resources/js/dev-dashboard/database';

function jsonResponse(body: unknown): Response {
  return new Response(JSON.stringify(body), {
    headers: { 'Content-Type': 'application/json' },
  });
}

const backup = {
  filename: 'backup_2026-07-27.sql.gz',
  timestamp: '2026-07-27 10:00',
  size: '2.0 KB',
  sizeBytes: 2048,
  age: '2 hours ago',
  encrypted: true,
  dbType: 'postgres',
  dbName: 'app',
};

function setupDom(): void {
  document.body.innerHTML = `
    <nav>
      <a href="#" class="sub-nav-link active" data-tab="overview">Overview</a>
      <a href="#" class="sub-nav-link" data-tab="backups">Backups</a>
    </nav>
    <div id="tab-overview" class="tab-panel"></div>
    <div id="tab-backups" class="tab-panel hidden"></div>
    <p id="stat-count">0</p>
    <p id="stat-size">0 B</p>
    <p id="stat-newest">-</p>
    <p id="stat-oldest">-</p>
    <input type="number" id="retention-input" value="30">
    <input type="checkbox" id="encrypt-input">
    <button id="btn-create-backup" data-action="create-backup">Create Backup Now</button>
    <button id="btn-load-backups" data-action="load-backups">Refresh</button>
    <button id="btn-copy" data-action="copy-cmd" data-cmd="make db-tools-up">Copy</button>
    <div id="backup-list"></div>
  `;
}

function click(el: Element | null): void {
  el?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
}

describe('DevDashboard database page', () => {
  beforeEach(() => {
    setupDom();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  describe('formatBytes', () => {
    it('formats byte counts with two decimals', () => {
      expect(formatBytes(0)).toBe('0.00 B');
      expect(formatBytes(2048)).toBe('2.00 KB');
      expect(formatBytes(5 * 1024 * 1024)).toBe('5.00 MB');
    });
  });

  describe('showNotification', () => {
    it('renders a success notification with the message as text', () => {
      showNotification('success', 'Backup created');

      const el = document.querySelector('.dev-notification--success');
      expect(el?.textContent).toBe('Backup created');
    });
  });

  describe('updateBackupList', () => {
    it('renders backup entries with restore/delete buttons and updates stats', () => {
      updateBackupList([backup]);

      const item = document.querySelector('#backup-list h3');
      expect(item?.textContent).toBe(backup.filename);
      expect(document.querySelector('#backup-list .badge-green')?.textContent).toBe('Encrypted');

      const restoreBtn = document.querySelector<HTMLElement>('[data-action="restore-backup"]');
      expect(restoreBtn?.dataset.filename).toBe(backup.filename);
      expect(document.querySelector('[data-action="delete-backup"]')).not.toBeNull();

      expect(document.getElementById('stat-count')?.textContent).toBe('1');
      expect(document.getElementById('stat-size')?.textContent).toBe('2.00 KB');
      expect(document.getElementById('stat-newest')?.textContent).toBe(backup.timestamp);
    });

    it('renders untrusted filenames as text, not markup', () => {
      updateBackupList([{ ...backup, filename: '<img src=x onerror=alert(1)>' }]);

      expect(document.querySelector('#backup-list img')).toBeNull();
      expect(document.querySelector('#backup-list h3')?.textContent).toBe(
        '<img src=x onerror=alert(1)>'
      );
    });

    it('renders unencrypted backups and treats a missing sizeBytes as zero', () => {
      updateBackupList([{ ...backup, encrypted: false, sizeBytes: undefined }]);

      expect(document.querySelector('#backup-list .badge-gray')?.textContent).toBe('Unencrypted');
      expect(document.getElementById('stat-size')?.textContent).toBe('0.00 B');
    });

    it('shows an empty state and zeroed stats without backups', () => {
      updateBackupList([]);

      expect(document.getElementById('backup-list')?.textContent).toContain('No backups found');
      expect(document.getElementById('stat-count')?.textContent).toBe('0');
      expect(document.getElementById('stat-size')?.textContent).toBe('0 B');
    });
  });

  describe('loadBackups', () => {
    it('renders the list returned by the API', async () => {
      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse({ success: true, backups: [backup] }))
      );

      await loadBackups();

      expect(document.querySelector('#backup-list h3')?.textContent).toBe(backup.filename);
    });

    it('shows an error notification when the API reports failure', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ success: false })));

      await loadBackups();

      expect(document.querySelector('.dev-notification--error')?.textContent).toBe(
        'Failed to load backups'
      );
    });

    it('shows an error notification when the request throws', async () => {
      vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('boom')));

      await loadBackups();

      expect(document.querySelector('.dev-notification--error')?.textContent).toContain(
        'Failed to load backups'
      );
    });
  });

  describe('createBackup', () => {
    it('does nothing when the user cancels the confirmation', async () => {
      vi.stubGlobal('confirm', vi.fn().mockReturnValue(false));
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);

      await createBackup();

      expect(fetchMock).not.toHaveBeenCalled();
    });

    it('POSTs with retention and encrypt params and notifies on success', async () => {
      vi.stubGlobal('confirm', vi.fn().mockReturnValue(true));
      const encryptInput = document.getElementById('encrypt-input') as HTMLInputElement;
      encryptInput.checked = true;
      const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse({ success: true, message: 'Backup created' }))
        .mockResolvedValue(jsonResponse({ success: true, backups: [] }));
      vi.stubGlobal('fetch', fetchMock);

      await createBackup();

      const url = String(fetchMock.mock.calls[0]?.[0]);
      expect(url).toContain('/_dev/api/backup/create');
      expect(url).toContain('retention=30');
      expect(url).toContain('encrypt=true');
      expect(document.querySelector('.dev-notification--success')?.textContent).toBe(
        'Backup created'
      );
    });
  });

  describe('restoreBackup', () => {
    it('POSTs the filename and re-enables the button afterwards', async () => {
      vi.stubGlobal('confirm', vi.fn().mockReturnValue(true));
      const fetchMock = vi
        .fn()
        .mockResolvedValue(jsonResponse({ success: true, message: 'Restored' }));
      vi.stubGlobal('fetch', fetchMock);
      const btn = document.createElement('button');
      btn.textContent = 'Restore';
      document.body.appendChild(btn);

      await restoreBackup(backup.filename, btn);

      const [url, init] = fetchMock.mock.calls[0] as [unknown, { body: string }];
      expect(String(url)).toContain('/_dev/api/backup/restore');
      expect(init.body).toContain(backup.filename);
      expect(btn.disabled).toBe(false);
      expect(btn.textContent).toBe('Restore');
      expect(document.querySelector('.dev-notification--success')?.textContent).toBe('Restored');
    });
  });

  describe('deleteBackup', () => {
    it('POSTs the filename and refreshes the list on success', async () => {
      vi.stubGlobal('confirm', vi.fn().mockReturnValue(true));
      const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse({ success: true, message: 'Deleted' }))
        .mockResolvedValue(jsonResponse({ success: true, backups: [] }));
      vi.stubGlobal('fetch', fetchMock);

      await deleteBackup(backup.filename);

      const [url, init] = fetchMock.mock.calls[0] as [unknown, { body: string }];
      expect(String(url)).toContain('/_dev/api/backup/delete');
      expect(init.body).toContain(backup.filename);
      expect(document.querySelector('.dev-notification--success')?.textContent).toBe('Deleted');
    });

    it('shows an error notification when the API reports failure', async () => {
      vi.stubGlobal('confirm', vi.fn().mockReturnValue(true));
      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse({ success: false, message: 'Nope' }))
      );

      await deleteBackup(backup.filename);

      expect(document.querySelector('.dev-notification--error')?.textContent).toBe('Nope');
    });
  });

  describe('createBackup defaults', () => {
    it('POSTs without params and falls back to a default success message', async () => {
      vi.stubGlobal('confirm', vi.fn().mockReturnValue(true));
      const retentionInput = document.getElementById('retention-input') as HTMLInputElement;
      retentionInput.value = '';
      const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse({ success: true }))
        .mockResolvedValue(jsonResponse({ success: true, backups: [] }));
      vi.stubGlobal('fetch', fetchMock);

      await createBackup();

      expect(String(fetchMock.mock.calls[0]?.[0])).toMatch(/\/_dev\/api\/backup\/create$/);
      expect(document.querySelector('.dev-notification--success')?.textContent).toBe(
        'Backup created'
      );
    });
  });

  describe('error paths', () => {
    it('createBackup shows an error notification when the request throws', async () => {
      vi.stubGlobal('confirm', vi.fn().mockReturnValue(true));
      vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('boom')));

      await createBackup();

      expect(document.querySelector('.dev-notification--error')?.textContent).toContain(
        'Failed to create backup'
      );
    });

    it('restoreBackup shows an error notification when the API reports failure', async () => {
      vi.stubGlobal('confirm', vi.fn().mockReturnValue(true));
      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse({ success: false, message: 'Bad dump' }))
      );
      const btn = document.createElement('button');
      document.body.appendChild(btn);

      await restoreBackup(backup.filename, btn);

      expect(document.querySelector('.dev-notification--error')?.textContent).toBe('Bad dump');
    });

    it('restoreBackup shows an error notification when the request throws', async () => {
      vi.stubGlobal('confirm', vi.fn().mockReturnValue(true));
      vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('boom')));
      const btn = document.createElement('button');
      document.body.appendChild(btn);

      await restoreBackup(backup.filename, btn);

      expect(document.querySelector('.dev-notification--error')?.textContent).toContain(
        'Failed to restore backup'
      );
    });

    it('deleteBackup shows an error notification when the request throws', async () => {
      vi.stubGlobal('confirm', vi.fn().mockReturnValue(true));
      vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('boom')));

      await deleteBackup(backup.filename);

      expect(document.querySelector('.dev-notification--error')?.textContent).toContain(
        'Failed to delete backup'
      );
    });
  });

  describe('event delegation', () => {
    // The lazy tab load is covered in page-init.test.ts: initTabs() binds to
    // the nav links at import time, so it needs the DOM before a fresh import.
    it('loads backups when a load-backups element is clicked', async () => {
      const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ success: true, backups: [] }));
      vi.stubGlobal('fetch', fetchMock);

      click(document.getElementById('btn-load-backups'));

      await vi.waitFor(() => {
        expect(fetchMock).toHaveBeenCalled();
      });
    });

    it('asks for confirmation when a create-backup element is clicked', () => {
      const confirmMock = vi.fn().mockReturnValue(false);
      vi.stubGlobal('confirm', confirmMock);
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);

      click(document.getElementById('btn-create-backup'));

      expect(confirmMock).toHaveBeenCalled();
      expect(fetchMock).not.toHaveBeenCalled();
    });

    it('asks for confirmation when rendered restore/delete buttons are clicked', () => {
      const confirmMock = vi.fn().mockReturnValue(false);
      vi.stubGlobal('confirm', confirmMock);
      vi.stubGlobal('fetch', vi.fn());
      updateBackupList([backup]);

      click(document.querySelector('[data-action="restore-backup"]'));
      click(document.querySelector('[data-action="delete-backup"]'));

      expect(confirmMock).toHaveBeenCalledTimes(2);
      expect(confirmMock.mock.calls[0]?.[0]).toContain('OVERWRITE');
      expect(confirmMock.mock.calls[1]?.[0]).toContain('Delete backup');
    });

    it('copies the command when a copy-cmd element is clicked', async () => {
      const writeText = vi.fn().mockResolvedValue(undefined);
      vi.stubGlobal('navigator', { clipboard: { writeText } });

      click(document.getElementById('btn-copy'));

      await vi.waitFor(() => {
        expect(writeText).toHaveBeenCalledWith('make db-tools-up');
      });
    });
  });
});
