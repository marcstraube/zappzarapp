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

      // SonarJS - IDE parity (catches "redundant variable" warnings)
      'sonarjs/prefer-immediate-return': 'warn',

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
      '@typescript-eslint/no-redundant-type-constituents': 'warn',
      '@typescript-eslint/no-useless-empty-export': 'warn',

      // Code quality rules
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'prefer-const': 'error',
      'no-var': 'error',
      eqeqeq: ['error', 'always'],
      curly: ['error', 'all'],
    },
  },

  // Test files - relax some rules that don't work well with mocks
  {
    files: ['tests/**/*.ts', '**/*.test.ts', '**/*.spec.ts'],
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
