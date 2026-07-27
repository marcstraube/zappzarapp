// @vitest-environment happy-dom
// @vitest-environment-options {"url": "https://localhost:8443/_dev/logs"}
/**
 * Tests for the DevDashboard logs page module
 *
 * Runs in happy-dom; covers tab switching, clipboard copy feedback,
 * modal open/close and the log API rendering (fetch mocked).
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
  switchTab,
  copyCmd,
  openLogModal,
  closeLogModal,
  formatModified,
  loadLogContent,
} from '@resources/js/dev-dashboard/logs';

function setupDom(): void {
  document.body.innerHTML = `
    <nav>
      <a href="#" class="sub-nav-link active" data-tab="overview">Overview</a>
      <a href="#" class="sub-nav-link" data-tab="sources">Sources</a>
    </nav>
    <div id="tab-overview" class="tab-panel"></div>
    <div id="tab-sources" class="tab-panel hidden"></div>
    <div id="logModal" style="display: none;">
      <h3 id="logModalTitle"></h3>
      <p id="logModalMeta"></p>
      <select id="logLinesSelect"><option value="100" selected>100</option></select>
      <pre id="logModalContent"></pre>
    </div>
    <button id="copyBtn" data-action="copy-cmd" data-cmd="make logs">Copy</button>
    <button id="openBtn" data-action="open-log" data-logfile="app.log">app.log</button>
    <button id="closeBtn" data-action="close-log">Close</button>
    <button id="refreshBtn" data-action="refresh-log">Refresh</button>
  `;
}

function click(id: string): void {
  document
    .getElementById(id)
    ?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
}

function jsonResponse(body: unknown): Response {
  return new Response(JSON.stringify(body), {
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('DevDashboard logs page', () => {
  beforeEach(() => {
    setupDom();
  });

  describe('switchTab', () => {
    it('activates the requested tab and hides the others', () => {
      switchTab('sources');

      expect(document.querySelector('[data-tab="sources"]')?.classList.contains('active')).toBe(
        true
      );
      expect(document.querySelector('[data-tab="overview"]')?.classList.contains('active')).toBe(
        false
      );
      expect(document.getElementById('tab-sources')?.classList.contains('hidden')).toBe(false);
      expect(document.getElementById('tab-overview')?.classList.contains('hidden')).toBe(true);
    });
  });

  describe('copyCmd', () => {
    it('shows success feedback when the clipboard write succeeds', async () => {
      vi.stubGlobal('navigator', {
        clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
      });
      const btn = document.createElement('button');
      btn.textContent = 'Copy';
      document.body.appendChild(btn);

      await copyCmd('make logs', btn);

      expect(btn.textContent).toBe('Copied!');
      expect(btn.classList.contains('bg-green-100')).toBe(true);
    });
  });

  describe('modal', () => {
    it('openLogModal shows the modal with the filename', () => {
      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(
          new Response(JSON.stringify({ content: 'x', size: 10, modified: 0, lines: 100 }), {
            headers: { 'Content-Type': 'application/json' },
          })
        )
      );

      openLogModal('app.log');

      expect(document.getElementById('logModal')?.style.display).toBe('block');
      expect(document.getElementById('logModalTitle')?.textContent).toBe('app.log');
      expect(document.body.style.overflow).toBe('hidden');
    });

    it('closeLogModal hides the modal and restores scrolling', () => {
      const modal = document.getElementById('logModal');
      if (modal) modal.style.display = 'block';
      document.body.style.overflow = 'hidden';

      closeLogModal();

      expect(document.getElementById('logModal')?.style.display).toBe('none');
      expect(document.body.style.overflow).toBe('');
    });
  });

  describe('loadLogContent', () => {
    it('renders log content and metadata on success', async () => {
      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(
          new Response(
            JSON.stringify({
              content: 'line 1\nline 2',
              size: 2048,
              modified: 1_700_000_000,
              lines: 100,
            }),
            { headers: { 'Content-Type': 'application/json' } }
          )
        )
      );

      await loadLogContent('app.log');

      expect(document.getElementById('logModalContent')?.textContent).toBe('line 1\nline 2');
      const meta = document.getElementById('logModalMeta')?.textContent ?? '';
      expect(meta).toContain('2.0 KB');
      expect(meta).toContain('100 lines');
    });

    it('renders (empty file) and fallback metadata for a minimal response', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ content: '' })));

      await loadLogContent('app.log');

      expect(document.getElementById('logModalContent')?.textContent).toBe('(empty file)');
      const meta = document.getElementById('logModalMeta')?.textContent ?? '';
      expect(meta).toContain('0.0 KB');
      expect(meta).toContain('100 lines');
    });

    it('renders an API error message', async () => {
      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(
          new Response(JSON.stringify({ error: 'File not found' }), {
            headers: { 'Content-Type': 'application/json' },
          })
        )
      );

      await loadLogContent('missing.log');

      expect(document.getElementById('logModalContent')?.textContent).toBe('Error: File not found');
    });

    it('renders a failure message when the request throws', async () => {
      vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('boom')));

      await loadLogContent('app.log');

      expect(document.getElementById('logModalContent')?.textContent).toContain(
        'Failed to load log'
      );
    });
  });

  describe('formatModified', () => {
    it('formats a unix timestamp into a locale string', () => {
      const formatted = formatModified(1_700_000_000);

      expect(formatted.length).toBeGreaterThan(8);
      expect(formatted).toContain('2023');
    });
  });

  describe('event delegation', () => {
    it('opens the log modal when an open-log element is clicked', () => {
      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse({ content: 'x', size: 1, modified: 0, lines: 100 }))
      );

      click('openBtn');

      expect(document.getElementById('logModal')?.style.display).toBe('block');
      expect(document.getElementById('logModalTitle')?.textContent).toBe('app.log');
    });

    it('closes the log modal when a close-log element is clicked', () => {
      const modal = document.getElementById('logModal');
      if (modal) modal.style.display = 'block';

      click('closeBtn');

      expect(document.getElementById('logModal')?.style.display).toBe('none');
    });

    it('copies the command when a copy-cmd element is clicked', async () => {
      const writeText = vi.fn().mockResolvedValue(undefined);
      vi.stubGlobal('navigator', { clipboard: { writeText } });

      click('copyBtn');
      await vi.waitFor(() => {
        expect(document.getElementById('copyBtn')?.textContent).toBe('Copied!');
      });

      expect(writeText).toHaveBeenCalledWith('make logs');
    });

    // The line-count select change is covered in page-init.test.ts: its
    // listener binds to the select element at import time.
    it('reloads the current log when a refresh-log element is clicked', async () => {
      const fetchMock = vi
        .fn()
        .mockResolvedValue(jsonResponse({ content: 'x', size: 1, modified: 0, lines: 100 }));
      vi.stubGlobal('fetch', fetchMock);

      click('openBtn');
      click('refreshBtn');

      await vi.waitFor(() => {
        expect(fetchMock).toHaveBeenCalledTimes(2);
      });
    });

    it('ignores clicks outside data-action elements', () => {
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);

      document.body.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

      expect(fetchMock).not.toHaveBeenCalled();
      expect(document.getElementById('logModal')?.style.display).toBe('none');
    });

    it('closes the modal on Escape', () => {
      const modal = document.getElementById('logModal');
      if (modal) modal.style.display = 'block';

      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

      expect(document.getElementById('logModal')?.style.display).toBe('none');
    });
  });
});
