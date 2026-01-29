# 19: Database Backup/Restore via Dev Dashboard

## Goal

Enable database backup and restore operations through the Dev Dashboard web
interface. Update displayed commands to reflect existing make targets.

## Tasks

1. **Web interface for backup/restore**
   - Add "Backup Database" button → executes `make db-backup`
   - Add "Restore Database" dropdown → lists available backups
   - Show backup history with timestamps
   - Confirmation dialog before restore (destructive operation)

2. **Update displayed commands**
   - Review currently shown database commands in dashboard
   - Replace manual commands with `make db-backup` / `make db-restore`
   - Consider if we still need to display commands at all
   - If keeping command display, ensure they match current Makefile

3. **Backup management**
   - List existing backups with sizes and dates
   - Option to delete old backups
   - Show backup location/path

4. **Safety features**
   - Confirm before restore
   - Show warning about data loss
   - Optional: auto-backup before restore

## Files to Investigate

- `src/php/DevDashboard/` - Dashboard code
- `Makefile` - `db-backup` and `db-restore` targets
- Existing dashboard database section

## Priority

Medium - Developer convenience feature.

## Notes

The make targets for db-backup and db-restore already exist. This task is about
exposing them through the web UI and cleaning up any outdated command displays.
