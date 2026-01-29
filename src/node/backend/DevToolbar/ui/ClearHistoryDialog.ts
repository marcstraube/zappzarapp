/**
 * ClearHistoryDialog - Confirmation dialog for clearing request history
 *
 * Shows a styled modal with:
 * - Warning about data deletion
 * - List of what will be deleted
 * - List of what will be preserved
 * - Confirm/Cancel actions
 */

/**
 * ClearHistoryDialog for showing clear confirmation
 */
export class ClearHistoryDialog {
  private modal: Element | null = null;
  private isOpen = false;
  private onConfirm: (() => void) | null = null;

  /**
   * Open dialog with confirmation callback
   */
  open(onConfirm: () => void): void {
    if (this.isOpen) {
      return;
    }

    this.onConfirm = onConfirm;
    this.createModal();
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
    this.onConfirm = null;
  }

  /**
   * Create modal HTML structure
   */
  private createModal(): void {
    const modalHTML = `
            <div class="dev-toolbar-modal-overlay" id="dev-toolbar-clear-history-overlay">
                <div class="dev-toolbar-modal dev-toolbar-clear-history-modal">
                    <div class="dev-toolbar-modal-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 1.5rem;">🗑️</span>
                            <h3>Clear Request History</h3>
                        </div>
                        <button class="dev-toolbar-modal-close" title="Close">×</button>
                    </div>

                    <div class="dev-toolbar-modal-content">
                        <!-- Warning -->
                        <div class="dev-toolbar-clear-warning">
                            <strong>⚠️ Warning:</strong> This action cannot be undone.
                        </div>

                        <!-- What will be deleted -->
                        <div class="dev-toolbar-clear-section">
                            <h4>Will be deleted:</h4>
                            <ul class="dev-toolbar-clear-list">
                                <li>All request metadata (timestamps, URIs, methods)</li>
                                <li>All request details (queries, performance data)</li>
                                <li>History statistics and trends</li>
                            </ul>
                        </div>

                        <!-- What will be preserved -->
                        <div class="dev-toolbar-clear-section">
                            <h4>Will be preserved:</h4>
                            <ul class="dev-toolbar-clear-list dev-toolbar-clear-list-preserved">
                                <li>DevToolbar settings (minibar labels, colors)</li>
                                <li>Active tab selection</li>
                                <li>Xdebug cookie settings</li>
                            </ul>
                        </div>
                    </div>

                    <div class="dev-toolbar-modal-footer">
                        <button class="dev-toolbar-btn dev-toolbar-btn-secondary" id="clear-history-cancel">
                            Cancel
                        </button>
                        <button class="dev-toolbar-btn dev-toolbar-btn-danger" id="clear-history-confirm">
                            🗑️ Clear History
                        </button>
                    </div>
                </div>
            </div>
        `;

    const container = document.createElement('div');
    container.innerHTML = modalHTML;
    document.body.appendChild(container.firstElementChild);

    this.modal = document.getElementById('dev-toolbar-clear-history-overlay');
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

    // Confirm button
    const confirmBtn = this.modal.querySelector('#clear-history-confirm');
    confirmBtn?.addEventListener('click', () => {
      if (this.onConfirm) {
        this.onConfirm();
      }
      this.close();
    });

    // Cancel button
    const cancelBtn = this.modal.querySelector('#clear-history-cancel');
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
}
