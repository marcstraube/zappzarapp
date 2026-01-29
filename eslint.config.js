import js from '@eslint/js';
import tsPlugin from '@typescript-eslint/eslint-plugin';
import tsParser from '@typescript-eslint/parser';
import prettierConfig from 'eslint-config-prettier';
import prettierPlugin from 'eslint-plugin-prettier';
import securityPlugin from 'eslint-plugin-security';
import sonarjsPlugin from 'eslint-plugin-sonarjs';

export default [
  // Ignore patterns (similar to .eslintignore)
  {
    ignores: [
      'node_modules/**',
      '**/dist/**',
      'build/**',
      'vendor/**',
      'public/build/**',
      '*.config.js',
      '*.config.cjs',
      'ecosystem.config.cjs',
      // Frontend has its own ESLint config via Nuxt/Vue
      'src/node/frontend/**',
      // DevToolbar compiled browser bundle (build artifact)
      'src/php/DevToolbar/assets/devtoolbar.js',
    ],
  },

  // Base JavaScript rules
  {
    files: ['**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        console: 'readonly',
        process: 'readonly',
        Buffer: 'readonly',
        __dirname: 'readonly',
        __filename: 'readonly',
      },
    },
    rules: {
      ...js.configs.recommended.rules,
    },
  },

  // TypeScript files
  {
    files: ['**/*.ts'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
        project: './tsconfig.json',
      },
    },
    plugins: {
      '@typescript-eslint': tsPlugin,
      prettier: prettierPlugin,
      security: securityPlugin,
      sonarjs: sonarjsPlugin,
    },
    rules: {
      // TypeScript recommended rules
      ...tsPlugin.configs['recommended'].rules,
      ...tsPlugin.configs['recommended-requiring-type-checking'].rules,

      // Prettier integration
      'prettier/prettier': 'error',

      // Security rules - detect dangerous patterns
      // Critical: These indicate likely security issues
      'security/detect-eval-with-expression': 'error',
      'security/detect-child-process': 'error',
      'security/detect-unsafe-regex': 'error',
      'security/detect-non-literal-require': 'error',
      // Warning: Review these manually (may have false positives in config code)
      'security/detect-non-literal-fs-filename': 'off', // Too noisy for config/utils
      'security/detect-non-literal-regexp': 'warn',
      'security/detect-possible-timing-attacks': 'warn',
      // Disabled: Very high false positive rate
      'security/detect-object-injection': 'off', // obj[key] is common pattern

      // SonarJS - IDE parity (catches code quality issues like PhpStorm)
      'sonarjs/prefer-immediate-return': 'warn', // Redundant variables before return
      'sonarjs/no-identical-functions': 'warn', // Duplicated function bodies
      'sonarjs/no-duplicated-branches': 'warn', // Identical if/else branches
      'sonarjs/no-redundant-jump': 'warn', // Redundant return/break/continue
      'sonarjs/no-useless-catch': 'warn', // Catch blocks that only rethrow
      'sonarjs/no-small-switch': 'warn', // Switch with only 1-2 cases
      'sonarjs/prefer-single-boolean-return': 'warn', // Simplify boolean return patterns
      'sonarjs/cognitive-complexity': ['warn', 15], // Warn if function too complex

      // TypeScript specific rules (similar to PHPStan level 5)
      '@typescript-eslint/explicit-function-return-type': 'warn',
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
      '@typescript-eslint/no-floating-promises': 'error',
      '@typescript-eslint/await-thenable': 'error',
      '@typescript-eslint/no-misused-promises': 'error',
      '@typescript-eslint/strict-boolean-expressions': 'warn',

      // Redundant code detection (catches issues PHPStorm would flag)
      '@typescript-eslint/no-unnecessary-condition': 'warn',
      '@typescript-eslint/no-unnecessary-type-assertion': 'warn',
      '@typescript-eslint/no-unnecessary-boolean-literal-compare': 'warn',
      '@typescript-eslint/no-redundant-type-constituents': 'warn',
      '@typescript-eslint/no-useless-empty-export': 'warn',
      '@typescript-eslint/prefer-nullish-coalescing': 'warn', // Use ?? instead of ||
      '@typescript-eslint/prefer-optional-chain': 'warn', // Use ?. instead of &&

      // Code quality rules
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'prefer-const': 'error',
      'no-var': 'error',
      eqeqeq: ['error', 'always', { null: 'ignore' }], // Allow == null for null/undefined checks
      curly: ['error', 'all'],
    },
  },

  // DevToolbar browser code - relax unsafe rules for browser DOM APIs
  // Note: Inherits all base TypeScript rules and only overrides specific ones
  {
    files: ['src/node/backend/DevToolbar/**/*.ts'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
        project: './src/node/backend/DevToolbar/tsconfig.json',
      },
      globals: {
        // Browser globals
        window: 'readonly',
        document: 'readonly',
        localStorage: 'readonly',
        console: 'readonly',
        URL: 'readonly',
        Blob: 'readonly',
      },
    },
    plugins: {
      '@typescript-eslint': tsPlugin,
      prettier: prettierPlugin,
      security: securityPlugin,
      sonarjs: sonarjsPlugin,
    },
    rules: {
      // Inherit all base TypeScript rules
      ...tsPlugin.configs['recommended'].rules,
      ...tsPlugin.configs['recommended-requiring-type-checking'].rules,

      // Prettier integration
      'prettier/prettier': 'error',

      // Security rules
      'security/detect-eval-with-expression': 'error',
      'security/detect-child-process': 'error',
      'security/detect-unsafe-regex': 'error',
      'security/detect-non-literal-require': 'error',
      'security/detect-non-literal-fs-filename': 'off',
      'security/detect-non-literal-regexp': 'warn',
      'security/detect-possible-timing-attacks': 'warn',
      'security/detect-object-injection': 'off',

      // SonarJS rules
      'sonarjs/prefer-immediate-return': 'warn',
      'sonarjs/no-identical-functions': 'warn',
      'sonarjs/no-duplicated-branches': 'warn',
      'sonarjs/no-redundant-jump': 'warn',
      'sonarjs/no-useless-catch': 'warn',
      'sonarjs/no-small-switch': 'warn',
      'sonarjs/prefer-single-boolean-return': 'warn',
      'sonarjs/cognitive-complexity': ['warn', 15],

      // TypeScript rules
      '@typescript-eslint/explicit-function-return-type': 'warn',
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
      '@typescript-eslint/no-floating-promises': 'error',
      '@typescript-eslint/await-thenable': 'error',
      '@typescript-eslint/no-misused-promises': 'error',
      '@typescript-eslint/strict-boolean-expressions': 'warn',

      // Redundant code detection
      '@typescript-eslint/no-unnecessary-condition': 'warn',
      '@typescript-eslint/no-unnecessary-type-assertion': 'warn',
      '@typescript-eslint/no-unnecessary-boolean-literal-compare': 'warn',
      '@typescript-eslint/no-useless-empty-export': 'warn',
      '@typescript-eslint/prefer-nullish-coalescing': 'warn',
      '@typescript-eslint/prefer-optional-chain': 'warn',

      // Code quality
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'prefer-const': 'error',
      'no-var': 'error',
      eqeqeq: ['error', 'always', { null: 'ignore' }],
      curly: ['error', 'all'],

      // DevToolbar-specific overrides: Relax unsafe rules for browser DOM APIs
      '@typescript-eslint/no-unsafe-assignment': 'off',
      '@typescript-eslint/no-unsafe-member-access': 'off',
      '@typescript-eslint/no-unsafe-argument': 'off',
      '@typescript-eslint/no-unsafe-call': 'off',
      '@typescript-eslint/no-unsafe-return': 'off',
      // HTMLElement union types are common in DOM code
      '@typescript-eslint/no-redundant-type-constituents': 'off',
    },
  },

  // Test files - relax some rules that don't work well with mocks
  {
    files: ['tests/**/*.ts', '**/*.test.ts', '**/*.spec.ts'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
        project: './tsconfig.json',
      },
    },
    plugins: {
      '@typescript-eslint': tsPlugin,
    },
    rules: {
      // vi.mocked() returns unbound methods by design
      '@typescript-eslint/unbound-method': 'off',
      // Test functions often don't need explicit return types
      '@typescript-eslint/explicit-function-return-type': 'off',
      // Mocks and test utilities often use 'any' types (e.g., expect.any(String))
      '@typescript-eslint/no-unsafe-assignment': 'off',
      '@typescript-eslint/no-unsafe-member-access': 'off',
      '@typescript-eslint/no-unsafe-argument': 'off',
      '@typescript-eslint/no-unsafe-call': 'off',
      '@typescript-eslint/no-unsafe-return': 'off',
    },
  },

  // Disable formatting rules that conflict with Prettier
  prettierConfig,
];
