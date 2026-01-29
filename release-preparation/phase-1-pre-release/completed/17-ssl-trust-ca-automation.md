# 17: Automate SSL CA Trust for Unix Systems

## Goal

Automate `make ssl-trust-ca` for Unix-based systems instead of only displaying
the commands. Evaluate integration into `make setup`.

## Tasks

1. **Audit current state**
   - [ ] Check if `make ssl-trust-ca` is mentioned in `make setup`
   - [ ] Check if mentioned in documentation as required step
   - [ ] Review what the current target does (display vs execute)

2. **Implement automation**
   - Detect OS (Linux distro, macOS)
   - Execute appropriate trust commands with sudo
   - Handle different certificate stores:
     - Debian/Ubuntu: `update-ca-certificates`
     - Fedora/RHEL: `update-ca-trust`
     - macOS: `security add-trusted-cert`
   - Provide clear feedback on success/failure

3. **Integration decision**
   - Evaluate if `make setup` should call `make ssl-trust-ca`
   - Consider: first-time setup vs. repeated runs
   - Consider: sudo requirement implications

## Files to Investigate

- `Makefile` - `ssl-trust-ca` and `setup` targets
- `docs/` - Setup documentation
- `.zappzarapp/scripts/` - Existing automation scripts

## Priority

Medium - Reduces manual setup steps for new developers.

## Considerations

- Requires sudo - may not be appropriate for automated `make setup`
- Some environments (CI, containers) don't need CA trust
- Could offer `make setup-full` that includes CA trust
