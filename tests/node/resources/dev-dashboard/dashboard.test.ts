// @vitest-environment happy-dom
// @vitest-environment-options {"url": "https://localhost:8443/_dev/"}
/**
 * Tests for the DevDashboard overview page module
 *
 * Runs in happy-dom; covers the docs regeneration buttons (fetch mocked).
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
  regenerateDocs,
  regenerateNodeDocs,
  regenerateAllDocs,
} from '@resources/js/dev-dashboard/dashboard';

function jsonResponse(body: unknown): Response {
  return new Response(JSON.stringify(body), {
    headers: { 'Content-Type': 'application/json' },
  });
}

function setupDom(): void {
  document.body.innerHTML = `
    <button id="btn-regen-php-docs" class="btn btn-secondary"
            data-action="regenerate-docs" data-type="php">Regenerate PHP</button>
    <button id="btn-regen-node-docs" class="btn btn-secondary"
            data-action="regenerate-node-docs">Regenerate Node</button>
    <button id="btn-regen-all-docs" class="btn btn-primary"
            data-action="regenerate-all-docs">Regenerate All</button>
  `;
}

function click(id: string): void {
  document
    .getElementById(id)
    ?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
}

describe('DevDashboard overview page', () => {
  beforeEach(() => {
    setupDom();
    vi.stubGlobal('alert', vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  describe('regenerateDocs', () => {
    it('POSTs the docs endpoint and shows Done! on success', async () => {
      const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ success: true }));
      vi.stubGlobal('fetch', fetchMock);

      const result = await regenerateDocs('php', true);

      expect(result).toBe(true);
      expect(String(fetchMock.mock.calls[0]?.[0])).toContain('/_dev/api/docs/generate?type=php');
      expect(document.getElementById('btn-regen-php-docs')?.textContent).toBe('Done!');
    });

    it('returns false when the button does not exist', async () => {
      const result = await regenerateDocs('unknown', true);

      expect(result).toBe(false);
    });
  });

  describe('missing buttons', () => {
    it('all regenerate functions bail out without their buttons', async () => {
      document.body.innerHTML = '';

      expect(await regenerateNodeDocs(true)).toBe(false);
      await expect(regenerateAllDocs()).resolves.toBeUndefined();
    });
  });

  describe('regenerateNodeDocs', () => {
    it('alerts with the command output on failure', async () => {
      vi.stubGlobal(
        'fetch',
        vi
          .fn()
          .mockResolvedValue(
            jsonResponse({ success: false, message: 'tsc failed', output: 'error TS2304' })
          )
      );

      const result = await regenerateNodeDocs(true);

      expect(result).toBe(false);
      expect(alert).toHaveBeenCalledWith('Error: tsc failed\n\nerror TS2304');
      expect(document.getElementById('btn-regen-node-docs')?.textContent).toBe('Failed');
    });
  });

  describe('regenerateAllDocs', () => {
    it('shows Done! on the all-button when at least one regeneration succeeds', async () => {
      vi.useFakeTimers();
      try {
        vi.stubGlobal(
          'fetch',
          vi
            .fn()
            .mockResolvedValueOnce(jsonResponse({ success: true }))
            .mockResolvedValueOnce(jsonResponse({ success: false, message: 'nope' }))
        );

        await regenerateAllDocs();

        const btn = document.getElementById('btn-regen-all-docs');
        expect(btn?.textContent).toBe('Done!');
        expect(btn?.classList.contains('btn-success')).toBe(true);
      } finally {
        vi.clearAllTimers();
        vi.useRealTimers();
      }
    });

    it('starts PHP docs regeneration when a regenerate-docs element is clicked', async () => {
      vi.useFakeTimers();
      try {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ success: true })));

        click('btn-regen-php-docs');

        await vi.waitFor(() => {
          expect(document.getElementById('btn-regen-php-docs')?.textContent).toBe('Done!');
        });
      } finally {
        vi.clearAllTimers();
        vi.useRealTimers();
      }
    });

    it('starts Node docs regeneration when a regenerate-node-docs element is clicked', async () => {
      vi.useFakeTimers();
      try {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ success: true })));

        click('btn-regen-node-docs');

        await vi.waitFor(() => {
          expect(document.getElementById('btn-regen-node-docs')?.textContent).toBe('Done!');
        });
      } finally {
        vi.clearAllTimers();
        vi.useRealTimers();
      }
    });

    it('runs both regenerations when a regenerate-all-docs element is clicked', async () => {
      vi.useFakeTimers();
      try {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ success: true }));
        vi.stubGlobal('fetch', fetchMock);

        click('btn-regen-all-docs');
        await vi.waitFor(() => {
          expect(document.getElementById('btn-regen-all-docs')?.textContent).toBe('Done!');
        });

        expect(fetchMock).toHaveBeenCalledTimes(2);
      } finally {
        vi.clearAllTimers();
        vi.useRealTimers();
      }
    });

    it('resets the all-button when both regenerations fail', async () => {
      vi.useFakeTimers();
      try {
        vi.stubGlobal(
          'fetch',
          vi.fn().mockResolvedValue(jsonResponse({ success: false, message: 'nope' }))
        );

        await regenerateAllDocs();

        const btn = document.getElementById('btn-regen-all-docs') as HTMLButtonElement;
        expect(btn.textContent).toBe('Regenerate All');
        expect(btn.disabled).toBe(false);
      } finally {
        vi.clearAllTimers();
        vi.useRealTimers();
      }
    });
  });
});
