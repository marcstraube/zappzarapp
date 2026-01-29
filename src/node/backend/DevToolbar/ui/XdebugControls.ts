/**
 * XdebugControls - Manages Xdebug debugging controls
 *
 * Renders and handles Xdebug controls in the Request tab:
 * - Display Xdebug status (enabled/disabled, active session)
 * - Enable/disable Xdebug debugging
 * - IDE-specific session activation (PhpStorm, VSCode)
 */

import type { XdebugConfig, DevToolbarWindow } from '../types';
import { debug } from '../utils/logger.js';

/**
 * XdebugControls singleton for managing Xdebug UI
 */
export class XdebugControls {
  private readonly CONTAINER_ID = 'dev-toolbar-request-controls-container';

  /**
   * Render Xdebug controls based on current state
   *
   * Reads configuration from window.__XDEBUG_CONFIG__ and cookies
   */
  render(): void {
    const container = document.getElementById(this.CONTAINER_ID);
    if (!container) {
      return; // Container not found (not on REQUEST tab)
    }

    // Get Xdebug configuration from server-injected data
    const xdebugConfig = this.getXdebugConfig();
    const xdebugEnabled = xdebugConfig.enabled;

    // Check current cookie status
    const { xdebugActive, xdebugSessionName } = this.getXdebugStatus();

    // Build HTML
    const html = this.buildControlsHTML(xdebugEnabled, xdebugActive, xdebugSessionName);

    // Inject HTML
    container.innerHTML = html;

    debug('[Xdebug] Controls rendered, status:', xdebugActive ? 'active' : 'inactive');
  }

  /**
   * Get Xdebug configuration from window global
   */
  private getXdebugConfig(): XdebugConfig {
    if (typeof window !== 'undefined' && 'window' in globalThis) {
      const win = window as DevToolbarWindow;
      return win.__XDEBUG_CONFIG__ || { enabled: false, mode: 'off' };
    }
    return { enabled: false, mode: 'off' };
  }

  /**
   * Get Xdebug session status from cookies
   */
  private getXdebugStatus(): { xdebugActive: boolean; xdebugSessionName: string } {
    const cookies = this.parseCookies();
    const xdebugActive = 'XDEBUG_SESSION' in cookies;
    const xdebugSessionName = cookies['XDEBUG_SESSION'] || '';

    return { xdebugActive, xdebugSessionName };
  }

  /**
   * Parse document.cookie into key-value pairs
   */
  private parseCookies(): Record<string, string> {
    if (typeof document === 'undefined') {
      return {};
    }

    return document.cookie.split(';').reduce((acc: Record<string, string>, cookie) => {
      const [key, value] = cookie.trim().split('=');
      if (key) {
        acc[key] = value || '';
      }
      return acc;
    }, {});
  }

  /**
   * Build controls HTML
   */
  private buildControlsHTML(
    xdebugEnabled: boolean,
    xdebugActive: boolean,
    xdebugSessionName: string
  ): string {
    let html = '<div class="dev-toolbar-request-status">';

    if (xdebugEnabled) {
      const statusClass = xdebugActive ? 'active' : 'inactive';
      const statusText = xdebugActive ? `Xdebug: ${xdebugSessionName}` : 'Xdebug: Off';
      const statusIcon = xdebugActive ? '●' : '○';

      html += `<div class="dev-toolbar-xdebug-compact dev-toolbar-xdebug-compact-${statusClass}">
                <span class="dev-toolbar-xdebug-indicator">${statusIcon}</span>
                <span class="dev-toolbar-xdebug-label">${this.escapeHtml(statusText)}</span>
            </div>`;
    } else {
      html += `<div class="dev-toolbar-xdebug-compact dev-toolbar-xdebug-compact-disabled">
                <span class="dev-toolbar-xdebug-label">Xdebug: Not Installed</span>
            </div>`;
    }

    html += '</div>'; // .dev-toolbar-request-status

    html += '<div class="dev-toolbar-request-actions">';

    if (xdebugEnabled) {
      if (xdebugActive) {
        html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-disable" title="Disable Xdebug step debugging">
                    ⏹ Disable
                </button>`;
      } else {
        html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="PHPSTORM" title="Enable Xdebug for PhpStorm">
                    ▶ PhpStorm
                </button>`;
        html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="xdebug-enable" data-ide="VSCODE" title="Enable Xdebug for VSCode">
                    ▶ VSCode
                </button>`;
      }
    }

    html += `<button class="dev-toolbar-btn dev-toolbar-btn-secondary" data-action="export-current" title="Export current request as JSON">
            ⬇ Export
        </button>`;

    html += '</div>'; // .dev-toolbar-request-actions

    return html;
  }

  /**
   * Escape HTML special characters
   */
  private escapeHtml(text: string): string {
    const map: Record<string, string> = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    };
    return text.replace(/[&<>"']/g, (char) => map[char] || char);
  }

  /**
   * Enable Xdebug debugging for specific IDE
   *
   * @param ide - IDE identifier (PHPSTORM, VSCODE)
   */
  enableXdebug(ide: string): void {
    debug(`[Xdebug] Enabling Xdebug for ${ide}`);

    // Set cookie
    document.cookie = `XDEBUG_SESSION=${ide}; path=/; max-age=3600`;

    // Re-render controls to show updated state
    this.render();

    // Reload page to activate Xdebug
    window.location.reload();
  }

  /**
   * Disable Xdebug debugging
   */
  disableXdebug(): void {
    debug('[Xdebug] Disabling Xdebug');

    // Remove cookie
    document.cookie = 'XDEBUG_SESSION=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';

    // Re-render controls to show updated state
    this.render();

    // Reload page to deactivate Xdebug
    window.location.reload();
  }
}
