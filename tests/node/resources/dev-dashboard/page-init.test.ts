// @vitest-environment happy-dom
// @vitest-environment-options {"url": "https://localhost:8443/_dev/database"}
/**
 * Import-time wiring tests for DevDashboard page modules
 *
 * Some listeners bind to concrete elements at module import time (initTabs
 * nav links, the logs line-count select), so these tests build the DOM
 * first and then import the module fresh — unlike the regular page tests,
 * which import statically before any DOM exists.
 */

import { describe, it, expect, vi } from 'vitest';

function jsonResponse(body: unknown): Response {
  return new Response(JSON.stringify(body), {
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('DevDashboard page init wiring', () => {
  it('database: loads backups when the backups tab is opened', async () => {
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
      <div id="backup-list"></div>
    `;
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ success: true, backups: [] }));
    vi.stubGlobal('fetch', fetchMock);

    await import('@resources/js/dev-dashboard/database');

    document
      .querySelector<HTMLElement>('[data-tab="backups"]')
      ?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    await vi.waitFor(() => {
      expect(fetchMock).toHaveBeenCalled();
    });
    expect(String(fetchMock.mock.calls[0]?.[0])).toContain('/_dev/api/backup/list');
    expect(document.getElementById('tab-backups')?.classList.contains('hidden')).toBe(false);
  });

  it('logs: reloads the current log when the line count changes', async () => {
    document.body.innerHTML = `
      <div id="logModal" style="display: none;">
        <h3 id="logModalTitle"></h3>
        <p id="logModalMeta"></p>
        <select id="logLinesSelect"><option value="100" selected>100</option></select>
        <pre id="logModalContent"></pre>
      </div>
    `;
    const fetchMock = vi
      .fn()
      .mockResolvedValue(jsonResponse({ content: 'x', size: 1, modified: 0, lines: 100 }));
    vi.stubGlobal('fetch', fetchMock);

    const { openLogModal } = await import('@resources/js/dev-dashboard/logs');

    openLogModal('app.log');
    document.getElementById('logLinesSelect')?.dispatchEvent(new Event('change'));

    await vi.waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(2);
    });
  });
});
