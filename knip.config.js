/** @type {import('knip').KnipConfig} */
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
    'dist/**',
    'docs/**',
    'coverage/**',
    'public/assets/**',
    '**/*.min.js',
    '**/*.d.ts',
    'typedoc.json'
  ],

  // Ignore dependencies (only those knip can't auto-detect)
  ignoreDependencies: [
    // Type definitions (used implicitly)
    '@types/*',

    // Git hooks (executed via husky/git, not imports)
    '@commitlint/cli',
    'lint-staged',

    // Runtime utilities (loaded dynamically, not via imports)
    'pino-pretty',
    'ws',

    // Analysis tools (self-referential, run via CLI)
    'depcheck'
  ]
};
