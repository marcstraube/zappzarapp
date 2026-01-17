#!/bin/sh
# Nuxt 3 Post-Install Script for Zappzarapp
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
// Nuxt 3 Configuration for Zappzarapp
// https://nuxt.com/docs/api/configuration/nuxt-config

export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },

  // Zappzarapp Infrastructure Settings
  devServer: {
    host: '0.0.0.0', // Required for Docker
    port: 3001,      // Frontend port (backend uses 3000)
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
      <h1>Zappzarapp Frontend</h1>
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

# 5. Create/update tsconfig.json to be minimal (Nuxt generates .nuxt/tsconfig.json)
echo "[nuxt] Creating tsconfig.json..."
cat > tsconfig.json << 'EOF'
{
  "extends": "./.nuxt/tsconfig.json"
}
EOF

# 6. Create .gitignore
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

echo "[nuxt] Configuration complete!"
echo "[nuxt] Run 'make pnpm-sync' to install dependencies."
