/**
 * lint-staged configuration
 *
 * Runs linters on staged files and auto-fixes issues before commit.
 * All tools use --fix/--write to automatically correct issues.
 * Full codebase checks remain available via make commands (make check, make analyse, etc.)
 *
 * Note: This runs inside the Node container via CaptainHook.
 * PHP files are handled separately in captainhook.json (PHP-CS-Fixer with autofix).
 */
export default {
  // TypeScript/JavaScript files (auto-fix and re-stage)
  '*.{ts,js}': [
    'pnpm exec prettier --write',
    'pnpm exec eslint --fix --max-warnings=0 --no-warn-ignored',
  ],

  // JSON files (auto-fix and re-stage, excluding auto-generated package manager configs)
  '!(composer|package|package-lock).json': ['pnpm exec prettier --write'],

  // Markdown files (auto-fix and re-stage)
  // Prettier first (formats tables), then markdownlint (checks remaining issues)
  '*.md': ['pnpm exec prettier --write', 'pnpm exec markdownlint-cli2 --fix'],
};
