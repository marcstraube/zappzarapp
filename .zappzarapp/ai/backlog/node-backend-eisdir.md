# Node Backend EISDIR Error

**Status:** Open **Size:** Small **Scope:** fix **Created:** 2026-01-22
**Planning:** Not required

## Context

Node backend container crashes on startup with EISDIR error when trying to read
a file that is actually a directory (bind mount issue with lockfiles).

## Goal

Node backend container starts successfully without EISDIR errors.

## Problem

```text
Error: EISDIR: illegal operation on a directory, read
    at Object.readSync (node:fs:739:18)
    at tryReadSync (node:fs:416:20)
    at readFileSync (node:fs:470:19)
    at startServer (/app/src/node/backend/server.ts:47:12)
```

The error occurs at `server.ts:47` where `readFileSync` is called on a path that
has been created as a directory instead of a file (Docker bind mount behavior
when file doesn't exist on host).

## Root Cause

When Docker bind mounts a file that doesn't exist on the host, it creates a
directory instead. This affects:

- `composer.lock` (PHP)
- `pnpm-lock.yaml` (Node)
- Potentially other files read by `server.ts:47`

## Implementation

1. Identify which file `server.ts:47` is trying to read
2. Either:
   - Ensure the file exists before container start
   - Add existence check before `readFileSync`
   - Use try-catch with fallback

## Files

- `src/node/backend/server.ts:47`
- Potentially `compose.override.yaml` (bind mount config)

## Notes

- This is a development environment issue (bind mounts)
- Production images don't have this problem (files are copied into image)
- Related to lockfile bind mount issues discovered during lint session
