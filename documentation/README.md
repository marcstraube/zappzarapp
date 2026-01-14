# Documentation

Technical documentation for zappzarapp.

---

## Getting Started

Essential documentation for new users and quick reference.

| Document                                 | Description                    |
| ---------------------------------------- | ------------------------------ |
| [QUICKSTART.md](QUICKSTART.md)           | Get started in under 5 minutes |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Common problems and solutions  |

## Security & Compliance

GDPR-compliant security features and data protection.

| Document                                                      | Description                                |
| ------------------------------------------------------------- | ------------------------------------------ |
| [SSL-CERTIFICATES.md](security/SSL-CERTIFICATES.md)           | SSL/TLS certificate management and renewal |
| [ENCRYPTION.md](security/ENCRYPTION.md)                       | Database encryption guide (GDPR Art. 32)   |
| [AUDIT-LOGGING.md](security/AUDIT-LOGGING.md)                 | Audit logging guide (GDPR Art. 30)         |
| [BACKUP.md](security/BACKUP.md)                               | Encrypted backup & restore (GDPR Art. 32)  |
| [SECRETS.md](security/SECRETS.md)                             | Docker Secrets management                  |
| [RETENTION-POLICY.md](security/RETENTION-POLICY.md)           | Database retention policies                |
| [ACCESS-LOG-MONITORING.md](security/ACCESS-LOG-MONITORING.md) | Access log analysis                        |
| [SECURITY-SCANNING.md](security/SECURITY-SCANNING.md)         | Vulnerability scanning tools               |

## Development

Tools and configurations for local development.

| Document                                                   | Description                                   |
| ---------------------------------------------------------- | --------------------------------------------- |
| [MAKEFILE-REFERENCE.md](development/MAKEFILE-REFERENCE.md) | Complete reference for all Make commands      |
| [DEPENDENCIES.md](development/DEPENDENCIES.md)             | Dependency management (Composer, pnpm, Watch) |
| [DEV-DASHBOARD.md](development/DEV-DASHBOARD.md)           | Development dashboard for monitoring          |
| [XDEBUG.md](development/XDEBUG.md)                         | Xdebug configuration for PHP debugging        |
| [RENOVATE.md](development/RENOVATE.md)                     | Automated dependency updates                  |

## Testing

Testing guides for PHP and Node.js.

| Document                                   | Description                    |
| ------------------------------------------ | ------------------------------ |
| [TESTING-PHP.md](testing/TESTING-PHP.md)   | PHP testing guide (PHPUnit)    |
| [TESTING-NODE.md](testing/TESTING-NODE.md) | Node.js testing guide (Vitest) |

## Infrastructure

Network architecture, system configuration, and deployment.

| Document                                          | Description                                    |
| ------------------------------------------------- | ---------------------------------------------- |
| [ARCHITECTURE.md](infrastructure/ARCHITECTURE.md) | System architecture and component overview     |
| [NETWORK.md](infrastructure/NETWORK.md)           | Network segmentation and security architecture |
| [ERROR-PAGES.md](infrastructure/ERROR-PAGES.md)   | Custom error page handling                     |
| [PERFORMANCE.md](infrastructure/PERFORMANCE.md)   | Performance tuning for dev and production      |
| [MONITORING.md](infrastructure/MONITORING.md)     | External monitoring integration                |
| [DEPLOYMENT.md](infrastructure/DEPLOYMENT.md)     | CI/CD pipelines and deployment strategies      |

## Setup Guides

Platform-specific installation and configuration.

| Document                       | Description                     |
| ------------------------------ | ------------------------------- |
| [WINDOWS.md](setup/WINDOWS.md) | Windows setup (WSL2 / Git Bash) |

## IDE Integration

Pre-configured settings for popular IDEs (located in their respective
directories):

| IDE          | Configuration                             | Description                                     |
| ------------ | ----------------------------------------- | ----------------------------------------------- |
| **VS Code**  | [.vscode/README.md](../.vscode/README.md) | Extensions, tasks, launch configs, settings     |
| **PhpStorm** | [.idea/README.md](../.idea/README.md)     | Run configurations, inspections, database tools |

---

## Directory Structure

```text
documentation/
├── README.md                 # This file (index)
├── QUICKSTART.md             # Getting started guide
├── TROUBLESHOOTING.md        # Common problems & solutions
├── CHANGELOG.md              # Boilerplate version history
├── CONTRIBUTING.md           # Contribution guidelines
├── security/                 # GDPR & Security
│   ├── ACCESS-LOG-MONITORING.md
│   ├── AUDIT-LOGGING.md
│   ├── BACKUP.md
│   ├── ENCRYPTION.md
│   ├── RETENTION-POLICY.md
│   ├── SECRETS.md
│   ├── SECURITY-SCANNING.md
│   └── SSL-CERTIFICATES.md
├── development/              # Development Tools
│   ├── DEPENDENCIES.md
│   ├── DEV-DASHBOARD.md
│   ├── MAKEFILE-REFERENCE.md
│   ├── RENOVATE.md
│   └── XDEBUG.md
├── testing/                  # Testing Guides
│   ├── TESTING-NODE.md
│   └── TESTING-PHP.md
├── infrastructure/           # Architecture & Deployment
│   ├── ARCHITECTURE.md
│   ├── DEPLOYMENT.md
│   ├── ERROR-PAGES.md
│   ├── MONITORING.md
│   ├── NETWORK.md
│   └── PERFORMANCE.md
└── setup/                    # Platform Setup
    └── WINDOWS.md
```

---

## Related Files

| File                               | Description                      |
| ---------------------------------- | -------------------------------- |
| [CHANGELOG.md](CHANGELOG.md)       | Boilerplate version history      |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution guidelines          |
| [../README.md](../README.md)       | Main project README              |
| [../CHANGELOG.md](../CHANGELOG.md) | Application changelog (your app) |

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:

- What files should not be committed (lock files, personal configs)
- Commit message conventions
- Running quality checks before commits
