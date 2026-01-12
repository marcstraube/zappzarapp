# Documentation

This directory contains **technical documentation** for the Docker WebDev Boilerplate.

---

## Available Documentation

### Security & Compliance

- **[SSL-CERTIFICATES.md](SSL-CERTIFICATES.md)** - SSL/TLS certificate management and renewal
- **[ENCRYPTION.md](ENCRYPTION.md)** - Database encryption guide (GDPR Art. 32)
- **[AUDIT-LOGGING.md](AUDIT-LOGGING.md)** - Audit logging guide (GDPR Art. 30)
- **[NETWORK.md](NETWORK.md)** - Network segmentation and security architecture

### Development

- **[DEV-DASHBOARD.md](DEV-DASHBOARD.md)** - Development dashboard for monitoring
- **[XDEBUG.md](XDEBUG.md)** - Xdebug configuration for PHP debugging

### Testing & Quality

- **[TESTING-PHP.md](TESTING-PHP.md)** - PHP testing guide (PHPUnit)
- **[TESTING-NODE.md](TESTING-NODE.md)** - Node.js testing guide (Vitest)

### Dependency Management

- **[RENOVATE.md](RENOVATE.md)** - Automated dependency updates with Renovate

---

## Related Files

### User Documentation
- **[../README.md](../README.md)** - Main project README with quick start

### Internal Project Planning
- **[../TODO.md](../TODO.md)** - Project changelog and development history
- **[../CHANGELOG.md](../CHANGELOG.md)** - Release changelog
- **[../GDPR-NEXT-STEPS.md](../GDPR-NEXT-STEPS.md)** - GDPR implementation roadmap

---

## Documentation Structure

```
documentation/
├── README.md              # This file (documentation index)
├── SSL-CERTIFICATES.md    # SSL/TLS certificate management
├── ENCRYPTION.md          # Database encryption guide
├── AUDIT-LOGGING.md       # Audit logging guide
├── NETWORK.md             # Network architecture
├── DEV-DASHBOARD.md       # Development dashboard
├── XDEBUG.md              # Xdebug configuration
├── TESTING-PHP.md         # PHP testing guide
├── TESTING-NODE.md        # Node.js testing guide
└── RENOVATE.md            # Dependency management
```

---

## Quick Links

### Getting Started
1. Read the [main README](../README.md) for quick start
2. Review [NETWORK.md](NETWORK.md) to understand the architecture
3. Setup SSL certificates with [SSL-CERTIFICATES.md](SSL-CERTIFICATES.md)

### Development Setup
- **PHP Debugging**: See [XDEBUG.md](XDEBUG.md)
- **Dashboard**: See [DEV-DASHBOARD.md](DEV-DASHBOARD.md)

### Security Implementation
- **SSL/TLS**: [SSL-CERTIFICATES.md](SSL-CERTIFICATES.md)
- **Encryption**: [ENCRYPTION.md](ENCRYPTION.md)
- **Audit Logging**: [AUDIT-LOGGING.md](AUDIT-LOGGING.md)
- **Network Segmentation**: [NETWORK.md](NETWORK.md)

### Testing & Quality
- **PHP Testing**: [TESTING-PHP.md](TESTING-PHP.md)
- **Node.js Testing**: [TESTING-NODE.md](TESTING-NODE.md)

---

## Contributing

When adding new documentation:
1. Place it in this directory (`documentation/`)
2. Update this README with a link
3. Use clear, descriptive filenames (UPPERCASE.md for major docs)
4. Include cross-references to related documentation
