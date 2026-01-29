/**
 * Vite client environment type definitions
 * See: https://vitejs.dev/guide/env-and-mode.html
 */

/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly DEV: boolean;
  readonly PROD: boolean;
  readonly MODE: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
