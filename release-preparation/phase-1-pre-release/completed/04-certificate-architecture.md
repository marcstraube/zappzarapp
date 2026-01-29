# Task 06: Certificate Architecture (Tier 2)

## Priority

MEDIUM - Pre-Release

## Estimated Effort

1-2 hours

## Context

Current setup uses a single SAN certificate for all services. This works for
development but lacks flexibility for production scenarios where:

- Public-facing services (nginx) need Let's Encrypt certificates
- Internal services need separate certificates (internal CA)
- Different certificate rotation schedules are required

## Target Architecture

```
docker/certs/
├── ca/                           # Internal CA
│   ├── ca.crt                    # CA certificate (trust anchor)
│   └── ca.key                    # CA private key (keep secure!)
│
├── nginx/                        # Public-facing
│   ├── cert.crt                  # CA-signed (dev) or Let's Encrypt (prod)
│   └── cert.key
│
├── internal/                     # Internal services
│   ├── cert.crt                  # SAN cert for all internal hostnames
│   ├── cert.key
│   └── ca.crt                    # Copy of CA for easy mounting
│
├── generate-ca.sh                # Generate internal CA
├── generate-internal.sh          # Generate certs signed by CA
└── setup-letsencrypt.sh          # Let's Encrypt for production
```

## Environment Variables

Add to `.env`:

```bash
# Certificate Paths (override for production)
# Development: Uses docker/certs/ defaults
# Production: Point to mounted secrets or Let's Encrypt paths
#
# CERT_PATH_NGINX=/etc/letsencrypt/live/example.com
# CERT_PATH_INTERNAL=/etc/ssl/internal
# CERT_CA_PATH=/etc/ssl/internal/ca.crt
```

## Implementation Steps

### Step 1: Update make setup for Directory Creation

Directories are created via `make setup` (consistent with existing pattern).

In Makefile, update the setup target to create cert directories:

```makefile
# In setup target, after "SSL/TLS Certificates" comment:
@mkdir -p docker/certs/{ca,nginx,internal}
```

### Step 2: Create CA Generation Script

Create `docker/certs/generate-ca.sh`:

```bash
#!/bin/bash
# ============================================================================
# INTERNAL CA GENERATOR (Development/Internal Use)
# ============================================================================
# Generates a Certificate Authority for signing internal service certificates.
# The CA certificate can be added to system trust stores for browser access.
#
# Usage: ./generate-ca.sh
# ============================================================================

set -e

CERT_DIR="$(cd "$(dirname "$0")" && pwd)"
CA_DIR="$CERT_DIR/ca"
DAYS=3650  # 10 years for CA

echo "============================================================================"
echo "Generating Internal Certificate Authority"
echo "============================================================================"

mkdir -p "$CA_DIR"

# Generate CA private key
echo "Generating CA private key..."
openssl genrsa -out "$CA_DIR/ca.key" 4096

# Generate CA certificate
echo "Generating CA certificate..."
openssl req -x509 -new -nodes \
    -key "$CA_DIR/ca.key" \
    -sha256 -days $DAYS \
    -out "$CA_DIR/ca.crt" \
    -subj "/C=DE/ST=Development/L=Local/O=Zappzarapp/OU=Internal CA/CN=Zappzarapp Internal CA"

# Set permissions
chmod 644 "$CA_DIR/ca.crt"
chmod 600 "$CA_DIR/ca.key"

echo "============================================================================"
echo "Internal CA generated successfully!"
echo "============================================================================"
echo "CA Certificate: $CA_DIR/ca.crt"
echo "CA Private Key: $CA_DIR/ca.key (keep secure!)"
echo ""
echo "To trust this CA in your browser/system, run: make ssl-trust-ca"
echo "============================================================================"
```

### Step 3: Create Internal Certificate Generation Script

Create `docker/certs/generate-internal.sh`:

