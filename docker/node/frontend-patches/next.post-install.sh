#!/bin/sh
# Next.js Post-Install Script for zappzarapp
# Configures Next.js to work with the zappzarapp infrastructure

set -e

FRONTEND_DIR="${1:-.}"
cd "$FRONTEND_DIR"

echo "[next] Configuring for zappzarapp infrastructure..."

# 1. Update package.json
echo "[next] Setting package name and scripts..."
node -e "
const fs = require('fs');
const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));
pkg.name = '@zappzarapp/frontend';
pkg.version = '1.0.0';
pkg.private = true;
// Set correct port in dev script
pkg.scripts = pkg.scripts || {};
// Serve dev over HTTPS with the mounted internal cert (nginx proxy + healthcheck
// expect HTTPS on 3001); fall back to HTTP when the cert is absent (laptop dev).
pkg.scripts.dev = 'if [ -f /etc/ssl/certs/cert.crt ]; then next dev -H 0.0.0.0 -p 3001 --experimental-https --experimental-https-key /etc/ssl/private/cert.key --experimental-https-cert /etc/ssl/certs/cert.crt; else next dev -H 0.0.0.0 -p 3001; fi';
pkg.scripts.build = 'next build';
pkg.scripts.start = 'next start -H 0.0.0.0 -p 3001';
pkg.scripts.lint = 'next lint';
fs.writeFileSync('package.json', JSON.stringify(pkg, null, 2) + '\n');
"

# 2. Create next.config.mjs with zappzarapp settings
echo "[next] Creating next.config.mjs..."
cat > next.config.mjs << 'EOF'
/** @type {import('next').NextConfig} */
const nextConfig = {
  // API Proxy: Route /api/backend/* to Express backend
  async rewrites() {
    return [
      {
        source: '/api/backend/:path*',
        destination: 'http://localhost:3000/:path*',
      },
    ];
  },

  // Environment variables available on client
  env: {
    NEXT_PUBLIC_API_BASE: '/api/backend',
  },

  // Output standalone for Docker production builds
  output: 'standalone',
};

export default nextConfig;
EOF

# 3. Remove old next.config.js if exists
rm -f next.config.js next.config.ts

# 4. Create app directory structure (App Router)
echo "[next] Creating app structure..."
mkdir -p app

# 5. Create globals.css
echo "[next] Creating globals.css..."
cat > app/globals.css << 'EOF'
/**
 * Global Styles - zappzarapp Next.js Frontend
 * Minimal global resets, component styles use CSS Modules
 */

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  line-height: 1.5;
  color: #333;
}

code {
  font-family: 'SF Mono', Monaco, 'Courier New', monospace;
  background: rgba(59, 130, 246, 0.1);
  padding: 0.2rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 0.875em;
}

pre code {
  display: block;
  padding: 1rem;
  overflow-x: auto;
  background: #1a1a2e;
  color: #0070f3;
}
EOF

# 6. Create layout.tsx
cat > app/layout.tsx << 'EOF'
import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'zappzarapp Frontend',
  description: 'Next.js SSR Frontend running on Node.js',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
EOF

# 7. Create page.module.css
echo "[next] Creating page.module.css..."
cat > app/page.module.css << 'EOF'
/**
 * Home Page Styles
 * CSS Modules provide scoped styles, CSP-compatible
 */

.main {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem;
}

.header {
  text-align: center;
  margin-bottom: 3rem;
}

