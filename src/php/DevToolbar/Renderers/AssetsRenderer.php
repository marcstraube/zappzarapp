<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

use DevToolbar\Security\NonceHelper;

/**
 * Renders inline CSS and JavaScript assets
 *
 * All assets are self-contained and independent of Vite/build process.
 * Uses CSP nonce for inline script/style tags.
 */
class AssetsRenderer implements RendererInterface
{
    public function render(): string
    {
        return $this->renderCSS() . $this->renderJavaScript();
    }

    /**
     * Render inline CSS
     *
     * @return string CSS in <style> tag
     */
    private function renderCSS(): string
    {
        $css = <<<'CSS'
/* Developer Toolbar CSS - Phase 1 MVP - Self-contained */
:root {
    --toolbar-bg-dark: rgba(17, 24, 39, 0.95);
    --toolbar-bg-light: #ffffff;
    --toolbar-border: #e5e7eb;
    --toolbar-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
    --toolbar-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --color-success: #10b981;
    --color-warning: #f59e0b;
    --color-danger: #ef4444;
    --color-info: #3b82f6;
    --color-primary: #3b82f6;
    --radius-sm: 4px;
    --radius-md: 6px;
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --font-mono: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Mono', 'Droid Sans Mono', 'Source Code Pro', monospace;
    --font-sans: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.dev-toolbar-mini {
    position: fixed;
    bottom: 10px;
    right: 10px;
    background: var(--toolbar-bg-dark);
    color: white;
    padding: 8px 16px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    font-family: var(--font-mono);
    font-size: 0.875rem;
    z-index: 9999;
    cursor: pointer;
    transition: var(--toolbar-transition);
    display: flex;
    align-items: center;
    gap: 12px;
}

.dev-toolbar-mini:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
}

.dev-toolbar-mini-env {
    background: var(--color-success);
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
}

.dev-toolbar-mini-env.staging { background: var(--color-warning); }
.dev-toolbar-mini-env.production { background: var(--color-danger); }

.dev-toolbar-mini-metric {
    display: flex;
    align-items: center;
    gap: 4px;
}

.dev-toolbar-mini-expand {
    margin-left: 8px;
    font-size: 1.2rem;
    transition: transform 0.2s ease;
}

.dev-toolbar-mini:hover .dev-toolbar-mini-expand {
    transform: translateY(-2px);
}

.dev-toolbar-panel {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 400px;
    background: var(--toolbar-bg-light);
    box-shadow: var(--toolbar-shadow);
    z-index: 9998;
    transform: translateY(100%);
    transition: var(--toolbar-transition);
    display: flex;
    flex-direction: column;
}

.dev-toolbar-panel.open {
    transform: translateY(0);
}

.dev-toolbar-panel.maximized {
    height: 90vh;
}

.dev-toolbar-panel-header {
    display: flex;
    border-bottom: 2px solid var(--toolbar-border);
    background: #f9fafb;
    padding: 0;
}

.dev-toolbar-panel-tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    font-family: var(--font-sans);
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
}

.dev-toolbar-panel-tab:hover {
    color: #111827;
    background: rgba(59, 130, 246, 0.05);
}

.dev-toolbar-panel-tab.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
    background: white;
}

.dev-toolbar-panel-tab-badge {
    display: inline-block;
    margin-left: 6px;
    padding: 2px 8px;
    background: #e5e7eb;
    border-radius: var(--radius-sm);
    font-size: 0.75rem;
    font-weight: 600;
}

.dev-toolbar-panel-tab.active .dev-toolbar-panel-tab-badge {
    background: var(--color-primary);
    color: white;
}

.dev-toolbar-panel-maximize {
    margin-left: auto;
    padding: 12px 16px;
    background: transparent;
    border: none;
    font-size: 1rem;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.2s ease;
}

.dev-toolbar-panel-maximize:hover {
    color: var(--color-primary);
    transform: scale(1.1);
}

.dev-toolbar-panel-close {
    padding: 12px 24px;
    background: transparent;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #6b7280;
    transition: color 0.2s ease;
}

