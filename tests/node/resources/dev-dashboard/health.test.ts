// @vitest-environment happy-dom
// @vitest-environment-options {"url": "https://localhost:8443/_dev/health"}
/**
 * Smoke test for the DevDashboard health page module
 *
 * The page only wires up the shared tab navigation on import; the tab logic
 * itself is covered by the shared module tests.
 */

import { describe, it, expect } from 'vitest';

describe('DevDashboard health page', () => {
  it('wires up tab switching on import', async () => {
    document.body.innerHTML = `
      <nav>
        <a href="#" class="sub-nav-link active" data-tab="services">Services</a>
        <a href="#" class="sub-nav-link" data-tab="ssl">SSL</a>
      </nav>
      <div id="tab-services" class="tab-panel"></div>
      <div id="tab-ssl" class="tab-panel hidden"></div>
    `;

    await import('@resources/js/dev-dashboard/health');

    document
      .querySelector<HTMLElement>('[data-tab="ssl"]')
      ?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    expect(document.getElementById('tab-ssl')?.classList.contains('hidden')).toBe(false);
    expect(document.getElementById('tab-services')?.classList.contains('hidden')).toBe(true);
  });
});
