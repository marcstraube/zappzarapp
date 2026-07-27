// @vitest-environment happy-dom
// @vitest-environment-options {"url": "https://localhost:8443/_dev/logs"}
/**
 * Tests for the shared DevDashboard copy-to-clipboard handling
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { copyCmd } from '@resources/js/dev-dashboard/shared/copy';

describe('shared copyCmd', () => {
  let btn: HTMLButtonElement;

  beforeEach(() => {
    document.body.innerHTML = '';
    btn = document.createElement('button');
    btn.textContent = 'Copy';
    document.body.appendChild(btn);
  });

  it('shows success feedback when the clipboard write succeeds', async () => {
    vi.stubGlobal('navigator', {
      clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    });

    await copyCmd('make logs', btn);

    expect(btn.textContent).toBe('Copied!');
    expect(btn.classList.contains('bg-green-100')).toBe(true);
  });

  it('shows failure feedback when both clipboard paths fail', async () => {
    // No Clipboard API and no execCommand fallback available
    vi.stubGlobal('navigator', {});

    await copyCmd('make logs', btn);

    expect(btn.textContent).toBe('Copy failed');
    expect(btn.classList.contains('bg-red-100')).toBe(true);
  });

  it('restores the original label after the feedback timeout', async () => {
    vi.useFakeTimers();
    try {
      vi.stubGlobal('navigator', {
        clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
      });

      await copyCmd('make logs', btn);
      vi.advanceTimersByTime(1500);

      expect(btn.textContent).toBe('Copy');
      expect(btn.classList.contains('bg-green-100')).toBe(false);
    } finally {
      vi.useRealTimers();
    }
  });
});
