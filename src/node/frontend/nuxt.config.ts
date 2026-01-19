// Nuxt 3 Configuration for Zappzarapp
// https://nuxt.com/docs/api/configuration/nuxt-config

export default defineNuxtConfig({
  compatibilityDate: '2024-11-01',
  devtools: { enabled: true },

  // Zappzarapp Infrastructure Settings
  devServer: {
    host: '0.0.0.0', // Required for Docker
    port: 3001, // Frontend port (backend uses 3000)
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
