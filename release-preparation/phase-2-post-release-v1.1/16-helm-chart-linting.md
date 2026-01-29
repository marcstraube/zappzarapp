# Helm Chart Linting

**Status:** Planned **Size:** Small **Scope:** chore **Created:** 2026-01-20
**Planning:** Not required

## Context

Kubernetes Helm charts in `kubernetes/` should be validated.

## Goal

Add `make helm-lint` to validate Helm chart syntax and best practices.

## What It Validates

- Chart.yaml validity
- Template syntax (Go templates)
- Values.yaml structure
- Kubernetes manifest validity
- Best practices (labels, resources, etc.)

## Implementation

```makefile
helm-lint: ## Lint Helm charts
    @helm lint kubernetes/
    @helm template kubernetes/ --dry-run > /dev/null && echo "✓ Templates render successfully"
```

## Additional Checks

- `helm template --debug` for detailed output
- `kubeval` or `kubeconform` for K8s schema validation (optional)

## Files

- `Makefile`
- `documentation/development/MAKEFILE-REFERENCE.md`
