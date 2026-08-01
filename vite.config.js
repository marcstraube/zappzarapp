import { defineConfig } from 'vite';
import { resolve } from 'path';
import autoprefixer from 'autoprefixer';

export default defineConfig({
  // Root directory for source files
  root: 'resources',

  // Public base path - only used in production
  base: process.env.NODE_ENV === 'production' ? '/build/' : '/',

  // Build configuration
  build: {
    // Output directory relative to project root
    outDir: '../public/build',

    // Empty output directory before building
    emptyOutDir: true,

    // Generate manifest.json for cache busting
    manifest: true,

    // Rollup options
    rollupOptions: {
      input: {
        // Main entry points
        app: resolve(import.meta.dirname, 'resources/js/app.js'),
        'dev-dashboard-dashboard': resolve(import.meta.dirname, 'resources/js/dev-dashboard/dashboard.ts'),
        'dev-dashboard-database': resolve(import.meta.dirname, 'resources/js/dev-dashboard/database.ts'),
        'dev-dashboard-health': resolve(import.meta.dirname, 'resources/js/dev-dashboard/health.ts'),
        'dev-dashboard-logs': resolve(import.meta.dirname, 'resources/js/dev-dashboard/logs.ts'),
        'dev-dashboard-quality': resolve(import.meta.dirname, 'resources/js/dev-dashboard/quality.ts'),
        'dev-dashboard-system': resolve(import.meta.dirname, 'resources/js/dev-dashboard/system.ts'),
        // Add more entry points as needed:
        // admin: resolve(import.meta.dirname, 'resources/js/admin.js'),
      },
    },

    // Minification
    minify: 'esbuild',

    // Source maps for debugging
    sourcemap: process.env.NODE_ENV !== 'production',

    // Chunk size warnings
    chunkSizeWarningLimit: 1000,
  },

  // Development server configuration
  server: {
    // Port for Vite dev server (HMR)
    port: 5173,

    // Allow external access (required for Docker)
    host: '0.0.0.0',

    // Watch options
    watch: {
      usePolling: true,
      interval: 100,
      // Ignore config files to prevent file locks during git operations
      ignored: [
        '**/node_modules/**',
        '**/.git/**',
        '**/.*ignore',
        '**/.*rc',
        '**/.*rc.json',
        '**/*.config.js',
        '**/*.config.cjs',
        '**/*.config.ts',
      ],
    },

    // CORS - explicitly allow localhost:8080
    cors: {
      origin: '*',
      credentials: true,
    },

    // HMR configuration
    // Browser connects via nginx proxy on port 8443 with HTTPS/WSS
    // Custom path avoids conflict with PHP routing at root path
    hmr: {
      path: '/__vite_hmr__',
      clientPort: 8443,
      protocol: 'wss',
    },

    // Serve index.html for SPA routing
    strictPort: true,

    // Proxy API requests to PHP backend
    proxy: {
      '/api': {
        target: 'http://nginx:8080',
        changeOrigin: true,
      },
    },
  },

  // CSS configuration
  css: {
    // PostCSS configuration
    postcss: {
      plugins: [autoprefixer],
    },

    // Preprocessor options
    preprocessorOptions: {
      scss: {
        // Additional SCSS data (variables, mixins)
        // additionalData: `@import "@/css/variables.scss";`,
      },
    },

    // Dev source maps
    devSourcemap: true,
  },

  // Path aliases
  resolve: {
    alias: {
      '@': resolve(import.meta.dirname, 'resources'),
      '@js': resolve(import.meta.dirname, 'resources/js'),
      '@css': resolve(import.meta.dirname, 'resources/css'),
      '@img': resolve(import.meta.dirname, 'resources/images'),
    },
  },

  // Optimize dependencies
  optimizeDeps: {
    include: [],
  },
});
