/**
 * UI Helper Utilities
 *
 * Shared utilities for DevToolbar UI components
 */

/**
 * Escape HTML special characters to prevent XSS
 */
export function escapeHtml(text: string): string {
  const map: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  };
  return text.replace(/[&<>"']/g, (char) => map[char] ?? char);
}

/**
 * Create ESC key handler for modal dialogs
 *
 * @param onClose - Callback to execute when ESC is pressed
 * @returns Cleanup function to remove the event listener
 */
export function createEscapeKeyHandler(onClose: () => void): () => void {
  const escHandler = (e: KeyboardEvent): void => {
    if (e.key === 'Escape') {
      onClose();
      cleanup();
    }
  };

  const cleanup = (): void => {
    document.removeEventListener('keydown', escHandler);
  };

  document.addEventListener('keydown', escHandler);

  return cleanup;
}
