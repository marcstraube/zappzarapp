/**
 * DevDashboard Quality page
 *
 * Handles the test coverage generation buttons via the shared button action
 * helper.
 */

import { runButtonAction } from './shared/action-button';
import { createDashboardApi } from './shared/api';
import { copyCmd } from './shared/copy';
import { initTabs } from './shared/tabs';

// Coverage generation runs the full test suite — allow far more than the
// default lookup timeout.
const coverageApi = createDashboardApi(600_000);

async function generateCoverage(type: string): Promise<boolean> {
  const btn = document.getElementById(`btn-coverage-${type}`);
  if (!(btn instanceof HTMLButtonElement)) return false;

  return runButtonAction(
    coverageApi,
    btn,
    `/_dev/api/coverage/generate?type=${encodeURIComponent(type)}`,
    { busyLabel: 'Running tests...' }
  );
}

async function generateNodeCoverage(): Promise<boolean> {
  const btn = document.getElementById('btn-coverage-node');
  if (!(btn instanceof HTMLButtonElement)) return false;

  return runButtonAction(coverageApi, btn, '/dev-dashboard/node/coverage/generate', {
    busyLabel: 'Running tests...',
  });
}

function init(): void {
  initTabs();

  // Event delegation for data-action clicks (CSP-compliant)
  document.addEventListener('click', (e) => {
    const target =
      e.target instanceof Element ? e.target.closest<HTMLElement>('[data-action]') : null;
    if (!target) return;

    switch (target.dataset.action) {
      case 'copy-cmd':
        if (target.dataset.cmd !== undefined) void copyCmd(target.dataset.cmd, target);
        break;
      case 'generate-coverage':
        if (target.dataset.type !== undefined) void generateCoverage(target.dataset.type);
        break;
      case 'generate-node-coverage':
        void generateNodeCoverage();
        break;
    }
  });
}

init();

// Exported for unit tests (happy-dom); the page itself only needs init()
export { generateCoverage, generateNodeCoverage };
