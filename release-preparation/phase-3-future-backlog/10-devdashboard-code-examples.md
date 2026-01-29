# DevDashboard: Code Examples Integration

**Status:** Planned **Size:** Medium **Scope:** feature **Created:** 2026-01-21
**Planning:** Required

## Context

Show usage examples for integrated services (Redis, RabbitMQ, DB, etc.)

## Goal

Integrate code examples into DevDashboard. Decision needed on placement.

## Options

| Option                     | Description                | Pro                           | Contra                     |
| -------------------------- | -------------------------- | ----------------------------- | -------------------------- |
| A: Welcome-Seite erweitern | Examples direkt in Welcome | Alles an einem Ort            | Seite wird lang            |
| B: Eigene Unterseite       | Neue Seite `/dev/examples` | Saubere Trennung, erweiterbar | Extra Navigation           |
| C: Tabs auf Welcome        | Examples als Tab           | Kompakt, schneller Zugriff    | Komplexere UI, JS nötig    |
| D: Collapsible Sections    | Ausklappbare Code-Blöcke   | Platzsparend                  | Unübersichtlich bei vielen |

## Recommendation

Option B (eigene Unterseite) - Welcome bleibt "Quick Overview", Examples-Seite
kann pro Service strukturiert werden (PHP + Node.js nebeneinander).

## Files (depends on option)

- Option A/C/D: `templates/dev-dashboard/welcome.php`
- Option B: New `src/php/DevDashboard/Controller/ExamplesController.php`, new
  `templates/dev-dashboard/examples.php`
