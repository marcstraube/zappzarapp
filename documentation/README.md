# Documentation

Technical documentation for the Docker WebDev Boilerplate.

---

## Security & Compliance

GDPR-compliant security features and data protection.

| Document                                            | Description                                    |
|-----------------------------------------------------|------------------------------------------------|
| [SSL-CERTIFICATES.md](security/SSL-CERTIFICATES.md) | SSL/TLS certificate management and renewal     |
| [ENCRYPTION.md](security/ENCRYPTION.md)             | Database encryption guide (GDPR Art. 32)       |
| [AUDIT-LOGGING.md](security/AUDIT-LOGGING.md)       | Audit logging guide (GDPR Art. 30)             |
| [BACKUP.md](security/BACKUP.md)                     | Encrypted backup & restore (GDPR Art. 32)      |

## Development

Tools and configurations for local development.

| Document                                            | Description                                    |
|-----------------------------------------------------|------------------------------------------------|
| [DEV-DASHBOARD.md](development/DEV-DASHBOARD.md)    | Development dashboard for monitoring           |
| [XDEBUG.md](development/XDEBUG.md)                  | Xdebug configuration for PHP debugging         |
| [RENOVATE.md](development/RENOVATE.md)              | Automated dependency updates                   |

## Testing

Testing guides for PHP and Node.js.

| Document                                            | Description                                    |
|-----------------------------------------------------|------------------------------------------------|
| [TESTING-PHP.md](testing/TESTING-PHP.md)            | PHP testing guide (PHPUnit)                    |
| [TESTING-NODE.md](testing/TESTING-NODE.md)          | Node.js testing guide (Vitest)                 |

## Infrastructure

Network architecture and system configuration.

| Document                                            | Description                                    |
|-----------------------------------------------------|------------------------------------------------|
| [NETWORK.md](infrastructure/NETWORK.md)             | Network segmentation and security architecture |

## Setup Guides

Platform-specific installation and configuration.

| Document                                            | Description                                    |
|-----------------------------------------------------|------------------------------------------------|
| [WINDOWS.md](setup/WINDOWS.md)                      | Windows setup (WSL2 / Git Bash)                |

---

## Directory Structure

```
documentation/
├── README.md                 # This file (index)
├── security/                 # GDPR & Security
│   ├── AUDIT-LOGGING.md
│   ├── BACKUP.md
│   ├── ENCRYPTION.md
│   └── SSL-CERTIFICATES.md
├── development/              # Development Tools
│   ├── DEV-DASHBOARD.md
│   ├── RENOVATE.md
│   └── XDEBUG.md
├── testing/                  # Testing Guides
│   ├── TESTING-NODE.md
│   └── TESTING-PHP.md
├── infrastructure/           # Architecture
│   └── NETWORK.md
└── setup/                    # Platform Setup
    └── WINDOWS.md
```

---

## Related Files

| File                                                | Description                                    |
|-----------------------------------------------------|------------------------------------------------|
| [../README.md](../README.md)                        | Main project README                            |
| [../CHANGELOG.md](../CHANGELOG.md)                  | Release changelog                              |
| [../GDPR-NEXT-STEPS.md](../GDPR-NEXT-STEPS.md)      | GDPR implementation roadmap                    |

---

## Contributing

When adding new documentation:
1. Place it in the appropriate subdirectory
2. Update this README with a link
3. Use clear, descriptive filenames (UPPERCASE.md)
4. Include cross-references to related documentation
