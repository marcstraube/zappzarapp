/**
 * Shared button-driven POST action for DevDashboard pages
 *
 * Covers the common "click button → POST endpoint → show Done!/Failed on the
 * button, then reload or reset" flow used by the docs regeneration (dashboard
 * page) and coverage generation (quality page) buttons.
 */

import type { RequestInterceptorInstance } from '@zappzarapp/browser-utils/request';

interface ActionResponse {
  success?: boolean;
  message?: string;
  output?: string;
}

interface ButtonActionOptions {
  /** Button label while the request is running, e.g. "Generating...". */
  busyLabel: string;
  /** Idle button class swapped for btn-success/btn-danger. */
  idleClass?: string;
  /** Reload the page one second after success. */
  reload?: boolean;
}

export async function runButtonAction(
  api: RequestInterceptorInstance,
  btn: HTMLButtonElement,
  url: string,
  { busyLabel, idleClass = 'btn-secondary', reload = true }: ButtonActionOptions
): Promise<boolean> {
  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = busyLabel;

  const fail = (label: string, alertMessage: string): false => {
    btn.textContent = label;
    btn.classList.remove(idleClass);
    btn.classList.add('btn-danger');
    alert(alertMessage);
    setTimeout(() => {
      btn.textContent = originalText;
      btn.classList.remove('btn-danger');
      btn.classList.add(idleClass);
      btn.disabled = false;
    }, 2000);
    return false;
  };

  try {
    const response = await api.post(url, null, {
      headers: { 'Content-Type': 'application/json' },
    });
    const result = (await response.json()) as ActionResponse;

    if (result.success === true) {
      btn.textContent = 'Done!';
      btn.classList.remove(idleClass);
      btn.classList.add('btn-success');
      if (reload) {
        setTimeout(() => window.location.reload(), 1000);
      }
      return true;
    }

    const output =
      result.output !== undefined && result.output !== ''
        ? `\n\n${result.output.slice(0, 500)}`
        : '';
    return fail('Failed', `Error: ${result.message ?? 'Unknown error'}${output}`);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    return fail('Error', `Request failed: ${message}`);
  }
}
