/**
 * SettingsManager - Manages DevToolbar Settings Modal
 *
 * Provides UI for configuring:
 * - Minibar label display mode (branding, branch, route, request-id)
 * - Git branch color scheme for different branch types
 */

import type { MinibarLabelType, BranchColors } from '../types/index.js';
import { StorageManager } from '../storage/StorageManager.js';
import { DEFAULT_BRANCH_COLORS } from '../storage/StorageConfig.js';
import { debug, error as logError } from '../utils/logger.js';

/**
 * SettingsManager singleton for managing settings UI
 */
export class SettingsManager {
  private modal: HTMLElement | null = null;
  private isOpen = false;

  /**
   * Open settings modal
   */
  open(): void {
    if (this.isOpen) {
      return; // Already open
    }

    this.createModal();
    this.showModal();
    this.attachModalHandlers();
    this.isOpen = true;
  }

  /**
   * Close settings modal
   */
  close(): void {
    if (!this.isOpen) {
      return;
    }

    this.hideModal();
    this.removeModal();
    this.isOpen = false;
  }

  /**
   * Create modal HTML structure
   */
  private createModal(): void {
    const currentLabels = StorageManager.getMinibarLabels();
    const currentColors = StorageManager.getBranchColors();

    const modalHTML = `
            <div class="dev-toolbar-modal-overlay" id="dev-toolbar-settings-overlay">
                <div class="dev-toolbar-modal">
                    <div class="dev-toolbar-modal-header">
                        <h3>DevToolbar Settings</h3>
                        <button class="dev-toolbar-modal-close" title="Close">×</button>
                    </div>

                    <div class="dev-toolbar-modal-content">
                        <!-- Minibar Label Selection -->
                        <div class="dev-toolbar-settings-group">
                            <label>Minibar Labels (multiple selection)</label>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 0.875rem;">
                                Select which labels to display in the minibar (left to right order)
                            </p>
                            <div class="dev-toolbar-settings-checkboxes">
                                ${this.buildCheckboxOption('branding', '⚡ Branding', 'Show lightning bolt icon', currentLabels)}
                                ${this.buildCheckboxOption('branch', 'Git Branch', 'Show current git branch name', currentLabels)}
                                ${this.buildCheckboxOption('route', 'Current Route', 'Show HTTP method and URI', currentLabels)}
                                ${this.buildCheckboxOption('request-id', 'Request ID', 'Show unique request identifier', currentLabels)}
                            </div>
                        </div>

                        <!-- Branch Color Configuration -->
                        <div class="dev-toolbar-settings-group">
                            <label>Git Branch Colors</label>
                            <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 0.875rem;">
                                Customize colors for different branch types when using branch display mode
                            </p>
                            <div class="dev-toolbar-settings-colors">
                                ${this.buildColorInput('feat', 'feat/* branches', currentColors.feat)}
                                ${this.buildColorInput('fix', 'fix/* branches', currentColors.fix)}
                                ${this.buildColorInput('hotfix', 'hotfix/* branches', currentColors.hotfix)}
                                ${this.buildColorInput('chore', 'chore/* branches', currentColors.chore)}
                                ${this.buildColorInput('default', 'Other branches', currentColors.default)}
                            </div>
                        </div>
                    </div>

                    <div class="dev-toolbar-modal-footer">
                        <button class="dev-toolbar-btn dev-toolbar-btn-secondary" id="settings-cancel">
                            Cancel
                        </button>
                        <button class="dev-toolbar-btn dev-toolbar-btn-primary" id="settings-save">
                            Save & Reload
                        </button>
                    </div>
                </div>
            </div>
        `;

    // Inject modal into DOM
    const container = document.createElement('div');
    container.innerHTML = modalHTML;
    const modalElement = container.firstElementChild;
    if (modalElement) {
      document.body.appendChild(modalElement);
    }

    this.modal = document.getElementById('dev-toolbar-settings-overlay');
  }

  /**
   * Build checkbox option HTML
   */
  private buildCheckboxOption(
    value: string,
    title: string,
    description: string,
    currentValues: MinibarLabelType[]
  ): string {
    const checked = currentValues.includes(value as MinibarLabelType) ? 'checked' : '';

    return `
            <label class="dev-toolbar-settings-checkbox-item">
                <input type="checkbox" name="minibar-label" value="${this.escapeHtml(value)}" ${checked}>
                <div>
                    <strong>${this.escapeHtml(title)}</strong>
                    <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 0.875rem;">
                        ${this.escapeHtml(description)}
                    </p>
                </div>
            </label>
        `;
  }

  /**
   * Build color input HTML
   */
  private buildColorInput(type: string, label: string, value: string): string {
    return `
            <div class="dev-toolbar-settings-color-item">
                <label for="color-${this.escapeHtml(type)}">${this.escapeHtml(label)}</label>
                <input type="color" id="color-${this.escapeHtml(type)}" name="color-${this.escapeHtml(type)}" value="${this.escapeHtml(value)}">
            </div>
        `;
  }