```bash
#!/bin/bash
# ============================================================================
# INTERNAL CERTIFICATE GENERATOR (Signed by Internal CA)
# ============================================================================
# Generates certificates for internal services, signed by the internal CA.
# Requires: CA must exist (run generate-ca.sh first)
#
# Usage: ./generate-internal.sh
# ============================================================================

set -e

CERT_DIR="$(cd "$(dirname "$0")" && pwd)"
CA_DIR="$CERT_DIR/ca"
INTERNAL_DIR="$CERT_DIR/internal"
NGINX_DIR="$CERT_DIR/nginx"
DAYS=365

# Verify CA exists
if [[ ! -f "$CA_DIR/ca.crt" ]] || [[ ! -f "$CA_DIR/ca.key" ]]; then
    echo "ERROR: Internal CA not found. Run ./generate-ca.sh first."
    exit 1
fi

echo "============================================================================"
echo "Generating Internal Service Certificates"
echo "============================================================================"

mkdir -p "$INTERNAL_DIR" "$NGINX_DIR"

# Internal services SAN list
INTERNAL_SANS="DNS:localhost,DNS:nginx,DNS:node,DNS:node-backend,DNS:php,DNS:mariadb,DNS:postgres,DNS:redis,DNS:elasticsearch,DNS:mailpit,DNS:meilisearch,DNS:mercure,DNS:rabbitmq,DNS:seaweedfs,IP:127.0.0.1"

# Generate internal services certificate
echo "Generating internal services certificate..."
openssl genrsa -out "$INTERNAL_DIR/cert.key" 2048

openssl req -new \
    -key "$INTERNAL_DIR/cert.key" \
    -out "$INTERNAL_DIR/cert.csr" \
    -subj "/C=DE/ST=Development/L=Local/O=Zappzarapp/OU=Internal Services/CN=internal.local"

openssl x509 -req -days $DAYS \
    -in "$INTERNAL_DIR/cert.csr" \
    -CA "$CA_DIR/ca.crt" \
    -CAkey "$CA_DIR/ca.key" \
    -CAcreateserial \
    -out "$INTERNAL_DIR/cert.crt" \
    -extfile <(printf "subjectAltName=$INTERNAL_SANS")

rm -f "$INTERNAL_DIR/cert.csr"

# Copy CA cert to internal dir for easy mounting
cp "$CA_DIR/ca.crt" "$INTERNAL_DIR/ca.crt"

# Generate nginx certificate (also signed by CA for consistency)
echo "Generating nginx certificate..."
NGINX_SANS="DNS:localhost,DNS:*.localhost,DNS:nginx,IP:127.0.0.1"

openssl genrsa -out "$NGINX_DIR/cert.key" 2048

openssl req -new \
    -key "$NGINX_DIR/cert.key" \
    -out "$NGINX_DIR/cert.csr" \
    -subj "/C=DE/ST=Development/L=Local/O=Zappzarapp/OU=Web Server/CN=localhost"

openssl x509 -req -days $DAYS \
    -in "$NGINX_DIR/cert.csr" \
    -CA "$CA_DIR/ca.crt" \
    -CAkey "$CA_DIR/ca.key" \
    -CAcreateserial \
    -out "$NGINX_DIR/cert.crt" \
    -extfile <(printf "subjectAltName=$NGINX_SANS")

rm -f "$NGINX_DIR/cert.csr"

# Set permissions
chmod 644 "$INTERNAL_DIR/cert.crt" "$INTERNAL_DIR/ca.crt" "$NGINX_DIR/cert.crt"
chmod 644 "$INTERNAL_DIR/cert.key" "$NGINX_DIR/cert.key"

echo "============================================================================"
echo "Internal certificates generated successfully!"
echo "============================================================================"
echo "Nginx:    $NGINX_DIR/cert.crt"
echo "Internal: $INTERNAL_DIR/cert.crt"
echo "CA:       $INTERNAL_DIR/ca.crt (for service trust)"
echo ""
echo "To avoid browser warnings, run: make ssl-trust-ca"
echo "============================================================================"
```

### Step 4: Update compose.yaml

Update certificate volume mounts to use new paths:

```yaml
# nginx service
volumes:
  # SSL certificates
  - ${CERT_PATH_NGINX:-./docker/certs/nginx}:/etc/nginx/certs:ro

# node service
volumes:
  # Internal certificates + CA for trust
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.crt:/etc/ssl/certs/cert.crt:ro
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.key:/etc/ssl/private/cert.key:ro
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/ca.crt:/etc/ssl/certs/internal-ca.crt:ro

# php service
volumes:
  # Internal CA for TLS verification
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/ca.crt:/etc/ssl/certs/internal-ca.crt:ro

# redis service
volumes:
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.crt:/etc/redis/certs/cert.crt:ro
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.key:/etc/redis/certs/cert.key:ro

# elasticsearch service
volumes:
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.crt:/usr/share/elasticsearch/config/certs/cert.crt:ro
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.key:/usr/share/elasticsearch/config/certs/cert.key:ro

# meilisearch service
volumes:
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.crt:/etc/ssl/certs/cert.crt:ro
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.key:/etc/ssl/private/cert.key:ro

# mercure service
volumes:
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.crt:/etc/mercure/certs/cert.crt:ro
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.key:/etc/mercure/certs/cert.key:ro

# rabbitmq service
volumes:
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.crt:/etc/ssl/certs/cert.crt:ro
  - ${CERT_PATH_INTERNAL:-./docker/certs/internal}/cert.key:/etc/ssl/private/cert.key:ro
```

