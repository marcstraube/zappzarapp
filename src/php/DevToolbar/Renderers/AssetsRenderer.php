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

/* ===================================
   HISTORY TAB STYLES
   =================================== */

.dev-toolbar-history-filters {
    background: #f9fafb;
    padding: 16px;
    border-radius: var(--radius-md);
    margin-bottom: 24px;
}

.dev-toolbar-history-filter-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    align-items: end;
}

.dev-toolbar-filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.dev-toolbar-filter-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
}

.dev-toolbar-filter-select,
.dev-toolbar-filter-input {
    padding: 8px 12px;
    border: 1px solid var(--toolbar-border);
    border-radius: var(--radius-sm);
    font-family: var(--font-sans);
    font-size: 0.875rem;
    background: white;
}

.dev-toolbar-filter-select:focus,
.dev-toolbar-filter-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.dev-toolbar-history-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.dev-toolbar-history-stat-card {
    background: #f9fafb;
    padding: 12px;
    border-radius: var(--radius-sm);
    text-align: center;
    border: 1px solid var(--toolbar-border);
}

.dev-toolbar-history-stat-label {
    font-size: 0.75rem;
    color: #6b7280;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
}

.dev-toolbar-history-stat-value {
    font-family: var(--font-mono);
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
}

.dev-toolbar-history-stat-value.fast { color: var(--color-success); }
.dev-toolbar-history-stat-value.slow { color: var(--color-danger); }

.dev-toolbar-history-trend {
    background: #1f2937;
    padding: 16px;
    border-radius: var(--radius-md);
    margin-bottom: 24px;
}

.dev-toolbar-history-sparkline {
    font-family: var(--font-mono);
    font-size: 1.5rem;
    color: var(--color-primary);
    text-align: center;
    letter-spacing: 2px;
}

.dev-toolbar-history-request-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 24px;
}

.dev-toolbar-history-item {
    padding: 12px;
    background: #f9fafb;
    border-radius: var(--radius-sm);
    border-left: 3px solid var(--color-success);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.dev-toolbar-history-item:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.dev-toolbar-history-item.warning {
    border-left-color: var(--color-warning);
    background: #fffbeb;
}

.dev-toolbar-history-item.slow {
    border-left-color: var(--color-danger);
    background: #fef2f2;
}

.dev-toolbar-history-item-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.dev-toolbar-history-icon {
    font-size: 1rem;
}

.dev-toolbar-history-method {
    font-family: var(--font-mono);
    font-weight: 700;
    font-size: 0.875rem;
    padding: 2px 8px;
    background: #e5e7eb;
    border-radius: var(--radius-sm);
}

.dev-toolbar-history-uri {
    font-family: var(--font-mono);
    font-size: 0.875rem;
    color: #374151;
    flex: 1;
}

.dev-toolbar-history-time-ago {
    font-size: 0.75rem;
    color: #6b7280;
    margin-left: auto;
}

.dev-toolbar-history-item-meta {
    display: flex;
    gap: 12px;
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: #6b7280;
}

.dev-toolbar-history-meta-item {
    display: inline-block;
}

.dev-toolbar-history-export {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.dev-toolbar-btn {
    padding: 8px 16px;
    border: none;
    border-radius: var(--radius-sm);
    font-family: var(--font-sans);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.dev-toolbar-btn-primary {
    background: var(--color-primary);
    color: white;
}

.dev-toolbar-btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.dev-toolbar-btn-secondary {
    background: #6b7280;
    color: white;
}

.dev-toolbar-btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

.dev-toolbar-btn-danger {
    background: var(--color-danger);
    color: white;
}

.dev-toolbar-btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* ===================================
   REQUEST SWITCHER STYLES
   =================================== */

.dev-toolbar-request-switcher {
    position: relative;
    display: flex;
    align-items: center;
    margin-left: 8px;
}

.dev-toolbar-request-switcher-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: transparent;
    border: none;
    font-family: var(--font-sans);
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    border-radius: var(--radius-sm);
}

.dev-toolbar-request-switcher-toggle:hover {
    background: rgba(59, 130, 246, 0.05);
    color: var(--color-primary);
}

.dev-toolbar-request-switcher.loading .dev-toolbar-request-switcher-toggle {
    opacity: 0.5;
    cursor: wait;
}

.dev-toolbar-request-switcher-label {
    font-weight: 600;
}

.dev-toolbar-request-switcher-arrow {
    font-size: 0.75rem;
    transition: transform 0.2s ease;
}

.dev-toolbar-request-switcher.open .dev-toolbar-request-switcher-arrow {
    transform: rotate(180deg);
}

.dev-toolbar-request-switcher-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    min-width: 400px;
    max-height: 400px;
    overflow-y: auto;
    background: white;
    border: 1px solid var(--toolbar-border);
    border-radius: var(--radius-md);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    display: none;
    z-index: 10000;
}

.dev-toolbar-request-switcher.open .dev-toolbar-request-switcher-dropdown {
    display: block;
}

.dev-toolbar-request-switcher-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    font-family: var(--font-mono);
    font-size: 0.875rem;
    cursor: pointer;
    transition: background 0.15s ease;
}

.dev-toolbar-request-switcher-item:hover {
    background: #f3f4f6;
}

.dev-toolbar-request-switcher-item.current {
    background: #dbeafe;
    font-weight: 600;
    color: var(--color-primary);
}

.dev-toolbar-request-icon {
    font-size: 0.875rem;
}

.dev-toolbar-request-method {
    font-weight: 700;
    padding: 2px 6px;
    background: #e5e7eb;
    border-radius: 3px;
}