  /**
   * Show modal (fade in)
   */
  private showModal(): void {
    if (this.modal) {
      this.modal.style.display = 'flex';
      // Trigger reflow for animation
      void this.modal.offsetHeight;
      this.modal.style.opacity = '1';
    }
  }

  /**
   * Hide modal (fade out)
   */
  private hideModal(): void {
    if (this.modal) {
      this.modal.style.opacity = '0';
    }
  }

  /**
   * Remove modal from DOM
   */
  private removeModal(): void {
    if (this.modal) {
      // Wait for fade animation
      setTimeout(() => {
        this.modal?.remove();
        this.modal = null;
      }, 200);
    }
  }

  /**
   * Attach event handlers to modal
   */
  private attachModalHandlers(): void {
    if (!this.modal) {
      return;
    }

    // Save button
    const saveBtn = this.modal.querySelector('#settings-save');
    saveBtn?.addEventListener('click', () => this.saveSettings());

    // Cancel button
    const cancelBtn = this.modal.querySelector('#settings-cancel');
    cancelBtn?.addEventListener('click', () => this.close());

    // Close button (×)
    const closeBtn = this.modal.querySelector('.dev-toolbar-modal-close');
    closeBtn?.addEventListener('click', () => this.close());

    // ESC key
    const escHandler = (e: KeyboardEvent): void => {
      if (e.key === 'Escape') {
        this.close();
        document.removeEventListener('keydown', escHandler);
      }
    };
    document.addEventListener('keydown', escHandler);

    // Overlay click (close if clicked outside modal)
    this.modal.addEventListener('click', (e) => {
      if (e.target === this.modal) {
        this.close();
      }
    });
  }

  /**
   * Save settings and reload page
   */
  private saveSettings(): void {
    const selectedLabels = this.getSelectedLabels();
    const branchColors = this.getBranchColors();

    if (selectedLabels.length === 0) {
      logError('[Settings] No labels selected');
      return;
    }

    // Save to localStorage
    StorageManager.setMinibarLabels(selectedLabels);
    StorageManager.setBranchColors(branchColors);

    // Save to cookies so PHP can read the settings
    this.saveSettingsToCookies(selectedLabels, branchColors);

    debug('[Settings] Saved:', { labels: selectedLabels, colors: branchColors });

    // Reload page to apply changes (server-side rendering)
    window.location.reload();
  }

  /**
   * Save settings to cookies for PHP access
   */
  private saveSettingsToCookies(labels: MinibarLabelType[], colors: BranchColors): void {
    // Set labels cookie (JSON encoded array)
    const labelsJson = JSON.stringify(labels);
    document.cookie = `devbar_labels=${encodeURIComponent(labelsJson)}; path=/; max-age=31536000`; // 1 year

    // Set branch colors cookie (JSON encoded)
    const colorsJson = JSON.stringify(colors);
    document.cookie = `devbar_colors=${encodeURIComponent(colorsJson)}; path=/; max-age=31536000`;

    debug('[Settings] Cookies set:', {
      labels: `devbar_labels=${encodeURIComponent(labelsJson)}`,
      colors: `devbar_colors=${encodeURIComponent(colorsJson)}`,
      allCookies: document.cookie,
    });
  }

  /**
   * Get selected minibar labels from form
   */
  private getSelectedLabels(): MinibarLabelType[] {
    if (!this.modal) {
      return [];
    }

    const selectedCheckboxes = this.modal.querySelectorAll<HTMLInputElement>(
      'input[name="minibar-label"]:checked'
    );

    const labels: MinibarLabelType[] = [];
    selectedCheckboxes.forEach((checkbox) => {
      labels.push(checkbox.value as MinibarLabelType);
    });

    return labels;
  }

  /**
   * Get branch colors from form
   */
  private getBranchColors(): BranchColors {
    return {
      feat: this.getColorValue('feat') ?? DEFAULT_BRANCH_COLORS.feat,
      fix: this.getColorValue('fix') ?? DEFAULT_BRANCH_COLORS.fix,
      hotfix: this.getColorValue('hotfix') ?? DEFAULT_BRANCH_COLORS.hotfix,
      chore: this.getColorValue('chore') ?? DEFAULT_BRANCH_COLORS.chore,
      default: this.getColorValue('default') ?? DEFAULT_BRANCH_COLORS.default,
    };
  }

  /**
   * Get color value from color input
   */
  private getColorValue(type: string): string | null {
    if (!this.modal) {
      return null;
    }

    const input = this.modal.querySelector<HTMLInputElement>(`input[name="color-${type}"]`);
    return input?.value ?? null;
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
}
