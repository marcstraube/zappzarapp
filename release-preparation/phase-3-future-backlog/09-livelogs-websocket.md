# LiveLogs via WebSocket

**Status:** Planned **Size:** Large **Scope:** feature **Created:** 2026-01-19
**Planning:** Required

## Context

Feature request for DevDashboard real-time log streaming.

## Goal

Investigate real-time log streaming to DevDashboard via WebSocket.

## Technical Approach

1. WebSocket endpoint in Node.js backend
2. Connect to Docker API or `docker logs --follow`
3. Stream logs to browser
4. Filter by service/container

## Challenges

- Docker socket access from container
- Authentication/authorization
- Performance with high log volume
- Log buffering strategy

## Alternatives

- Mercure for SSE-based streaming
- Polling (simpler but less real-time)

## Files

- `src/node/backend/websocket/logStreamer.ts`
- `templates/dev-dashboard/partials/log-viewer.php`
