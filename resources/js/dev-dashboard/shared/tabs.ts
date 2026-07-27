/**
 * Shared tab navigation for DevDashboard pages
 *
 * All DevDashboard pages use the same sub-nav markup (`.sub-nav-link` with a
 * `data-tab` attribute, `.tab-panel` containers with `tab-<id>` element ids)
 * and persist the active tab in the URL hash.
 */

export function switchTab(tabId: string, onSwitch?: (tabId: string) => void): void {
  document.querySelectorAll('.sub-nav-link').forEach((l) => l.classList.remove('active'));
  document.querySelector(`.sub-nav-link[data-tab="${tabId}"]`)?.classList.add('active');

  document.querySelectorAll('.tab-panel').forEach((p) => p.classList.add('hidden'));
  document.getElementById(`tab-${tabId}`)?.classList.remove('hidden');

  history.replaceState(null, '', `#${tabId}`);
  onSwitch?.(tabId);
}

/**
 * Wires up the sub-nav click handlers and restores the tab from the URL hash.
 * The optional callback fires after every tab switch (including the initial
 * hash restore), e.g. to lazy-load tab content.
 */
export function initTabs(onSwitch?: (tabId: string) => void): void {
  document.querySelectorAll<HTMLElement>('.sub-nav-link').forEach((link) => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const tab = link.dataset.tab;
      if (tab !== undefined) switchTab(tab, onSwitch);
    });
  });

  const hash = window.location.hash.slice(1);
  if (hash && document.getElementById(`tab-${hash}`)) {
    switchTab(hash, onSwitch);
  }
}
