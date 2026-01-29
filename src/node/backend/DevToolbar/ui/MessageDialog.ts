/**
 * MessageDialog - Generic message dialog for errors, warnings, and info
 *
 * Shows a styled modal with:
 * - Icon based on message type
 * - Title and message
 * - OK button to dismiss
 */

import { createEscapeKeyHandler } from '../utils/uiHelpers.js';

type MessageType = 'error' | 'warning' | 'info' | 'success';

interface MessageDialogOptions {
  type?: MessageType;
  title?: string;
  message: string;
  okButtonText?: string;
}

/**
 * MessageDialog for showing messages to the user
 */
export class MessageDialog {
  private modal: HTMLElement | null = null;
  private isOpen = false;
  private escKeyCleanup: (() => void) | null = null;

  /**
   * Open dialog with message
   */
  open(options: MessageDialogOptions): void {
    if (this.isOpen) {
      return;
    }

    this.createModal(options);
    this.showModal();
    this.attachModalHandlers();
    this.isOpen = true;
  }

  /**
   * Close dialog
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
   * Get icon and color for message type
   */
  private getTypeConfig(type: MessageType): { icon: string; color: string } {
    const configs = {
      error: { icon: '❌', color: '#ef4444' },
      warning: { icon: '⚠️', color: '#f59e0b' },
      info: { icon: 'ℹ️', color: '#3b82f6' },
      success: { icon: '✅', color: '#10b981' },
    };
    return configs[type] || configs.info;
  }

  /**
   * Create modal HTML structure
   */
  private createModal(options: MessageDialogOptions): void {
    const type = options.type || 'info';
    const title = options.title || this.getDefaultTitle(type);
    const okButtonText = options.okButtonText || 'OK';
    const { icon, color } = this.getTypeConfig(type);

    const modalHTML = `
            <div class="dev-toolbar-modal-overlay" id="dev-toolbar-message-overlay">
                <div class="dev-toolbar-modal dev-toolbar-message-modal">
                    <div class="dev-toolbar-modal-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 1.5rem;">${icon}</span>
                            <h3 style="color: ${color};">${this.escapeHtml(title)}</h3>
                        </div>
                        <button class="dev-toolbar-modal-close" title="Close">×</button>
                    </div>

                    <div class="dev-toolbar-modal-content">
                        <p style="white-space: pre-wrap; margin: 0;">${this.escapeHtml(options.message)}</p>
                    </div>

                    <div class="dev-toolbar-modal-footer">
                        <button class="dev-toolbar-btn dev-toolbar-btn-primary" id="message-dialog-ok">
                            ${this.escapeHtml(okButtonText)}
                        </button>
                    </div>
                </div>
            </div>
        `;

    const container = document.createElement('div');
    container.innerHTML = modalHTML;
    const modalElement = container.firstElementChild;
    if (modalElement != null) {
      document.body.appendChild(modalElement);
    }

    this.modal = document.getElementById('dev-toolbar-message-overlay');
  }

  /**
   * Get default title for message type
   */
  private getDefaultTitle(type: MessageType): string {
    const titles = {
      error: 'Error',
      warning: 'Warning',
      info: 'Information',
      success: 'Success',
    };
    return titles[type] || 'Information';
  }

  /**
   * Escape HTML to prevent XSS
   */
  private escapeHtml(text: string): string {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Show modal (fade in)
   */
  private showModal(): void {
    if (this.modal != null) {
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
    if (this.modal != null) {
      this.modal.style.opacity = '0';
    }
  }

  /**
   * Remove modal from DOM
   */
  private removeModal(): void {
    if (this.modal != null) {
      // Clean up ESC key handler
      if (this.escKeyCleanup != null) {
        this.escKeyCleanup();
        this.escKeyCleanup = null;
      }
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
    if (this.modal == null) {
      return;
    }

    // OK button
    const okBtn = this.modal.querySelector('#message-dialog-ok');
    okBtn?.addEventListener('click', () => this.close());

    // Close button (×)
    const closeBtn = this.modal.querySelector('.dev-toolbar-modal-close');
    closeBtn?.addEventListener('click', () => this.close());

    // ESC key
    this.escKeyCleanup = createEscapeKeyHandler(() => this.close());

    // Overlay click (close if clicked outside modal)
    this.modal.addEventListener('click', (e) => {
      if (e.target === this.modal) {
        this.close();
      }
    });
  }
}

/**
 * Helper function to show a message dialog
 */
export function showMessage(options: MessageDialogOptions): void {
  const dialog = new MessageDialog();
  dialog.open(options);
}

/**
 * Helper function to show an error dialog
 */
export function showError(message: string, title?: string): void {
  showMessage({ type: 'error', title, message });
}

/**
 * Helper function to show a warning dialog
 */
export function showWarning(message: string, title?: string): void {
  showMessage({ type: 'warning', title, message });
}

/**
 * Helper function to show an info dialog
 */
export function showInfo(message: string, title?: string): void {
  showMessage({ type: 'info', title, message });
}
