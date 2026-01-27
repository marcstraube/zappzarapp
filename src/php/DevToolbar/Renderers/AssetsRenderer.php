<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

/**
 * Renders inline CSS and JavaScript assets
 *
 * All assets are self-contained and independent of Vite/build process.
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

.dev-toolbar-panel-close {
    margin-left: auto;
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
}

.dev-toolbar-panel-tab-pane {
    display: none;
}

.dev-toolbar-panel-tab-pane.active {
    display: block;
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
CSS;

        return sprintf('<style>%s</style>', $css);
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
        currentTab: 'request',

        init() {
            this.miniBar = document.querySelector('.dev-toolbar-mini');
            this.panel = document.querySelector('.dev-toolbar-panel');

            if (!this.miniBar || !this.panel) {
                return;
            }

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

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.panel.classList.contains('open')) {
                    this.closePanel();
                }
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
        },

        closePanel() {
            this.panel.classList.remove('open');
        },

        setActiveTab(tabName) {
            this.currentTab = tabName;

            document.querySelectorAll('.dev-toolbar-panel-tab').forEach(tab => {
                if (tab.dataset.tab === tabName) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });

            document.querySelectorAll('.dev-toolbar-panel-tab-pane').forEach(pane => {
                if (pane.dataset.tab === tabName) {
                    pane.classList.add('active');
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

        return sprintf('<script>%s</script>', $js);
    }
}