### Step 5: Update Makefile - New Targets

Add new SSL targets:

```makefile
##@ SSL/TLS Certificates

ssl-ca: ## Generate internal Certificate Authority
	@echo -e "\033[0;33mGenerating internal CA...\033[0m"
	@mkdir -p docker/certs/ca
	@chmod +x docker/certs/generate-ca.sh
	@docker/certs/generate-ca.sh

ssl-internal: ssl-ca ## Generate internal service certificates (signed by CA)
	@echo -e "\033[0;33mGenerating internal certificates...\033[0m"
	@mkdir -p docker/certs/{nginx,internal}
	@chmod +x docker/certs/generate-internal.sh
	@docker/certs/generate-internal.sh

ssl-trust-ca: ## Show instructions to trust internal CA in your system
	@echo "============================================================================"
	@echo "To trust the internal CA in your system:"
	@echo "============================================================================"
	@echo ""
	@echo "macOS:"
	@echo "  sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain docker/certs/ca/ca.crt"
	@echo ""
	@echo "Linux (Debian/Ubuntu):"
	@echo "  sudo cp docker/certs/ca/ca.crt /usr/local/share/ca-certificates/zappzarapp-ca.crt"
	@echo "  sudo update-ca-certificates"
	@echo ""
	@echo "Linux (RHEL/Fedora):"
	@echo "  sudo cp docker/certs/ca/ca.crt /etc/pki/ca-trust/source/anchors/zappzarapp-ca.crt"
	@echo "  sudo update-ca-trust"
	@echo ""
	@echo "Windows:"
	@echo "  Import docker/certs/ca/ca.crt into 'Trusted Root Certification Authorities'"
	@echo "============================================================================"
```

### Step 6: Update make setup

Modify setup to use `ssl-internal`:

```makefile
setup: ## Initial project setup (directories, secrets, certificates, dependencies)
	# ... existing steps ...

	# SSL/TLS Certificates
	@mkdir -p docker/certs/{ca,nginx,internal}

	# Certificate Check - generate if not exists
	@echo -e "\033[0;33mChecking SSL/TLS certificates...\033[0m"
	@if [ ! -f docker/certs/nginx/cert.crt ]; then \
		echo -e "\033[0;34mSSL certificates not found. Generating CA-signed certificates...\033[0m"; \
		$(MAKE) --silent ssl-internal; \
	else \
		echo -e "\033[0;32m✓ SSL certificates exist\033[0m"; \
	fi

	# ... rest of setup ...
```

### Step 7: Update .gitignore

```gitignore
# Certificates (keep scripts, ignore generated certs and directories)
docker/certs/ca/
docker/certs/nginx/
docker/certs/internal/
docker/certs/*.srl
!docker/certs/generate-*.sh
!docker/certs/setup-*.sh
```

### Step 8: Update make reset-full

Update the reset-full target to remove certificate directories:

```makefile
# In reset-full target, update the certificate removal section:
@echo -e "\033[0;33mRemoving generated certificates...\033[0m"
@rm -rf docker/certs/ca docker/certs/nginx docker/certs/internal 2>/dev/null || true
@rm -f docker/certs/*.srl 2>/dev/null || true
```

Update the info message:
```
║  • docker/certs/{ca,nginx,internal}/ (certificate directories)    ║
```

### Step 9: Update ssl-clean Target

```makefile
ssl-clean: ## Remove all generated SSL certificates (DANGEROUS!)
	@echo -e "\033[0;31m⚠️  WARNING: This will delete all SSL certificates!\033[0m"
	@read -p "Type 'YES' to confirm: " CONFIRM; \
	if [ "$$CONFIRM" = "YES" ]; then \
		rm -rf docker/certs/ca docker/certs/nginx docker/certs/internal; \
		rm -rf docker/certs/*.srl docker/certs/letsencrypt; \
		echo -e "\033[0;32mSSL certificates removed!\033[0m"; \
	else \
		echo -e "\033[0;34mOperation cancelled.\033[0m"; \
	fi
```

