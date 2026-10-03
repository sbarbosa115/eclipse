// Component and unit tests for the React side: `npm test` (Vitest + Testing Library, in jsdom).
// Test files sit next to what they test: ui/ProductCard.test.tsx.
import path from 'node:path';
import {defineConfig} from 'vitest/config';

export default defineConfig({
  resolve: {alias: {'@': path.resolve(import.meta.dirname, 'assets/react')}},
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['assets/**/*.test.{ts,tsx}'],
    setupFiles: ['assets/react/shared/test/setup.ts'],
    css: false,
    // userEvent-driven tests take seconds when the machine is busy (several stacks building at once): a slow run is
    // not a failing one.
    testTimeout: 15_000,
  },
});
