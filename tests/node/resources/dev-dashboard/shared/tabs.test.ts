// @vitest-environment happy-dom
// @vitest-environment-options {"url": "https://localhost:8443/_dev/system"}
/**
 * Tests for the shared DevDashboard tab navigation
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { switchTab, initTabs } from '@resources/js/dev-dashboard/shared/tabs';

function setupDom(): void {
  document.body.innerHTML = `
    <nav>
      <a href="#" class="sub-nav-link active" data-tab="overview">Overview</a>
      <a href="#" class="sub-nav-link" data-tab="details">Details</a>
    </nav>
    <div id="tab-overview" class="tab-panel"></div>
    <div id="tab-details" class="tab-panel hidden"></div>
  `;
  history.replaceState(null, '', '/_dev/system');
}

describe('shared tabs', () => {
  beforeEach(() => {
    setupDom();
  });

  describe('switchTab', () => {
    it('activates the requested tab and hides the others', () => {
      switchTab('details');

      expect(document.querySelector('[data-tab="details"]')?.classList.contains('active')).toBe(
        true
      );
      expect(document.querySelector('[data-tab="overview"]')?.classList.contains('active')).toBe(
        false
      );
      expect(document.getElementById('tab-details')?.classList.contains('hidden')).toBe(false);
      expect(document.getElementById('tab-overview')?.classList.contains('hidden')).toBe(true);
      expect(window.location.hash).toBe('#details');
    });

    it('invokes the onSwitch callback with the tab id', () => {
      const onSwitch = vi.fn();

      switchTab('details', onSwitch);

      expect(onSwitch).toHaveBeenCalledWith('details');
    });
  });

  describe('initTabs', () => {
    it('switches tabs on nav link clicks', () => {
      initTabs();

      document
        .querySelector<HTMLElement>('[data-tab="details"]')
        ?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

      expect(document.getElementById('tab-details')?.classList.contains('hidden')).toBe(false);
      expect(document.getElementById('tab-overview')?.classList.contains('hidden')).toBe(true);
    });

    it('restores the tab from the URL hash', () => {
      history.replaceState(null, '', '#details');
      const onSwitch = vi.fn();

      initTabs(onSwitch);

      expect(document.getElementById('tab-details')?.classList.contains('hidden')).toBe(false);
      expect(onSwitch).toHaveBeenCalledWith('details');
    });

    it('ignores a hash without a matching panel', () => {
      history.replaceState(null, '', '#missing');

      initTabs();

      expect(document.getElementById('tab-overview')?.classList.contains('hidden')).toBe(false);
    });
  });
});