### Step 10: Update ssl-info Target

```makefile
ssl-info: ## Show SSL certificate information
	@echo -e "\033[0;33mSSL Certificate Information:\033[0m"
	@echo ""
	@# CA certificate
	@if [ -f docker/certs/ca/ca.crt ]; then \
		echo -e "\033[0;36m=== Internal CA ===\033[0m"; \
		openssl x509 -in docker/certs/ca/ca.crt -noout -subject -dates | sed 's/^/  /'; \
		echo ""; \
	fi
	@# Nginx certificate
	@if [ -f docker/certs/nginx/cert.crt ]; then \
		echo -e "\033[0;36m=== Nginx Certificate ===\033[0m"; \
		openssl x509 -in docker/certs/nginx/cert.crt -noout -subject -issuer -dates | sed 's/^/  /'; \
		echo ""; \
	fi
	@# Internal certificate
	@if [ -f docker/certs/internal/cert.crt ]; then \
		echo -e "\033[0;36m=== Internal Services Certificate ===\033[0m"; \
		openssl x509 -in docker/certs/internal/cert.crt -noout -subject -issuer -dates | sed 's/^/  /'; \
		echo ""; \
	fi
	@# No certificates found
	@if [ ! -f docker/certs/ca/ca.crt ] && [ ! -f docker/certs/nginx/cert.crt ]; then \
		echo -e "\033[0;31mNo certificates found. Generate with:\033[0m"; \
		echo -e "\033[0;34m  make ssl-internal   (development - CA-signed)\033[0m"; \
		echo -e "\033[0;34m  make ssl-letsencrypt (production)\033[0m"; \
	fi
```

### Step 11: Update ssl-renew Target

Update to use new paths:

```makefile
ssl-renew: ## Renew Let's Encrypt certificate and reload all SSL services
	@echo -e "\033[0;33mRenewing Let's Encrypt certificate...\033[0m"
	@CERT_CHANGED=false; \
	CERT_BEFORE=""; \
	if [ -f docker/certs/nginx/cert.crt ]; then \
		CERT_BEFORE=$$(openssl x509 -in docker/certs/nginx/cert.crt -noout -fingerprint 2>/dev/null || echo ""); \
	fi; \
	# ... rest of renewal logic (certbot) ...
	CERT_AFTER=""; \
	if [ -f docker/certs/nginx/cert.crt ]; then \
		CERT_AFTER=$$(openssl x509 -in docker/certs/nginx/cert.crt -noout -fingerprint 2>/dev/null || echo ""); \
	fi; \
	# ... comparison and reload ...
```

### Step 12: Remove Deprecated Files

Delete the following files that are no longer needed:

```bash
rm docker/certs/generate-selfsigned.sh
rm docker/certs/selfsigned.crt docker/certs/selfsigned.key 2>/dev/null || true
rm docker/certs/cert.crt docker/certs/cert.key 2>/dev/null || true
```

### Step 13: Remove ssl-selfsigned Target from Makefile

Delete the entire `ssl-selfsigned` target from Makefile.

### Step 14: Update Documentation

Update the following documentation files:

**`.zappzarapp/docs/security/SSL.md`** (or create if not exists):

