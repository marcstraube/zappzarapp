# Frontend Testing Framework (Storybook)

**Status:** Planned **Size:** Large **Scope:** feature **Created:** 2026-01-19
**Planning:** Required

## Context

Evaluate Storybook or similar for component testing and accessibility.

## Goal

Decide on and implement frontend component testing/documentation framework.

## Benefits

- Visual component documentation
- Isolated component development
- Accessibility testing (color contrast, WCAG)
- Screenshot testing for visual regression

## Tool Comparison

| Tool          | Vite Support           | Bundle Size   | DX               | Best For                    |
| ------------- | ---------------------- | ------------- | ---------------- | --------------------------- |
| **Storybook** | ✅ Native              | Large (~20MB) | ⭐⭐⭐ Ecosystem | Established projects, teams |
| **Histoire**  | ✅ Native (Vite-first) | Small (~2MB)  | ⭐⭐⭐⭐ Fast    | Vue/Svelte, Vite-native     |
| **Ladle**     | ✅ Native              | Tiny (~1MB)   | ⭐⭐⭐ Minimal   | React, minimal footprint    |

## Storybook Pros/Cons

**Pros:**

- Largest ecosystem (addons, integrations)
- Best documentation, community support
- Works with any framework
- Chromatic for visual regression (paid)

**Cons:**

- Heavyweight, slow startup
- Complex configuration
- Overkill for small projects

## Recommendation

- **Vue/Svelte project → Histoire** (Vite-native, fast)
- **React project → Ladle** (minimal) or **Storybook** (full-featured)
- **Unknown/Mixed → Storybook** (most flexible)

## Questions to Answer

- Which frontend framework will be used?
- How important is visual regression testing?
- CI/CD integration requirements?
