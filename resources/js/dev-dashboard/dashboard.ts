/**
 * DevDashboard overview page
 *
 * Handles the API docs regeneration buttons via the shared button action
 * helper.
 */

import { runButtonAction } from './shared/action-button';
import { createDashboardApi } from './shared/api';

// Docs generation shells out to phpDocumentor/TypeDoc — allow far more than
// the default lookup timeout.
const docsApi = createDashboardApi(300_000);

async function regenerateDocs(type: string, skipReload = false): Promise<boolean> {
  const btn = document.getElementById(`btn-regen-${type}-docs`);
  if (!(btn instanceof HTMLButtonElement)) return false;

  return runButtonAction(docsApi, btn, `/_dev/api/docs/generate?type=${encodeURIComponent(type)}`, {
    busyLabel: 'Generating...',
    reload: !skipReload,
  });
}

async function regenerateNodeDocs(skipReload = false): Promise<boolean> {
  const btn = document.getElementById('btn-regen-node-docs');
  if (!(btn instanceof HTMLButtonElement)) return false;

  return runButtonAction(docsApi, btn, '/dev-dashboard/node/docs/generate', {
    busyLabel: 'Generating...',
    reload: !skipReload,
  });
}

async function regenerateAllDocs(): Promise<void> {
  const btn = document.getElementById('btn-regen-all-docs');
  if (!(btn instanceof HTMLButtonElement)) return;

  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Generating...';

  const phpSuccess = await regenerateDocs('php', true);
  const nodeSuccess = await regenerateNodeDocs(true);

  if (phpSuccess || nodeSuccess) {
    btn.textContent = 'Done!';
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-success');
    setTimeout(() => window.location.reload(), 1000);
  } else {
    btn.textContent = originalText;
    btn.disabled = false;
  }
}

function init(): void {
  // Event delegation for data-action clicks (CSP-compliant)
  document.addEventListener('click', (e) => {
    const target =
      e.target instanceof Element ? e.target.closest<HTMLElement>('[data-action]') : null;
    if (!target) return;

    switch (target.dataset.action) {
      case 'regenerate-docs':
        if (target.dataset.type !== undefined) void regenerateDocs(target.dataset.type);
        break;
      case 'regenerate-node-docs':
        void regenerateNodeDocs();
        break;
      case 'regenerate-all-docs':
        void regenerateAllDocs();
        break;
    }
  });
}

init();

// Exported for unit tests (happy-dom); the page itself only needs init()
export { regenerateDocs, regenerateNodeDocs, regenerateAllDocs };