```markdown
# SSL/TLS Certificate Management

## Overview

This project uses an Internal CA architecture for TLS certificates:

- **Internal CA**: Self-signed Certificate Authority for development
- **Nginx certificates**: CA-signed, used for HTTPS frontend
- **Internal certificates**: CA-signed, used for service-to-service TLS

## Development Workflow

### Initial Setup

Certificates are automatically generated during `make setup`:

```bash
make setup  # Generates CA + all certificates
```

### Manual Certificate Generation

```bash
make ssl-internal      # Regenerate all certificates (CA + services)
make ssl-ca            # Regenerate CA only (invalidates existing certs!)
```

### Avoid Browser Warnings

To trust the internal CA in your browser/system:

```bash
make ssl-trust-ca      # Shows instructions for your OS
```

### View Certificate Information

```bash
make ssl-info          # Shows CA, nginx, and internal cert details
```

### Clean Certificates

```bash
make ssl-clean         # Remove all certificates (requires confirmation)
```

## Production Workflow

### Let's Encrypt (Recommended)

```bash
make ssl-letsencrypt   # Interactive setup for Let's Encrypt
make ssl-renew         # Renew certificate
make ssl-prod-enable   # Generate production nginx config
```

### Custom Certificates

Override certificate paths in `.env` or `.env.production`:

```bash
CERT_PATH_NGINX=/etc/letsencrypt/live/example.com
CERT_PATH_INTERNAL=/etc/ssl/internal
```

## Architecture

```
docker/certs/
├── ca/                    # Certificate Authority
│   ├── ca.crt             # Public CA certificate
│   └── ca.key             # Private CA key (chmod 600)
├── nginx/                 # Frontend/reverse proxy
│   ├── cert.crt
│   └── cert.key
├── internal/              # Service-to-service TLS
│   ├── cert.crt
│   ├── cert.key
│   └── ca.crt             # CA copy for easy mounting
├── generate-ca.sh
├── generate-internal.sh
└── setup-letsencrypt.sh
```

## Make Targets

| Target | Description |
|--------|-------------|
| `ssl-internal` | Generate CA + all certificates (default for development) |
| `ssl-ca` | Generate CA only |
| `ssl-trust-ca` | Show instructions to trust CA in system |
| `ssl-info` | Show certificate information |
| `ssl-clean` | Remove all certificates |
| `ssl-letsencrypt` | Setup Let's Encrypt (production) |
| `ssl-renew` | Renew Let's Encrypt certificate |
| `ssl-prod-enable` | Generate production nginx config |
| `ssl-reload-services` | Reload services after cert change |
```

**Update `make help` output** to reflect new targets.

**Update README.md** Quick Start section if it mentions SSL.

## Verification

```bash
# Generate certificates
make ssl-internal

# Verify structure
ls -la docker/certs/
ls -la docker/certs/ca/
ls -la docker/certs/nginx/
ls -la docker/certs/internal/

# Verify CA signed the certificates
openssl verify -CAfile docker/certs/ca/ca.crt docker/certs/nginx/cert.crt
openssl verify -CAfile docker/certs/ca/ca.crt docker/certs/internal/cert.crt

# Show certificate info
make ssl-info

# Start services and verify TLS works
make up
curl -k https://localhost:8443/  # Works (with warning)

# After trusting CA (make ssl-trust-ca):
curl https://localhost:8443/     # Works without -k
```

## Files to Create

1. `docker/certs/generate-ca.sh`
2. `docker/certs/generate-internal.sh`
3. `.zappzarapp/docs/security/SSL.md`

**Note:** Directories (`ca/`, `nginx/`, `internal/`) are created via `make setup`.

## Files to Modify

1. `compose.yaml` - Update certificate volume mounts
2. `Makefile` - Multiple changes:
   - Add `ssl-ca`, `ssl-internal`, `ssl-trust-ca` targets
   - Remove `ssl-selfsigned` target
   - Update `setup` to use `ssl-internal`
   - Update `reset-full` to remove cert directories
   - Update `ssl-clean` to remove cert directories
   - Update `ssl-info` to show all cert types
   - Update `ssl-renew` to use new paths
3. `.gitignore` - Update certificate ignore patterns
4. `.env` - Add CERT_PATH_* variables (commented)
5. `tests/goss/bats/*.bats` - Update SSL target tests
6. `tests/goss/*.yaml` - Update runtime certificate path checks
7. `.github/workflows/ci.yml` - Update certificate structure checks
8. `.gitlab-ci.yml` (if exists) - Update certificate checks

## Files to Delete

1. `docker/certs/generate-selfsigned.sh`
2. `docker/certs/selfsigned.crt` (if exists)
3. `docker/certs/selfsigned.key` (if exists)
4. `docker/certs/cert.crt` (symlink, if exists)
5. `docker/certs/cert.key` (symlink, if exists)

## BATS Tests Updates

### New Tests to Add

**`tests/goss/bats/ssl-targets.bats`** (or update existing):

