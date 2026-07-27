/**
 * Shared copy-to-clipboard button handling for DevDashboard pages
 *
 * Uses ClipboardManager from @zappzarapp/browser-utils with the execCommand
 * fallback for non-secure contexts, and gives visual feedback on the button.
 */

import { Result } from '@zappzarapp/browser-utils/core';
import { ClipboardManager } from '@zappzarapp/browser-utils/clipboard';

export async function copyCmd(cmd: string, btn: HTMLElement): Promise<void> {
  let copied = await ClipboardManager.writeText(cmd);
  if (Result.isErr(copied)) {
    // Async Clipboard API needs a secure context; fall back to execCommand
    copied = ClipboardManager.writeTextFallback(cmd);
  }

  const orig = btn.textContent;
  btn.textContent = Result.isOk(copied) ? 'Copied!' : 'Copy failed';
  btn.classList.add(Result.isOk(copied) ? 'bg-green-100' : 'bg-red-100');
  setTimeout(() => {
    btn.textContent = orig;
    btn.classList.remove('bg-green-100', 'bg-red-100');
  }, 1500);
}
