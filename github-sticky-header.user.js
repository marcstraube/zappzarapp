// ==UserScript==
// @name         GitHub Sticky Header
// @namespace    https://github.com/gemini-userscripts
// @version      2.7
// @description  Makes multiple GitHub headers sticky, and ensures PageLayout-columns (sidebar/main content wrapper) is sticky and contained.
// @author       Gemini
// @match        https://github.com/*
// @grant        none
// @license      MIT
// ==/UserScript==

(function() {
    'use strict';

    const stickyHeaderStyles = `
        /* Main GitHub Header (highest priority) */
        .js-header-wrapper {
            position: sticky !important;
            top: 0 !important;
            z-index: 1000 !important;
            background-color: var(--color-header-bg) !important;
        }

        /* Workflow Run Page Header (below main header) */
        .run-header.js-sticky {
            position: sticky !important;
            top: 60px !important;
            z-index: 999 !important;
            background-color: var(--bgColor-default) !important;
        }

        /* PageHeader-contextBar */
        [class*="PageHeader-contextBar"] {
            position: sticky !important;
            top: 60px !important; /* Sticks below the main GitHub header */
            z-index: 998 !important; /* Higher than PageLayout-header */
            background-color: var(--bgColor-default) !important;
        }
        [class*="PageHeader-contextBar"] > * {
             background-color: var(--bgColor-default) !important;
        }


        /* PageLayout-header (e.g., the title bar) */
        .PageLayout-header.PageLayout-region {
            position: sticky !important;
            top: 100px !important; /* 60px (main) + 40px (estimated contextBar height) */
            z-index: 997 !important; /* Higher than PageLayout-columns */
            background-color: var(--bgColor-default) !important;
        }
        .PageLayout-header.PageLayout-region > * {
            background-color: var(--bgColor-default) !important;
        }

        /* NEW Target: PageLayout-columns - The outer container for sidebars and main content */
        .PageLayout-columns {
            position: sticky !important;
            top: 100px !important; /* Sticks below PageLayout-header (which is at 100px) */
            z-index: 995 !important; /* Below PageLayout-header */
            background-color: var(--bgColor-default) !important; /* Ensure it has a background */
            max-height: calc(100vh - 100px) !important; /* Limit its height based on top position */
            overflow-y: auto !important; /* Make it scrollable internally */
        }

        /* Ensure inner elements flow normally within PageLayout-columns */
        .PageLayout-region.PageLayout-pane.PageLayout-region--dividerNarrow-none-after.PageLayout-pane--sticky.border-right-0,
        nav[aria-label="Workflow run"] {
            position: static !important;
            top: auto !important;
            z-index: auto !important;
            background-color: transparent !important;
            max-height: none !important;
            overflow-y: visible !important;
            height: auto !important; /* Added height auto */
        }

        /* Neutralize potential sticky-breaking properties on other parents */
        .PageLayout-main {
            overflow: visible !important;
        }
    `;
    const styleSheet = document.createElement("style");
    styleSheet.innerText = stickyHeaderStyles;
    document.head.appendChild(styleSheet);

})();