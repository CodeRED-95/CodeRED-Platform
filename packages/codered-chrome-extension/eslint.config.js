import js from '@eslint/js';
import tseslint from 'typescript-eslint';

export default [
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['src/**/*.ts', 'tests/**/*.ts', 'vite.config.ts'],
    languageOptions: {
      parserOptions: { project: './tsconfig.json' },
      globals: { chrome: 'readonly', document: 'readonly', window: 'readonly', navigator: 'readonly', URL: 'readonly', fetch: 'readonly', process: 'readonly', AbortController: 'readonly', setTimeout: 'readonly', clearTimeout: 'readonly' },
    },
    rules: {
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },
  { ignores: ['dist/**', 'node_modules/**', 'release/**', 'scripts/**/*.mjs'] },
];
