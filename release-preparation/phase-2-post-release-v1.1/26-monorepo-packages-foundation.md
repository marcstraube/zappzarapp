# Monorepo Packages: Foundation Libraries (v1.1)

**Status:** Planned
**Priority:** Medium
**Complexity:** Medium
**Target Version:** 1.1
**Estimated Effort:** 3-4 weeks

## Problem

The boilerplate includes reusable infrastructure components (TLS config, Docker secrets, database connections) that could benefit other projects. However, extracting these into separate repositories would create significant maintenance overhead.

**Current state:**
- All infrastructure code lives in main boilerplate
- Tight coupling between boilerplate and infrastructure
- No easy way to reuse components in other projects
- Good separation achieved (App/DevDashboard/Shared in both PHP and Node)

## Solution

Introduce a monorepo packages structure for foundation libraries while keeping the "batteries included" philosophy of the boilerplate.

### Phase 1: Top 3 Foundation Libraries

Extract the most valuable, stable, and dependency-free components:

1. **`packages/php/tls-config` + `packages/node/tls-config`**
   - TLS verification logic (production vs development)
   - CA certificate path resolution
   - Socket options generation
   - ~100-200 LOC, zero dependencies
   - Every project needs TLS handling

2. **`packages/php/docker-secrets` + `packages/node/docker-secrets`**
   - Docker secrets loading (`/run/secrets/`, `/tmp/secrets/`)
   - Environment variable fallback
   - Priority: secrets > env > default
   - ~50-100 LOC, minimal dependencies
   - Standard pattern for production deployments

3. **`packages/php/db-connection` + `packages/node/db-connection`**
   - PostgreSQL/MySQL/MariaDB abstraction
   - Connection pooling with configuration
   - TLS/SSL support
   - Parameter placeholder abstraction ($1 vs ?)
   - Identifier quoting (double-quotes vs backticks)
   - ~300-500 LOC per language
   - Depends on: tls-config, docker-secrets

## Implementation Plan

### Step 1: Create Packages Structure (Week 1)

```
zappzarapp/
├── packages/
│   ├── php/
│   │   ├── tls-config/
│   │   │   ├── composer.json
│   │   │   ├── src/TlsConfig.php
│   │   │   ├── tests/
│   │   │   └── README.md
│   │   ├── docker-secrets/
│   │   │   ├── composer.json
│   │   │   ├── src/DockerSecrets.php
│   │   │   ├── tests/
│   │   │   └── README.md
│   │   └── db-connection/
│   │       ├── composer.json
│   │       ├── src/
│   │       │   ├── ConnectionFactory.php
│   │       │   ├── DatabaseConfig.php
│   │       │   └── Pool.php
│   │       ├── tests/
│   │       └── README.md
│   └── node/
│       ├── tls-config/
│       │   ├── package.json
│       │   ├── src/TlsConfig.ts
│       │   ├── tests/
│       │   └── README.md
│       ├── docker-secrets/
│       │   ├── package.json
│       │   ├── src/credentials.ts
│       │   ├── tests/
│       │   └── README.md
│       └── db-connection/
│           ├── package.json
│           ├── src/
│           │   ├── ConnectionFactory.ts
│           │   ├── DatabaseConfig.ts
│           │   └── pool.ts
│           ├── tests/
│           └── README.md
├── src/
│   ├── php/   # imports from packages/ via composer path repo
│   └── node/  # imports from packages/ via pnpm workspace
```

### Step 2: Extract TLS Config (Week 1-2)

**PHP:**
```json
// packages/php/tls-config/composer.json
{
  "name": "zappzarapp/tls-config",
  "description": "TLS/SSL configuration utilities for production and development",
  "type": "library",
  "license": "MIT",
  "autoload": {
    "psr-4": {
      "Zappzarapp\\TlsConfig\\": "src/"
    }
  },
  "require": {
    "php": "^8.2"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0"
  }
}
```

**Node:**
```json
// packages/node/tls-config/package.json
{
  "name": "@zappzarapp/tls-config",
  "version": "1.0.0",
  "description": "TLS/SSL configuration utilities for production and development",
  "type": "module",
  "main": "./dist/index.js",
  "types": "./dist/index.d.ts",
  "exports": {
    ".": {
      "import": "./dist/index.js",
      "types": "./dist/index.d.ts"
    }
  },
  "scripts": {
    "build": "tsc",
    "test": "vitest"
  },
  "devDependencies": {
    "typescript": "^5.3.0",
    "vitest": "^1.2.0"
  }
}
```

**Move and adapt:**
- `src/php/App/Infrastructure/TlsConfig.php` → `packages/php/tls-config/src/TlsConfig.php`
- `src/node/backend/Shared/Config/TlsConfig.ts` → `packages/node/tls-config/src/TlsConfig.ts`
- Move existing tests
- Update imports in boilerplate

### Step 3: Extract Docker Secrets (Week 2)

**PHP:**
```json
// packages/php/docker-secrets/composer.json
{
  "name": "zappzarapp/docker-secrets",
  "description": "Docker secrets loader with environment fallback",
  "type": "library",
  "license": "MIT",
  "autoload": {
    "psr-4": {
      "Zappzarapp\\DockerSecrets\\": "src/"
    }
  },
  "require": {
    "php": "^8.2"
  }
}
```

