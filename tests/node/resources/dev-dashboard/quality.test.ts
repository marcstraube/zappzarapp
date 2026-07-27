// @vitest-environment happy-dom
// @vitest-environment-options {"url": "https://localhost:8443/_dev/quality"}
/**
 * Tests for the DevDashboard quality page module
 *
 * Runs in happy-dom; covers the coverage generation buttons (fetch mocked).
 * Tab switching and copy handling are covered by the shared module tests.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { generateCoverage, generateNodeCoverage } from '@resources/js/dev-dashboard/quality';

function jsonResponse(body: unknown): Response {
  return new Response(JSON.stringify(body), {
    headers: { 'Content-Type': 'application/json' },
  });
}

function setupDom(): void {
  document.body.innerHTML = `
    <button id="btn-coverage-php" class="btn btn-secondary"
            data-action="generate-coverage" data-type="php">Generate</button>
    <button id="btn-coverage-node" class="btn btn-secondary"
            data-action="generate-node-coverage">Generate</button>
    <button id="btn-copy" data-action="copy-cmd" data-cmd="make check">Copy</button>
  `;
}

function click(id: string): void {
  document
    .getElementById(id)
    ?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
}

describe('DevDashboard quality page', () => {
  beforeEach(() => {
    setupDom();
    vi.stubGlobal('alert', vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  describe('generateCoverage', () => {
    it('POSTs the coverage endpoint and shows Done! on success', async () => {
      vi.useFakeTimers();
      try {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ success: true }));
        vi.stubGlobal('fetch', fetchMock);

        const result = await generateCoverage('php');

        expect(result).toBe(true);
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
          '/_dev/api/coverage/generate?type=php'
        );
        expect(document.getElementById('btn-coverage-php')?.textContent).toBe('Done!');
      } finally {
        vi.clearAllTimers();
        vi.useRealTimers();
      }
    });

    it('returns false when the button does not exist', async () => {
      const result = await generateCoverage('unknown');

      expect(result).toBe(false);
    });
  });

  describe('missing buttons', () => {
    it('generateNodeCoverage bails out without its button', async () => {
      document.body.innerHTML = '';

      expect(await generateNodeCoverage()).toBe(false);
    });
  });

  describe('generateNodeCoverage', () => {
    it('POSTs the node coverage endpoint and alerts on failure', async () => {
      vi.useFakeTimers();
      try {
        const fetchMock = vi
          .fn()
          .mockResolvedValue(jsonResponse({ success: false, message: 'vitest failed' }));
        vi.stubGlobal('fetch', fetchMock);

        const result = await generateNodeCoverage();

        expect(result).toBe(false);
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
          '/dev-dashboard/node/coverage/generate'
        );
        expect(alert).toHaveBeenCalledWith('Error: vitest failed');
        expect(document.getElementById('btn-coverage-node')?.textContent).toBe('Failed');
      } finally {
        vi.clearAllTimers();
        vi.useRealTimers();
      }
    });
  });

  describe('event delegation', () => {
    it('starts PHP coverage generation when a generate-coverage element is clicked', async () => {
      vi.useFakeTimers();
      try {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ success: true }));
        vi.stubGlobal('fetch', fetchMock);

        click('btn-coverage-php');

        await vi.waitFor(() => {
          expect(document.getElementById('btn-coverage-php')?.textContent).toBe('Done!');
        });
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain('/_dev/api/coverage/generate');
      } finally {
        vi.clearAllTimers();
        vi.useRealTimers();
      }
    });

    it('starts Node coverage generation when a generate-node-coverage element is clicked', async () => {
      vi.useFakeTimers();
      try {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ success: true }));
        vi.stubGlobal('fetch', fetchMock);

        click('btn-coverage-node');

        await vi.waitFor(() => {
          expect(document.getElementById('btn-coverage-node')?.textContent).toBe('Done!');
        });
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
          '/dev-dashboard/node/coverage/generate'
        );
      } finally {
        vi.clearAllTimers();
        vi.useRealTimers();
      }
    });

    it('copies the command when a copy-cmd element is clicked', async () => {
      const writeText = vi.fn().mockResolvedValue(undefined);
      vi.stubGlobal('navigator', { clipboard: { writeText } });

      click('btn-copy');

      await vi.waitFor(() => {
        expect(writeText).toHaveBeenCalledWith('make check');
      });
    });
  });
});
