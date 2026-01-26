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

```text
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

| Target                | Description                                              |
| --------------------- | -------------------------------------------------------- |
| `ssl-internal`        | Generate CA + all certificates (default for development) |
| `ssl-ca`              | Generate CA only                                         |
| `ssl-trust-ca`        | Auto-detect OS and trust CA in system (requires sudo)    |
| `ssl-trust-ca-help`   | Show manual instructions to trust CA                     |
| `ssl-info`            | Show certificate information                             |
| `ssl-clean`           | Remove all certificates                                  |
| `ssl-letsencrypt`     | Setup Let's Encrypt (production)                         |
| `ssl-renew`           | Renew Let's Encrypt certificate                          |
| `ssl-prod-enable`     | Generate production nginx config                         |
| `ssl-reload-services` | Reload services after cert change                        |
