/**
 * lint-staged configuration
 *
 * Runs linters on staged files and auto-fixes issues before commit.
 * All tools use --fix/--write to automatically correct issues.
 * Full codebase checks remain available via make commands (make check, make analyse, etc.)
 *
 * Note: This runs inside the Node container via CaptainHook.
 * PHP files are handled separately in captainhook.json (PHP-CS-Fixer with autofix).
 *
 * Root config files (*.config.js, *.config.ts, etc.) are excluded because they are
 * mounted as read-only in containers for security. Edit them manually if needed.
 */
export default {
  // TypeScript/JavaScript files (auto-fix and re-stage)
  // Excludes root config files (mounted as read-only in containers)
  'src/**/*.{ts,js}': [
    'pnpm exec prettier --write',
    'pnpm exec eslint --fix --max-warnings=0 --no-warn-ignored',
  ],
  'tests/**/*.{ts,js}': [
    'pnpm exec prettier --write',
    'pnpm exec eslint --fix --max-warnings=0 --no-warn-ignored',
  ],
  'resources/**/*.{ts,js}': [
    'pnpm exec prettier --write',
    'pnpm exec eslint --fix --max-warnings=0 --no-warn-ignored',
  ],

  // JSON files (auto-fix and re-stage, excluding auto-generated and config files)
  // Note: .vscode/ is excluded because it's not mounted in containers
  // AI tool dirs (.claude/, .gemini/) are mounted in dev-tools (compose.override.yaml)
  '!(.vscode/**|composer|package|package-lock|tsconfig*|typedoc*|.prettierrc|.markdownlint*|.depcheckrc|captainhook|renovate|.versionrc).json': [
    'pnpm exec prettier --write',
  ],

  // Markdown files (auto-fix and re-stage)
  // Prettier first (formats tables), then markdownlint (checks remaining issues)
  // IDE dirs (.idea/, .vscode/) are filtered out: they are not mounted in the
  // dev-tools container, so in-container linters cannot see those files
  '**/*.md': (files) => {
    const lintable = files.filter((f) => !/(^|\/)\.(idea|vscode)\//.test(f));
    if (lintable.length === 0) {
      return [];
    }
    const fileArgs = lintable.join(' ');
    return [
      `pnpm exec prettier --write ${fileArgs}`,
      `pnpm exec markdownlint-cli2 --fix ${fileArgs}`,
    ];
  },
};
