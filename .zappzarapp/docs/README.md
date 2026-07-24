# Documentation

Technical documentation for zappzarapp.

---

## Getting Started

Essential documentation for new users and quick reference.

| Document                                             | Description                    |
| ---------------------------------------------------- | ------------------------------ |
| [QUICKSTART.md](QUICKSTART.md)                       | Get started in under 5 minutes |
| [CUSTOMIZATION.md](getting-started/CUSTOMIZATION.md) | Project customization guide    |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md)             | Common problems and solutions  |

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
| [INTERNAL-TLS.md](security/INTERNAL-TLS.md)                   | Internal TLS for zero-trust networking     |
| [CORS.md](security/CORS.md)                                   | CORS configuration for APIs                |
| [SECURITY-HEADERS.md](security/SECURITY-HEADERS.md)           | HTTP security headers                      |
| [KNOWN-VULNERABILITIES.md](security/KNOWN-VULNERABILITIES.md) | Documented vulnerabilities, accepted risks |

## Development

Tools and configurations for local development.

| Document                                                       | Description                                   |
| -------------------------------------------------------------- | --------------------------------------------- |
| [MAKEFILE-REFERENCE.md](development/MAKEFILE-REFERENCE.md)     | Complete reference for all Make commands      |
| [DEPENDENCIES.md](development/DEPENDENCIES.md)                 | Dependency management (Composer, pnpm, Watch) |
| [DEV-DASHBOARD.md](development/DEV-DASHBOARD.md)               | Development dashboard for monitoring          |
| [XDEBUG.md](development/XDEBUG.md)                             | Xdebug configuration for PHP debugging        |
| [RENOVATE.md](development/RENOVATE.md)                         | Automated dependency updates                  |
| [FRONTEND-SCAFFOLDING.md](development/FRONTEND-SCAFFOLDING.md) | Node.js frontend framework setup              |
| [AI-INTEGRATION.md](development/AI-INTEGRATION.md)             | AI tooling and agent workflow                 |
| [DATABASE-TOOLS.md](development/DATABASE-TOOLS.md)             | Database administration and CLI tools         |
| [DEV-TOOLBAR.md](development/DEV-TOOLBAR.md)                   | Dev toolbar (zappzarapp/devtoolbar package)   |

## Testing

Testing guides for PHP, Node.js, and shell.

| Document                                     | Description                    |
| -------------------------------------------- | ------------------------------ |
| [TESTING-PHP.md](testing/TESTING-PHP.md)     | PHP testing guide (PHPUnit)    |
| [TESTING-NODE.md](testing/TESTING-NODE.md)   | Node.js testing guide (Vitest) |
| [TESTING-SHELL.md](testing/TESTING-SHELL.md) | Shell testing guide (BATS)     |

## Infrastructure

Network architecture, system configuration, and deployment.

| Document                                                    | Description                                    |
| ----------------------------------------------------------- | ---------------------------------------------- |
| [ARCHITECTURE.md](infrastructure/ARCHITECTURE.md)           | System architecture and component overview     |
| [NETWORK.md](infrastructure/NETWORK.md)                     | Network segmentation and security architecture |
| [ERROR-PAGES.md](infrastructure/ERROR-PAGES.md)             | Custom error page handling                     |
| [PERFORMANCE.md](infrastructure/PERFORMANCE.md)             | Performance tuning for dev and production      |
| [MONITORING.md](infrastructure/MONITORING.md)               | External monitoring integration                |
| [DEPLOYMENT.md](infrastructure/DEPLOYMENT.md)               | CI/CD pipelines and deployment strategies      |
| [NGINX.md](infrastructure/NGINX.md)                         | Nginx configuration and customization          |
| [OPTIONAL-SERVICES.md](infrastructure/OPTIONAL-SERVICES.md) | Optional services (Mercure, Meilisearch, etc.) |
| [KUBERNETES.md](infrastructure/KUBERNETES.md)               | Kubernetes deployment with Helm                |
| [DATABASE.md](infrastructure/DATABASE.md)                   | Database setup (PostgreSQL / MariaDB)          |
| [NODE-SSL.md](infrastructure/NODE-SSL.md)                   | SSL/TLS for direct Node.js exposure            |

## Setup Guides

Platform-specific installation and configuration.

| Document                                       | Description                     |
| ---------------------------------------------- | ------------------------------- |
| [WINDOWS.md](setup/WINDOWS.md)                 | Windows setup (WSL2 / Git Bash) |
| [IDE-INTEGRATION.md](setup/IDE-INTEGRATION.md) | PhpStorm / VS Code integration  |

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
.zappzarapp/
├── ai/                       # AI knowledge files
│   ├── DECISIONS.md
│   ├── LEARNINGS.md
│   └── REFERENCES.md
├── CHANGELOG.md              # Boilerplate version history
├── docs/                     # This documentation
│   ├── README.md             # This file (index)
│   ├── CONTRIBUTING.md
│   ├── QUICKSTART.md
│   ├── TROUBLESHOOTING.md
│   ├── development/
│   │   ├── AI-INTEGRATION.md
│   │   ├── DATABASE-TOOLS.md
│   │   ├── DEPENDENCIES.md
│   │   ├── DEV-DASHBOARD.md
│   │   ├── DEV-TOOLBAR.md
│   │   ├── FRONTEND-SCAFFOLDING.md
│   │   ├── MAKEFILE-REFERENCE.md
│   │   ├── RENOVATE.md
│   │   └── XDEBUG.md
│   ├── getting-started/
│   │   └── CUSTOMIZATION.md
│   ├── infrastructure/
│   │   ├── ARCHITECTURE.md
│   │   ├── DATABASE.md
│   │   ├── DEPLOYMENT.md
│   │   ├── ERROR-PAGES.md
│   │   ├── KUBERNETES.md
│   │   ├── MONITORING.md
│   │   ├── NETWORK.md
│   │   ├── NGINX.md
│   │   ├── NODE-SSL.md
│   │   ├── OPTIONAL-SERVICES.md
│   │   └── PERFORMANCE.md
│   ├── security/
│   │   ├── ACCESS-LOG-MONITORING.md
│   │   ├── AUDIT-LOGGING.md
│   │   ├── BACKUP.md
│   │   ├── CORS.md
│   │   ├── ENCRYPTION.md
│   │   ├── INTERNAL-TLS.md
│   │   ├── KNOWN-VULNERABILITIES.md
│   │   ├── RETENTION-POLICY.md
│   │   ├── SECRETS.md
│   │   ├── SECURITY-HEADERS.md
│   │   ├── SECURITY-SCANNING.md
│   │   └── SSL-CERTIFICATES.md
│   ├── setup/
│   │   ├── IDE-INTEGRATION.md
│   │   └── WINDOWS.md
│   └── testing/
│       ├── TESTING-NODE.md
│       ├── TESTING-PHP.md
│       └── TESTING-SHELL.md
└── standards/                # Coding standards (all AI agents)
```

---

## Related Files

| File                               | Description                 |
| ---------------------------------- | --------------------------- |
| [CHANGELOG.md](../CHANGELOG.md)    | Boilerplate version history |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution guidelines     |
| [README.md](../../README.md)       | Main project README         |

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:

- What files should not be committed (lock files, personal configs)
- Commit message conventions
- Running quality checks before commits
