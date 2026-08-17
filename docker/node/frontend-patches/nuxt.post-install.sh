#!/bin/sh
# Nuxt 3 Post-Install Script for zappzarapp
# Configures Nuxt to work with the zappzarapp infrastructure

set -e

FRONTEND_DIR="${1:-.}"
cd "$FRONTEND_DIR"

echo "[nuxt] Configuring for zappzarapp infrastructure..."

# 1. Update package.json
echo "[nuxt] Setting package name and scripts..."
# Use node to safely modify JSON
node -e "
const fs = require('fs');
const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));
pkg.name = '@zappzarapp/frontend';
pkg.version = '1.0.0';
pkg.private = true;
// Ensure correct scripts
pkg.scripts = pkg.scripts || {};
pkg.scripts.dev = 'nuxt dev';
pkg.scripts.build = 'nuxt build';
pkg.scripts.start = 'node .output/server/index.mjs';
pkg.scripts.preview = 'nuxt preview';
fs.writeFileSync('package.json', JSON.stringify(pkg, null, 2) + '\n');
"

# 2. Create nuxt.config.ts with zappzarapp settings
echo "[nuxt] Creating nuxt.config.ts..."
cat > nuxt.config.ts << 'EOF'
// Nuxt 3 Configuration for zappzarapp
// https://nuxt.com/docs/api/configuration/nuxt-config
import { existsSync } from 'node:fs';

// Serve the dev server over HTTPS with the internal certificate mounted into the
// container. The nginx framework proxy and the container healthcheck both expect
// HTTPS on port 3001 (zero-trust). Guarded so cert-less contexts (e.g. plain
// `pnpm dev` on a laptop) fall back to plain HTTP.
const CERT_PATH = '/etc/ssl/certs/cert.crt';
const KEY_PATH = '/etc/ssl/private/cert.key';
const devHttps =
  existsSync(CERT_PATH) && existsSync(KEY_PATH)
    ? { https: { key: KEY_PATH, cert: CERT_PATH } }
    : {};

export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },

  // zappzarapp Infrastructure Settings
  devServer: {
    host: '0.0.0.0', // Required for Docker
    port: 3001,      // Frontend port (backend uses 3000)
    ...devHttps,     // internal TLS when the mounted cert is present
  },

  // API Proxy: Route /api/backend/* to Express backend
  // This allows SSR to call the backend directly
  routeRules: {
    '/api/backend/**': {
      proxy: 'http://localhost:3000/**',
    },
  },

  // Runtime config for client-side API calls
  runtimeConfig: {
    public: {
      apiBase: '/api/backend',
    },
  },

  // TypeScript configuration
  // Note: Set typeCheck: true after installing vue-tsc (pnpm add -D vue-tsc)
  typescript: {
    strict: true,
  },
});
EOF

# 3. Create a minimal app.vue if it doesn't exist or replace default
echo "[nuxt] Creating app.vue..."
cat > app.vue << 'EOF'
<template>
  <NuxtPage />
</template>
EOF

# 4. Create pages directory with index page
echo "[nuxt] Creating pages/index.vue..."
mkdir -p pages
cat > pages/index.vue << 'EOF'
<script setup lang="ts">
// Fetch backend health status
const { data: health, error } = await useFetch<{
  status: string;
  service: string;
  timestamp: string;
}>('/api/backend/health');
</script>

<template>
  <main class="container">
    <header>
      <h1>zappzarapp Frontend</h1>
      <p class="subtitle">Nuxt 3 SSR Frontend running on Node.js</p>
    </header>

    <section class="status">
      <h2>Frontend Status</h2>
      <div class="status-card">
        <span class="status-indicator online"></span>
        <span>Nuxt 3 SSR Active</span>
      </div>
    </section>

    <section class="api-status">
      <h2>Backend API Status</h2>
      <div v-if="health" class="api-card">
        <pre>{{ JSON.stringify(health, null, 2) }}</pre>
      </div>
      <div v-else-if="error" class="api-card offline">
        <span class="status-indicator offline"></span>
        <span>Backend not available</span>
      </div>
      <div v-else class="api-card loading">
        <span>Loading...</span>
      </div>
    </section>
  </main>
