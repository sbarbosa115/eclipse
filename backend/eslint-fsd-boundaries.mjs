// Feature-Sliced Design import rules for ESLint 9 flat config, eslint-plugin-boundaries 7. Spread it into eslint.config.mjs:
//
//     import fsd from './eslint-fsd-boundaries.mjs';
//     export default tseslint.config(…, ...fsd, prettier);
//
// Needs: npm install --save-dev eslint-plugin-boundaries@^7 eslint-import-resolver-typescript
// (Tested with eslint 9, eslint-plugin-boundaries 7.2: the v5 rules element-types and entry-point are replaced by
// boundaries/dependencies.)
//
// What it enforces (steps/04-build.md, 4.2):
//   - layers import only downwards: app → pages → widgets → features → entities → shared;
//   - a slice never imports another slice of its own layer (features/a cannot import features/b);
//   - code outside a slice imports it through its public API (index.ts), never a file inside it.
// Code that predates FSD is the `legacy` element type: FSD layers may still import it while it is moved over, and it
// may import anything. Legacy patterns are matched first, so an old folder can share a name with a layer (an old
// pages/admin next to new FSD pages). Delete a pattern as soon as what it matches has moved.
import boundaries from 'eslint-plugin-boundaries';

// The folder that holds the React code, relative to the ESLint root (the folder of eslint.config.mjs).
const SRC = 'assets/react';
// Globs under SRC for code that is not FSD yet, e.g. ['components/**', 'lib/**', 'pages/admin/**']. Empty in a new project.
const LEGACY = [];

const LAYERS = ['app', 'pages', 'widgets', 'features', 'entities', 'shared'];
const SLICED = ['pages', 'widgets', 'features', 'entities'];
const below = (layer) => LAYERS.slice(LAYERS.indexOf(layer) + 1);

export default [
  {
    files: [`${SRC}/**/*.{ts,tsx,js,jsx}`],
    plugins: {boundaries},
    settings: {
      'import/resolver': {typescript: {alwaysTryTypes: true}, node: true},
      'boundaries/include': [`${SRC}/**/*`],
      'boundaries/elements': [
        ...LEGACY.map((glob) => ({
          type: 'legacy',
          pattern: `${SRC}/${glob}`,
          partialMatch: false,
        })),
        {type: 'app', pattern: `${SRC}/app`},
        ...SLICED.map((layer) => ({
          type: layer,
          pattern: `${SRC}/${layer}/*`,
          capture: ['slice'],
        })),
        {type: 'shared', pattern: `${SRC}/shared`},
      ],
    },
    rules: {
      'boundaries/dependencies': [
        'error',
        {
          default: 'disallow',
          message:
            'FSD: {{ from.element.types }} must not import this ({{ to.element.path }}). Layers import only downwards, and a slice from outside only through its index.ts',
          policies: [
            // A layer may import the layers below it, and legacy code while it is moved over.
            ...LAYERS.map((layer) => ({
              from: {element: {type: layer}},
              allow: {
                to: {element: {types: {anyOf: [...below(layer), 'legacy']}}},
              },
            })),
            // Files of one slice import each other; shared imports shared.
            ...SLICED.map((layer) => ({
              from: {element: {type: layer}},
              allow: {
                to: {
                  element: {
                    type: layer,
                    captured: {slice: '{{ from.element.captured.slice }}'},
                  },
                },
              },
            })),
            {
              from: {element: {types: {anyOf: ['app', 'shared']}}},
              allow: {to: {element: {type: '{{ from.element.types.[0] }}'}}},
            },
            // Legacy code may import anything.
            {
              from: {element: {type: 'legacy'}},
              allow: {to: {element: {types: {anyOf: [...LAYERS, 'legacy']}}}},
            },
            // From outside a slice, only its public API. Last, so it wins over the allows above.
            // (imports inside one slice are internal and not checked by this policy).
            {
              disallow: {
                to: {
                  element: {
                    types: {anyOf: SLICED},
                    fileInternalPath: '!index.{ts,tsx,js,jsx}',
                  },
                },
              },
            },
          ],
        },
      ],
    },
  },
];