.title {
  color: #0070f3;
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

.subtitle {
  color: #666;
  font-size: 1.1rem;
}

.section {
  margin-bottom: 2rem;
}

.sectionTitle {
  color: #333;
  font-size: 1.3rem;
  margin-bottom: 1rem;
  border-bottom: 2px solid #0070f3;
  padding-bottom: 0.5rem;
}

.statusCard {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.statusIndicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #0070f3;
  box-shadow: 0 0 8px #0070f3;
}

.apiCard {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
}

.apiCardOffline {
  composes: apiCard;
  background: #fef2f2;
  border: 1px solid #fecaca;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.statusIndicatorOffline {
  composes: statusIndicator;
  background: #ef4444;
  box-shadow: 0 0 8px #ef4444;
}

.apiPre {
  background: #1a1a2e;
  color: #0070f3;
  padding: 1rem;
  border-radius: 4px;
  overflow: auto;
  font-size: 0.875rem;
}
EOF

# 8. Create page.tsx
cat > app/page.tsx << 'EOF'
import styles from './page.module.css';

async function getHealth() {
  try {
    const res = await fetch('http://localhost:3000/health', {
      cache: 'no-store',
    });
    if (!res.ok) return null;
    return res.json();
  } catch {
    return null;
  }
}

export default async function Home() {
  const health = await getHealth();

  return (
    <main className={styles.main}>
      <header className={styles.header}>
        <h1 className={styles.title}>zappzarapp Frontend</h1>
        <p className={styles.subtitle}>
          Next.js SSR Frontend running on Node.js
        </p>
      </header>

      <section className={styles.section}>
        <h2 className={styles.sectionTitle}>Frontend Status</h2>
        <div className={styles.statusCard}>
          <span className={styles.statusIndicator}></span>
          <span>Next.js SSR Active</span>
        </div>
      </section>

      <section className={styles.section}>
        <h2 className={styles.sectionTitle}>Backend API Status</h2>
        {health ? (
          <div className={styles.apiCard}>
            <pre className={styles.apiPre}>
              {JSON.stringify(health, null, 2)}
            </pre>
          </div>
        ) : (
          <div className={styles.apiCardOffline}>
            <span className={styles.statusIndicatorOffline}></span>
            <span>Backend not available</span>
          </div>
        )}
      </section>
    </main>
  );
}
EOF

# 9. Create tsconfig.json
echo "[next] Creating tsconfig.json..."
cat > tsconfig.json << 'EOF'
{
  "compilerOptions": {
    "lib": ["dom", "dom.iterable", "esnext"],
    "allowJs": true,
    "skipLibCheck": true,
    "strict": true,
    "noEmit": true,
    "esModuleInterop": true,
    "module": "esnext",
    "moduleResolution": "bundler",
    "resolveJsonModule": true,
    "isolatedModules": true,
    "jsx": "preserve",
    "incremental": true,
    "plugins": [
      {
        "name": "next"
      }
    ],
    "paths": {
      "@/*": ["./*"]
    }
  },
  "include": ["next-env.d.ts", "**/*.ts", "**/*.tsx", ".next/types/**/*.ts"],
  "exclude": ["node_modules"]
}
EOF

# 10. Create the /health route handler (container orchestrator probes hit
#     it; force-dynamic keeps it a live handler instead of a build-time
#     prerender with a frozen timestamp)
echo "[next] Creating app/health/route.ts..."
mkdir -p app/health
cat > app/health/route.ts << 'EOF'
// Lightweight liveness/readiness endpoint for container orchestrators.
export const dynamic = 'force-dynamic';

export function GET(): Response {
  return Response.json({
    status: 'ok',
    service: 'node-frontend',
    timestamp: new Date().toISOString(),
  });
}
EOF

# 11. Create .gitignore
echo "[next] Creating .gitignore..."
cat > .gitignore << 'EOF'
# Next.js build output
.next/
out/

# Production
build

# Node
node_modules

# Debug
npm-debug.log*
yarn-debug.log*
yarn-error.log*

# Local env files
.env*.local

# Vercel
.vercel

# TypeScript
*.tsbuildinfo
next-env.d.ts
EOF

# 12. Approve dependency build scripts (pnpm blocks them by default and
#     hard-fails the install with ERR_PNPM_IGNORED_BUILDS on unapproved ones)
echo "[next] Creating pnpm-workspace.yaml..."
cat > pnpm-workspace.yaml << 'EOF'
# Build-script approvals: pnpm blocks dependency build scripts by default
# and hard-fails (ERR_PNPM_IGNORED_BUILDS) on unapproved ones. These ship
# prebuilt binaries, so their scripts are safe to run.
allowBuilds:
  '@parcel/watcher': true
  esbuild: true
  sharp: true
  unrs-resolver: true
EOF

# 13. Write the framework-specific GOSS output check consumed by the
#     test-framework image stage. The base node-frontend.yaml spec stays
#     framework-agnostic; this file pins the Next.js build output.
#     Removed by make node-frontend-clean.
echo "[next] Writing GOSS framework output check..."
cat > ../../../tests/goss/services/node-frontend.framework.yaml << 'EOF'
# Framework-specific GOSS checks for the production framework image.
# Generated by make node-frontend-next: validates that the image build
# produced the Next.js output (BUILD_ID marks a completed next build).
file:
  /app/.next:
    exists: true
    filetype: directory
  /app/.next/BUILD_ID:
    exists: true
EOF

echo "[next] Configuration complete!"
echo "[next] Run 'make pnpm-sync' to install dependencies."
