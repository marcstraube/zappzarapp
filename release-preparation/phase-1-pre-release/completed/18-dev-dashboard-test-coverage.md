# 18: Test Coverage Display in Dev Dashboard

## Goal

Display test coverage results in the Dev Dashboard with web-triggered refresh
and stale detection (similar to documentation feature).

## Tasks

1. **Coverage display**
   - Show PHP coverage percentage and report
   - Show Node/TypeScript coverage percentage and report
   - Link to detailed HTML coverage reports

2. **Web-triggered refresh**
   - Add "Refresh Coverage" button
   - Execute `make test-coverage-php` / `make test-coverage-node`
   - Show progress indicator during generation
   - Display last-run timestamp

3. **Stale detection**
   - Track when coverage was last generated
   - Compare against source file modification times
   - Show "stale" warning when source changed since last run
   - Similar UX to existing documentation stale detection

4. **Coverage reports location**
   - PHP: `coverage/php/` or similar
   - Node: `coverage/node/` or similar
   - Embed or iframe HTML reports in dashboard

## Files to Investigate

- `src/php/DevDashboard/` - Existing dashboard code
- `src/php/DevDashboard/Services/DocsService.php` - Stale detection reference
- `Makefile` - Coverage targets

## Priority

Medium - Developer productivity feature.

## Notes

Mirror the UX pattern from the existing documentation refresh feature.
