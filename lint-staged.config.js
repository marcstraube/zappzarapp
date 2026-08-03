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
// Markdown task shared by the two md globs below. Also filters IDE dirs as a
// second line of defense (see the glob comment).
function markdownTask(files) {
  const lintable = files.filter((f) => !/(^|\/)\.(idea|vscode)\//.test(f));
  if (lintable.length === 0) {
    return [];
  }
  const fileArgs = lintable.join(' ');
  return [
    `pnpm exec prettier --write ${fileArgs}`,
    `pnpm exec markdownlint-cli2 --fix ${fileArgs}`,
  ];
}

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
  // IDE dirs (.idea/, .vscode/) must not even be MATCHED (not just filtered):
  // lint-staged runs in the dev-tools container where those dirs are not
  // mounted, and its post-task re-stage of a matched-but-missing tracked file
  // stages a DELETE. One brace pattern covers root and subdirectories while
  // excluding the IDE dirs — and its slash keeps lint-staged's matchBase off
  // (a slashless pattern would match basenames in EVERY directory, silently
  // re-opening the trap). The filter in the task stays as a second defense.
  '{*.md,!(.idea|.vscode)/**/*.md}': markdownTask,
};
