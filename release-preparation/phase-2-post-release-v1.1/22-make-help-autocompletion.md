# Make Help Autocompletion (direnv)

**Status:** Planned **Size:** Small **Scope:** feature **Created:** 2026-01-20
**Planning:** Not required

## Context

`make help FILTER=` parameter exists but lacks shell autocompletion.

## Goal

Add bash/zsh completion for `make help FILTER=<TAB>` that suggests available
categories.

## Current State

- `make help FILTER=docker` works
- Categories shown at end of filtered output
- No autocompletion for FILTER values

## Implementation

```bash
# scripts/make-completion.bash
_make_zappzarapp_help() {
    local cur="${COMP_WORDS[COMP_CWORD]}"
    if [[ "$cur" == FILTER=* ]]; then
        local filter_val="${cur#FILTER=}"
        local categories=$(grep -oP '(?<=^##@ ).*' Makefile | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
        COMPREPLY=($(compgen -P "FILTER=" -W "$categories" -- "$filter_val"))
    fi
}
```

## direnv Integration (.envrc)

```bash
source_up_if_exists
source scripts/make-completion.bash
```

## Files

Create:

- `scripts/make-completion.bash`
- `.envrc` (or update existing)

## Notes

- Must be project-local (not override global make completion)
- direnv ensures completion loads/unloads on directory change
- Document in CONTRIBUTING.md or GETTING-STARTED.md