```bash
#!/usr/bin/env bats

@test "ssl-ca generates CA certificate" {
    run make ssl-ca
    [ "$status" -eq 0 ]
    [ -f "docker/certs/ca/ca.crt" ]
    [ -f "docker/certs/ca/ca.key" ]
}

@test "ssl-internal generates all certificates" {
    run make ssl-internal
    [ "$status" -eq 0 ]
    [ -f "docker/certs/ca/ca.crt" ]
    [ -f "docker/certs/nginx/cert.crt" ]
    [ -f "docker/certs/nginx/cert.key" ]
    [ -f "docker/certs/internal/cert.crt" ]
    [ -f "docker/certs/internal/cert.key" ]
    [ -f "docker/certs/internal/ca.crt" ]
}

@test "ssl-info shows certificate information" {
    # Ensure certs exist first
    make ssl-internal
    run make ssl-info
    [ "$status" -eq 0 ]
    [[ "$output" == *"Internal CA"* ]]
    [[ "$output" == *"Nginx Certificate"* ]]
    [[ "$output" == *"Internal Services Certificate"* ]]
}

@test "ssl-clean removes all certificates" {
    # Generate first
    make ssl-internal
    # Clean (with YES confirmation)
    echo "YES" | make ssl-clean
    [ ! -d "docker/certs/ca" ]
    [ ! -d "docker/certs/nginx" ]
    [ ! -d "docker/certs/internal" ]
}

@test "certificates are CA-signed not self-signed" {
    make ssl-internal
    # Verify nginx cert is signed by our CA
    run openssl verify -CAfile docker/certs/ca/ca.crt docker/certs/nginx/cert.crt
    [ "$status" -eq 0 ]
    [[ "$output" == *"OK"* ]]
    # Verify internal cert is signed by our CA
    run openssl verify -CAfile docker/certs/ca/ca.crt docker/certs/internal/cert.crt
    [ "$status" -eq 0 ]
    [[ "$output" == *"OK"* ]]
}

@test "reset-full removes certificate directories" {
    make ssl-internal
    [ -d "docker/certs/ca" ]
    # Run reset-full (requires confirmation in real scenario)
    # This test may need adjustment based on reset-full implementation
    run rm -rf docker/certs/ca docker/certs/nginx docker/certs/internal
    [ ! -d "docker/certs/ca" ]
}
```

### Tests to Remove/Update

- Remove any tests for `ssl-selfsigned` target
- Remove tests checking for `docker/certs/cert.crt` (legacy symlink)
- Update tests checking certificate paths to use new structure

### Files to Update

1. `tests/goss/bats/make-targets.bats` - Update SSL target tests
2. `tests/goss/bats/setup.bats` - Update certificate checks in setup tests
3. `tests/goss/bats/reset.bats` - Update reset-full certificate cleanup tests

## CI Pipeline Updates

### GitHub Actions

**`.github/workflows/ci.yml`** updates:

```yaml
# Update any certificate path references
- name: Setup project
  run: make setup
  # Now calls ssl-internal instead of ssl-selfsigned

# Update security scan paths if checking certificates
- name: Security scan
  run: |
    # Check new certificate structure
    test -d docker/certs/ca
    test -d docker/certs/nginx
    test -d docker/certs/internal
```

### GitLab CI (if applicable)

**`.gitlab-ci.yml`** updates:

```yaml
setup:
  script:
    - make setup
    # Verify new certificate structure
    - test -f docker/certs/ca/ca.crt
    - test -f docker/certs/nginx/cert.crt
    - test -f docker/certs/internal/cert.crt
```

### CI Security Scan Updates

If the CI runs security scans on certificates, update paths:

```yaml
# Old paths (remove)
# - docker/certs/cert.crt
# - docker/certs/selfsigned.crt

# New paths (add)
- docker/certs/ca/ca.crt
- docker/certs/nginx/cert.crt
- docker/certs/internal/cert.crt
```

### Goss Runtime Tests

Update `tests/goss/` runtime test files to check new certificate mounts:

```yaml
# tests/goss/nginx.yaml
file:
  /etc/nginx/certs/cert.crt:
    exists: true
  /etc/nginx/certs/cert.key:
    exists: true

# tests/goss/node.yaml
file:
  /etc/ssl/certs/cert.crt:
    exists: true
  /etc/ssl/certs/internal-ca.crt:
    exists: true

# tests/goss/php.yaml
file:
  /etc/ssl/certs/internal-ca.crt:
    exists: true
```

## Notes

- **Directory pattern:** Created by `make setup`, removed by `make reset-full`
- **CA key security:** `ca.key` has restricted permissions (600)
- **Browser trust:** Optional - run `make ssl-trust-ca` to avoid warnings
- **Production:** Override paths via `CERT_PATH_*` environment variables
