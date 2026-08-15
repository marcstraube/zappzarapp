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
make ssl-trust-ca      # Auto-detects OS and trusts CA (requires sudo)
make ssl-trust-ca-help # Show manual instructions for all OSes
```

Supported auto-detection:

- **macOS**: Adds to System Keychain
- **Arch/Manjaro**: Uses `trust anchor`
- **Debian/Ubuntu**: Uses `update-ca-certificates`
- **RHEL/Fedora**: Uses `update-ca-trust`
- **openSUSE**: Uses `update-ca-certificates`
- **Windows**: Manual import required (see `ssl-trust-ca-help`)

To undo this later (e.g. after `make reset-full` or `make ssl-clean`), run
`make ssl-untrust-ca` — it removes the CA from the system trust store and works
even when the certificate files are already deleted (the Linux stores are
addressed by the installed file name, macOS/Arch by the certificate name).
Manual instructions: `make ssl-untrust-ca-help`.

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

The setup issues the certificate via certbot (local install or Docker fallback)
and installs a copy as `docker/certs/nginx/cert.{crt,key}` — the directory the
nginx container bind mounts. A copy is required because nginx only mounts
`nginx/`; files under `letsencrypt/live/<domain>/` are not visible inside the
container. `make ssl-renew` re-runs the install step after each renewal and
reloads the SSL services when the certificate changed, so a single cron entry
covers the whole renewal:

```bash
0 0 * * * cd /path/to/project && make ssl-renew >> /var/log/ssl-renew.log 2>&1
```

### Staging and ACME Test Servers

`ssl-letsencrypt` accepts variables for non-interactive use and alternative ACME
environments:

```bash
# Let's Encrypt staging (relaxed rate limits, not browser-trusted) - use this
# to validate a new deployment before requesting the production certificate
make ssl-letsencrypt DOMAIN=example.com EMAIL=admin@example.com STAGING=1

# Local ACME test server (Pebble), no public domain required
make ssl-letsencrypt DOMAIN=test.example EMAIL=admin@test.example \
    ACME_SERVER=https://localhost:14000/dir \
    ACME_CA_BUNDLE=/path/to/pebble.minica.pem HTTP_PORT=5002
```

The Pebble end-to-end flow is covered by the opt-in BATS suite
`tests/bats/integration/letsencrypt-e2e.bats` (`BATS_ENABLE_ACME_E2E=true`); the
certificate install logic is covered by
`tests/bats/integration/letsencrypt.bats`.

### Custom Certificates

Override certificate paths in `.env` or `.env.production`:

```bash
# Directory must contain cert.crt and cert.key as regular files (nginx
# mounts it as /etc/nginx/certs; symlink targets outside the directory do
# not resolve inside the container)
CERT_PATH_NGINX=/etc/ssl/my-certs
CERT_PATH_INTERNAL=/etc/ssl/internal
```

## Architecture

```text
docker/certs/
├── ca/                    # Certificate Authority
│   ├── ca.crt             # Public CA certificate
│   └── ca.key             # Private CA key (chmod 600)
├── nginx/                 # Frontend/reverse proxy
│   ├── cert.crt
│   └── cert.key           # chmod 600 + read ACLs for container uids
├── internal/              # Service-to-service TLS
│   ├── cert.crt
│   ├── cert.key           # chmod 600 + read ACLs for container uids
│   └── ca.crt             # CA copy for easy mounting
├── generate-ca.sh
├── generate-internal.sh
├── install-letsencrypt.sh # Installs the LE cert into nginx/ (setup + renew)
└── setup-letsencrypt.sh
```

## Make Targets

| Target                | Description                                              |
| --------------------- | -------------------------------------------------------- |
| `ssl-internal`        | Generate CA + all certificates (default for development) |
| `ssl-ca`              | Generate CA only                                         |
| `ssl-trust-ca`        | Auto-detect OS and trust CA in system (requires sudo)    |
| `ssl-trust-ca-help`   | Show manual instructions to trust CA                     |
| `ssl-untrust-ca`      | Remove CA from the system trust store (requires sudo)    |
| `ssl-untrust-ca-help` | Show manual instructions to remove the CA                |
| `ssl-info`            | Show certificate information                             |
| `ssl-clean`           | Remove all certificates                                  |
| `ssl-letsencrypt`     | Setup Let's Encrypt (production)                         |
| `ssl-renew`           | Renew Let's Encrypt certificate                          |
| `ssl-prod-enable`     | Generate production nginx config                         |
| `ssl-reload-services` | Reload services after cert change                        |