.dev-toolbar-request-uri {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dev-toolbar-request-time {
    color: #6b7280;
    font-size: 0.75rem;
}

.dev-toolbar-request-label {
    flex: 1;
}

.dev-toolbar-request-switcher-divider {
    height: 1px;
    background: var(--toolbar-border);
    margin: 4px 0;
}

/* ===================================
   NOTIFICATION STYLES
   =================================== */

.dev-toolbar-notification {
    position: fixed;
    bottom: 60px;
    right: 20px;
    padding: 12px 20px;
    border-radius: var(--radius-md);
    background: white;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    font-family: var(--font-sans);
    font-size: 0.875rem;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 10001;
}

.dev-toolbar-notification.show {
    transform: translateY(0);
    opacity: 1;
}

.dev-toolbar-notification-success {
    border-left: 4px solid var(--color-success);
}

.dev-toolbar-notification-error {
    border-left: 4px solid var(--color-danger);
}

/* Export button in REQUEST tab */
.dev-toolbar-export-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--color-primary);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-family: var(--font-sans);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.dev-toolbar-export-btn:hover {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Export icon in HISTORY tab items */
.dev-toolbar-history-export {
    padding: 4px 8px;
    background: transparent;
    border: 1px solid var(--toolbar-border);
    border-radius: var(--radius-sm);
    font-size: 0.875rem;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    opacity: 0;
    margin-left: auto;
}

.dev-toolbar-history-item:hover .dev-toolbar-history-export {
    opacity: 1;
}

.dev-toolbar-history-export:hover {
    background: var(--color-primary);
    color: white;
    border-color: var(--color-primary);
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

    /**
     * StorageManager - Handles localStorage persistence for DevToolbar
     *
     * Stores up to 50 lightweight metadata entries and 20 full request data entries.
     * Implements LRU eviction and automatic quota management.
     */
    const StorageManager = {
        CONFIG_KEY: 'devToolbar.config',
        META_KEY: 'devToolbar.meta',
        DATA_PREFIX: 'devToolbar.req_',
        MAX_METADATA: 50,
        MAX_FULL_DATA: 20,
        MIN_SAFE_ENTRIES: 5,

        // In-memory fallback for private browsing
        memoryStore: {
            meta: [],
            requests: {}
        },
        useMemoryFallback: false,

        /**
         * Initialize storage, handle migration, store current request
         */
        init() {
            // Test localStorage availability
            if (!this.isLocalStorageAvailable()) {
                console.warn('[DevToolbar] localStorage unavailable, using in-memory storage');
                this.useMemoryFallback = true;
            }

            // Handle one-time migration from session storage
            this.handleMigration();

            // Store current request data
            if (window.__DEV_TOOLBAR_DATA__) {
                const { id, metadata, tabs } = window.__DEV_TOOLBAR_DATA__;
                this.storeRequest(id, metadata, tabs);
            }
        },

        /**
         * Check if localStorage is available
         */
        isLocalStorageAvailable() {
            try {
                const testKey = '__devToolbarTest__';
                localStorage.setItem(testKey, '1');
                localStorage.removeItem(testKey);
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Handle one-time migration from session storage
         */
        handleMigration() {
            const config = this.getConfig();

            if (config.migrated) {
                return; // Already migrated
            }

            // Check for migration data injected by PHP
            if (window.__DEV_TOOLBAR_MIGRATION__ && Array.isArray(window.__DEV_TOOLBAR_MIGRATION__)) {
                console.log('[DevToolbar] Migrating', window.__DEV_TOOLBAR_MIGRATION__.length, 'requests from session');

                window.__DEV_TOOLBAR_MIGRATION__.forEach(request => {
                    this.storeRequest(request.id, request.metadata, request.tabs);
                });

                console.log('[DevToolbar] Migration completed');
            }

            // Mark as migrated
            config.migrated = true;
            this.setConfig(config);
        },

        /**
         * Store request with quota handling
         *
         * @param {string} id Request ID
         * @param {Object} metadata Lightweight metadata
         * @param {Object} tabs Full tab HTML content
         */
        storeRequest(id, metadata, tabs) {
            console.log('[StorageManager] Storing request:', id, 'useMemoryFallback:', this.useMemoryFallback);

            try {
                if (this.useMemoryFallback) {
                    this.storeInMemory(id, metadata, tabs);
                    console.log('[StorageManager] Stored in memory, total:', this.memoryStore.meta.length);
                    return;
                }

                // Store metadata
                const metaArray = this.getMetadata();
                metaArray.unshift(metadata); // Add to beginning (newest first)

                // Enforce metadata limit
                if (metaArray.length > this.MAX_METADATA) {
                    metaArray.length = this.MAX_METADATA;
                }

                localStorage.setItem(this.META_KEY, JSON.stringify(metaArray));

                // Store full data
                const fullData = { metadata, tabs };
                localStorage.setItem(this.DATA_PREFIX + id, JSON.stringify(fullData));

                // Enforce quota limits
                this.enforceQuotaLimits();

            } catch (e) {
                if (e.name === 'QuotaExceededError') {
                    console.warn('[DevToolbar] Quota exceeded, evicting oldest entries');
                    this.evictOldest();

                    // Retry
                    try {
                        const metaArray = this.getMetadata();
                        metaArray.unshift(metadata);
                        if (metaArray.length > this.MAX_METADATA) {
                            metaArray.length = this.MAX_METADATA;
                        }
                        localStorage.setItem(this.META_KEY, JSON.stringify(metaArray));
                        localStorage.setItem(this.DATA_PREFIX + id, JSON.stringify({ metadata, tabs }));
                    } catch (retryError) {
                        console.error('[DevToolbar] Failed to store after eviction:', retryError);
                    }
                } else {
                    console.error('[DevToolbar] Storage error:', e);
                }
            }
        },

        /**
         * Store in memory (private browsing fallback)
         */
        storeInMemory(id, metadata, tabs) {
            this.memoryStore.meta.unshift(metadata);
            if (this.memoryStore.meta.length > this.MAX_METADATA) {
                this.memoryStore.meta.length = this.MAX_METADATA;
            }

            this.memoryStore.requests[id] = { metadata, tabs };

            // Evict old full data
            const ids = this.memoryStore.meta.map(m => m.id);
            const keysToKeep = ids.slice(0, this.MAX_FULL_DATA);

            for (const key in this.memoryStore.requests) {
                if (!keysToKeep.includes(key)) {
                    delete this.memoryStore.requests[key];
                }
            }
        },

        /**
         * Retrieve full request data
         *
         * @param {string} id Request ID
         * @return {Object|null} Request data or null
         */
        getRequest(id) {
            try {
                if (this.useMemoryFallback) {
                    return this.memoryStore.requests[id] || null;
                }

                const data = localStorage.getItem(this.DATA_PREFIX + id);
                return data ? JSON.parse(data) : null;
            } catch (e) {
                console.error('[DevToolbar] Failed to retrieve request:', e);
                return null;
            }
        },

        /**
         * Get all metadata entries
         *
         * @return {Array} Array of metadata objects
         */
        getMetadata() {
            try {
                if (this.useMemoryFallback) {
                    return this.memoryStore.meta;
                }

                const data = localStorage.getItem(this.META_KEY);
                return data ? JSON.parse(data) : [];
            } catch (e) {
                console.error('[DevToolbar] Failed to retrieve metadata:', e);
                return [];
            }
        },

        /**
         * Enforce quota limits - keep only MAX_FULL_DATA entries
         */
        enforceQuotaLimits() {
            if (this.useMemoryFallback) {
                return;
            }

            const metaArray = this.getMetadata();
            const fullDataIds = metaArray.slice(0, this.MAX_FULL_DATA).map(m => m.id);

            // Find all stored request keys
            const keysToDelete = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith(this.DATA_PREFIX)) {
                    const id = key.substring(this.DATA_PREFIX.length);
                    if (!fullDataIds.includes(id)) {
                        keysToDelete.push(key);
                    }
                }
            }

            // Delete old entries
            keysToDelete.forEach(key => {
                try {
                    localStorage.removeItem(key);
                } catch (e) {
                    console.error('[DevToolbar] Failed to remove key:', key, e);
                }
            });

            if (keysToDelete.length > 0) {
                console.log('[DevToolbar] Evicted', keysToDelete.length, 'old request entries');
            }
        },

        /**
         * Emergency eviction - delete oldest entries until under MIN_SAFE_ENTRIES
         */
        evictOldest() {
            if (this.useMemoryFallback) {
                return;
            }

            const metaArray = this.getMetadata();

            // Keep only MIN_SAFE_ENTRIES newest metadata
            if (metaArray.length > this.MIN_SAFE_ENTRIES) {
                metaArray.length = this.MIN_SAFE_ENTRIES;
                localStorage.setItem(this.META_KEY, JSON.stringify(metaArray));
            }

            const idsToKeep = metaArray.slice(0, this.MIN_SAFE_ENTRIES).map(m => m.id);

            // Delete all request data except the safe entries
            const keysToDelete = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith(this.DATA_PREFIX)) {
                    const id = key.substring(this.DATA_PREFIX.length);
                    if (!idsToKeep.includes(id)) {
                        keysToDelete.push(key);
                    }
                }
            }

            keysToDelete.forEach(key => {
                try {
                    localStorage.removeItem(key);
                } catch (e) {
                    console.error('[DevToolbar] Failed to remove key during eviction:', key, e);
                }
            });

            console.log('[DevToolbar] Emergency eviction: removed', keysToDelete.length, 'entries');
        },

        /**
         * Clear all DevToolbar data
         */
        clear() {
            if (this.useMemoryFallback) {
                this.memoryStore = { meta: [], requests: {} };
                return;
            }

            try {
                const keysToDelete = [];
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && (key.startsWith('devToolbar.') || key.startsWith(this.DATA_PREFIX))) {
                        keysToDelete.push(key);
                    }
                }

                keysToDelete.forEach(key => localStorage.removeItem(key));
                console.log('[DevToolbar] Cleared all data');
            } catch (e) {
                console.error('[DevToolbar] Failed to clear data:', e);
            }
        },

        /**
         * Get config object
         */
        getConfig() {
            if (this.useMemoryFallback) {
                return { migrated: false };
            }

            try {
                const config = localStorage.getItem(this.CONFIG_KEY);
                return config ? JSON.parse(config) : { migrated: false };
            } catch (e) {
                return { migrated: false };
            }
        },

        /**
         * Set config object
         */
        setConfig(config) {
            if (this.useMemoryFallback) {
                return;
            }

            try {
                localStorage.setItem(this.CONFIG_KEY, JSON.stringify(config));
            } catch (e) {
                console.error('[DevToolbar] Failed to save config:', e);
            }
        }
    };

    const DevToolbar = {
        miniBar: null,
        panel: null,
        currentTab: 'request',
        historyTabInitialized: false,
        isLoadingRequest: false,
        isViewingHistoricalRequest: false,
        currentRequestData: null, // Store initial request data for restoration

        init() {
            console.log('[DevToolbar] Initializing...');

            // Initialize storage FIRST - migrate and store current request
            StorageManager.init();

            this.miniBar = document.querySelector('.dev-toolbar-mini');
            this.panel = document.querySelector('.dev-toolbar-panel');

            if (!this.miniBar || !this.panel) {
                console.error('[DevToolbar] Mini bar or panel not found');
                return;
            }

            // Debug: Check all badges in DOM
            const allBadges = document.querySelectorAll('.dev-toolbar-panel-tab-badge');
            console.log('[DevToolbar] Found', allBadges.length, 'badges in DOM:',
                Array.from(allBadges).map(b => ({
                    parent: b.closest('.dev-toolbar-panel-tab')?.dataset.tab,
                    value: b.textContent
                }))
            );

            // Store current request data for restoration
            this.storeCurrentRequestData();

            // Update badge with count from localStorage
            this.updateRequestBadge();

            // Restore state from localStorage
            this.restoreMaximizedState();
            this.restoreOpenState();

            this.attachEventListeners();
            this.setActiveTab(this.currentTab);
            this.initRequestSwitcher(); // Request switcher is always visible

            console.log('[DevToolbar] Initialization complete');
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

            // Prevent background scrolling when hovering over non-scrollable areas
            this.preventBackgroundScroll();

            // Attach alert dismiss handlers
            this.attachAlertHandlers();
        },

        /**
         * Prevent background page scrolling when mouse is over DevToolbar
         */
        preventBackgroundScroll() {
            // Block all scroll events on entire panel from propagating to background
            if (this.panel) {
                this.panel.addEventListener('wheel', (e) => {
                    e.stopPropagation();

                    // Allow internal scrolling in content area
                    const content = document.querySelector('.dev-toolbar-panel-content');
                    if (content && content.contains(e.target)) {
                        const atTop = content.scrollTop === 0;
                        const atBottom = content.scrollTop + content.clientHeight >= content.scrollHeight;

                        // Prevent scroll bounce at content boundaries
                        if ((atTop && e.deltaY < 0) || (atBottom && e.deltaY > 0)) {
                            e.preventDefault();
                        }
                    } else {
                        // Non-scrollable area (header, alerts, etc.) - block completely
                        e.preventDefault();
                    }
                }, { passive: false });
            }
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

            // Update tab buttons
            const tabButtons = document.querySelectorAll('.dev-toolbar-panel-tab');
            console.log('Found tab buttons:', tabButtons.length);

            tabButtons.forEach(tab => {
                if (tab.dataset.tab === tabName) {
                    tab.classList.add('active');
                    console.log('Activated tab button:', tabName);
                } else {
                    tab.classList.remove('active');
                }
            });

            // Update tab panes
            const panes = document.querySelectorAll('.dev-toolbar-panel-tab-pane');
            console.log('Found panes:', panes.length);

            let activatedPane = false;
            panes.forEach(pane => {
                const paneTab = pane.dataset.tab;
                console.log('Pane:', paneTab, 'Target:', tabName, 'Match:', paneTab === tabName);

                if (paneTab === tabName) {
                    pane.classList.add('active');
                    activatedPane = true;
                    console.log('✓ Activated pane for', tabName);
                } else {
                    pane.classList.remove('active');
                }
            });

            if (!activatedPane) {
                console.error('Warning: No pane activated for tab:', tabName);
            }

            // REQUEST tab has no dynamic content anymore (history removed)

            // Initialize HISTORY tab when it becomes active
            if (tabName === 'history') {
                // Use setTimeout to ensure DOM is fully updated
                setTimeout(() => this.initHistoryTab(), 50);
            }
        },

        // HISTORY TAB: Filtering and Export
        initHistoryTab() {
            const methodFilter = document.getElementById('history-filter-method');
            const statusFilter = document.getElementById('history-filter-status');
            const uriFilter = document.getElementById('history-filter-uri');
            const minTimeFilter = document.getElementById('history-filter-min-time');
            const resetBtn = document.getElementById('history-filter-reset');

            if (!methodFilter) {
                console.log('History tab elements not found, skipping init');
                return; // Tab not active
            }

            // Prevent multiple initializations
            if (this.historyTabInitialized) {
                console.log('History tab already initialized, skipping');
                return;
            }

            console.log('Initializing History tab filters');
            this.historyTabInitialized = true;

            // Render history data from localStorage FIRST
            this.renderHistoryTabData();

            // Attach export button listeners
            this.attachHistoryExportListeners();

            // Attach filter listeners
            [methodFilter, statusFilter, uriFilter, minTimeFilter].forEach(el => {
                el?.addEventListener('input', () => this.filterHistoryRequests());
            });

            resetBtn?.addEventListener('click', () => this.resetHistoryFilters());

            // Export controls
            document.getElementById('history-export-json')?.addEventListener('click',
                () => this.exportHistoryAsJSON());
            document.getElementById('history-export-csv')?.addEventListener('click',
                () => this.exportHistoryAsCSV());
            document.getElementById('history-clear')?.addEventListener('click',
                () => this.clearHistory());
        },

        filterHistoryRequests() {
            const filters = {
                method: document.getElementById('history-filter-method')?.value || '',
                status: document.getElementById('history-filter-status')?.value || '',
                uri: document.getElementById('history-filter-uri')?.value.toLowerCase() || '',
                minTime: parseFloat(document.getElementById('history-filter-min-time')?.value || '0')
            };

            const items = document.querySelectorAll('.dev-toolbar-history-item');
            let visibleCount = 0;

            items.forEach(item => {
                const matches = this.historyItemMatches(item, filters);
                item.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });

            this.updateHistoryListTitle(visibleCount);
        },

        historyItemMatches(item, filters) {
            if (filters.method && item.dataset.method !== filters.method) return false;
            if (filters.status && !item.dataset.status.startsWith(filters.status)) return false;
            if (filters.uri && !item.dataset.uri.toLowerCase().includes(filters.uri)) return false;
            if (filters.minTime > 0 && parseFloat(item.dataset.time) < filters.minTime) return false;
            return true;
        },

        updateHistoryListTitle(visibleCount) {
            const title = document.getElementById('history-list-title');
            if (title) {
                const totalCount = document.querySelectorAll('.dev-toolbar-history-item').length;
                title.textContent = `Request History (${visibleCount} of ${totalCount})`;
            }
        },

        resetHistoryFilters() {
            ['method', 'status', 'uri', 'min-time'].forEach(id => {
                const el = document.getElementById(`history-filter-${id}`);
                if (el) el.value = '';
            });
            this.filterHistoryRequests();
        },

        exportHistoryAsJSON() {
            const data = this.collectVisibleHistoryData();
            const json = JSON.stringify({
                toolbar_version: '2.1.0',
                export_time: new Date().toISOString(),
                requests: data
            }, null, 2);

            this.downloadFile(json, `devtoolbar-history-${Date.now()}.json`, 'application/json');
        },

        exportHistoryAsCSV() {
            const data = this.collectVisibleHistoryData();
            let csv = 'Timestamp,Method,URI,Status,Time (ms),Memory (MB),Queries\n';

            data.forEach(item => {
                const timestamp = new Date(item.timestamp * 1000).toISOString();
                const uri = `"${item.uri.replace(/"/g, '""')}"`;
                csv += `${timestamp},${item.method},${uri},${item.status},${item.time},${item.memory},${item.query_count}\n`;
            });

            this.downloadFile(csv, `devtoolbar-history-${Date.now()}.csv`, 'text/csv');
        },

        downloadFile(content, filename, mimeType) {
            const blob = new Blob([content], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        },

        collectVisibleHistoryData() {
            const items = document.querySelectorAll('.dev-toolbar-history-item:not([style*="display: none"])');
            return Array.from(items).map(item => {
                const memoryText = item.querySelector('.dev-toolbar-history-meta-item:nth-child(3)')?.textContent || '';
                const memoryMatch = memoryText.match(/[\d.]+/);
                const memory = memoryMatch ? parseFloat(memoryMatch[0]) : 0;

                const queriesText = item.querySelector('.dev-toolbar-history-meta-item:nth-child(4)')?.textContent || '';
                const queriesMatch = queriesText.match(/\d+/);
                const queryCount = queriesMatch ? parseInt(queriesMatch[0], 10) : 0;

                return {
                    method: item.dataset.method,
                    uri: item.dataset.uri,
                    status: parseInt(item.dataset.status, 10),
                    time: parseFloat(item.dataset.time),
                    memory: memory,
                    query_count: queryCount,
                    timestamp: this.parseTimeAgo(item.querySelector('.dev-toolbar-history-time-ago')?.textContent || '')
                };
            });
        },

        parseTimeAgo(text) {
            const match = text.match(/(\d+)([smhd])/);
            if (!match) return Math.floor(Date.now() / 1000);

            const value = parseInt(match[1], 10);
            const multipliers = { s: 1, m: 60, h: 3600, d: 86400 };
            return Math.floor(Date.now() / 1000) - (value * (multipliers[match[2]] || 1));
        },

        /**
         * Render HISTORY tab data from localStorage
         */
        renderHistoryTabData() {
            console.log('[HISTORY Tab] Rendering HISTORY tab data');

            const metaArray = StorageManager.getMetadata();
            console.log('[HISTORY Tab] Found', metaArray.length, 'requests in metadata');

            // Update statistics
            const stats = this.calculateHistoryStats(metaArray);

            const totalEl = document.querySelector('[data-history-stat="total"]');
            const avgTimeEl = document.querySelector('[data-history-stat="avg_time"]');
            const avgMemoryEl = document.querySelector('[data-history-stat="avg_memory"]');
            const avgQueriesEl = document.querySelector('[data-history-stat="avg_queries"]');
            const fastestEl = document.querySelector('[data-history-stat="fastest"]');
            const slowestEl = document.querySelector('[data-history-stat="slowest"]');

            if (totalEl) totalEl.textContent = metaArray.length;
            if (avgTimeEl) avgTimeEl.textContent = stats.avg_time.toFixed(0) + 'ms';
            if (avgMemoryEl) avgMemoryEl.textContent = stats.avg_memory.toFixed(1) + 'MB';
            if (avgQueriesEl) avgQueriesEl.textContent = stats.avg_queries.toFixed(1);
            if (fastestEl) fastestEl.textContent = stats.fastest_time.toFixed(0) + 'ms';
            if (slowestEl) slowestEl.textContent = stats.slowest_time.toFixed(0) + 'ms';

            // Update request count
            const countEl = document.getElementById('history-list-count');
            if (countEl) {
                countEl.textContent = metaArray.length;
            }

            // Render request list
            const listContainer = document.getElementById('history-request-list-container');
            if (!listContainer) {
                console.warn('[localStorage] History list container not found');
                return;
            }

            if (metaArray.length === 0) {
                listContainer.innerHTML = '<p style="color: #888;">No request history yet. Reload the page to see requests.</p>';
                return;
            }

            let html = '';

            metaArray.forEach(request => {
                const statusIcon = this.getStatusIcon(request.status);
                const timeAgo = this.timeAgo(request.timestamp);

                // Performance class
                let perfClass = '';
                if (request.time > 500) {
                    perfClass = 'slow';
                } else if (request.time > 200) {
                    perfClass = 'warning';
                }

                html += `<div class="dev-toolbar-history-item ${perfClass}"
                          data-method="${this.escapeHtml(request.method)}"
                          data-uri="${this.escapeHtml(request.uri)}"
                          data-status="${request.status}"
                          data-time="${request.time}"
                          data-request-id="${this.escapeHtml(request.id)}">
                        <div class="dev-toolbar-history-item-header">
                            <span class="dev-toolbar-history-icon">${statusIcon}</span>
                            <span class="dev-toolbar-history-method">${this.escapeHtml(request.method)}</span>
                            <span class="dev-toolbar-history-uri">${this.escapeHtml(request.uri)}</span>
                            <span class="dev-toolbar-history-time-ago">${timeAgo}</span>
                            <button class="dev-toolbar-history-export"
                                    data-request-id="${this.escapeHtml(request.id)}"
                                    title="Export this request">⬇</button>
                        </div>
                        <div class="dev-toolbar-history-item-meta">
                            <span class="dev-toolbar-history-meta-item">Status: ${request.status}</span>
                            <span class="dev-toolbar-history-meta-item">Time: ${request.time.toFixed(0)}ms</span>
                            <span class="dev-toolbar-history-meta-item">Memory: ${(request.memory / 1024 / 1024).toFixed(1)}MB</span>
                            <span class="dev-toolbar-history-meta-item">Queries: ${request.query_count}</span>
                        </div>
                    </div>`;
            });

            listContainer.innerHTML = html;
            console.log('[localStorage] Rendered', metaArray.length, 'requests in HISTORY tab');

            // Attach export listeners after rendering
            setTimeout(() => this.attachHistoryExportListeners(), 50);
        },

        clearHistory() {
            if (!confirm('Clear all request history? This cannot be undone.')) return;

            // Clear localStorage instead of calling server
            StorageManager.clear();

            // Reload page to reset everything
            location.reload();
        },

        // REQUEST SWITCHER: AJAX Navigation
        initRequestSwitcher() {
            const switcher = document.querySelector('.dev-toolbar-request-switcher');
            if (!switcher) return;

            const toggle = switcher.querySelector('.dev-toolbar-request-switcher-toggle');
            const dropdown = switcher.querySelector('.dev-toolbar-request-switcher-dropdown');

            // Populate dropdown from localStorage
            this.populateRequestSwitcher();

            // Toggle dropdown
            toggle?.addEventListener('click', (e) => {
                e.stopPropagation();
                switcher.classList.toggle('open');
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!switcher.contains(e.target)) {
                    switcher.classList.remove('open');
                }
            });

            console.log('[Switcher] Attaching click handler to dropdown');

            // Handle item clicks
            dropdown?.addEventListener('click', (e) => {
                console.log('[Switcher] Dropdown clicked, target:', e.target);
                const item = e.target.closest('.dev-toolbar-request-switcher-item');
                if (!item) {
                    console.log('[Switcher] Click but no item found');
                    return;
                }

                console.log('[Switcher] Item clicked:', item.dataset);

                if (item.dataset.action === 'view-history') {
                    console.log('[Switcher] Opening HISTORY tab');
                    this.setActiveTab('history');
                    switcher.classList.remove('open');
                    return;
                }

                if (item.classList.contains('current')) {
                    switcher.classList.remove('open');

                    // Check if we're currently viewing a historical request
                    if (this.isViewingHistoricalRequest) {
                        // Restore current request data without reloading page
                        this.restoreCurrentRequest();
                    } else {
                        console.log('Already viewing current request');
                    }
                    return;
                }

                const requestId = item.dataset.requestId;

                if (requestId) {
                    this.loadHistoricalRequest(requestId);
                }
            });

        },

        async loadHistoricalRequest(requestId) {
            // Prevent multiple simultaneous loads
            if (this.isLoadingRequest) {
                console.log('Already loading a request, ignoring');
                return;
            }

            this.isLoadingRequest = true;
            const switcher = document.querySelector('.dev-toolbar-request-switcher');
            switcher?.classList.add('loading');

            console.log('[localStorage] Loading historical request:', requestId);

            try {
                // Load from localStorage instead of AJAX
                const requestData = StorageManager.getRequest(requestId);

                if (!requestData) {
                    throw new Error('Request not found in localStorage');
                }

                console.log('[localStorage] Retrieved request data from localStorage');

                // Reconstruct tabs HTML from stored data
                let tabsHtml = '';
                for (const [tabName, content] of Object.entries(requestData.tabs)) {
                    tabsHtml += `<div class="dev-toolbar-panel-tab-pane" data-tab="${tabName}">${content}</div>`;
                }

                if (!tabsHtml) {
                    throw new Error('No tab content found in stored data');
                }

                // Replace tab content
                const contentContainer = document.querySelector('.dev-toolbar-panel-content');
                if (contentContainer) {
                    // Remember current tab before replacing content
                    const currentTab = this.currentTab;
                    console.log('Current tab before replace:', currentTab);

                    // Replace content
                    contentContainer.innerHTML = tabsHtml;

                    // Mark that we're viewing a historical request
                    this.isViewingHistoricalRequest = true;

                    // Reset history tab initialization flag (so it can be re-initialized)
                    this.historyTabInitialized = false;

                    // Wait for DOM to update before re-attaching listeners and activating tab
                    setTimeout(() => {
                        // Re-initialize tab switching for new content
                        this.attachTabEventListeners();

                        // Restore the active tab (re-activate it to show correct pane)
                        console.log('Restoring tab:', currentTab);
                        this.setActiveTab(currentTab);

                        // Show success feedback
                        console.log('[localStorage] Successfully loaded historical request');
                        this.showNotification('Historical request loaded (<10ms)', 'success');
                    }, 50);

                    // Update switcher label and badge immediately
                    const toggle = switcher?.querySelector('.dev-toolbar-request-switcher-toggle');
                    if (toggle) {
                        toggle.dataset.current = requestId;

                        // Update label to show we're viewing history
                        const label = toggle.querySelector('.dev-toolbar-request-switcher-label');
                        if (label) {
                            label.textContent = 'Request (Historical)';
                            label.style.color = '#f59e0b'; // Orange to indicate historical
                        }
                    }
                } else {
                    throw new Error('Content container not found');
                }
            } catch (error) {
                console.error('[localStorage] Failed to load request:', error);
                this.showNotification('Failed: ' + error.message, 'error');
            } finally {
                this.isLoadingRequest = false;
                switcher?.classList.remove('loading');
                switcher?.classList.remove('open');
            }
        },

        attachTabEventListeners() {
            // Re-attach tab click listeners (needed after AJAX content replacement)
            console.log('Re-attaching tab event listeners');
            const tabButtons = document.querySelectorAll('.dev-toolbar-panel-tab');
            console.log('Found', tabButtons.length, 'tab buttons');

            tabButtons.forEach(tab => {
                // Clone and replace to remove old listeners
                const newTab = tab.cloneNode(true);
                tab.parentNode.replaceChild(newTab, tab);

                // Add new listener
                newTab.addEventListener('click', () => {
                    console.log('Tab clicked:', newTab.dataset.tab);
                    this.setActiveTab(newTab.dataset.tab);
                });
            });
        },

        showNotification(message, type = 'info') {
            // Simple notification system
            const notification = document.createElement('div');
            notification.className = `dev-toolbar-notification dev-toolbar-notification-${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        },

        /**
         * Store current request data for later restoration
         */
        storeCurrentRequestData() {
            const contentContainer = document.querySelector('.dev-toolbar-panel-content');
            if (contentContainer) {
                this.currentRequestData = contentContainer.innerHTML;
            }
        },

        /**
         * Restore current request data (return from historical view)
         */
        restoreCurrentRequest() {
            console.log('[localStorage] Restoring current request');

            const contentContainer = document.querySelector('.dev-toolbar-panel-content');
            if (contentContainer && this.currentRequestData) {
                const currentTab = this.currentTab;

                // Restore original content
                contentContainer.innerHTML = this.currentRequestData;

                // Reset flags
                this.isViewingHistoricalRequest = false;
                this.historyTabInitialized = false;

                // Re-attach listeners and restore tab
                setTimeout(() => {
                    this.attachTabEventListeners();
                    this.setActiveTab(currentTab);
                    this.showNotification('Returned to current request', 'success');
                }, 50);

                // Update switcher label
                const switcher = document.querySelector('.dev-toolbar-request-switcher');
                const toggle = switcher?.querySelector('.dev-toolbar-request-switcher-toggle');
                if (toggle) {
                    toggle.dataset.current = 'current';

                    const label = toggle.querySelector('.dev-toolbar-request-switcher-label');
                    if (label) {
                        label.textContent = 'Request';
                        label.style.color = ''; // Reset color
                    }
                }
            }
        },

        /**
         * Update HISTORY tab badge with count from localStorage
         * Note: REQUEST tab has no badge (always shows current request)
         * Other tabs (QUERIES, LOGS, EXCEPTIONS) have static badges from PHP collectors
         */
        updateRequestBadge() {
            const metaArray = StorageManager.getMetadata();
            console.log('[Badge] Updating HISTORY badge with', metaArray.length, 'requests');

            // Update HISTORY badge (only tab that needs JavaScript update)
            const historyBadge = document.querySelector('.dev-toolbar-panel-tab[data-tab="history"] .dev-toolbar-panel-tab-badge');
            if (historyBadge) {
                historyBadge.textContent = metaArray.length;
                console.log('[Badge] HISTORY badge updated to:', metaArray.length);
            } else {
                console.warn('[Badge] HISTORY badge element not found');
            }
        },

        /**
         * Populate request switcher dropdown from localStorage
         */
        populateRequestSwitcher() {
            const dropdown = document.querySelector('.dev-toolbar-request-switcher-dropdown');
            if (!dropdown) return;

            console.log('[localStorage] Populating request switcher from localStorage');

            // Get metadata from storage
            const metaArray = StorageManager.getMetadata();

            // Build dropdown HTML
            let html = '';

            // Current request (highlighted)
            html += `<div class="dev-toolbar-request-switcher-item current">
                <span class="dev-toolbar-request-icon">●</span>
                <span class="dev-toolbar-request-label">Current Request</span>
            </div>`;

            // Recent requests - only show those with full data (MAX_FULL_DATA = 20)
            if (metaArray.length > 0) {
                html += '<div class="dev-toolbar-request-switcher-divider"></div>';

                // Show up to MAX_FULL_DATA (20) recent requests - these have full tab data
                const recentRequests = metaArray.slice(0, StorageManager.MAX_FULL_DATA);

                recentRequests.forEach(request => {
                    const statusIcon = this.getStatusIcon(request.status);

                    html += `<div class="dev-toolbar-request-switcher-item"
                                 data-request-id="${this.escapeHtml(request.id)}">
                            <span class="dev-toolbar-request-icon">${statusIcon}</span>
                            <span class="dev-toolbar-request-method">${this.escapeHtml(request.method)}</span>
                            <span class="dev-toolbar-request-uri">${this.escapeHtml(request.uri)}</span>
                            <span class="dev-toolbar-request-time">${Math.round(request.time)}ms</span>
                        </div>`;
                });

                html += '<div class="dev-toolbar-request-switcher-divider"></div>';
            }

            // View all history link
            html += `<div class="dev-toolbar-request-switcher-item" data-action="view-history">
                <span class="dev-toolbar-request-icon">📋</span>
                <span class="dev-toolbar-request-label">View All History →</span>
            </div>`;

            // Replace dropdown content
            dropdown.innerHTML = html;

            console.log('[localStorage] Populated', metaArray.length, 'requests in switcher');
        },

        /**
         * Get status icon for HTTP status code
         */
        getStatusIcon(status) {
            if (status >= 200 && status < 300) return '✓';
            if (status >= 300 && status < 400) return '↻';
            if (status >= 400 && status < 500) return '⚠';
            if (status >= 500) return '✗';
            return '○';
        },

        /**
         * Escape HTML special characters
         */
        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        /**
         * Render request history from localStorage
         */
        renderRequestHistory() {
            console.log('[REQUEST Tab] Rendering request history');

            const historyList = document.getElementById('dev-toolbar-history-list');
            if (!historyList) {
                console.warn('[REQUEST Tab] History list container not found');
                return;
            }

            const metaArray = StorageManager.getMetadata();
            console.log('[REQUEST Tab] Found', metaArray.length, 'requests in metadata');
            console.log('[REQUEST Tab] Memory fallback active:', StorageManager.useMemoryFallback);

            if (metaArray.length === 0) {
                historyList.innerHTML = '<p style="color: #888;">No request history yet. Reload the page to see requests.</p>';
                return;
            }

            // Calculate statistics
            const stats = this.calculateHistoryStats(metaArray);

            // Update count
            const countEl = document.getElementById('dev-toolbar-history-count');
            if (countEl) {
                countEl.textContent = metaArray.length;
            }

            // Update statistics safely
            const avgTimeEl = document.querySelector('[data-stat="avg_time"]');
            const avgMemoryEl = document.querySelector('[data-stat="avg_memory"]');
            const avgQueriesEl = document.querySelector('[data-stat="avg_queries"]');
            const slowestTimeEl = document.querySelector('[data-stat="slowest_time"]');
            const fastestTimeEl = document.querySelector('[data-stat="fastest_time"]');

            if (avgTimeEl) avgTimeEl.textContent = stats.avg_time.toFixed(2);
            if (avgMemoryEl) avgMemoryEl.textContent = stats.avg_memory.toFixed(2);
            if (avgQueriesEl) avgQueriesEl.textContent = stats.avg_queries.toFixed(1);
            if (slowestTimeEl) slowestTimeEl.textContent = stats.slowest_time.toFixed(2);
            if (fastestTimeEl) fastestTimeEl.textContent = stats.fastest_time.toFixed(2);

            // Render request list (show max 10 recent)
            let html = '';

            const recentRequests = metaArray.slice(0, 10);

            recentRequests.forEach(request => {
                const statusIcon = this.getStatusIcon(request.status);
                const timeAgo = this.timeAgo(request.timestamp);

                // Determine performance class
                let perfClass = '';
                if (request.time > 1000) {
                    perfClass = 'slow';
                } else if (request.time > 500) {
                    perfClass = 'warning';
                }

                html += `<div class="dev-toolbar-request-history-item ${perfClass}">
                    <div class="dev-toolbar-request-history-header">
                        ${statusIcon} <strong>${this.escapeHtml(request.method)}</strong>
                        <span class="dev-toolbar-request-uri">${this.escapeHtml(request.uri)}</span>
                        <span class="dev-toolbar-request-time-ago">${timeAgo}</span>
                    </div>
                    <div class="dev-toolbar-request-history-meta">
                        <span>${request.status}</span> •
                        <span>${request.time.toFixed(2)}ms</span> •
                        <span>${(request.memory / 1024 / 1024).toFixed(2)}MB</span> •
                        <span>${request.query_count} queries</span>
                    </div>
                </div>`;
            });

            historyList.innerHTML = html;
            console.log('[localStorage] Rendered', recentRequests.length, 'requests in history list');
        },

        /**
         * Calculate statistics from metadata array
         */
        calculateHistoryStats(metaArray) {
            if (metaArray.length === 0) {
                return {
                    avg_time: 0,
                    avg_memory: 0,
                    avg_queries: 0,
                    slowest_time: 0,
                    fastest_time: 0
                };
            }

            let totalTime = 0;
            let totalMemory = 0;
            let totalQueries = 0;
            let slowest = 0;
            let fastest = Infinity;

            metaArray.forEach(req => {
                totalTime += req.time;
                totalMemory += req.memory;
                totalQueries += req.query_count;
                if (req.time > slowest) slowest = req.time;
                if (req.time < fastest) fastest = req.time;
            });

            return {
                avg_time: totalTime / metaArray.length,
                avg_memory: (totalMemory / metaArray.length) / 1024 / 1024, // Convert to MB
                avg_queries: totalQueries / metaArray.length,
                slowest_time: slowest,
                fastest_time: fastest === Infinity ? 0 : fastest
            };
        },

        /**
         * Format timestamp as "time ago"
         */
        timeAgo(timestamp) {
            const seconds = Math.floor(Date.now() / 1000 - timestamp);

            if (seconds < 60) return `${seconds}s ago`;
            if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
            if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
            return `${Math.floor(seconds / 86400)}d ago`;
        },

        /**
         * Attach export button listeners in HISTORY tab
         */
        attachHistoryExportListeners() {
            // Remove old listeners to prevent duplicates
            const exportButtons = document.querySelectorAll('.dev-toolbar-history-export');
            exportButtons.forEach(btn => {
                // Clone node to remove old listeners
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);

                // Add new listener
                newBtn.addEventListener('click', (e) => {
                    e.stopPropagation(); // Prevent triggering other click handlers
                    const requestId = newBtn.dataset.requestId;
                    if (requestId) {
                        this.exportRequest(requestId);
                    }
                });
            });

            // Also attach to REQUEST tab export button
            const requestExportBtn = document.querySelector('[data-action="export-current"]');
            if (requestExportBtn) {
                // Clone to remove old listeners
                const newExportBtn = requestExportBtn.cloneNode(true);
                requestExportBtn.parentNode.replaceChild(newExportBtn, requestExportBtn);

                newExportBtn.addEventListener('click', () => {
                    this.exportCurrentRequest();
                });
            }
        },

        /**
         * Export a specific request by ID from localStorage
         */
        exportRequest(requestId) {
            console.log('[Export] Exporting request:', requestId);

            const requestData = StorageManager.getRequest(requestId);
            if (!requestData) {
                alert('Request data not found in localStorage');
                return;
            }

            const data = {
                toolbar_version: '2.1.0',
                export_time: new Date().toISOString(),
                request_id: requestId,
                metadata: requestData.metadata,
                html_data: requestData.tabs
            };

            const json = JSON.stringify(data, null, 2);
            this.downloadFile(json, `devtoolbar-${requestId}.json`, 'application/json');
        },

        /**
         * Export the currently visible request
         */
        exportCurrentRequest() {
            const currentId = document.querySelector('.dev-toolbar-request-switcher-toggle')?.dataset.current || 'current';

            // Collect current request data from visible tab content
            const allData = {};
            const panes = document.querySelectorAll('.dev-toolbar-panel-tab-pane');

            panes.forEach(pane => {
                const tabName = pane.dataset.tab;
                allData[tabName] = pane.innerHTML;
            });

            const data = {
                toolbar_version: '2.1.0',
                export_time: new Date().toISOString(),
                request_id: currentId,
                html_data: allData
            };

            const json = JSON.stringify(data, null, 2);
            this.downloadFile(json, `devtoolbar-${currentId}.json`, 'application/json');
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
