// @vitest-environment happy-dom
// @vitest-environment-options {"url": "https://localhost:8443/_dev/system"}
/**
 * Smoke test for the DevDashboard system page module
 *
 * The page only wires up the shared tab navigation on import; the tab logic
 * itself is covered by the shared module tests.
 */

import { describe, it, expect } from 'vitest';

describe('DevDashboard system page', () => {
  it('wires up tab switching on import', async () => {
    document.body.innerHTML = `
      <nav>
        <a href="#" class="sub-nav-link active" data-tab="php">PHP</a>
        <a href="#" class="sub-nav-link" data-tab="git">Git</a>
      </nav>
      <div id="tab-php" class="tab-panel"></div>
      <div id="tab-git" class="tab-panel hidden"></div>
    `;

    await import('@resources/js/dev-dashboard/system');

    document
      .querySelector<HTMLElement>('[data-tab="git"]')
      ?.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    expect(document.getElementById('tab-git')?.classList.contains('hidden')).toBe(false);
    expect(document.getElementById('tab-php')?.classList.contains('hidden')).toBe(true);
  });
});
