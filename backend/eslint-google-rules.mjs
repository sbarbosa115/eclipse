// The Google JavaScript Style Guide rules that are not layout (Prettier owns layout). Spread it into
// eslint.config.mjs before eslint-config-prettier (steps/05-static-analysis.md §5.2):
//
//     import google from './eslint-google-rules.mjs';
//     import prettier from 'eslint-config-prettier';
//     export default tseslint.config(…, ...google, prettier);
export default [
  {
    files: ['**/*.{js,jsx,ts,tsx,mjs,mts}'],
    rules: {
      'no-var': 'error',
      'prefer-const': 'error',
      'prefer-rest-params': 'error',
      'prefer-spread': 'error',
      'eqeqeq': ['error', 'always', {null: 'ignore'}],
      'new-cap': 'error',
      'no-throw-literal': 'error',
      'guard-for-in': 'error',
    },
  },
  {
    files: ['**/*.{ts,tsx,mts}'],
    rules: {
      '@typescript-eslint/naming-convention': [
        'error',
        {
          selector: 'default',
          format: ['camelCase'],
          leadingUnderscore: 'allow',
        },
        {
          selector: 'variable',
          format: ['camelCase', 'UPPER_CASE', 'PascalCase'],
          leadingUnderscore: 'allow',
        },
        {selector: 'function', format: ['camelCase', 'PascalCase']},
        {selector: 'typeLike', format: ['PascalCase']},
        {selector: 'enumMember', format: ['PascalCase', 'UPPER_CASE']},
        {
          selector: ['property', 'parameterProperty', 'objectLiteralMethod'],
          format: null,
        },
        {selector: 'import', format: null},
      ],
    },
  },
];