</template>

<style scoped>
.container {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

header {
  text-align: center;
  margin-bottom: 3rem;
}

h1 {
  color: #00dc82;
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

.subtitle {
  color: #666;
  font-size: 1.1rem;
}

section {
  margin-bottom: 2rem;
}

h2 {
  color: #333;
  font-size: 1.3rem;
  margin-bottom: 1rem;
  border-bottom: 2px solid #00dc82;
  padding-bottom: 0.5rem;
}

.status-card, .api-card {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.status-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.status-indicator.online {
  background: #00dc82;
  box-shadow: 0 0 8px #00dc82;
}

.status-indicator.offline {
  background: #ef4444;
  box-shadow: 0 0 8px #ef4444;
}

.api-card pre {
  background: #1a1a2e;
  color: #00dc82;
  padding: 1rem;
  border-radius: 4px;
  overflow-x: auto;
  font-size: 0.875rem;
  width: 100%;
}

.api-card.offline {
  background: #fef2f2;
  border: 1px solid #fecaca;
}

.api-card.loading {
  color: #666;
}
</style>
EOF

# 5. Create the /health server route (container orchestrator probes hit it;
#    a Nitro server route answers without rendering any SSR page)
echo "[nuxt] Creating server/routes/health.get.ts..."
mkdir -p server/routes
cat > server/routes/health.get.ts << 'EOF'
// Lightweight liveness/readiness endpoint for container orchestrators.
export default defineEventHandler(() => ({
  status: 'ok',
  service: 'node-frontend',
  timestamp: new Date().toISOString(),
}));
EOF

# 6. Create/update tsconfig.json to be minimal (Nuxt generates .nuxt/tsconfig.json)
echo "[nuxt] Creating tsconfig.json..."
cat > tsconfig.json << 'EOF'
{
  "extends": "./.nuxt/tsconfig.json"
}
EOF

# 7. Create .gitignore
echo "[nuxt] Creating .gitignore..."
cat > .gitignore << 'EOF'
# Nuxt dev/build outputs
.output
.nuxt
.nitro
.cache
dist

# Node
node_modules

# Logs
*.log

# Misc
.DS_Store

# Local env files
.env.local
.env.*.local
EOF

# 8. Approve dependency build scripts (pnpm blocks them by default and
#    hard-fails the install with ERR_PNPM_IGNORED_BUILDS on unapproved ones)
echo "[nuxt] Creating pnpm-workspace.yaml..."
cat > pnpm-workspace.yaml << 'EOF'
# Build-script approvals: pnpm blocks dependency build scripts by default
# and hard-fails (ERR_PNPM_IGNORED_BUILDS) on unapproved ones. These ship
# prebuilt binaries, so their scripts are safe to run.
allowBuilds:
  '@parcel/watcher': true
  esbuild: true
EOF

# 9. Write the framework-specific GOSS output check consumed by the
#    test-framework image stage. The base node-frontend.yaml spec stays
#    framework-agnostic; this file pins the Nuxt build output the start
#    script serves. Removed by make node-frontend-clean.
echo "[nuxt] Writing GOSS framework output check..."
cat > ../../../tests/goss/services/node-frontend.framework.yaml << 'EOF'
# Framework-specific GOSS checks for the production framework image.
# Generated by make node-frontend-nuxt: validates that the image build
# produced the Nuxt output served by the start script
# (node .output/server/index.mjs).
file:
  /app/.output:
    exists: true
    filetype: directory
  /app/.output/server/index.mjs:
    exists: true
EOF

echo "[nuxt] Configuration complete!"
echo "[nuxt] Run 'make pnpm-sync' to install dependencies."