**Node:**
```json
// packages/node/docker-secrets/package.json
{
  "name": "@zappzarapp/docker-secrets",
  "version": "1.0.0",
  "description": "Docker secrets loader with environment fallback",
  "type": "module",
  "main": "./dist/index.js",
  "types": "./dist/index.d.ts"
}
```

**Move and adapt:**
- PHP: Create new `DockerSecrets` class (currently inline in various places)
- `src/node/backend/Shared/Config/credentials.ts` → `packages/node/docker-secrets/src/credentials.ts`
- Write tests
- Update imports

### Step 4: Extract DB Connection (Week 3-4)

**PHP:**
```json
// packages/php/db-connection/composer.json
{
  "name": "zappzarapp/db-connection",
  "description": "PostgreSQL/MySQL/MariaDB connection abstraction with pooling",
  "type": "library",
  "license": "MIT",
  "autoload": {
    "psr-4": {
      "Zappzarapp\\DbConnection\\": "src/"
    }
  },
  "require": {
    "php": "^8.2",
    "ext-pdo": "*",
    "zappzarapp/tls-config": "^1.0",
    "zappzarapp/docker-secrets": "^1.0"
  }
}
```

**Node:**
```json
// packages/node/db-connection/package.json
{
  "name": "@zappzarapp/db-connection",
  "version": "1.0.0",
  "description": "PostgreSQL/MySQL/MariaDB connection abstraction with pooling",
  "type": "module",
  "dependencies": {
    "@zappzarapp/tls-config": "workspace:*",
    "@zappzarapp/docker-secrets": "workspace:*",
    "pg": "^8.11.0",
    "mysql2": "^3.9.0"
  }
}
```

**Move and adapt:**
- `src/php/App/Infrastructure/Database/` → `packages/php/db-connection/src/`
- `src/node/backend/Shared/Database/` → `packages/node/db-connection/src/`
- Maintain full test coverage (80%+)
- Update all imports in boilerplate

### Step 5: Integrate Packages in Boilerplate (Week 4)

**PHP Composer Configuration:**
```json
// src/php/composer.json
{
  "repositories": [
    {
      "type": "path",
      "url": "../../packages/php/tls-config",
      "options": {
        "symlink": true
      }
    },
    {
      "type": "path",
      "url": "../../packages/php/docker-secrets",
      "options": {
        "symlink": true
      }
    },
    {
      "type": "path",
      "url": "../../packages/php/db-connection",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "zappzarapp/tls-config": "^1.0",
    "zappzarapp/docker-secrets": "^1.0",
    "zappzarapp/db-connection": "^1.0"
  }
}
```

**Node pnpm Workspace (already exists!):**
```yaml
# pnpm-workspace.yaml (update)
packages:
  - 'src/node/*'
  - 'packages/node/*'
```

```json
// src/node/backend/package.json
{
  "dependencies": {
    "@zappzarapp/tls-config": "workspace:*",
    "@zappzarapp/docker-secrets": "workspace:*",
    "@zappzarapp/db-connection": "workspace:*"
  }
}
```

### Step 6: Documentation & CI (Week 4)

**Each package needs:**
- README.md with:
  - Installation instructions (for standalone use)
  - API documentation
  - Usage examples
  - Configuration options
- CHANGELOG.md (Keep a Changelog format)
- Tests with 80%+ coverage
- CI workflow in main repo

**Add to `.github/workflows/quality-checks.yml`:**
```yaml
- name: Test PHP Packages
  run: |
    cd packages/php/tls-config && composer test
    cd ../docker-secrets && composer test
    cd ../db-connection && composer test

- name: Test Node Packages
  run: |
    pnpm --filter @zappzarapp/tls-config test
    pnpm --filter @zappzarapp/docker-secrets test
    pnpm --filter @zappzarapp/db-connection test
```

## Benefits

1. **Reusability**: Other projects can use these packages
2. **Clear APIs**: Package boundaries force clean interfaces
3. **Focused Testing**: Isolated coverage per package
4. **Independent Versions**: Can be versioned separately (later)
5. **Low Overhead**: Single repo, single CI, atomic commits
6. **Exit Strategy**: Can be extracted to separate repos if needed

## Trade-offs

**Advantages:**
- Keeps "batteries included" philosophy
- Minimal maintenance overhead
- No synchronization issues
- Fast development velocity

**Disadvantages:**
- Not standalone yet (requires monorepo checkout)
- No independent release cycles (all packages released together)
- Package namespace coupling (all `zappzarapp/*`)

## Success Criteria

- [ ] All 3 packages created with proper structure
- [ ] Test coverage maintained at 80%+
- [ ] All boilerplate imports updated
- [ ] Documentation complete for each package
- [ ] CI/CD validates all packages
- [ ] No breaking changes to boilerplate API
- [ ] Performance impact < 5% (if any)

## Future Considerations (v1.2+)

After v1.1 stabilizes, evaluate:
- Extracting `audit-logger` package
- Extracting `encryption` package
- Publishing packages to Packagist/npm (optional)
- Separate release cycles per package

## References

- Node structure: `src/node/backend/Shared/`
- PHP structure: `src/php/App/Infrastructure/`
- pnpm workspaces: `pnpm-workspace.yaml`
- Composer path repositories: https://getcomposer.org/doc/05-repositories.md#path
