# Task 05: Generate API Documentation in CI

## Priority

MEDIUM - Post-Release v1.1

## Estimated Effort

2 hours

## Context

During the Documentation & Developer Experience review, it was noted that while
TypeDoc and PHPDoc configurations exist, the generated documentation is not:

- Automatically built in CI
- Published to GitHub Pages
- Linked from main documentation

## Current State

- `typedoc.backend.json` and `typedoc.frontend.json` exist
- `package.json` has `docs` script
- `composer.json` has `docs` script
- No CI integration for documentation generation
- No GitHub Pages publishing

## Target State

1. Generate TypeDoc documentation in CI
2. Generate PHPDoc documentation in CI
3. Publish to GitHub Pages automatically
4. Link from main README

## Implementation Steps

### Step 1: Create Documentation Build Script

**Create `scripts/build-docs.sh`:**

```bash
#!/bin/bash
set -e

echo "Building API documentation..."

# Create output directory
mkdir -p docs/api/{php,node-backend,node-frontend}

# Build PHP documentation
echo "Building PHP documentation..."
if [ -f "tools/phpdoc.phar" ]; then
    php tools/phpdoc.phar --config=phpdoc.xml
else
    echo "Warning: phpdoc.phar not found, skipping PHP docs"
fi

# Build Node.js documentation
echo "Building Node.js documentation..."
pnpm run docs:backend
pnpm run docs:frontend

# Create index page
cat > docs/api/index.html << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>zappzarapp API Documentation</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        h1 { border-bottom: 2px solid #333; padding-bottom: 0.5rem; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 1rem; }
        .card h2 { margin-top: 0; }
        .card a { color: #0066cc; }
    </style>
</head>
<body>
    <h1>zappzarapp API Documentation</h1>
    <div class="cards">
        <div class="card">
            <h2>PHP API</h2>
            <p>Backend PHP application code documentation.</p>
            <a href="./php/index.html">View PHP Docs →</a>
        </div>
        <div class="card">
            <h2>Node.js Backend</h2>
            <p>Node.js backend services documentation.</p>
            <a href="./node-backend/index.html">View Backend Docs →</a>
        </div>
        <div class="card">
            <h2>Node.js Frontend</h2>
            <p>Node.js frontend utilities documentation.</p>
            <a href="./node-frontend/index.html">View Frontend Docs →</a>
        </div>
    </div>
</body>
</html>
EOF

echo "Documentation built successfully!"
echo "Output: docs/api/"
```

### Step 2: Update TypeDoc Configuration

**Update `typedoc.backend.json`:**

```json
{
  "$schema": "https://typedoc.org/schema.json",
  "entryPoints": ["src/node/backend"],
  "entryPointStrategy": "expand",
  "out": "docs/api/node-backend",
  "name": "zappzarapp Backend API",
  "readme": "src/node/backend/README.md",
  "excludePrivate": true,
  "excludeInternal": true,
  "includeVersion": true,
  "githubPages": true
}
```

**Update `typedoc.frontend.json`:**

```json
{
  "$schema": "https://typedoc.org/schema.json",
  "entryPoints": ["src/node/frontend"],
  "entryPointStrategy": "expand",
  "out": "docs/api/node-frontend",
  "name": "zappzarapp Frontend API",
  "readme": "src/node/frontend/README.md",
  "excludePrivate": true,
  "excludeInternal": true,
  "includeVersion": true,
  "githubPages": true
}
```

### Step 3: Create PHPDoc Configuration

**Create or update `phpdoc.xml`:**

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<phpdocumentor
    configVersion="3"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns="https://www.phpdoc.org"
    xsi:noNamespaceSchemaLocation="https://docs.phpdoc.org/latest/phpdoc.xsd"
>
    <title>zappzarapp PHP API</title>
    <paths>
        <output>docs/api/php</output>
        <cache>.cache/phpdoc</cache>
    </paths>
    <version number="1.0.0">
        <api>
            <source dsn=".">
                <path>src/php</path>
            </source>
            <output>.</output>
            <ignore hidden="true" symlinks="true">
                <path>vendor/**/*</path>
                <path>tests/**/*</path>
            </ignore>
            <visibility>public</visibility>
        </api>
    </version>
</phpdocumentor>
```

### Step 4: Add GitHub Actions Workflow

**Create `.github/workflows/docs.yml`:**

```yaml
name: Documentation

on:
  push:
    branches: [master]
  workflow_dispatch:

permissions:
  contents: read
  pages: write
  id-token: write

concurrency:
  group: 'pages'
  cancel-in-progress: true

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '24'

      - name: Setup pnpm
        uses: pnpm/action-setup@v4
        with:
          version: 10

      - name: Install dependencies
        run: pnpm install

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'

      - name: Download phpDocumentor
        run: |
          mkdir -p tools
          curl -L https://phpdoc.org/phpDocumentor.phar -o tools/phpdoc.phar
          chmod +x tools/phpdoc.phar

      - name: Build documentation
        run: |
          chmod +x scripts/build-docs.sh
          ./scripts/build-docs.sh

      - name: Setup Pages
        uses: actions/configure-pages@v4

      - name: Upload artifact
        uses: actions/upload-pages-artifact@v3
        with:
          path: 'docs/api'

  deploy:
    environment:
      name: github-pages
      url: ${{ steps.deployment.outputs.page_url }}
    runs-on: ubuntu-latest
    needs: build
    steps:
      - name: Deploy to GitHub Pages
        id: deployment
        uses: actions/deploy-pages@v4
```

### Step 5: Add Makefile Target

Add to `Makefile`:

```makefile
##@ Documentation

.PHONY: docs docs-serve

docs: ## Build API documentation
	@chmod +x scripts/build-docs.sh
	@./scripts/build-docs.sh

docs-serve: docs ## Build and serve documentation locally
	@echo "Serving documentation at http://localhost:8000"
	@cd docs/api && python3 -m http.server 8000
```

### Step 6: Update README

Add to `README.md`:

```markdown
## Documentation

- [User Documentation](.zappzarapp/docs/README.md)
- [API Documentation](https://marcstraube.github.io/zappzarapp/) (generated)

Generate documentation locally:

```bash
make docs
make docs-serve  # View at http://localhost:8000
```
```

### Step 7: Update .gitignore

Add to `.gitignore`:

```gitignore
# Generated documentation
docs/api/
.cache/phpdoc/
```

## Verification

1. **Local build works:**

   ```bash
   make docs
   ls docs/api/
   # Should show: index.html, php/, node-backend/, node-frontend/
   ```

2. **Local preview works:**

   ```bash
   make docs-serve
   # Open http://localhost:8000 in browser
   ```

3. **CI workflow exists:**

   ```bash
   test -f .github/workflows/docs.yml && echo "Workflow exists"
   ```

4. **After merge to master:**
   - Check Actions tab for docs workflow
   - Verify GitHub Pages URL works

## Files to Create/Modify

1. `scripts/build-docs.sh` - New build script
2. `typedoc.backend.json` - Update output path
3. `typedoc.frontend.json` - Update output path
4. `phpdoc.xml` - Create or update
5. `.github/workflows/docs.yml` - New workflow
6. `Makefile` - Add docs targets
7. `README.md` - Add docs link
8. `.gitignore` - Exclude generated docs

## Notes

- GitHub Pages must be enabled in repository settings
- First deployment may take a few minutes
- Docs are rebuilt on every push to master
- Consider adding a version selector for tagged releases
