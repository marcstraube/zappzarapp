#!/bin/sh
# Next.js Post-Install Script for Zappzarapp
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
pkg.scripts.dev = 'next dev -H 0.0.0.0 -p 3001';
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

# 5. Create layout.tsx
cat > app/layout.tsx << 'EOF'
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Zappzarapp Frontend',
  description: 'Next.js SSR Frontend running on Node.js',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body style={{ margin: 0, fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' }}>
        {children}
      </body>
    </html>
  );
}
EOF

# 6. Create page.tsx
cat > app/page.tsx << 'EOF'
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
    <main style={{ maxWidth: '800px', margin: '0 auto', padding: '2rem' }}>
      <header style={{ textAlign: 'center', marginBottom: '3rem' }}>
        <h1 style={{ color: '#0070f3', fontSize: '2.5rem', marginBottom: '0.5rem' }}>
          Zappzarapp Frontend
        </h1>
        <p style={{ color: '#666', fontSize: '1.1rem' }}>
          Next.js SSR Frontend running on Node.js
        </p>
      </header>

      <section style={{ marginBottom: '2rem' }}>
        <h2 style={{
          color: '#333',
          fontSize: '1.3rem',
          marginBottom: '1rem',
          borderBottom: '2px solid #0070f3',
          paddingBottom: '0.5rem'
        }}>
          Frontend Status
        </h2>
        <div style={{
          background: '#f8f9fa',
          borderRadius: '8px',
          padding: '1rem',
          display: 'flex',
          alignItems: 'center',
          gap: '0.75rem'
        }}>
          <span style={{
            width: '12px',
            height: '12px',
            borderRadius: '50%',
            background: '#0070f3',
            boxShadow: '0 0 8px #0070f3'
          }}></span>
          <span>Next.js SSR Active</span>
        </div>
      </section>

      <section>
        <h2 style={{
          color: '#333',
          fontSize: '1.3rem',
          marginBottom: '1rem',
          borderBottom: '2px solid #0070f3',
          paddingBottom: '0.5rem'
        }}>
          Backend API Status
        </h2>
        {health ? (
          <div style={{
            background: '#f8f9fa',
            borderRadius: '8px',
            padding: '1rem'
          }}>
            <pre style={{
              background: '#1a1a2e',
              color: '#0070f3',
              padding: '1rem',
              borderRadius: '4px',
              overflow: 'auto',
              fontSize: '0.875rem'
            }}>
              {JSON.stringify(health, null, 2)}
            </pre>
          </div>
        ) : (
          <div style={{
            background: '#fef2f2',
            border: '1px solid #fecaca',
            borderRadius: '8px',
            padding: '1rem',
            display: 'flex',
            alignItems: 'center',
            gap: '0.75rem'
          }}>
            <span style={{
              width: '12px',
              height: '12px',
              borderRadius: '50%',
              background: '#ef4444',
              boxShadow: '0 0 8px #ef4444'
            }}></span>
            <span>Backend not available</span>
          </div>
        )}
      </section>
    </main>
  );
}
EOF

# 7. Create tsconfig.json
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

# 8. Create .gitignore
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

echo "[next] Configuration complete!"
echo "[next] Run 'make pnpm-sync' to install dependencies."
