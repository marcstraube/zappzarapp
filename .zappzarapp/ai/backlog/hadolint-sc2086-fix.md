# Hadolint SC2086 Fix

## Overview

Fix shell quoting issue found by hadolint.

## Issue

```
-:143 SC2086 info: Double quote to prevent globbing and word splitting.
```

## Fix

Add double quotes around variable expansions to prevent word splitting.

## Command

```bash
make lint-docker
```

## Reference

- [ShellCheck SC2086](https://www.shellcheck.net/wiki/SC2086)