.dev-toolbar-panel-close:hover {
    color: var(--color-danger);
}

.dev-toolbar-panel-content {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    overscroll-behavior: contain;
}

.dev-toolbar-panel-tab-pane {
    display: none !important;
}

.dev-toolbar-panel-tab-pane.active {
    display: block !important;
}

.dev-toolbar-query {
    margin-bottom: 16px;
    padding: 12px;
    background: #f9fafb;
    border-radius: var(--radius-md);
    border-left: 3px solid var(--color-success);
}

.dev-toolbar-query.slow {
    border-left-color: var(--color-warning);
}

.dev-toolbar-query.very-slow {
    border-left-color: var(--color-danger);
    background: #fef2f2;
}

.dev-toolbar-query-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.dev-toolbar-query-time {
    font-family: var(--font-mono);
    font-size: 0.875rem;
    font-weight: 600;
}

.dev-toolbar-query-time.fast { color: var(--color-success); }
.dev-toolbar-query-time.slow { color: var(--color-warning); }
.dev-toolbar-query-time.very-slow { color: var(--color-danger); }

.dev-toolbar-query-sql {
    font-family: var(--font-mono);
    font-size: 0.875rem;
    background: #1f2937;
    color: #e5e7eb;
    padding: 12px;
    border-radius: var(--radius-sm);
    overflow-x: auto;
    margin-bottom: 8px;
}

.dev-toolbar-query-bindings {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 8px;
}

.dev-toolbar-message {
    margin-bottom: 12px;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    border-left: 3px solid #9ca3af;
    background: #f9fafb;
}

