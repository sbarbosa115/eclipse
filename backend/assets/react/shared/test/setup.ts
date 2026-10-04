// Loaded before every Vitest file: DOM matchers (toBeInTheDocument…), a clean DOM between tests, and the browser APIs
// jsdom lacks.
import '@testing-library/jest-dom/vitest';
import {cleanup, configure} from '@testing-library/react';
import {afterEach, vi} from 'vitest';

afterEach(() => cleanup());

// findBy… waits up to 3 s (1 s by default), for the same reason as testTimeout in vitest.config.mts.
configure({asyncUtilTimeout: 3_000});

class ResizeObserverMock {
  observe = vi.fn();
  unobserve = vi.fn();
  disconnect = vi.fn();
}
window.ResizeObserver = ResizeObserverMock;
