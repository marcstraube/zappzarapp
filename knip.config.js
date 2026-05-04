/** @type {import('knip').KnipConfig} */
// noinspection JSUnusedGlobalSymbols -- knip auto-loads this file by convention (knip.config.{js,ts,mjs}) via dynamic import; the default export has no static importer for the IDE to discover.
export default {
  // Entry points (knip auto-detects from package.json, only add non-standard ones)
  entry: [
    'resources/js/app.js'
  ],

  // Project files to analyze
  project: [
    'src/node/**/*.ts',
    'tests/node/**/*.ts',
    'resources/**/*.{js,ts}'
  ],

  // Ignore patterns (build artifacts, generated files, config files)
  ignore: [
    'build/**',
    '**/dist/**',
    'docs/**',
    'coverage/**',
    'public/assets/**',
    '**/*.min.js',
    '**/*.d.ts',
    'typedoc.json',
    // DevToolbar browser bundle: esbuild reads src/node/backend/DevToolbar/index.ts
    // (driven by devtoolbar.build.ts) and writes the IIFE to
    // src/php/DevToolbar/assets/devtoolbar.js. Both ends are invisible to knip's
    // import-graph crawl — the entry runs as a side effect and the output is a
    // bundled artifact loaded by the PHP layer.
    'src/node/backend/DevToolbar/index.ts',
    'src/php/DevToolbar/assets/devtoolbar.js',

    // Boilerplate scaffolding shipped for users to build on. No in-tree
    // importer expected — these are the documented Public API surface
    // (DevToolbar barrels) and ready-made test scaffolding (backend fixtures)
    // that ship with the platform.
    'src/node/backend/DevToolbar/storage/index.ts',
    'src/node/backend/DevToolbar/ui/index.ts',
    'src/node/backend/DevToolbar/utils/index.ts',
    'tests/node/backend/fixtures/responses.ts',
    'tests/node/backend/fixtures/testConfig.ts',
    'tests/node/backend/fixtures/users.ts'
  ],

  // Ignore dependencies (only those knip can't auto-detect)
  ignoreDependencies: [
    // Type definitions (used implicitly)
    '@types/*',

    // Git hooks (executed via husky/git, not imports)
    '@commitlint/cli',
    '@commitlint/config-conventional',
    'lint-staged',

    // Runtime utilities (loaded dynamically, not via imports)
    'pino-pretty',
    'ws',

    // Boilerplate dev-tools shipped with the platform — faker is delivered so
    // users can generate test data out of the box, even when no in-tree test
    // currently imports it
    '@faker-js/faker',

    // Build-tool plugins (loaded by name)
    'autoprefixer',
    'postcss',

    // CLI tools used via Make targets
    'ai-command-converter',
    'rulesync',
    'standard-version',

    // Analysis tools (self-referential, run via CLI)
    'depcheck'
  ]
};