.dev-toolbar-message.debug { border-left-color: #9ca3af; }
.dev-toolbar-message.info { border-left-color: var(--color-info); background: #ecfeff; }
.dev-toolbar-message.warning { border-left-color: var(--color-warning); background: #fffbeb; }
.dev-toolbar-message.error { border-left-color: var(--color-danger); background: #fef2f2; }

.dev-toolbar-message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}

.dev-toolbar-message-level {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.dev-toolbar-message-time {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
}

.dev-toolbar-message-text {
    font-size: 0.875rem;
    margin-bottom: 4px;
}

.dev-toolbar-exception {
    margin-bottom: 16px;
    padding: 12px;
    border-radius: var(--radius-md);
    border-left: 3px solid var(--color-danger);
    background: #fef2f2;
}

.dev-toolbar-exception.handled {
    border-left-color: var(--color-warning);
    background: #fffbeb;
}

.dev-toolbar-exception-class {
    font-family: var(--font-mono);
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-danger);
    margin-bottom: 4px;
}

.dev-toolbar-exception.handled .dev-toolbar-exception-class {
    color: var(--color-warning);
}

.dev-toolbar-exception-message {
    font-size: 0.875rem;
    margin-bottom: 8px;
}

.dev-toolbar-exception-location {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 8px;
}

.dev-toolbar-section {
    margin-bottom: 24px;
}

.dev-toolbar-section-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 12px;
    color: #111827;
}

.dev-toolbar-kv-table {
    width: 100%;
    font-size: 0.875rem;
}

.dev-toolbar-kv-table td {
    padding: 6px 12px;
    border-bottom: 1px solid var(--toolbar-border);
}

.dev-toolbar-kv-table td:first-child {
    font-family: var(--font-mono);
    font-weight: 600;
    color: #6b7280;
    width: 200px;
}

.dev-toolbar-kv-table td:last-child {
    word-break: break-all;
}

/* Phase 2: Performance Alerts */
.dev-toolbar-alerts {
    background: #fffbeb;
    border: 2px solid var(--color-warning);
    border-radius: var(--radius-md);
    padding: 12px 16px;
    margin: 12px 16px 16px 16px;
    transition: opacity 0.2s ease, max-height 0.3s ease;
}

.dev-toolbar-alerts.dismissed {
    display: none;
}

.dev-toolbar-alerts-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.dev-toolbar-alerts-title {
    font-weight: 600;
    font-size: 1rem;
    color: #92400e;
}

.dev-toolbar-alerts-dismiss {
    background: transparent;
    border: none;
    font-size: 1.5rem;
    line-height: 1;
    color: #92400e;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    transition: all 0.2s ease;
}

.dev-toolbar-alerts-dismiss:hover {
    background: rgba(146, 64, 14, 0.1);
    transform: scale(1.1);
}

.dev-toolbar-alert {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    margin-bottom: 8px;
    border-left: 3px solid;
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.dev-toolbar-alert.dismissed {
    opacity: 0;
    transform: translateX(-10px);
    max-height: 0;
    margin: 0;
    padding: 0;
    overflow: hidden;
}

.dev-toolbar-alert-content {
    flex: 1;
}

.dev-toolbar-alert-close {
    background: transparent;
    border: none;
    font-size: 1.25rem;
    line-height: 1;
    color: #6b7280;
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.dev-toolbar-alert-close:hover {
    background: rgba(0, 0, 0, 0.1);
    color: #111827;
    transform: scale(1.1);
}

.dev-toolbar-alert.alert-critical {
    background: #fef2f2;
    border-left-color: var(--color-danger);
}

.dev-toolbar-alert.alert-warning {
    background: #fffbeb;
    border-left-color: var(--color-warning);
}

.dev-toolbar-alert.alert-info {
    background: #eff6ff;
    border-left-color: var(--color-info);
}

.dev-toolbar-alert-message {
    font-size: 0.875rem;
    margin-bottom: 4px;
}

.dev-toolbar-alert-details {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 4px;
}

.dev-toolbar-alert-action {
    font-size: 0.75rem;
    color: #6b7280;
    font-style: italic;
}

/* Phase 2: N+1 Query Detection */
.dev-toolbar-n-plus-one {
    display: block;
    width: 100%;
    background: #fef2f2;
    border: 2px solid var(--color-danger);
    border-radius: var(--radius-md);
    padding: 12px;
    margin-bottom: 12px;
    font-size: 0.875rem;
    box-sizing: border-box;
}

.dev-toolbar-n-plus-one div {
    margin-bottom: 6px;
}

.dev-toolbar-suggestion {
    background: #ecfeff;
    border-left: 3px solid var(--color-info);
    padding: 8px;
    margin-top: 8px;
    border-radius: var(--radius-sm);
    font-size: 0.875rem;
}

/* Phase 2: HTTP Client Tab */
.dev-toolbar-http-request {
    margin-bottom: 16px;
    padding: 12px;
    background: #f9fafb;
    border-radius: var(--radius-md);
    border-left: 3px solid var(--color-success);
}

.dev-toolbar-http-request.warning {
    border-left-color: var(--color-warning);
    background: #fffbeb;
}

.dev-toolbar-http-request.critical {
    border-left-color: var(--color-danger);
    background: #fef2f2;
}

.dev-toolbar-http-header {
    font-family: var(--font-mono);
    font-size: 0.875rem;
    margin-bottom: 6px;
}

.dev-toolbar-http-time {
    color: #6b7280;
    font-weight: 600;
}

.dev-toolbar-http-status {
    font-size: 0.875rem;
    margin-bottom: 4px;
}

.dev-toolbar-http-location {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
}

/* Phase 2: Cache Tab */
.dev-toolbar-cache-stats {
    font-size: 0.875rem;
    margin-bottom: 16px;
    font-family: var(--font-mono);
}

.dev-toolbar-cache-stats div {
    margin-bottom: 4px;
}

.dev-toolbar-cache-operation {
    margin-bottom: 12px;
    padding: 10px;
    background: #f9fafb;
    border-radius: var(--radius-sm);
}

.dev-toolbar-cache-op-header {
    font-family: var(--font-mono);
    font-size: 0.875rem;
    margin-bottom: 4px;
}

.dev-toolbar-cache-ttl,
.dev-toolbar-cache-size {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
}

/* Phase 2: Timeline Tab */
.dev-toolbar-timeline-item {
    margin-bottom: 16px;
    padding: 10px;
    background: #f9fafb;
    border-radius: var(--radius-md);
}

.dev-toolbar-timeline-item.bottleneck {
    background: #fef2f2;
    border-left: 3px solid var(--color-danger);
}

.dev-toolbar-timeline-label {
    font-weight: 600;
    font-size: 0.875rem;
    margin-bottom: 8px;
}

.dev-toolbar-timeline-bar-container {
    position: relative;
    height: 24px;
    background: #e5e7eb;
    border-radius: var(--radius-sm);
    overflow: hidden;
    margin-bottom: 8px;
}

.dev-toolbar-timeline-bar {
    position: absolute;
    height: 100%;
    background: var(--color-primary);
    transition: width 0.3s ease;
}

.dev-toolbar-timeline-item.bottleneck .dev-toolbar-timeline-bar {
    background: var(--color-danger);
}

.dev-toolbar-timeline-time {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    font-family: var(--font-mono);
    font-size: 0.75rem;
    font-weight: 600;
    color: #111827;
}

.dev-toolbar-timeline-events {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 8px;
}

.dev-toolbar-timeline-subevent {
    margin-bottom: 4px;
    padding-left: 8px;
}

/* Request History */
.dev-toolbar-request-history-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 8px;
    padding: 12px;
    background: #f9fafb;
    border-radius: var(--radius-sm);
    font-family: var(--font-mono);
    font-size: 0.875rem;
    margin-bottom: 16px;
}

.dev-toolbar-request-history-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.dev-toolbar-request-history-item {
    padding: 10px 12px;
    background: #f9fafb;
    border-radius: var(--radius-sm);
    border-left: 3px solid var(--color-success);
    font-size: 0.875rem;
}

.dev-toolbar-request-history-item.warning {
    border-left-color: var(--color-warning);
    background: #fffbeb;
}

.dev-toolbar-request-history-item.slow {
    border-left-color: var(--color-danger);
    background: #fef2f2;
}

.dev-toolbar-request-history-header {
    font-family: var(--font-mono);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.dev-toolbar-request-uri {
    color: #6b7280;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dev-toolbar-request-time-ago {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-left: auto;
}

.dev-toolbar-request-history-meta {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
    display: flex;
    gap: 8px;
}
CSS;

        $nonce = NonceHelper::get();
        return sprintf('<style nonce="%s">%s</style>', htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'), $css);
    }

    /**
     * Render inline JavaScript
     *
     * @return string JavaScript in <script> tag
     */
    private function renderJavaScript(): string
    {
        $js = <<<'JS'
(function() {
    'use strict';

    const DevToolbar = {
        miniBar: null,
        panel: null,
        currentTab: 'queries',

        init() {
            this.miniBar = document.querySelector('.dev-toolbar-mini');
            this.panel = document.querySelector('.dev-toolbar-panel');

            if (!this.miniBar || !this.panel) {
                return;
            }

            // Restore state from localStorage
            this.restoreMaximizedState();
            this.restoreOpenState();

            this.attachEventListeners();
            this.setActiveTab(this.currentTab);
        },

        attachEventListeners() {
            this.miniBar.addEventListener('click', () => {
                this.togglePanel();
            });

            document.querySelectorAll('.dev-toolbar-panel-tab').forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabName = e.target.closest('.dev-toolbar-panel-tab').dataset.tab;
                    this.setActiveTab(tabName);
                });
            });

            const closeBtn = document.querySelector('.dev-toolbar-panel-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    this.closePanel();
                });
            }

            const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
            if (maximizeBtn) {
                maximizeBtn.addEventListener('click', () => {
                    this.toggleMaximize();
                });
            }

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.panel.classList.contains('open')) {
                    this.closePanel();
                }
            });

            // Attach alert dismiss handlers
            this.attachAlertHandlers();
        },

        attachAlertHandlers() {
            // Dismiss all alerts
            const dismissAllBtn = document.querySelector('.dev-toolbar-alerts-dismiss');
            if (dismissAllBtn) {
                dismissAllBtn.addEventListener('click', () => {
                    const alertsContainer = document.querySelector('.dev-toolbar-alerts');
                    if (alertsContainer) {
                        alertsContainer.classList.add('dismissed');
                    }
                });
            }

            // Dismiss individual alerts
            document.querySelectorAll('.dev-toolbar-alert-close').forEach(closeBtn => {
                closeBtn.addEventListener('click', (e) => {
                    const alert = e.target.closest('.dev-toolbar-alert');
                    if (alert) {
                        alert.classList.add('dismissed');

                        // After animation, check if all alerts are dismissed
                        setTimeout(() => {
                            const alertsContainer = document.querySelector('.dev-toolbar-alerts');
                            const remainingAlerts = alertsContainer?.querySelectorAll('.dev-toolbar-alert:not(.dismissed)');

                            if (remainingAlerts && remainingAlerts.length === 0) {
                                // All alerts dismissed, hide container
                                alertsContainer.classList.add('dismissed');
                            }
                        }, 300);
                    }
                });
            });
        },

        togglePanel() {
            if (this.panel.classList.contains('open')) {
                this.closePanel();
            } else {
                this.openPanel();
            }
        },

        openPanel() {
            this.panel.classList.add('open');

            // Save state to localStorage
            try {
                localStorage.setItem('devToolbar.open', '1');
            } catch (e) {
                console.warn('DevToolbar: Could not save open state', e);
            }
        },

        closePanel() {
            this.panel.classList.remove('open');

            // Save state to localStorage
            try {
                localStorage.setItem('devToolbar.open', '0');
            } catch (e) {
                console.warn('DevToolbar: Could not save open state', e);
            }
        },

        toggleMaximize() {
            this.panel.classList.toggle('maximized');
            const isMaximized = this.panel.classList.contains('maximized');

            // Save state to localStorage
            try {
                localStorage.setItem('devToolbar.maximized', isMaximized ? '1' : '0');
            } catch (e) {
                console.warn('DevToolbar: Could not save maximized state', e);
            }

            // Update maximize button icon
            const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
            if (maximizeBtn) {
                maximizeBtn.textContent = isMaximized ? '⛶' : '⛶';
                maximizeBtn.title = isMaximized ? 'Restore' : 'Maximize';
            }
        },

        restoreMaximizedState() {
            try {
                const isMaximized = localStorage.getItem('devToolbar.maximized') === '1';
                if (isMaximized) {
                    this.panel.classList.add('maximized');

                    // Update button on next tick to ensure DOM is ready
                    setTimeout(() => {
                        const maximizeBtn = document.querySelector('.dev-toolbar-panel-maximize');
                        if (maximizeBtn) {
                            maximizeBtn.title = 'Restore';
                        }
                    }, 0);
                }
            } catch (e) {
                console.warn('DevToolbar: Could not restore maximized state', e);
            }
        },

        restoreOpenState() {
            try {
                const isOpen = localStorage.getItem('devToolbar.open') === '1';
                if (isOpen) {
                    // Add 'open' class without triggering save again
                    this.panel.classList.add('open');
                }
            } catch (e) {
                console.warn('DevToolbar: Could not restore open state', e);
            }
        },

        setActiveTab(tabName) {
            this.currentTab = tabName;
            console.log('Setting active tab:', tabName);

            document.querySelectorAll('.dev-toolbar-panel-tab').forEach(tab => {
                if (tab.dataset.tab === tabName) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });

            const panes = document.querySelectorAll('.dev-toolbar-panel-tab-pane');
            console.log('Found panes:', panes.length);
            
            panes.forEach(pane => {
                console.log('Pane tab:', pane.dataset.tab, 'matches:', pane.dataset.tab === tabName);
                if (pane.dataset.tab === tabName) {
                    pane.classList.add('active');
                    console.log('Activated pane for', tabName);
                } else {
                    pane.classList.remove('active');
                }
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => DevToolbar.init());
    } else {
        DevToolbar.init();
    }
})();
JS;

        $nonce = NonceHelper::get();
        return sprintf('<script nonce="%s">%s</script>', htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'), $js);
    }
}
