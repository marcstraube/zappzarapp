# Task 03: Add Canary Deployment Examples

## Priority

LOW - Future Backlog

## Estimated Effort

4-6 hours

## Context

Canary deployments allow gradual rollout of new versions, reducing risk. The
boilerplate already supports Kubernetes via Helm, but doesn't include canary
deployment patterns.

## Current State

- Kubernetes documentation exists
- Blue-green deployment mentioned
- No canary deployment examples

## Target State

1. Add Istio-based canary deployment example
2. Add Nginx-based weighted routing example
3. Document rollback procedures
4. Include monitoring for canary metrics

## Implementation Outline

### Istio Canary

**Create `kubernetes/canary/virtual-service.yaml`:**

```yaml
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: zappzarapp
spec:
  hosts:
    - zappzarapp.example.com
  http:
    - route:
        - destination:
            host: zappzarapp-stable
            port:
              number: 80
          weight: 90
        - destination:
            host: zappzarapp-canary
            port:
              number: 80
          weight: 10
```

### Progressive Rollout

```bash
# Start: 100% stable
make k8s-canary-start

# Phase 1: 10% canary
make k8s-canary-10

# Phase 2: 50% canary
make k8s-canary-50

# Complete: 100% new version
make k8s-canary-complete

# Rollback if issues
make k8s-canary-rollback
```

## Notes

- Requires Istio service mesh or similar
- Simpler weighted routing possible with Nginx ingress
- Combine with monitoring to auto-rollback on errors
- Consider Argo Rollouts for GitOps approach
