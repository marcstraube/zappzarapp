# Review 16: Kubernetes Configuration

**Role**: Kubernetes and Container Orchestration Expert

**Weight**: 5% of final score

**Report Location**: `reports/review-16-kubernetes.md`

---

## Verification Commands

```bash
# List K8s manifests
find kubernetes/ -name "*.yaml"

# Validate manifests (if kubectl available)
kubectl apply --dry-run=client -f kubernetes/

# Check for security contexts
grep -r "securityContext" kubernetes/
```

---

## Analysis Checklist

### A. Manifest Quality

For each manifest:

- [ ] API version current
- [ ] Labels consistent
- [ ] Annotations appropriate
- [ ] Resource limits defined
- [ ] Health probes configured
- [ ] Security context defined

### B. Security

- [ ] Non-root containers
- [ ] Read-only root filesystem
- [ ] Capabilities dropped
- [ ] Network policies present
- [ ] Secrets management (not in manifests)
- [ ] RBAC configured

### C. High Availability

- [ ] Replicas configurable
- [ ] Pod disruption budgets
- [ ] Anti-affinity rules
- [ ] Horizontal pod autoscaler

### D. Configuration Management

- [ ] ConfigMaps for configuration
- [ ] Secrets for sensitive data
- [ ] Environment-specific overlays
- [ ] Helm charts (if applicable)

### E. Networking

- [ ] Services defined correctly
- [ ] Ingress configuration
- [ ] Internal communication secured

---

## Output Format

See `00-overview.md` for standard report format.
