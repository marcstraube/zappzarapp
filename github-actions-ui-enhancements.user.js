// ==UserScript==
// @name         GitHub Actions UI Enhancements
// @namespace    https://github.com/gemini-userscripts
// @version      1.0
// @description  Keeps workflow steps collapsed by default and constrains the log module height on GitHub Actions run pages.
// @author       Gemini
// @match        https://github.com/*
// @grant        none
// @license      MIT
// ==/UserScript==

(function() {
    'use strict';

    if (window.location.pathname.includes('/actions/runs/')) {

        // --- PART 2: COLLAPSE WORKFLOW STEPS ---
        const userOpenedSteps = new Set(); // Stores IDs of steps the user has explicitly opened.

        document.body.addEventListener('mousedown', (e) => {
            const details = e.target.closest('details[data-step-number]');
            if (details && details.id) {
                // If the user is opening it (it's currently closed)...
                if (!details.hasAttribute('open')) {
                    userOpenedSteps.add(details.id);
                }
                // If the user is closing it (it's currently open)...
                else {
                    userOpenedSteps.delete(details.id);
                }
            }
        }, true);

        const observer = new MutationObserver(mutations => {
            for (const mutation of mutations) {
                // Case 1: An 'open' attribute changed on an existing element.
                if (mutation.type === 'attributes' && mutation.attributeName === 'open') {
                    const detailsElement = mutation.target;

                    if (detailsElement.matches('details[data-step-number]')) {
                        // If it was opened...
                        if (detailsElement.hasAttribute('open')) {
                            // ...and its ID is NOT in our set, it was an auto-open. Close it.
                            if (!userOpenedSteps.has(detailsElement.id)) {
                                detailsElement.removeAttribute('open');
                            }
                        }
                    }
                }
                // Case 2: New nodes were added to the DOM (e.g., entire log section re-rendered).
                else if (mutation.type === 'childList') {
                    if (mutation.addedNodes.length > 0 && mutation.addedNodes[0].nodeType === Node.ELEMENT_NODE) {
                        const addedElement = mutation.addedNodes[0];
                        if (addedElement.matches && addedElement.matches('details[data-step-number]')) {
                            if (addedElement.hasAttribute('open') && !userOpenedSteps.has(addedElement.id)) {
                                addedElement.removeAttribute('open');
                            }
                        }
                        const newDetailsElements = addedElement.querySelectorAll ? addedElement.querySelectorAll('details[data-step-number]') : [];
                        for (const newDetails of newDetailsElements) {
                            if (newDetails.hasAttribute('open') && !userOpenedSteps.has(newDetails.id)) {
                                newDetails.removeAttribute('open');
                            }
                        }
                    }
                }
            }
        });

        observer.observe(document.body, {
            subtree: true,
            attributes: true,
            attributeFilter: ['open'],
            childList: true,
        });

        document.querySelectorAll('details[data-step-number][open]').forEach(details => {
            if (!userOpenedSteps.has(details.id)) {
                details.removeAttribute('open');
            }
        });

        // --- PART 3: CONSTRAIN LOG DISPLAY HEIGHT ---
        const actionPageSpecificStyles = `
            /* Overall module container - set max-height and make it a flex column */
            .actions-fullwidth-module.js-selected-check-run {
                max-height: calc(100vh - 156px) !important; /* Force max-height */
                display: flex;
                flex-direction: column;
            }

            /* The primary log container (#logs) - should take available space but NOT handle scrolling itself */
            #logs {
                flex-grow: 1; /* Make it fill available vertical space within its parent */
                overflow-y: visible !important; /* Let its child (js-checks-log-display) handle scrolling */
                max-height: none !important; /* Ensure it plays well with flex-grow */
                min-height: 0; /* Allow flex item to shrink */
            }

            /* FINAL SCROLL TARGET: The container of all the steps (js-checks-log-display) */
            .js-checks-log-display {
                flex-grow: 1; /* Make it fill available vertical space within #logs */
                max-height: calc(100vh - 160px) !important; /* Use a direct vh calculation */
                overflow-y: auto !important; /* Make its content scrollable */
                min-height: 0; /* Allow flex item to shrink */
            }
        `;
        const actionStyleSheet = document.createElement("style");
        actionStyleSheet.innerText = actionPageSpecificStyles;
        document.head.appendChild(actionStyleSheet);
    }
})();
