#!/bin/sh
# React Router Post-Install Script for zappzarapp
# Configures React Router (formerly Remix v2) to work with the zappzarapp infrastructure

set -e

FRONTEND_DIR="${1:-.}"
cd "$FRONTEND_DIR"

echo "[react-router] Configuring for zappzarapp infrastructure..."

# 1. Update package.json
echo "[react-router] Setting package name..."
node -e "
const fs = require('fs');
const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));
pkg.name = '@zappzarapp/frontend';
pkg.version = '1.0.0';
pkg.private = true;
fs.writeFileSync('package.json', JSON.stringify(pkg, null, 2) + '\n');
"

# 2. Update vite.config.ts with zappzarapp settings (preserve existing plugins)
echo "[react-router] Updating vite.config.ts..."
cat > vite.config.ts << 'EOF'
import { reactRouter } from "@react-router/dev/vite";
import tailwindcss from "@tailwindcss/vite";
import { defineConfig } from "vite";

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
  plugins: [tailwindcss(), reactRouter()],
});
EOF

# 3. Create a simple home route that shows backend health
echo "[react-router] Creating home route with backend health check..."
cat > app/routes/home.tsx << 'EOF'
import type { Route } from "./+types/home";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "zappzarapp Frontend" },
    { name: "description", content: "React Router SSR Frontend running on Node.js" },
  ];
}

export async function loader({ request }: Route.LoaderArgs) {
  try {
    const res = await fetch('http://localhost:3000/health');
    if (!res.ok) return { health: null };
    const health = await res.json();
    return { health };
  } catch {
    return { health: null };
  }
}

export default function Home({ loaderData }: Route.ComponentProps) {
  const { health } = loaderData;

  return (
    <main className="max-w-4xl mx-auto p-8">
      <header className="text-center mb-12">
        <h1 className="text-4xl font-bold text-[#e8625e] mb-2">
          zappzarapp Frontend
        </h1>
        <p className="text-gray-600 text-lg">
          React Router SSR Frontend running on Node.js
        </p>
      </header>

      <section className="mb-8">
        <h2 className="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-[#e8625e]">
          Frontend Status
        </h2>
        <div className="bg-gray-100 rounded-lg p-4 flex items-center gap-3">
          <span className="w-3 h-3 rounded-full bg-[#e8625e] shadow-[0_0_8px_#e8625e]"></span>
          <span>React Router SSR Active</span>
        </div>
      </section>

      <section>
        <h2 className="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b-2 border-[#e8625e]">
          Backend API Status
        </h2>
        {health ? (
          <div className="bg-gray-100 rounded-lg p-4">
            <pre className="bg-[#1a1a2e] text-[#e8625e] p-4 rounded overflow-auto text-sm">
              {JSON.stringify(health, null, 2)}
            </pre>
          </div>
        ) : (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center gap-3">
            <span className="w-3 h-3 rounded-full bg-red-500 shadow-[0_0_8px_#ef4444]"></span>
            <span>Backend not available</span>
          </div>
        )}
      </section>
    </main>
  );
}
EOF

# 4. Create the /health resource route and register it (container
#    orchestrator probes hit it; a loader-only route answers without
#    rendering any page)
echo "[react-router] Creating app/routes/health.ts..."
cat > app/routes/health.ts << 'EOF'
// Lightweight liveness/readiness endpoint for container orchestrators.
export async function loader() {
  return Response.json({
    status: 'ok',
    service: 'node-frontend',
    timestamp: new Date().toISOString(),
  });
}
EOF

echo "[react-router] Registering routes in app/routes.ts..."
cat > app/routes.ts << 'EOF'
import { type RouteConfig, index, route } from '@react-router/dev/routes';

export default [
  index('routes/home.tsx'),
  route('health', 'routes/health.ts'),
] satisfies RouteConfig;
EOF

# 5. Update .gitignore
echo "[react-router] Updating .gitignore..."
cat > .gitignore << 'EOF'
# React Router build output
build/
.react-router/

# Node
node_modules

# Logs
*.log

# Local env files
.env
.env.local
.env.*.local

# Editor
.idea
.vscode
EOF

# 6. Approve dependency build scripts (pnpm blocks them by default and
#    hard-fails the install with ERR_PNPM_IGNORED_BUILDS on unapproved ones)
echo "[react-router] Creating pnpm-workspace.yaml..."
cat > pnpm-workspace.yaml << 'EOF'
# Build-script approvals: pnpm blocks dependency build scripts by default
# and hard-fails (ERR_PNPM_IGNORED_BUILDS) on unapproved ones. These ship
# prebuilt binaries, so their scripts are safe to run.
allowBuilds:
  '@parcel/watcher': true
  esbuild: true
EOF

# 7. Write the framework-specific GOSS output check consumed by the
#    test-framework image stage. The base node-frontend.yaml spec stays
#    framework-agnostic; this file pins the React Router build output the
#    start script serves. Removed by make node-frontend-clean.
echo "[react-router] Writing GOSS framework output check..."
cat > ../../../tests/goss/services/node-frontend.framework.yaml << 'EOF'
# Framework-specific GOSS checks for the production framework image.
# Generated by make node-frontend-remix: validates that the image build
# produced the React Router output served by the start script
# (react-router-serve ./build/server/index.js).
file:
  /app/build:
    exists: true
    filetype: directory
  /app/build/server/index.js:
    exists: true
EOF

echo "[react-router] Configuration complete!"
echo "[react-router] Run 'make pnpm-sync' to install dependencies."
