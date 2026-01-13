/**
 * lint-staged configuration
 *
 * Runs linters only on staged files for faster commits.
 * Full codebase checks remain available via make commands (make check, make analyse, etc.)
 *
 * Note: This runs inside the Node container via CaptainHook.
 * PHP files cannot be checked from Node container - use make commands for full PHP checks.
 */
export default {
  // TypeScript/JavaScript files (--no-warn-ignored suppresses warnings for eslint-ignored files)
  '*.{ts,js}': [
    'pnpm exec prettier --check',
    'pnpm exec eslint --max-warnings=0 --no-warn-ignored',
  ],

  // JSON files
  '*.json': ['pnpm exec prettier --check'],

  // Markdown files
  '*.md': ['pnpm exec markdownlint-cli2'],
};
