// @vitest-environment happy-dom
// @vitest-environment-options {"url": "https://localhost:8443/_dev/quality"}
/**
 * Tests for the shared DevDashboard button-driven POST action
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { runButtonAction } from '@resources/js/dev-dashboard/shared/action-button';
import { createDashboardApi } from '@resources/js/dev-dashboard/shared/api';

function jsonResponse(body: unknown): Response {
  return new Response(JSON.stringify(body), {
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('shared runButtonAction', () => {
  let btn: HTMLButtonElement;
  const api = createDashboardApi();

  beforeEach(() => {
    document.body.innerHTML = '<button id="btn" class="btn btn-secondary">Go</button>';
    btn = document.getElementById('btn') as HTMLButtonElement;
    vi.stubGlobal('alert', vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('shows Done! and returns true on success', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ success: true })));

    const result = await runButtonAction(api, btn, '/_dev/api/x', {
      busyLabel: 'Busy...',
      reload: false,
    });

    expect(result).toBe(true);
    expect(btn.textContent).toBe('Done!');
    expect(btn.classList.contains('btn-success')).toBe(true);
    expect(btn.classList.contains('btn-secondary')).toBe(false);
  });

  it('shows the busy label while the request is running', async () => {
    let resolveFetch: (r: Response) => void = () => undefined;
    vi.stubGlobal(
      'fetch',
      vi.fn().mockReturnValue(
        new Promise<Response>((resolve) => {
          resolveFetch = resolve;
        })
      )
    );

    const pending = runButtonAction(api, btn, '/_dev/api/x', {
      busyLabel: 'Busy...',
      reload: false,
    });

    expect(btn.disabled).toBe(true);
    expect(btn.textContent).toBe('Busy...');

    resolveFetch(jsonResponse({ success: true }));
    await pending;
  });

  it('alerts with message and output on API failure, then resets the button', async () => {
    vi.useFakeTimers();
    try {
      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse({ success: false, message: 'nope', output: 'log' }))
      );

      const result = await runButtonAction(api, btn, '/_dev/api/x', { busyLabel: 'Busy...' });

      expect(result).toBe(false);
      expect(btn.textContent).toBe('Failed');
      expect(btn.classList.contains('btn-danger')).toBe(true);
      expect(alert).toHaveBeenCalledWith('Error: nope\n\nlog');

      vi.advanceTimersByTime(2000);

      expect(btn.textContent).toBe('Go');
      expect(btn.disabled).toBe(false);
      expect(btn.classList.contains('btn-secondary')).toBe(true);
      expect(btn.classList.contains('btn-danger')).toBe(false);
    } finally {
      vi.useRealTimers();
    }
  });

  it('swaps a custom idle class and schedules a reload on success', async () => {
    vi.useFakeTimers();
    try {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ success: true })));
      btn.classList.remove('btn-secondary');
      btn.classList.add('btn-primary');

      const result = await runButtonAction(api, btn, '/_dev/api/x', {
        busyLabel: 'Busy...',
        idleClass: 'btn-primary',
      });

      expect(result).toBe(true);
      expect(btn.classList.contains('btn-primary')).toBe(false);
      expect(btn.classList.contains('btn-success')).toBe(true);
      expect(vi.getTimerCount()).toBeGreaterThan(0);
    } finally {
      vi.clearAllTimers();
      vi.useRealTimers();
    }
  });

  it('omits the output block when the API failure has no output', async () => {
    vi.useFakeTimers();
    try {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ success: false })));

      const result = await runButtonAction(api, btn, '/_dev/api/x', { busyLabel: 'Busy...' });

      expect(result).toBe(false);
      expect(alert).toHaveBeenCalledWith('Error: Unknown error');
    } finally {
      vi.clearAllTimers();
      vi.useRealTimers();
    }
  });

  it('alerts and returns false when the request throws', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('boom')));

    const result = await runButtonAction(api, btn, '/_dev/api/x', { busyLabel: 'Busy...' });

    expect(result).toBe(false);
    expect(btn.textContent).toBe('Error');
    expect(alert).toHaveBeenCalledWith(expect.stringContaining('Request failed:'));
  });
});
