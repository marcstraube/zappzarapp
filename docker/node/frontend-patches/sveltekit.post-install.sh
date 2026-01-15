#!/bin/sh
# SvelteKit Post-Install Script for Zappzarapp
# Configures SvelteKit to work with the zappzarapp infrastructure

set -e

FRONTEND_DIR="${1:-.}"
cd "$FRONTEND_DIR"

echo "[sveltekit] Configuring for zappzarapp infrastructure..."

# 1. Update package.json
echo "[sveltekit] Setting package name and scripts..."
node -e "
const fs = require('fs');
const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));
pkg.name = '@zappzarapp/frontend';
pkg.version = '1.0.0';
pkg.private = true;
// Ensure correct scripts
pkg.scripts = pkg.scripts || {};
pkg.scripts.dev = 'vite dev --host 0.0.0.0 --port 3001';
pkg.scripts.build = 'vite build';
pkg.scripts.preview = 'vite preview --host 0.0.0.0 --port 3001';
pkg.scripts.start = 'node build';
fs.writeFileSync('package.json', JSON.stringify(pkg, null, 2) + '\n');
"

# 2. Create vite.config.ts with zappzarapp settings
echo "[sveltekit] Creating vite.config.ts..."
cat > vite.config.ts << 'EOF'
import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

export default defineConfig({
  server: {
    host: '0.0.0.0', // Required for Docker
    port: 3001,      // Frontend port (backend uses 3000)
    // API Proxy: Route /api/backend/* to Express backend
    proxy: {
      '/api/backend': {
        target: 'http://localhost:3000',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api\/backend/, ''),
      },
    },
  },
  plugins: [sveltekit()],
});
EOF

# 3. Create svelte.config.js with Node adapter
echo "[sveltekit] Creating svelte.config.js..."
cat > svelte.config.js << 'EOF'
import adapter from '@sveltejs/adapter-node';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

/** @type {import('@sveltejs/kit').Config} */
const config = {
  preprocess: vitePreprocess(),

  kit: {
    adapter: adapter({
      out: 'build',
    }),
  },
};

export default config;
EOF

# 4. Create src directory structure
echo "[sveltekit] Creating src structure..."
mkdir -p src/routes
mkdir -p src/lib

# 5. Create app.html
cat > src/app.html << 'EOF'
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <link rel="icon" href="%sveltekit.assets%/favicon.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    %sveltekit.head%
  </head>
  <body data-sveltekit-preload-data="hover">
    <div style="display: contents">%sveltekit.body%</div>
  </body>
</html>
EOF

# 6. Create +layout.svelte
cat > src/routes/+layout.svelte << 'EOF'
<script>
  import { onMount } from 'svelte';
</script>

<svelte:head>
  <style>
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
  </style>
</svelte:head>

<slot />
EOF

# 7. Create +page.server.ts (server-side data loading)
cat > src/routes/+page.server.ts << 'EOF'
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = async ({ fetch }) => {
  try {
    const res = await fetch('http://localhost:3000/health');
    if (!res.ok) return { health: null };
    const health = await res.json();
    return { health };
  } catch {
    return { health: null };
  }
};
EOF

# 8. Create +page.svelte
cat > src/routes/+page.svelte << 'EOF'
<script lang="ts">
  import type { PageData } from './$types';
  export let data: PageData;
</script>

<svelte:head>
  <title>Zappzarapp Frontend</title>
  <meta name="description" content="SvelteKit SSR Frontend running on Node.js" />
</svelte:head>

<main class="container">
  <header>
    <h1>Zappzarapp Frontend</h1>
    <p class="subtitle">SvelteKit SSR Frontend running on Node.js</p>
  </header>

  <section class="status">
    <h2>Frontend Status</h2>
    <div class="status-card">
      <span class="status-indicator online"></span>
      <span>SvelteKit SSR Active</span>
    </div>
  </section>

  <section class="api-status">
    <h2>Backend API Status</h2>
    {#if data.health}
      <div class="api-card">
        <pre>{JSON.stringify(data.health, null, 2)}</pre>
      </div>
    {:else}
      <div class="api-card offline">
        <span class="status-indicator offline"></span>
        <span>Backend not available</span>
      </div>
    {/if}
  </section>
</main>

<style>
  .container {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
  }

  header {
    text-align: center;
    margin-bottom: 3rem;
  }

  h1 {
    color: #ff3e00;
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
    border-bottom: 2px solid #ff3e00;
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
    background: #ff3e00;
    box-shadow: 0 0 8px #ff3e00;
  }

  .status-indicator.offline {
    background: #ef4444;
    box-shadow: 0 0 8px #ef4444;
  }

  .api-card pre {
    background: #1a1a2e;
    color: #ff3e00;
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
</style>
EOF

# 9. Create app.d.ts
cat > src/app.d.ts << 'EOF'
// See https://svelte.dev/docs/kit/types#app.d.ts
// for information about these interfaces
declare global {
  namespace App {
    // interface Error {}
    // interface Locals {}
    // interface PageData {}
    // interface PageState {}
    // interface Platform {}
  }
}

export {};
EOF

# 10. Create tsconfig.json
echo "[sveltekit] Creating tsconfig.json..."
cat > tsconfig.json << 'EOF'
{
  "extends": "./.svelte-kit/tsconfig.json",
  "compilerOptions": {
    "allowJs": true,
    "checkJs": true,
    "esModuleInterop": true,
    "forceConsistentCasingInFileNames": true,
    "resolveJsonModule": true,
    "skipLibCheck": true,
    "sourceMap": true,
    "strict": true,
    "moduleResolution": "bundler"
  }
}
EOF

# 11. Create static directory with favicon
mkdir -p static
# Create a simple SVG favicon
cat > static/favicon.png << 'EOF'
EOF
# Note: Users should replace with their own favicon

# 12. Create .gitignore
echo "[sveltekit] Creating .gitignore..."
cat > .gitignore << 'EOF'
# SvelteKit build output
.svelte-kit/
build/

# Node
node_modules

# Logs
*.log

# Local env files
.env
.env.*
!.env.example

# Editor
.idea
.vscode
EOF

echo "[sveltekit] Configuration complete!"
echo "[sveltekit] Run 'make pnpm-install' to install dependencies."
