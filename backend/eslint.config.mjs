// Static analysis for the React side: `npm run lint`.
//
// The rules that matter most are the ones webpack does not catch: react/jsx-no-undef (a component used but not
// imported compiles and renders a blank screen), the hooks rules, and the Feature-Sliced Design import boundaries
// (eslint-fsd-boundaries.mjs). Prettier owns layout, so eslint-config-prettier comes last.
import js from '@eslint/js';
import globals from 'globals';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import prettier from 'eslint-config-prettier';
import tseslint from 'typescript-eslint';
import fsd from './eslint-fsd-boundaries.mjs';
import google from './eslint-google-rules.mjs';

export default tseslint.config(
  {
    ignores: [
      'public/**',
      'node_modules/**',
      'vendor/**',
      'var/**',
      'assets/types/api.d.ts',
      'e2e/.results/**',
    ],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['assets/**/*.{ts,tsx}', 'e2e/**/*.ts', '*.config.{js,mjs,ts,mts}'],
    plugins: {react, 'react-hooks': reactHooks},
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      parserOptions: {ecmaFeatures: {jsx: true}},
      globals: {...globals.browser, ...globals.node},
    },
    settings: {react: {version: 'detect'}},
    rules: {
      ...react.configs.recommended.rules,
      ...reactHooks.configs.recommended.rules,
      'react/jsx-no-undef': 'error',
      'react/react-in-jsx-scope': 'off',
      'react/prop-types': 'off',
      '@typescript-eslint/no-unused-vars': [
        'error',
        {
          argsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
          caughtErrors: 'none',
        },
      ],
    },
  },
  ...fsd,
  ...google,
  prettier,
);
