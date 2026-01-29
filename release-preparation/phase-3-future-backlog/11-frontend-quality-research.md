# Frontend Quality Testing Research

**Status:** Planned **Size:** Small **Scope:** docs **Created:** 2026-01-20
**Planning:** Not required

## Context

Research task to evaluate additional frontend quality tools for future
implementation.

## Goal

Research and document tools for accessibility, performance, and visual
regression testing to inform future decisions.

## Areas to Research

### 1. Accessibility Testing (a11y)

| Tool           | Type              | Integration                           |
| -------------- | ----------------- | ------------------------------------- |
| **axe-core**   | Runtime/CI        | Jest, Playwright, Storybook addon     |
| **pa11y**      | CLI/CI            | Standalone, CI pipelines              |
| **Lighthouse** | Browser/CI        | Chrome DevTools, CI via lighthouse-ci |
| **WAVE**       | Browser extension | Manual testing                        |

**Questions:**

- Which level of WCAG compliance is needed (A, AA, AAA)?
- Automated vs. manual testing balance?
- Integration with chosen component library?

### 2. Performance Testing

| Tool           | Type         | Best For                    |
| -------------- | ------------ | --------------------------- |
| **Lighthouse** | Synthetic    | Core Web Vitals, SEO, a11y  |
| **k6**         | Load testing | API endpoints, stress tests |
| **Artillery**  | Load testing | HTTP, WebSocket, scenarios  |
| **Web Vitals** | RUM          | Real user metrics           |

**Questions:**

- API load testing vs. frontend performance?
- Synthetic vs. real user monitoring?
- CI/CD integration (performance budgets)?

### 3. Visual Regression Testing

| Tool           | Type             | Cost             |
| -------------- | ---------------- | ---------------- |
| **Playwright** | Screenshots      | Free, built-in   |
| **Percy**      | Cloud comparison | Paid (free tier) |
| **Chromatic**  | Storybook-native | Paid (free tier) |
| **BackstopJS** | Self-hosted      | Free             |
| **reg-suit**   | Self-hosted      | Free             |

**Questions:**

- Self-hosted vs. cloud service?
- Storybook integration needed?
- How many snapshots/components?

### 4. E2E/Integration Testing

| Tool           | Language | Best For              |
| -------------- | -------- | --------------------- |
| **Playwright** | JS/TS    | Cross-browser, modern |
| **Cypress**    | JS/TS    | Developer experience  |
| **Puppeteer**  | JS/TS    | Chrome-specific       |

## Deliverable

Update this task with findings and recommendations after research, then create
specific implementation tasks as needed.
